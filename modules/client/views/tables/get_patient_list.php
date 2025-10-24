<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('client_model');

// Inputs
$draw = intval($CI->input->post('draw'));
$start = intval($CI->input->post('start'));
$length = intval($CI->input->post('length'));
$search = $CI->input->post('search')['value'] ?? '';
$from_date = $consulted_from_date;
$to_date = $consulted_to_date;

$order_column_index = $CI->input->post('order')[0]['column'] ?? 0;
$order_dir = strtolower($CI->input->post('order')[0]['dir'] ?? 'desc');
if (!in_array($order_dir, ['asc', 'desc'], true)) {
    $order_dir = 'desc';
}


$summary_filter = $CI->input->get('summary_filter');

// Replace with your column order
$columns = [
    'c.userid',
    'c.company',
    'new.age',
    'new.gender',
    'c.phonenumber',
    'treatment_name',
    'doctor_name',
    'patient_source_name',
    'last_calling_date',
    'next_calling_date',
    'latest_status_name',
    'new.current_status',
    'new.registration_start_date',
    'new.registration_end_date',
    'new.patient_status',
];
$order_column = $columns[$order_column_index] ?? 'c.userid';
// Total count
$totalQuery = $CI->db;
$totalQuery->reset_query();
$totalQuery->select('COUNT(DISTINCT c.userid) as total');
$totalQuery->from(db_prefix() . 'clients c');
$totalQuery->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$totalQuery->join(db_prefix() . 'customer_groups group', 'group.customer_id = c.userid', 'left');

if (isset($current_branch_id) && $current_branch_id) {
    $totalQuery->where('group.groupid', $current_branch_id);
}
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $totalQuery->where("DATE(new.registration_start_date) BETWEEN '$from_date' AND '$to_date'");
}

if ($summary_filter === 'due') {
    $totalQuery->where('EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid AND i.status != 2
    )', null, false);
} elseif ($summary_filter === 'no_due') {
    $totalQuery->where('NOT EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid AND i.status != 2
    )', null, false);
} elseif ($summary_filter === 'registered') {
    $totalQuery->where('new.mr_no IS NOT NULL');
} elseif ($summary_filter === 'not_registered') {
    $totalQuery->group_start();
	$totalQuery->where('new.mr_no IS NULL', null, false);
	$totalQuery->or_where('new.mr_no', '');
	$totalQuery->group_end();
	
} elseif ($summary_filter === 'renewal') {
    $CI->db->where('new.mr_no IS NOT NULL'); // ensure registered

    $today = date('Y-m-d');

    $subquery = '
        SELECT 1 FROM ' . db_prefix() . 'invoices e
        WHERE e.clientid = c.userid
        AND e.duedate IS NOT NULL
        AND e.duedate = (
            SELECT MAX(e2.duedate)
            FROM ' . db_prefix() . 'invoices e2
            WHERE e2.clientid = c.userid
        )
    ';

    // Apply range or expiry check AFTER finding the max date
    if ($from_date && $to_date) {
        $subquery .= ' AND DATE(e.duedate) BETWEEN "' . $from_date . '" AND "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $CI->db->where('EXISTS (' . $subquery . ')', null, false);
} elseif ($summary_filter === 'new_patients') {
    $totalQuery->where('new.mr_no IS NOT NULL');
}

$totalRecords = $totalQuery->get()->row()->total;


// Filtered count
$filterQuery = $CI->db;
$filterQuery->reset_query();
$filterQuery->select('COUNT(DISTINCT c.userid) as total');
$filterQuery->from(db_prefix() . 'clients c');
$filterQuery->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$filterQuery->join(db_prefix() . 'customer_groups group', 'group.customer_id = c.userid', 'left');
$filterQuery->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');

if (isset($current_branch_id) && $current_branch_id) {
    $filterQuery->where('group.groupid', $current_branch_id);
}
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $filterQuery->where("DATE(new.registration_start_date) BETWEEN '$from_date' AND '$to_date'");
}

