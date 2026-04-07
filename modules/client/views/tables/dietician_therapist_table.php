<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

$draw = (int) $CI->input->post('draw');
$start = (int) $CI->input->post('start');
$length = (int) $CI->input->post('length');
$search = $CI->input->post('search')['value'] ?? '';

$client_id = isset($client_id) ? (int) $client_id : 0;

$CI->db->select('dt.*, s.firstname, s.lastname');
$CI->db->from(db_prefix() . 'patient_dietician_therapist dt');
$CI->db->join(db_prefix() . 'staff s', 's.staffid = dt.created_by', 'left');
$CI->db->where('dt.patientid', $client_id);

if ($search !== '') {
    $CI->db->group_start();
    $CI->db->like('dt.name', $search);
    $CI->db->or_like('dt.description', $search);
    $CI->db->or_like('dt.remarks', $search);
    $CI->db->or_like('s.firstname', $search);
    $CI->db->or_like('s.lastname', $search);
    $CI->db->group_end();
}

$total_records = $CI->db->count_all_results('', false);

$CI->db->order_by('dt.id', 'DESC');
if ($length > 0) {
    $CI->db->limit($length, $start);
}

$results = $CI->db->get()->result_array();

$data = [];
foreach ($results as $row) {
    $attachment_link = '-';
    if (!empty($row['attachment'])) {
        $attachment_url = base_url($row['attachment']);
        $ext = strtolower(pathinfo((string) $row['attachment'], PATHINFO_EXTENSION));
        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

        if ($is_image) {
            $attachment_link = '<a href="' . $attachment_url . '" target="_blank">'
                . '<img src="' . $attachment_url . '" alt="attachment" style="max-width:60px;max-height:60px;border:1px solid #ddd;border-radius:4px;" />'
                . '</a>';
        } else {
            $attachment_link = '<a href="' . $attachment_url . '" target="_blank">' . _l('view') . '</a>';
        }
    }

    $created_by = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
    if ($created_by === '') {
        $created_by = '-';
    }

    $data[] = [
        e($row['name']),
        e($row['description']),
        $attachment_link,
        e($row['remarks']),
        e($created_by),
    ];
}

echo json_encode([
    'draw' => $draw,
    'iTotalRecords' => $total_records,
    'iTotalDisplayRecords' => $total_records,
    'aaData' => $data,
]);
exit;
