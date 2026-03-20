<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'l.id',
    'l.name as lead_name',
    'l.phonenumber',
    'ls.name as status_name',
    'CONCAT(s.firstname, " ", s.lastname) as referrer_name',
    'b.name as branch_name',
    'l.dateadded',
];

$sIndexColumn = 'l.id';
$sTable = db_prefix() . 'leads l';

$join = [
    'LEFT JOIN ' . db_prefix() . 'staff s ON s.staffid = l.refer_id',
    'LEFT JOIN ' . db_prefix() . 'leads_status ls ON ls.id = l.status',
    'LEFT JOIN ' . db_prefix() . 'branch b ON b.id = l.branch_id',
];

$where = [];
$where[] = "AND l.refer_type = 'staff'";

// Date range filter on dateadded
if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $to_date = date('Y-m-d', strtotime($consulted_to_date));
    $where[] = "AND DATE(l.dateadded) >= '{$from_date}' AND DATE(l.dateadded) <= '{$to_date}'";
} elseif (!empty($consulted_from_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $where[] = "AND DATE(l.dateadded) = '{$from_date}'";
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['l.id', 'l.refer_id']);
$output = $result['output'];
$rResult = $result['rResult'];

$serial = (isset($_POST['start']) ? intval($_POST['start']) : 0) + 1;

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $serial++;
    $row[] = e($aRow['lead_name']);
    $row[] = e($aRow['phonenumber']);
    $row[] = e($aRow['status_name']) ?: '-';
    $row[] = e($aRow['referrer_name']) ?: '-';
    $row[] = e($aRow['branch_name']) ?: '-';
    $row[] = $aRow['dateadded'] ? _dt($aRow['dateadded']) : '-';

    $output['aaData'][] = $row;
}

echo json_encode($output);
exit;