// Apply summary filter
if ($summary_filter === 'due') {
    $filterQuery->where('EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid AND i.status != 2
    )', null, false);
} elseif ($summary_filter === 'no_due') {
    $filterQuery->where('NOT EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid AND i.status != 2
    )', null, false);
} elseif ($summary_filter === 'registered') {
    $filterQuery->where('new.mr_no IS NOT NULL');
} elseif ($summary_filter === 'not_registered') {
    $filterQuery->group_start();
	$filterQuery->where('new.mr_no IS NULL', null, false);
	$filterQuery->or_where('new.mr_no', '');
	$filterQuery->group_end();

} elseif ($summary_filter === 'renewal') {
    $CI->db->where('new.mr_no IS NOT NULL'); // ensure registered

    $today = date('Y-m-d');

    $subquery = '
        SELECT 1 FROM ' . db_prefix() . 'invoices e
        WHERE e.clientid = c.userid
        AND e.duedate IS NOT NULL
        AND e.duedate = (
            SELECT MAX(e2.duedate)
            FROM ' . db_prefix() . 'invoices e2
            WHERE e2.clientid = c.userid
        )
    ';

    // Now apply date condition on the outer query
    if ($from_date && $to_date) {
        $subquery .= ' AND DATE(e.duedate) BETWEEN "' . $from_date . '" AND "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $CI->db->where('EXISTS (' . $subquery . ')', null, false);
}
 elseif ($summary_filter === 'new_patients') {
    $filterQuery->where('new.mr_no IS NOT NULL');
}

// Apply search filters
if (!empty($search)) {
    $filterQuery->group_start();
    $filterQuery->like('c.company', $search);
    $filterQuery->or_like('c.phonenumber', $search);
    $filterQuery->or_like('new.mr_no', $search);
    $filterQuery->or_like('new.alt_number1', $search);
    $filterQuery->group_end();
}

$filteredRecords = $filterQuery->get()->row()->total;


// Main data query
$CI->db->reset_query();
$CI->db->distinct();
$ciPrefix = db_prefix();
$CI->db->select("
    c.userid,
    c.company,
    c.phonenumber,
    c.datecreated,
    new.age,
    new.gender,
    c.city,
    c.state,
    new.registration_start_date,
    new.registration_end_date,
    new.current_status,
    new.patient_status,
    source.name as patient_source_name,
    (
        SELECT i.description
        FROM {$ciPrefix}appointment ap
        LEFT JOIN {$ciPrefix}items i ON i.id = ap.treatment_id
        WHERE ap.userid = c.userid
        ORDER BY ap.appointment_id DESC
        LIMIT 1
    ) as treatment_name,
    (
        SELECT CONCAT_WS(' ', st.firstname, st.lastname)
        FROM {$ciPrefix}appointment ap2
        LEFT JOIN {$ciPrefix}staff st ON st.staffid = ap2.enquiry_doctor_id
        WHERE ap2.userid = c.userid
        ORDER BY ap2.appointment_id DESC
        LIMIT 1
    ) as doctor_name,
    (
        SELECT cl.created_date
        FROM {$ciPrefix}patient_call_logs cl
        WHERE cl.patientid = c.userid
        ORDER BY cl.id DESC
        LIMIT 1
    ) as last_calling_date,
    (
        SELECT cl.next_calling_date
        FROM {$ciPrefix}patient_call_logs cl
        WHERE cl.patientid = c.userid
        ORDER BY cl.id DESC
        LIMIT 1
    ) as next_calling_date,
    (
        SELECT ls.name
        FROM {$ciPrefix}lead_patient_journey lj
        LEFT JOIN {$ciPrefix}leads_status ls ON ls.id = lj.status
        WHERE lj.userid = c.userid
        ORDER BY lj.id DESC
        LIMIT 1
    ) as latest_status_name,
    (
        SELECT ls.color
        FROM {$ciPrefix}lead_patient_journey lj
        LEFT JOIN {$ciPrefix}leads_status ls ON ls.id = lj.status
        WHERE lj.userid = c.userid
        ORDER BY lj.id DESC
        LIMIT 1
    ) as latest_status_color,
    (
        SELECT lj.status
        FROM {$ciPrefix}lead_patient_journey lj
        WHERE lj.userid = c.userid
        ORDER BY lj.id DESC
        LIMIT 1
    ) as latest_status_id
");
$CI->db->from(db_prefix() . 'clients c');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'customer_groups group', 'group.customer_id = c.userid', 'left');
$CI->db->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');
if (isset($current_branch_id) && $current_branch_id) {
    $CI->db->where(['group.groupid' => $current_branch_id]);
}
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $CI->db->where("DATE(new.registration_start_date) BETWEEN '$from_date' AND '$to_date'");
}

if (!empty($search)) {
    $CI->db->group_start();
    $CI->db->like('c.company', $search);
    $CI->db->or_like('c.phonenumber', $search);
    $CI->db->or_like('new.mr_no', $search);
    $CI->db->or_like('new.alt_number1', $search);
    $CI->db->group_end();
}
$CI->db->order_by($order_column, $order_dir);
if ($length != -1) {
    $CI->db->limit($length, $start);
}


