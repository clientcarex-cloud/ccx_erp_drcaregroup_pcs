<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

$draw = (int) ($CI->input->post('draw') ?? 0);
$start = (int) ($CI->input->post('start') ?? 0);
$length = (int) ($CI->input->post('length') ?? 10);
$search = $CI->input->post('search')['value'] ?? '';

$client_id = (int) ($client_id ?? 0);
$lead_id = 0;
if ($client_id > 0) {
    $row = $CI->db->select('leadid')->where('userid', $client_id)->get(db_prefix() . 'clients')->row();
    $lead_id = isset($row->leadid) ? (int) $row->leadid : 0;
}

$CI->db->select('l.*, ls.name as response_name');
$CI->db->from(db_prefix() . 'lead_call_logs l');
$CI->db->join(db_prefix() . 'leads_status ls', 'ls.id = l.patient_response_id', 'left');
$CI->db->where('1 = 0', null, false);
if ($lead_id > 0) {
    $CI->db->or_where('l.leads_id', $lead_id);
}

if ($search !== '') {
    $CI->db->group_start();
    $CI->db->like('l.comments', $search);
    $CI->db->or_like('ls.name', $search);
    $CI->db->or_like('l.created_date', $search);
    $CI->db->group_end();
}

$total_records = $CI->db->count_all_results('', false);

$CI->db->order_by('l.created_date', 'DESC');
$CI->db->limit($length, $start);
$results = $CI->db->get()->result_array();

$data = [];
$serial = $start + 1;

foreach ($results as $row) {
    $data[] = [
        $serial++,
        e(get_staff_full_name($row['enquired_by'] ?? 0)),
        e($row['response_name'] ?? '-'),
        !empty($row['followup_date']) ? _d($row['followup_date']) : '-',
        '-',
        '-',
        '-',
        !empty($row['created_date']) ? _d($row['created_date']) : '-',
        e($row['comments'] ?? '-'),
    ];
}

echo json_encode([
    'draw' => $draw,
    'iTotalRecords' => $total_records,
    'iTotalDisplayRecords' => $total_records,
    'aaData' => $data,
]);

exit;
