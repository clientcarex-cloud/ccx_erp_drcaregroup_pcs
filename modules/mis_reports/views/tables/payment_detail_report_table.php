<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();
$CI->load->model('client_model');

// ===== Get Filters and Pagination Parameters =====
$from_date = !empty($consulted_from_date) ? to_sql_date($consulted_from_date) : date('Y-m-01');
$to_date = !empty($consulted_to_date) ? to_sql_date($consulted_to_date) : date('Y-m-d');
$selected_branch_id = isset($selected_branch_id) ? (array)$selected_branch_id : [];

$start = $CI->input->post('start');
$length = $CI->input->post('length');
$search = $CI->input->post('search');

$search_value = '';
if (isset($search['value']) && $search['value'] != '') {
    $search_value = $search['value'];
}

// ===== Branch filter via EXISTS (avoids fetching all IDs into PHP) =====
$applyBranchFilter = static function ($query) use ($selected_branch_id) {
    if (empty($selected_branch_id)) return;
    $cleanIds = array_filter(array_map('intval', $selected_branch_id), function ($v) { return $v > 0; });
    if (empty($cleanIds)) return;
    $query->where('EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'customer_groups cg_filter
        WHERE cg_filter.customer_id = inv.clientid
        AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
    )', null, false);
};

// ===== Search filter closure =====
$applySearchFilter = static function ($query, $search_value) {
    if (empty($search_value)) return;
    $query->group_start();
    $query->like('c.company', $search_value);
    $query->or_like('new.mr_no', $search_value);
    $query->or_like('branch.name', $search_value);
    $query->or_like('sources.name', $search_value);
    $query->or_like('item.description', $search_value);
    $query->group_end();
};

