<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'l.id',
    'l.name',
    'l.phonenumber',
    'ls.name as status_name',
    'l.refer_id',
    'l.branch_id',
    'l.dateadded',
];

$sIndexColumn = 'l.id';
$sTable = db_prefix() . 'leads l';

$join = [
    'LEFT JOIN ' . db_prefix() . 'leads_status ls ON ls.id = l.status',
];

$where = [];
$where[] = "AND l.refer_type = 'staff'";
$where[] = "AND l.refer_id IS NOT NULL AND l.refer_id != 0";

// Date range filter on dateadded
if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $to_date = date('Y-m-d', strtotime($consulted_to_date));
    $where[] = "AND DATE(l.dateadded) >= '{$from_date}' AND DATE(l.dateadded) <= '{$to_date}'";
} elseif (!empty($consulted_from_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $where[] = "AND DATE(l.dateadded) = '{$from_date}'";
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['l.id', 'l.refer_id', 'l.branch_id']);
$output = $result['output'];
$rResult = $result['rResult'];

$serial = (isset($_POST['start']) ? intval($_POST['start']) : 0) + 1;

// Cache branch names
$CI = &get_instance();
$branches_result = $CI->db->get(db_prefix() . 'branch')->result_array();
$branch_map = [];
foreach ($branches_result as $b) {
    $branch_map[$b['id']] = $b['name'];
}

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $serial++;
    $row[] = $aRow['name'];
    $row[] = $aRow['phonenumber'];
    $row[] = $aRow['status_name'] ?: '-';

    // Get staff referrer name
    $referrer_name = '-';
    if (!empty($aRow['refer_id'])) {
        $referrer_name = get_staff_full_name($aRow['refer_id']);
    }
    $row[] = $referrer_name;

    // Branch name
    $row[] = isset($branch_map[$aRow['branch_id']]) ? $branch_map[$aRow['branch_id']] : '-';

    $row[] = $aRow['dateadded'] ? _dt($aRow['dateadded']) : '-';

    $output['aaData'][] = $row;
}

echo json_encode($output);
exit;
