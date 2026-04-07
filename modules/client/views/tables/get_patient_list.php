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

$order_column_index = (int) ($CI->input->post('order')[0]['column'] ?? 0);
$incoming_order_dir = strtolower($CI->input->post('order')[0]['dir'] ?? 'desc');
$order_dir = $incoming_order_dir === 'asc' ? 'asc' : 'desc';

$summary_filter = $CI->input->get('summary_filter');

$applyRegistrationDateFilter = static function ($query, $from, $to) {
    if (empty($from) || empty($to)) {
        return;
    }

    // Keep this index-friendly by avoiding DATE(column) wrappers.
    $query->where('new.registration_start_date >=', $from . ' 00:00:00');
    $query->where('new.registration_start_date <=', $to . ' 23:59:59');
};

// Map DataTable columns to actual SQL columns/aliases (null means fallback to default)
$columns = [
    'c.userid',
    'c.company',
    'c.datecreated',
    'new.mr_no',
    'new.age',
    'new.gender',
    'c.phonenumber',
    null,
    null,
    'patient_source_name',
    'branch_names',
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
// Total count
$normalizeBranchList = static function ($value) {
    if ($value === null) {
        return [];
    }

    $list = is_array($value) ? $value : explode(',', (string) $value);
    $normalized = [];

    foreach ($list as $item) {
        if ($item === null) {
            continue;
        }
        $item = (string) $item;
        if ($item === '' || strtolower($item) === 'null') {
            continue;
        }
        $decoded = rawurldecode($item);
        $decoded = (string) $decoded;
        if ($decoded === '' || strtolower($decoded) === 'null') {
            continue;
        }
        if (is_numeric($decoded)) {
            $normalized[] = (int) $decoded;
        }
    }

    return array_values(array_unique($normalized));
};

$branchInputValue = $CI->input->post('branch_ids');
if ($branchInputValue === null) {
    $branchInputValue = $CI->input->get('branch_ids');
}

$branchFilterIds = $normalizeBranchList($branchInputValue);
$session = $CI->session ?? null;
$sessionFilterKey = 'patient_branch_filter';

$allowedBranchIds = $normalizeBranchList($accessible_branch_ids ?? []);
$restrictToAllowedBranches = static function ($ids) use ($allowedBranchIds) {
    if (empty($allowedBranchIds)) {
        return $ids;
    }
    if (empty($ids)) {
        return [];
    }

    $filtered = [];
    foreach ($ids as $id) {
        $intId = (int) $id;
        if (in_array($intId, $allowedBranchIds, true)) {
            $filtered[] = $intId;
        }
    }

    return array_values(array_unique($filtered));
};

$branchFilterIds = $restrictToAllowedBranches($branchFilterIds);

if (!empty($branchFilterIds) && $session) {
    $session->set_userdata([$sessionFilterKey => implode(',', $branchFilterIds)]);
}

if (empty($branchFilterIds) && isset($branch_filter_ids) && is_array($branch_filter_ids)) {
    $branchFilterIds = $restrictToAllowedBranches($normalizeBranchList($branch_filter_ids));
}

if (empty($branchFilterIds) && isset($selected_branch_id) && is_array($selected_branch_id)) {
    $branchFilterIds = $restrictToAllowedBranches($normalizeBranchList($selected_branch_id));
}

if (empty($branchFilterIds) && isset($current_branch_id) && $current_branch_id) {
    $branchFilterIds = $restrictToAllowedBranches($normalizeBranchList($current_branch_id));
}

if (empty($branchFilterIds) && $session) {
    $savedFilter = $restrictToAllowedBranches($normalizeBranchList($session->userdata($sessionFilterKey)));
    if (!empty($savedFilter)) {
        $branchFilterIds = $savedFilter;
    }
}

if (empty($branchFilterIds) && !empty($allowedBranchIds)) {
    $branchFilterIds = $allowedBranchIds;
}

$applyBranchFilter = static function ($query) use ($branchFilterIds) {
    if (empty($branchFilterIds)) {
        return;
    }

    $cleanIds = array_map('intval', $branchFilterIds);
    $cleanIds = array_filter($cleanIds, function ($value) {
        return $value > 0;
    });

    if (empty($cleanIds)) {
        return;
    }

    $query->where('EXISTS (
        SELECT 1
        FROM ' . db_prefix() . 'customer_groups cg_filter
        WHERE cg_filter.customer_id = c.userid
        AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
    )', null, false);
};

$totalQuery = $CI->db;
$totalQuery->reset_query();
$totalQuery->select('COUNT(*) as total');
$totalQuery->from(db_prefix() . 'clients c');
$totalQuery->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');

$applyBranchFilter($totalQuery);
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $applyRegistrationDateFilter($totalQuery, $from_date, $to_date);
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
    $totalQuery->where('new.mr_no IS NOT NULL'); // ensure registered

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
		$subquery .= ' AND e.duedate >= "' . $from_date . '" AND e.duedate <= "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $totalQuery->where('EXISTS (' . $subquery . ')', null, false);
} elseif ($summary_filter === 'new_patients') {
    $totalQuery->where('new.mr_no IS NOT NULL');
}

$totalRecords = $totalQuery->get()->row()->total;


