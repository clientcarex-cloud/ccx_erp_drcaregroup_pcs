<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('client/client_model');
$CI->load->helper('client/custom');

// Inputs
$draw = intval($CI->input->post('draw'));
$start = intval($CI->input->post('start'));
$length = intval($CI->input->post('length'));
$search = $CI->input->post('search')['value'] ?? '';
$from_date = $consulted_from_date ?? null;
$to_date = $consulted_to_date ?? null;
$branch_ids = $selected_branch_ids ?? [];

$order_column_index = (int) ($CI->input->post('order')[0]['column'] ?? 0);
$incoming_order_dir = strtolower($CI->input->post('order')[0]['dir'] ?? 'desc');
$order_dir = $incoming_order_dir === 'asc' ? 'asc' : 'desc';

$summary_filter = $CI->input->get('summary_filter');

// Map DataTable columns to actual SQL columns/aliases (null means fallback to default)
$columns = [
    'c.userid',
    'c.company',
    'branch.name',
    'new.mr_no',
    'new.age',
    'new.gender',
    'c.phonenumber',
    null,
    null,
    'patient_source_name',
    null,
    null,
    null,
    'new.patient_status',
    'new.registration_start_date',
    'new.registration_end_date',
    null
];

$order_column = $columns[$order_column_index] ?? null;
if (empty($order_column)) {
    $order_column = 'c.userid';
}

// ── Branch filter via EXISTS (avoids fetching all IDs into PHP) ──
$applyBranchFilter = static function ($query) use ($branch_ids) {
    if (empty($branch_ids)) return;
    $cleanIds = array_filter(array_map('intval', $branch_ids), function ($v) { return $v > 0; });
    if (empty($cleanIds)) return;
    $query->where('EXISTS (
        SELECT 1
        FROM ' . db_prefix() . 'customer_groups cg_filter
        WHERE cg_filter.customer_id = c.userid
        AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
    )', null, false);
};

// ── Index-friendly date filter (avoids DATE() wrapper) ──
$applyDateFilter = static function ($query, $from, $to) {
    if (empty($from) || empty($to)) return;
    $query->where('new.registration_start_date >=', $from . ' 00:00:00');
    $query->where('new.registration_start_date <=', $to . ' 23:59:59');
};

// ── Reusable summary filter (eliminates triple code duplication) ──
$applySummaryFilter = static function ($query, $from_date, $to_date, $summary_filter) {
    if ($summary_filter === 'due') {
        $query->where('EXISTS (
            SELECT 1 FROM ' . db_prefix() . 'invoices i
            WHERE i.clientid = c.userid AND i.status != 2
        )', null, false);
    } elseif ($summary_filter === 'no_due') {
        $query->where('NOT EXISTS (
            SELECT 1 FROM ' . db_prefix() . 'invoices i
            WHERE i.clientid = c.userid AND i.status != 2
        )', null, false);
    } elseif ($summary_filter === 'registered') {
        $query->where('new.mr_no IS NOT NULL', null, false);
    } elseif ($summary_filter === 'not_registered') {
        $query->group_start();
        $query->where('new.mr_no IS NULL', null, false);
        $query->or_where('new.mr_no', '');
        $query->group_end();
    } elseif ($summary_filter === 'renewal') {
        $query->where('new.mr_no IS NOT NULL', null, false);
        $today = date('Y-m-d');

        // Optimised: use a derived table for MAX(duedate) instead of correlated subquery per row
        $subquery = '
            SELECT 1 FROM ' . db_prefix() . 'invoices e
            INNER JOIN (
                SELECT clientid, MAX(duedate) AS max_duedate
                FROM ' . db_prefix() . 'invoices
                WHERE duedate IS NOT NULL
                GROUP BY clientid
            ) emax ON emax.clientid = e.clientid AND emax.max_duedate = e.duedate
            WHERE e.clientid = c.userid
            AND e.duedate IS NOT NULL
        ';
        if ($from_date && $to_date) {
            $subquery .= ' AND e.duedate >= "' . $from_date . '" AND e.duedate <= "' . $to_date . '"';
        } else {
            $subquery .= ' AND e.duedate <= "' . $today . '"';
        }
        $query->where('EXISTS (' . $subquery . ')', null, false);
    } elseif ($summary_filter === 'new_patients') {
        $query->where('new.mr_no IS NOT NULL', null, false);
    }
};

