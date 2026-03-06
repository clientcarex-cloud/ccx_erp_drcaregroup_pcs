<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();
$CI->load->model('client/client_model');

// DataTables Params
$draw = $CI->input->post('draw');
$start = $CI->input->post('start');
$length = $CI->input->post('length');
$search = $CI->input->post('search')['value'] ?? '';
$order = $CI->input->post('order');

$order_dir = 'desc';

// ======================== Date range filter ========================
$date_where = '';
if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $to_date = date('Y-m-d', strtotime($consulted_to_date));
    $date_where = " AND DATE(c.date) >= '{$from_date}' AND DATE(c.date) <= '{$to_date}'";
} elseif (!empty($consulted_from_date)) {
    $from_date = date('Y-m-d', strtotime($consulted_from_date));
    $date_where = " AND DATE(c.date) = '{$from_date}'";
}

// ======================== COUNT TOTAL RECORDS ========================
$CI->db->select('COUNT(DISTINCT c.id) as total_records', false);
$CI->db->from(db_prefix() . 'casesheet c');
$CI->db->join(db_prefix() . 'clients patients', 'patients.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields new_fields', 'new_fields.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'patient_treatment t', 't.casesheet_id = c.id', 'left');
$CI->db->join(db_prefix() . 'suggested_diagnostics sd', 'sd.suggested_diagnostics_id = t.suggested_diagnostics_id', 'left');

if (!empty($date_where)) {
    $CI->db->where($date_where);
}

if (!empty($search)) {
    $CI->db->group_start();
    $CI->db->like('c.clinical_observation', $search);
    $CI->db->or_like('patients.company', $search);
    $CI->db->or_like('new_fields.mr_no', $search);
    $CI->db->group_end();
}

$total_records_row = $CI->db->get()->row();
$total_records = $total_records_row->total_records ?? 0;

// ======================== FETCH ACTUAL DATA ========================
$CI->db->select("
    c.id,
    c.userid,
    c.date,
    c.staffid,
    c.clinical_observation,
    c.medicine_days,
    c.doctor_medicine_days,
    c.created_at,
    patients.company as patient_name,
    new_fields.mr_no,
    sd.suggested_diagnostics_name
");
$CI->db->from(db_prefix() . 'casesheet c');
$CI->db->join(db_prefix() . 'clients patients', 'patients.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields new_fields', 'new_fields.userid = c.userid', 'left');
$CI->db->join(db_prefix() . 'patient_treatment t', 't.casesheet_id = c.id', 'left');
$CI->db->join(db_prefix() . 'suggested_diagnostics sd', 'sd.suggested_diagnostics_id = t.suggested_diagnostics_id', 'left');

if (!empty($date_where)) {
    $CI->db->where($date_where);
}

if (!empty($search)) {
    $CI->db->group_start();
    $CI->db->like('c.clinical_observation', $search);
    $CI->db->or_like('patients.company', $search);
    $CI->db->or_like('new_fields.mr_no', $search);
    $CI->db->group_end();
}

$CI->db->group_by('c.id');
$CI->db->order_by("c.date", "DESC");
$CI->db->limit($length, $start);

$results = $CI->db->get()->result_array();

// ======================== Map prescriptions ========================
// Collect all casesheet IDs to batch-load prescriptions
$casesheet_ids = array_column($results, 'id');
$prescription_map = [];

if (!empty($casesheet_ids)) {
    $CI->db->select('casesheet_id, prescription_data');
    $CI->db->from(db_prefix() . 'patient_prescription');
    $CI->db->where_in('casesheet_id', $casesheet_ids);
    $prescriptions = $CI->db->get()->result_array();

    foreach ($prescriptions as $p) {
        if (!empty($p['casesheet_id'])) {
            $prescription_map[$p['casesheet_id']][] = $p['prescription_data'];
        }
    }
}

// ======================== Render Output ========================
$data = [];
$serial = ($start ?? 0) + 1;

foreach ($results as $row) {
    // Format prescription
    $formatted_prescription = '<em>No prescription</em>';
    if (isset($prescription_map[$row['id']])) {
        $i = 1;
        $formatted_prescription = '';
        foreach ($prescription_map[$row['id']] as $raw_prescription) {
            $lines = explode('|', $raw_prescription);
            foreach ($lines as $line) {
                $formatted_prescription .= '<div>' . $i++ . ') ' . htmlspecialchars(trim(str_replace(';', ', ', $line))) . '</div>';
            }
        }
    }

    // Action buttons
    $action_buttons = '';
    $action_buttons .= '<a href="' . admin_url('client/view_casesheet/' . $row['id'] . '/' . $row['userid']) . '"><button type="button" class="btn btn-sm view-button" style="background-color: black; color: white;" title="View Casesheet"><i class="fas fa-eye"></i></button></a>';

    $url = admin_url('client/get_patient_list/' . $row['userid']);

    $data[] = [
        $serial++,
        '<b><a href="' . $url . '">' . e($row['patient_name']) . '</a></b>',
        e($row['mr_no']),
        _d($row['date']),
        get_staff_full_name($row['staffid']),
        $row['clinical_observation'] ?: '-',
        $row['suggested_diagnostics_name'] ?: '-',
        $row['doctor_medicine_days'] ?: '-',
        $row['medicine_days'] ?: '-',
        $formatted_prescription,
        $action_buttons,
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'iTotalRecords' => $total_records,
    'iTotalDisplayRecords' => $total_records,
    'aaData' => $data,
]);

exit();