if ($summary_filter === 'due') {
    $CI->db->where('EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid
        AND i.status != 2
    )');
} elseif ($summary_filter === 'no_due') {
    $CI->db->where('NOT EXISTS (
        SELECT 1 FROM ' . db_prefix() . 'invoices i
        WHERE i.clientid = c.userid
        AND i.status != 2
    )');
}elseif ($summary_filter === 'registered') {
    $CI->db->where('new.mr_no IS NOT NULL');
} elseif ($summary_filter === 'not_registered') {
    $CI->db->group_start();
	$CI->db->where('new.mr_no IS NULL', null, false);
	$CI->db->or_where('new.mr_no', '');
	$CI->db->group_end();

}elseif ($summary_filter === 'renewal') {
    $CI->db->where('new.mr_no IS NOT NULL'); // ensure registered

    $today = date('Y-m-d');

    $subquery = '
        SELECT 1 FROM ' . db_prefix() . 'invoices e
        WHERE e.clientid = c.userid
        AND e.duedate IS NOT NULL
        AND e.duedate = (
            SELECT MAX(e2.duedate)
            FROM ' . db_prefix() . 'invoices e2
            WHERE e2.clientid = c.userid
        )
    ';

    // Add optional from_date and to_date condition
    if ($from_date && $to_date) {
        $subquery .= ' AND DATE(e.duedate) BETWEEN "' . $from_date . '" AND "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $CI->db->where('EXISTS (' . $subquery . ')', null, false);
}elseif ($summary_filter === 'new_patients') {
    $CI->db->where('new.mr_no IS NOT NULL');
}


/* echo $CI->db->get_compiled_select();
exit; */  

$results = $CI->db->get()->result_array();

// Prepare output
$output = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => []
];

$hasPermissionDelete = has_permission('clients', '', 'delete');
$i = $start + 1;

$statusColorMap = [];
$CI->db->select('name, color, id');
$statuses = $CI->db->get(db_prefix() . 'leads_status')->result_array();
foreach ($statuses as $statusRow) {
    $statusColorMap[$statusRow['name']] = [
        'id'    => $statusRow['id'],
        'color' => $statusRow['color']
    ];
}


foreach ($results as $row) {
    $dataRow = [];

    $company = e(format_name($row['company'])) ?: _l('no_company_view_profile');
    $company .= '<br><label style="font-weight: 300; font-size: 12px">' . e(_dt($row['datecreated'])) . '</label>';
    $url = admin_url('client/get_patient_list/' . $row['userid']);
    $company = '<a href="' . $url . '" class="tw-font-medium">' . $company . '</a>';
    $company .= '<div class="row-options">';
    if ($hasPermissionDelete) {
        $confirmText = addslashes(_l('confirm_action_prompt'));
        $company    .= '<a href="' . admin_url('client/delete/' . $row['userid']) . '" class="_delete" onclick="return confirm(\'' . $confirmText . '\')">' . _l('delete') . '</a>';
    }
    $company .= '</div>';

    $phonenumber = (staff_can('mobile_masking', 'customers') && !is_admin()) 
        ? mask_last_5_digits_1($row['phonenumber']) 
        : $row['phonenumber'];

    $statusId    = (int) ($row['latest_status_id'] ?? 0);
    $statusName  = $row['latest_status_name'] ?? 'Unknown';
    $statusColor = $row['latest_status_color'] ?? '#7cb342';
    $statusLabel = '<span class="lead-status-' . $statusId . ' label" style="color:' . $statusColor . ';border:1px solid ' . adjust_hex_brightness($statusColor, 0.4) . ';background:' . adjust_hex_brightness($statusColor, 0.04) . ';">' . e($statusName) . '</span>';
	
	
	$currentStatusName = trim($row['current_status']);
	$currentStatusLabel = '-';

	if (!empty($currentStatusName) && isset($statusColorMap[$currentStatusName])) {
		$statusInfo = $statusColorMap[$currentStatusName];
		$color = $statusInfo['color'];
		$id    = $statusInfo['id'];

		$currentStatusLabel = '<span class="lead-status-' . $id . ' label" style="color:' . $color . ';border:1px solid ' . adjust_hex_brightness($color, 0.4) . ';background:' . adjust_hex_brightness($color, 0.04) . ';">' . e($currentStatusName) . '</span>';
	}


    $dataRow[] = $i++;
    $dataRow[] = $company;
    $dataRow[] = $row['age'];
    $dataRow[] = $row['gender'];
    $dataRow[] = $phonenumber;
    $dataRow[] = $row['treatment_name'] ?? '';
    $dataRow[] = $row['doctor_name'] ?? '-';

    $dataRow[] = $row['patient_source_name'];
    $dataRow[] = $row['last_calling_date'];
    $dataRow[] = $row['next_calling_date'];
    $dataRow[] = $statusLabel;
    $dataRow[] = $currentStatusLabel;
    //$dataRow[] = $row['current_status'];
    $dataRow[] = $row['registration_start_date'];
    $dataRow[] = (!empty($row['registration_end_date']) && $row['registration_end_date'] !== '1970-01-01') ? $row['registration_end_date'] : '';
    $dataRow[] = '<a href="' . admin_url('clients/client/' . $row['userid']) . '" data-toggle="tooltip" title="' . _l('view') . '">' . e($row['patient_status']) . '</a>';

    $output['data'][] = $dataRow;
}



echo json_encode($output);
exit;