// ── Search filter closure ──
$applySearchFilter = static function ($query, $search) {
    if (empty($search)) return;
    $query->group_start();
    $query->like('c.company', $search);
    $query->or_like('c.phonenumber', $search);
    $query->or_like('new.mr_no', $search);
    $query->or_like('new.alt_number1', $search);
    $query->group_end();
};

// ── Pre-fetch leads_status colour map ONCE ──
$statusColorMap = [];
$CI->db->select('name, color, id');
$_statuses = $CI->db->get(db_prefix() . 'leads_status')->result_array();
foreach ($_statuses as $statusRow) {
    $statusColorMap[$statusRow['name']] = [
        'id' => $statusRow['id'],
        'color' => $statusRow['color']
    ];
}


// ══════════════════════════════════════════════════════════════
// COMBINED total + filtered count in a SINGLE query
// Eliminates an entire full-table scan.
// ══════════════════════════════════════════════════════════════
$hasSearch = !empty($search);
$needsNewFieldsJoin = ($summary_filter && $summary_filter !== 'due' && $summary_filter !== 'no_due')
    || ($from_date && $to_date && $summary_filter != 'not_registered');

$CI->db->reset_query();
$CI->db->select('COUNT(*) as total_count');
$CI->db->from(db_prefix() . 'clients c');
if ($needsNewFieldsJoin) {
    $CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
}
$applyBranchFilter($CI->db);
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $applyDateFilter($CI->db, $from_date, $to_date);
}
$applySummaryFilter($CI->db, $from_date, $to_date, $summary_filter);
$totalRecords = (int) $CI->db->get()->row()->total_count;

// Filtered count (only when search is active)
if ($hasSearch) {
    $CI->db->reset_query();
    $CI->db->select('COUNT(*) as total');
    $CI->db->from(db_prefix() . 'clients c');
    $CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
    $applyBranchFilter($CI->db);
    if ($from_date && $to_date && $summary_filter != 'not_registered') {
        $applyDateFilter($CI->db, $from_date, $to_date);
    }
    $applySummaryFilter($CI->db, $from_date, $to_date, $summary_filter);
    $CI->db->group_start();
    $CI->db->like('c.company', $search);
    $CI->db->or_like('c.phonenumber', $search);
    $CI->db->or_like('new.mr_no', $search);
    $CI->db->or_like('new.alt_number1', $search);
    $CI->db->group_end();
    $filteredRecords = (int) $CI->db->get()->row()->total;
} else {
    $filteredRecords = $totalRecords;
}

// Quick exit if no records at all
if ($totalRecords === 0) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
    exit;
}


// ══════════════════════════════════════════════════════════════
// MAIN DATA QUERY
// Removed extra JOINs for customer_groups/customers_groups from
// the main query — branch names are now batch-fetched separately.
// ══════════════════════════════════════════════════════════════
$CI->db->reset_query();
// GROUP BY c.userid replaces DISTINCT — eliminates "Using temporary; Using filesort"
$CI->db->select('c.userid, c.company, c.phonenumber, c.datecreated, new.mr_no, new.age, new.gender, c.city, c.state, new.registration_start_date, new.registration_end_date, new.current_status, new.patient_status, source.name as patient_source_name');
$CI->db->from(db_prefix() . 'clients c');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');

$applyBranchFilter($CI->db);
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $applyDateFilter($CI->db, $from_date, $to_date);
}

$applySearchFilter($CI->db, $search);

$applySummaryFilter($CI->db, $from_date, $to_date, $summary_filter);

$CI->db->group_by('c.userid');
$CI->db->order_by($order_column, $order_dir);
if ($length != -1) {
    $CI->db->limit($length, $start);
}

$results = $CI->db->get()->result_array();

