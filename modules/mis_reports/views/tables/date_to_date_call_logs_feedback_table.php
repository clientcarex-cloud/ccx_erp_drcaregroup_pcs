<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'p.id',
    'patients.company as patient_name',
    'new_fields.mr_no',
    'p.called_by',
    'criteria.criteria_name',
    'p.next_calling_date',
    'p.better_patient',
    'p.pharmacy_medicine_days',
    'p.patient_took_medicine_days',
    'p.created_date',
    'p.comments',
];

$sIndexColumn = 'p.id';
$sTable = db_prefix() . 'patient_call_logs p';

$join = [
    'LEFT JOIN ' . db_prefix() . 'clients patients ON patients.userid = p.patientid',
    'LEFT JOIN ' . db_prefix() . 'clients_new_fields new_fields ON new_fields.userid = p.patientid',
    'LEFT JOIN ' . db_prefix() . 'criteria criteria ON criteria.criteria_id = p.criteria_id',
];

$where = [];

// Date range filter on created_date
if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $to_date = date('Y-m-d', strtotime($consulted_to_date));
    $where[] = "AND DATE(p.created_date) >= '{$from_date}' AND DATE(p.created_date) <= '{$to_date}'";
} elseif (!empty($consulted_from_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $where[] = "AND DATE(p.created_date) = '{$from_date}'";
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['p.id', 'p.patientid']);
$output = $result['output'];
$rResult = $result['rResult'];

$serial = (isset($_POST['start']) ? intval($_POST['start']) : 0) + 1;

foreach ($rResult as $aRow) {
    $row = [];

    $url = admin_url('client/get_patient_list/' . $aRow['patientid']);

    $row[] = $serial++;
    $row[] = '<b><a href="' . $url . '">' . e($aRow['patient_name']) . '</a></b>';
    $row[] = e($aRow['mr_no']);
    $row[] = e(get_staff_full_name($aRow['called_by']));
    $row[] = e($aRow['criteria_name']);
    $row[] = $aRow['next_calling_date'] ? _dt($aRow['next_calling_date']) : '-';
    $row[] = e($aRow['better_patient']) ?: '-';
    $row[] = e($aRow['pharmacy_medicine_days']) ?: '-';
    $row[] = e($aRow['patient_took_medicine_days']) ?: '-';
    $row[] = $aRow['created_date'] ? _dt($aRow['created_date']) : '-';
    $row[] = e($aRow['comments']) ?: '-';

    $output['aaData'][] = $row;
}

echo json_encode($output);
exit;