// ===== Step 1: Get Total Filtered Records Count =====
$CI->db->reset_query();
$CI->db->select('COUNT(DISTINCT payment.id) as total_filtered');
$CI->db->from(db_prefix() . 'invoicepaymentrecords payment');
$CI->db->join(db_prefix() . 'invoices inv', 'inv.id = payment.invoiceid', 'left');
$CI->db->join(db_prefix() . 'clients c', 'c.userid = inv.clientid', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
// Only join branch/source tables if search needs them
if (!empty($search_value)) {
    $CI->db->join(db_prefix() . 'customer_groups cc', 'cc.customer_id = c.userid', 'left');
    $CI->db->join(db_prefix() . 'customers_groups branch', 'branch.id = cc.groupid', 'left');
    $CI->db->join(db_prefix() . 'leads_sources sources', 'sources.id = new.patient_source_id', 'left');
    $CI->db->join(db_prefix() . 'itemable item', 'item.rel_id = inv.id AND item.rel_type = "invoice"', 'left');
}
$CI->db->where('payment.date >=', $from_date);
$CI->db->where('payment.date <=', $to_date);
$applyBranchFilter($CI->db);
$applySearchFilter($CI->db, $search_value);

$total_filtered = (int) $CI->db->get()->row()->total_filtered;

// Quick exit if no records
if ($total_filtered === 0) {
    echo json_encode([
        'draw' => intval($CI->input->post('draw')),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'aaData' => [],
    ]);
    exit;
}

// ===== Step 2: Get Payment Records with Joins and Pagination =====
$CI->db->reset_query();
$CI->db->select('
    payment.id,
    payment.date,
    payment.amount as paid,
    payment.received_by,
    payment.transactionid,
    payment.invoiceid,
    payment.utr_no,
    c.company,
    c.userid,
    item.description as package,
    new.mr_no,
    mode.name as payment_mode,
    staff.firstname,
    staff.lastname,
    sources.name as patient_source,
    inv.addedfrom,
    inv.datecreated,
    inv.total as total,
    branch.name as branch_name,
    payment_category.appointment_type_name
');
$CI->db->from(db_prefix() . 'invoicepaymentrecords payment');
$CI->db->join(db_prefix() . 'invoices inv', 'inv.id = payment.invoiceid', 'left');
$CI->db->join(db_prefix() . 'itemable item', 'item.rel_id = inv.id AND item.rel_type = "invoice"', 'left');
$CI->db->join(db_prefix() . 'payment_modes mode', 'mode.id = payment.paymentmode', 'left');
$CI->db->join(db_prefix() . 'staff staff', 'staff.staffid = payment.received_by', 'left');
$CI->db->join(db_prefix() . 'clients c', 'c.userid = inv.clientid', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'customer_groups cc', 'cc.customer_id = c.userid', 'left');
$CI->db->join(db_prefix() . 'customers_groups branch', 'branch.id = cc.groupid', 'left');
$CI->db->join(db_prefix() . 'leads_sources sources', 'sources.id = new.patient_source_id', 'left');
$CI->db->join(db_prefix() . 'appointment_type as payment_category', 'payment_category.appointment_type_id = inv.appointment_type_id', 'left');

$CI->db->where('payment.date >=', $from_date);
$CI->db->where('payment.date <=', $to_date);
$applyBranchFilter($CI->db);
$applySearchFilter($CI->db, $search_value);

$CI->db->group_by('payment.id');
$CI->db->order_by('payment.date', 'DESC');
if ($length != -1) {
    $CI->db->limit($length, $start);
}
$results = $CI->db->get()->result_array();

// ===== Step 3: Batch-fetch all per-row data ONCE (eliminates N+1 queries) =====
$userIds = array_unique(array_column($results, 'userid'));
$invoiceIds = array_unique(array_column($results, 'invoiceid'));
$paymentIds = array_column($results, 'id');

// ── Batch: cumulative paid for each payment (single query with window function) ──
$cumulativePaidMap = [];
if (!empty($paymentIds)) {
    $paymentIdsStr = implode(',', array_map('intval', $paymentIds));
    $invoiceIdsStr = implode(',', array_map('intval', $invoiceIds));

    // Get cumulative paid up to each payment.id for each invoice
    $sql = 'SELECT p1.id as payment_id, p1.invoiceid,
        (SELECT COALESCE(SUM(p2.amount), 0)
         FROM ' . db_prefix() . 'invoicepaymentrecords p2
         WHERE p2.invoiceid = p1.invoiceid
         AND (p2.date < p1.date OR (p2.date = p1.date AND p2.id <= p1.id))
        ) as cumulative_paid
        FROM ' . db_prefix() . 'invoicepaymentrecords p1
        WHERE p1.id IN (' . $paymentIdsStr . ')';
    $cumRows = $CI->db->query($sql)->result_array();
    foreach ($cumRows as $cr) {
        $cumulativePaidMap[$cr['payment_id']] = (float) $cr['cumulative_paid'];
    }
}

// ── Batch: package count per patient ──
$packageCountMap = [];
if (!empty($userIds)) {
    $userIdsStr = implode(',', array_map('intval', $userIds));
    $sql = 'SELECT clientid as userid, COUNT(*) as pkg_count
        FROM ' . db_prefix() . 'invoices
        WHERE clientid IN (' . $userIdsStr . ')
        GROUP BY clientid';
    $pkgRows = $CI->db->query($sql)->result_array();
    foreach ($pkgRows as $pr) {
        $packageCountMap[$pr['userid']] = (int) $pr['pkg_count'];
    }
}

// ── Batch: latest appointment per patient+date combo ──
// Collect unique userid+date pairs from results
$apptLookup = [];
if (!empty($results)) {
    $conditions = [];
    $seenPairs = [];
    foreach ($results as $row) {
        $key = $row['userid'] . '_' . $row['date'];
        if (!isset($seenPairs[$key])) {
            $seenPairs[$key] = true;
            $conditions[] = '(a.userid = ' . (int)$row['userid'] . ' AND (DATE(a.appointment_date) = ' . $CI->db->escape($row['date']) . ' OR DATE(a.created_at) = ' . $CI->db->escape($row['date']) . '))';
        }
    }

    if (!empty($conditions)) {
        $sql = 'SELECT a.userid, DATE(a.appointment_date) as appt_date, DATE(a.created_at) as created_date,
                t.description as treatment_name, type.appointment_type_name
            FROM ' . db_prefix() . 'appointment a
            LEFT JOIN ' . db_prefix() . 'items t ON t.id = a.treatment_id
            LEFT JOIN ' . db_prefix() . 'appointment_type type ON type.appointment_type_id = a.appointment_type_id
            WHERE (' . implode(' OR ', $conditions) . ')
            ORDER BY a.appointment_date DESC';
        $apptRows = $CI->db->query($sql)->result_array();
        foreach ($apptRows as $ar) {
            // Key by userid + appointment_date or created_date
            $key1 = $ar['userid'] . '_' . $ar['appt_date'];
            $key2 = $ar['userid'] . '_' . $ar['created_date'];
            if (!isset($apptLookup[$key1])) {
                $apptLookup[$key1] = $ar;
            }
            if (!isset($apptLookup[$key2])) {
                $apptLookup[$key2] = $ar;
            }
        }
    }
}

// ===== Step 4: Build Output Rows (NO per-row queries) =====
$data = [];
$total_package = 0;
$total_paid = 0;
$total_due = 0;

foreach ($results as $row) {
    $userid = $row['userid'];
    $invoiceid = $row['invoiceid'];
    $payment_id = $row['id'];
    $current_paid = (float) $row['paid'];

    // Total package
    $package_total = (float) $row['total'];

    // Cumulative paid — from batch map
    $cumulative_paid = $cumulativePaidMap[$payment_id] ?? $current_paid;

    // Due
    $due = $package_total - $cumulative_paid;

    // Package count — from batch map
    $packageCount = $packageCountMap[$userid] ?? 0;

    // Latest appointment — from batch map
    $apptKey = $userid . '_' . $row['date'];
    $latestAppointment = $apptLookup[$apptKey] ?? null;
    $treatmentName = $latestAppointment ? ($latestAppointment['treatment_name'] ?? '') : '';
    $appointment_type_name = $latestAppointment ? ($latestAppointment['appointment_type_name'] ?? '') : '';

    $url = admin_url('mis_reports/reports/' . $type . '/' . $row['userid']);
    $company = '<a target="_blank" href="' . $url . '" class="tw-font-medium">' . format_name($row['company']) . '</a>';

    // Totals
    $total_package += $package_total;
    $total_paid += $current_paid;
    $total_due += $due;

    $renewal_count = max(0, $packageCount - 1);

    // Row
    $data[] = [
        $company,
        $row['mr_no'],
        $row['branch_name'],
        $renewal_count,
        $row['patient_source'],
        $row['appointment_type_name'],
        $treatmentName,
        $appointment_type_name,
        get_staff_full_name($row['addedfrom']),
        _d($row['datecreated']),
        get_staff_full_name($row['received_by']),
        _d($row['date']),
        e($row['payment_mode']),
        e($row['utr_no']),
        e($row['package']),
        e(app_format_money_custom($package_total, 1)),
        e(app_format_money_custom($current_paid, 1)),
        e(app_format_money_custom($due, 1)),
    ];
}

// ===== Step 5: Add Totals Row and Return JSON =====
$data[] = [
    '<strong>Total</strong>',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '<strong>' . app_format_money_custom($total_paid, 1) . '</strong>',
    ''
];

echo json_encode([
    'draw' => intval($CI->input->post('draw')),
    'recordsTotal' => $total_filtered,
    'recordsFiltered' => $total_filtered,
    'aaData' => $data,
]);
exit;