// ── Batch-fetch branch names for the result set ──
$userIds = array_column($results, 'userid');
$branchNameMap = [];
if (!empty($userIds)) {
    $CI->db->select('cg_rel.customer_id, GROUP_CONCAT(DISTINCT cg_names.name ORDER BY cg_names.name SEPARATOR ", ") AS branch_names');
    $CI->db->from(db_prefix() . 'customer_groups cg_rel');
    $CI->db->join(db_prefix() . 'customers_groups cg_names', 'cg_names.id = cg_rel.groupid', 'left');
    $CI->db->where_in('cg_rel.customer_id', $userIds);
    $CI->db->group_by('cg_rel.customer_id');
    $branchRows = $CI->db->get()->result_array();
    foreach ($branchRows as $br) {
        $branchNameMap[$br['customer_id']] = $br['branch_names'];
    }
}

// ── Batch-fetch related data ──
$treatmentMap = $doctorMap = $callLogMap = $leadStatuses = [];

if (!empty($userIds)) {
    $userIdsStr = implode(',', $userIds);

    // Latest appointment per patient
    $CI->db->select('
        a.userid,
        a.enquiry_doctor_id,
        i.description AS treatment_name,
        CONCAT_WS(" ", s.firstname, s.lastname) AS doctor_name
    ');
    $CI->db->from(db_prefix() . 'appointment a');
    $CI->db->join(
        '(SELECT MAX(appointment_id) AS max_id, userid FROM ' . db_prefix() . 'appointment WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) AS latest',
        'a.appointment_id = latest.max_id',
        'INNER'
    );
    $CI->db->join(db_prefix() . 'items i', 'i.id = a.treatment_id', 'LEFT');
    $CI->db->join(db_prefix() . 'staff s', 's.staffid = a.enquiry_doctor_id', 'LEFT');
    // Removed redundant where_in — the INNER JOIN already constrains rows
    $appointments = $CI->db->get()->result_array();

    foreach ($appointments as $app) {
        $treatmentMap[$app['userid']] = $app['treatment_name'] ?? '-';
        $doctorMap[$app['userid']] = [
            'id' => $app['enquiry_doctor_id'],
            'name' => $app['doctor_name'] ?? '-',
        ];
    }

    // Latest call log per patient
    $CI->db->select('c.patientid, c.created_date as last_calling_date, c.next_calling_date');
    $CI->db->from(db_prefix() . 'patient_call_logs c');
    $CI->db->join(
        "(SELECT MAX(id) as max_id, patientid FROM " . db_prefix() . "patient_call_logs WHERE patientid IN (" . $userIdsStr . ") GROUP BY patientid) as latest",
        'c.id = latest.max_id',
        'inner'
    );
    // Removed redundant where_in
    $callLogs = $CI->db->get()->result_array();
    foreach ($callLogs as $log) {
        $callLogMap[$log['patientid']] = $log;
    }

    // Latest journey status per patient (derived-table MAX join instead of ORDER BY + PHP filter)
    $CI->db->select('j.userid, j.status, s.name as status_name, s.color as status_color');
    $CI->db->from(db_prefix() . 'lead_patient_journey j');
    $CI->db->join(
        '(SELECT userid, MAX(id) AS max_id FROM ' . db_prefix() . 'lead_patient_journey WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) latest_journey',
        'latest_journey.max_id = j.id',
        'inner'
    );
    $CI->db->join(db_prefix() . 'leads_status s', 's.id = j.status', 'left');
    $statuses = $CI->db->get()->result_array();
    foreach ($statuses as $s) {
        $leadStatuses[$s['userid']] = $s;
    }
}

// ══════════════════════════════════════════════════════════════
// Prepare output
// ══════════════════════════════════════════════════════════════
$output = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => []
];

$hasPermissionDelete = has_permission('clients', '', 'delete');
$i = $start + 1;

