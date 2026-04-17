<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

// Filters passed from controller are unpacked natively via get_table_data() 
// So $branch_id, $from_date, $to_date, $cell_type are already valid here.
$branch_id = isset($branch_id) ? $branch_id : '';
$from_date = isset($from_date) ? $from_date : '';
$to_date   = isset($to_date)   ? $to_date   : '';
$cell_type = isset($cell_type) ? $cell_type : '';

$from_date_esc = $CI->db->escape_str($from_date);
$to_date_esc   = $CI->db->escape_str($to_date);

if (!$from_date_esc) $from_date_esc = date('Y-m-01');
if (!$to_date_esc)   $to_date_esc = date('Y-m-t');
if (!$branch_id)     $branch_id = 0;
$branch_id = (int)$branch_id;

$draw   = intval($CI->input->post('draw') ?? 1);
$start  = intval($CI->input->post('start') ?? 0);
$length = intval($CI->input->post('length') ?? 10);

$search = '';
$search_post = $CI->input->post('search');
if (is_array($search_post) && isset($search_post['value'])) {
    $search = $search_post['value'];
}

// Common exists clauses 
$first_appt_exists = "
    EXISTS (
        SELECT 1 FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        WHERE a.userid = inv.clientid
          AND atype.appointment_type_name = 'First Appointment'
          AND (DATE(a.appointment_date) = DATE(pr.date) OR DATE(a.created_at) = DATE(pr.date))
    )
";

$followup_appt_exists = "
    EXISTS (
        SELECT 1 FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        WHERE a.userid = inv.clientid
          AND atype.appointment_type_name <> 'First Appointment'
          AND (DATE(a.appointment_date) = DATE(pr.date) OR DATE(a.created_at) = DATE(pr.date))
    )
    AND NOT EXISTS (
        SELECT 1 FROM tblappointment a2
        JOIN tblappointment_type atype2 ON atype2.appointment_type_id = a2.appointment_type_id
        WHERE a2.userid = inv.clientid
          AND atype2.appointment_type_name = 'First Appointment'
          AND (DATE(a2.appointment_date) = DATE(pr.date) OR DATE(a2.created_at) = DATE(pr.date))
    )
";

$referral_source_filter = "
    EXISTS (
        SELECT 1 FROM tblgoal_lead_sources gls
        JOIN tblclients_new_fields nf_src ON nf_src.userid = inv.clientid
        WHERE gls.source_id = nf_src.patient_source_id
          AND gls.category = 'referral'
    )
";

$not_referral_source_filter = "
    NOT EXISTS (
        SELECT 1 FROM tblgoal_lead_sources gls
        JOIN tblclients_new_fields nf_src ON nf_src.userid = inv.clientid
        WHERE gls.source_id = nf_src.patient_source_id
          AND gls.category = 'referral'
    )
";

$final_sql = "SELECT c.company as patient_name, '' as mr_no, '' as source_name FROM tblclients c LIMIT 10";

$results = $CI->db->query($final_sql)->result_array();
$total = count($results);

$data = [];
foreach ($results as $row) {
    $data[] = [
        isset($row['patient_name']) ? e($row['patient_name']) : '',
        isset($row['mr_no']) ? e($row['mr_no']) : '',
        isset($row['source_name']) ? e($row['source_name']) : '',
        '', // Treatment
        '', // Appt. Type
        '', // Created Date
        '', // First Visit
        '', // Appt Date
        '', // Visited
        '', // Consulted
        '', // Doctor
        '', // Reg Date
        '', // Pkg Amount
        '', // Paid
        '', // Pmt Date
        ''  // Pmt Type
    ];
}

$output = [
    'draw' => intval($draw),
    'recordsTotal' => $total,
    'recordsFiltered' => $total,
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($output);
exit;
