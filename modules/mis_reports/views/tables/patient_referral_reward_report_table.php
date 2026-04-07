<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$search_value = $_POST['search']['value'] ?? '';

$from_date = $CI->input->get('from_date');
$to_date   = $CI->input->get('to_date');
$branch_ids_raw = $CI->input->get('branch');

$branch_ids = [];
if (!empty($branch_ids_raw)) {
    $branch_ids_raw = urldecode($branch_ids_raw);
    $branch_ids = array_filter(array_map('intval', explode(',', $branch_ids_raw)));
}

$CI->db->select("
    l.id as lead_id,
    l.name as lead_name,
    l.phonenumber as lead_phone,
    l.email as lead_email,
    l.dateadded as lead_created,
    l.refer_type,
    l.refer_id,
    l.branch_id as lead_branch_id,
    l.junk as lead_junk,
    l.lost as lead_lost,
    rp.userid as referrer_id,
    rp.company as referrer_name,
    ls.name as lead_source_name,
    lst.name as lead_status_name,
    c.userid as client_id,
    c.company as patient_name,
    cnf.mr_no
", false);

$CI->db->from(db_prefix() . 'leads l');

$CI->db->join(db_prefix() . 'clients rp', 'rp.userid = l.refer_id', 'left');
$CI->db->join(db_prefix() . 'leads_sources ls', 'ls.id = l.source', 'left');
$CI->db->join(db_prefix() . 'leads_status lst', 'lst.id = l.status', 'left');
$CI->db->join(db_prefix() . 'clients c', 'c.leadid = l.id', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields cnf', 'cnf.userid = c.userid', 'left');

$CI->db->where('l.refer_type', 'patient');
$CI->db->where('l.refer_id IS NOT NULL', null, false);
$CI->db->where('l.refer_id !=', 0);

if (!empty($from_date)) {
    $CI->db->where('DATE(l.dateadded) >=', $from_date);
}
if (!empty($to_date)) {
    $CI->db->where('DATE(l.dateadded) <=', $to_date);
}

if (!empty($branch_ids)) {
    $CI->db->where_in('l.branch_id', $branch_ids);
}

if (!empty($search_value)) {
    $CI->db->group_start();
    $CI->db->like('l.name', $search_value);
    $CI->db->or_like('l.phonenumber', $search_value);
    $CI->db->or_like('l.email', $search_value);
    $CI->db->or_like('rp.company', $search_value);
    $CI->db->or_like('c.company', $search_value);
    $CI->db->or_like('cnf.mr_no', $search_value);
    $CI->db->or_like('ls.name', $search_value);
    $CI->db->group_end();
}

$CI->db->order_by('l.dateadded', 'DESC');

$db_count = clone $CI->db;
$total_filtered = $db_count->get()->num_rows();

$CI->db->limit($length, $start);
$results = $CI->db->get()->result_array();

$CI->db->select('COUNT(*) as total');
$CI->db->from(db_prefix() . 'leads l');
$CI->db->where('l.refer_type', 'patient');
$CI->db->where('l.refer_id IS NOT NULL', null, false);
$CI->db->where('l.refer_id !=', 0);
if (!empty($from_date)) {
    $CI->db->where('DATE(l.dateadded) >=', $from_date);
}
if (!empty($to_date)) {
    $CI->db->where('DATE(l.dateadded) <=', $to_date);
}
if (!empty($branch_ids)) {
    $CI->db->where_in('l.branch_id', $branch_ids);
}
$total_all = $CI->db->get()->row()->total;

$output = [
    'draw'            => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
    'recordsTotal'    => $total_all,
    'recordsFiltered' => $total_filtered,
    'aaData'          => [],
];

$i = $start + 1;
foreach ($results as $row) {
    $referrer_name = trim($row['referrer_name'] ?? '');
    if (empty($referrer_name)) {
        $referrer_name = 'N/A';
    }

    $lead_name  = $row['lead_name'] ?? '-';
    $lead_phone = $row['lead_phone'] ?? '-';
    $lead_email = $row['lead_email'] ?? '-';
    $source     = $row['lead_source_name'] ?? '-';
    $status     = $row['lead_status_name'] ?? '-';
    $created    = !empty($row['lead_created']) ? _dt($row['lead_created']) : '-';

    $converted = !empty($row['client_id']) ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>';
    $patient_name = !empty($row['patient_name']) ? $row['patient_name'] : '-';
    $mr_no = !empty($row['mr_no']) ? $row['mr_no'] : '-';

    $output['aaData'][] = [
        $i++,
        $referrer_name,
        $lead_name,
        $lead_phone,
        $lead_email,
        $source,
        $status,
        $created,
        $converted,
        $patient_name,
        $mr_no,
    ];
}

echo json_encode($output);
exit;