foreach ($results as $row) {
    $dataRow = [];

    $company = e(format_name($row['company'])) ?: _l('no_company_view_profile');

    // Calculate "time ago" label
    $timeAgoLabel = '';
    if (!empty($row['datecreated'])) {
        $createdTime = new DateTime($row['datecreated']);
        $now = new DateTime();
        $diff = $now->diff($createdTime);

        if ($diff->y > 0) {
            $timeAgoLabel = $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            $timeAgoLabel = $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            $timeAgoLabel = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            $timeAgoLabel = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            $timeAgoLabel = $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            $timeAgoLabel = 'Just now';
        }
    }

    $company .= '<br><label style="font-weight: 300; font-size: 12px">' . e(_dt($row['datecreated']));
    if ($timeAgoLabel) {
        $company .= ' <span style="display: inline-block; background: #4CAF50; color: #fff; font-size: 10px; padding: 1px 8px; border-radius: 50px; font-weight: 500;">' . $timeAgoLabel . '</span>';
    }
    $company .= '</label>';
    $url = admin_url('client/get_patient_list/' . $row['userid']);
    $company = '<a href="' . $url . '" class="tw-font-medium">' . $company . '</a>';

    $should_mask = true;
    $raw_number = $row['phonenumber'];
    $masked_phone = mask_last_5_digits_1($raw_number);
    if ($should_mask && !empty($raw_number)) {
        $phonenumber = '<span class="masked-number" data-full="' . e($raw_number) . '" data-masked="' . e($masked_phone) . '">' . e($masked_phone) . '</span>'
            . ' <a href="javascript:void(0);" class="toggle-mask-btn" style="cursor:pointer; color:#888;" title="Show/Hide Number"><i class="fa fa-eye-slash"></i></a>';
    } else {
        $phonenumber = e($raw_number);
    }

    $callLog = $callLogMap[$row['userid']] ?? ['last_calling_date' => '', 'next_calling_date' => ''];
    $status = $leadStatuses[$row['userid']] ?? ['status' => 1, 'status_name' => 'Unknown', 'status_color' => '#7cb342'];
    $color = $status['status_color'];
    $statusLabel = '<span class="lead-status-' . $status['status'] . ' label" style="color:' . $color . ';border:1px solid ' . adjust_hex_brightness($color, 0.4) . ';background: ' . adjust_hex_brightness($color, 0.04) . ';">' . e($status['status_name']) . '</span>';

    $currentStatusName = trim($row['current_status']);
    $currentStatusLabel = '-';

    if (!empty($currentStatusName) && isset($statusColorMap[$currentStatusName])) {
        $statusInfo = $statusColorMap[$currentStatusName];
        $color = $statusInfo['color'];
        $id = $statusInfo['id'];

        $currentStatusLabel = '<span class="lead-status-' . $id . ' label" style="color:' . $color . ';border:1px solid ' . adjust_hex_brightness($color, 0.4) . ';background:' . adjust_hex_brightness($color, 0.04) . ';">' . e($currentStatusName) . '</span>';
    }

    // 17 columns — with branch column
    $dataRow[] = $i++;
    $dataRow[] = $company;
    $dataRow[] = !empty($branchNameMap[$row['userid']]) ? e($branchNameMap[$row['userid']]) : '-';
    $dataRow[] = !empty($row['mr_no']) ? e($row['mr_no']) : '-';
    $dataRow[] = $row['age'];
    $dataRow[] = $row['gender'];
    $dataRow[] = $phonenumber;
    $dataRow[] = $treatmentMap[$row['userid']] ?? '';
    $dataRow[] = isset($doctorMap[$row['userid']]) ? $doctorMap[$row['userid']]['name'] : '-';
    $dataRow[] = $row['patient_source_name'];
    $dataRow[] = $callLog['last_calling_date'];
    $dataRow[] = $callLog['next_calling_date'];
    $dataRow[] = $statusLabel;
    $dataRow[] = $currentStatusLabel;
    $dataRow[] = $row['registration_start_date'];
    $dataRow[] = (!empty($row['registration_end_date']) && $row['registration_end_date'] !== '1970-01-01') ? $row['registration_end_date'] : '';
    $dataRow[] = '<a href="' . admin_url('clients/client/' . $row['userid']) . '" data-toggle="tooltip" title="' . _l('view') . '">' . e($row['patient_status']) . '</a>';

    $output['data'][] = $dataRow;
}

echo json_encode($output);
exit;