// Filtered count
$filterQuery = $CI->db;
$filterQuery->reset_query();
$filterQuery->select('COUNT(*) as total');
$filterQuery->from(db_prefix() . 'clients c');
$filterQuery->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$filterQuery->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');

$applyBranchFilter($filterQuery);
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $applyRegistrationDateFilter($filterQuery, $from_date, $to_date);
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
    $filterQuery->where('new.mr_no IS NOT NULL'); // ensure registered

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
		$subquery .= ' AND e.duedate >= "' . $from_date . '" AND e.duedate <= "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $filterQuery->where('EXISTS (' . $subquery . ')', null, false);
} elseif ($summary_filter === 'new_patients') {
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
$CI->db->select('c.userid, c.company, c.phonenumber, c.datecreated, new.mr_no, new.age, new.gender, c.city, c.state, new.registration_start_date, new.registration_end_date, new.current_status, new.patient_status, source.name as patient_source_name,
    (
        SELECT GROUP_CONCAT(DISTINCT cg_names.name ORDER BY cg_names.name SEPARATOR ", ")
        FROM ' . db_prefix() . 'customer_groups cg_rel
        LEFT JOIN ' . db_prefix() . 'customers_groups cg_names ON cg_names.id = cg_rel.groupid
        WHERE cg_rel.customer_id = c.userid
    ) AS branch_names');
$CI->db->from(db_prefix() . 'clients c');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');
$applyBranchFilter($CI->db);
if ($from_date && $to_date && $summary_filter != 'not_registered') {
    $applyRegistrationDateFilter($CI->db, $from_date, $to_date);
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
} elseif ($summary_filter === 'registered') {
    $CI->db->where('new.mr_no IS NOT NULL');
} elseif ($summary_filter === 'not_registered') {
    $CI->db->group_start();
    $CI->db->where('new.mr_no IS NULL', null, false);
    $CI->db->or_where('new.mr_no', '');
    $CI->db->group_end();

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

    // Add optional from_date and to_date condition
    if ($from_date && $to_date) {
		$subquery .= ' AND e.duedate >= "' . $from_date . '" AND e.duedate <= "' . $to_date . '"';
    } else {
        $subquery .= ' AND e.duedate <= "' . $today . '"';
    }

    $CI->db->where('EXISTS (' . $subquery . ')', null, false);
} elseif ($summary_filter === 'new_patients') {
    $CI->db->where('new.mr_no IS NOT NULL');
}


$results = $CI->db->get()->result_array();

// Process user IDs
$userIds = array_column($results, 'userid');
$treatmentMap = $doctorMap = $callLogMap = $leadStatuses = [];

if (!empty($userIds)) {
    // Latest appointment
    $userIdsStr = implode(',', $userIds);

    $treatmentMap = [];
    $doctorMap = [];

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
    $CI->db->where_in('a.userid', $userIds);

    $appointments = $CI->db->get()->result_array();

    foreach ($appointments as $app) {
        $treatmentMap[$app['userid']] = $app['treatment_name'] ?? '-';
        $doctorMap[$app['userid']] = [
            'id' => $app['enquiry_doctor_id'],
            'name' => $app['doctor_name'] ?? '-',
        ];
    }



    // Latest call logs
    $CI->db->select('c.patientid, c.created_date as last_calling_date, c.next_calling_date');
    $CI->db->from(db_prefix() . 'patient_call_logs c');
    $CI->db->join("(SELECT MAX(id) as max_id, patientid FROM " . db_prefix() . "patient_call_logs WHERE patientid IN (" . $userIdsStr . ") GROUP BY patientid) as latest", 'c.id = latest.max_id', 'inner');
    $CI->db->where_in('c.patientid', $userIds);
    $callLogs = $CI->db->get()->result_array();
    foreach ($callLogs as $log) {
        $callLogMap[$log['patientid']] = $log;
    }

    // Latest journey status (single row per user)
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
        'id' => $statusRow['id'],
        'color' => $statusRow['color']
    ];
}


foreach ($results as $row) {
    $dataRow = [];

    $company = e(format_name($row['company'])) ?: _l('no_company_view_profile');
    $createdDateTime = !empty($row['datecreated']) ? e(_dt($row['datecreated'])) : '-';

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

    if ($timeAgoLabel) {
        $createdDateTime .= ' <span style="display: inline-block; background: #4CAF50; color: #fff; font-size: 10px; padding: 1px 8px; border-radius: 50px; font-weight: 500;">' . $timeAgoLabel . '</span>';
    }
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


    $dataRow[] = $i++;
    $dataRow[] = $company;
    $dataRow[] = $createdDateTime;
    $dataRow[] = !empty($row['mr_no']) ? e($row['mr_no']) : '-';
    $dataRow[] = $row['age'];
    $dataRow[] = $row['gender'];
    $dataRow[] = $phonenumber;
    $dataRow[] = $treatmentMap[$row['userid']] ?? '';
    $dataRow[] = isset($doctorMap[$row['userid']]) ? $doctorMap[$row['userid']]['name'] : '-';

    $dataRow[] = $row['patient_source_name'];
    $dataRow[] = !empty($row['branch_names']) ? e($row['branch_names']) : '-';
    $dataRow[] = $callLog['last_calling_date'];
    $dataRow[] = $callLog['next_calling_date'];
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
