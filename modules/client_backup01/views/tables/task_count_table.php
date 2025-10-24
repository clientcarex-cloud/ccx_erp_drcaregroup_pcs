<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('tasks_model');

// Inputs
$draw   = intval($CI->input->post('draw'));
$start  = intval($CI->input->post('start'));
$length = intval($CI->input->post('length'));
$search = $CI->input->post('search')['value'] ?? '';
//$selected_branch_id = $CI->input->post('branch') ?? [];
$selected_roles     = $doctor_id;
$order_column_index = $CI->input->post('order')[0]['column'] ?? 0;
$order_dir          = $CI->input->post('order')[0]['dir'] ?? 'asc';

// Columns for ordering
$columns = ['s.staffid', 's.firstname'];
$order_column = $columns[$order_column_index] ?? 's.staffid';

// Total staff count
$totalRecords = $CI->db->count_all(db_prefix().'staff');

// ====================
// Filtered staff
// ====================
$CI->db->reset_query();
$CI->db->select('s.staffid, s.firstname, s.lastname, s.branch_id, s.role, b.name as branch_name, r.name as role_name');
$CI->db->from(db_prefix().'staff s');
$CI->db->join(db_prefix().'customers_groups b', 'b.id = s.branch_id', 'left'); // branch table corrected
$CI->db->join(db_prefix().'roles r', 'r.roleid = s.role', 'left');

// Branch filter
if (!empty($selected_branch_id)) {
    $CI->db->where_in('s.branch_id', $selected_branch_id);
}

// Role filter
if (!empty($selected_roles)) {
    $CI->db->where_in('s.role', $selected_roles);
}

// Search filter
if (!empty($search)) {
    $CI->db->group_start();
    $CI->db->like('s.firstname', $search);
    $CI->db->or_like('s.lastname', $search);
    $CI->db->or_like('s.email', $search);
    $CI->db->group_end();
}

$filteredStaff = $CI->db->get()->result_array();
$filteredRecords = count($filteredStaff);

// ====================
// Pagination
// ====================
$CI->db->reset_query();
$CI->db->select('s.staffid, s.firstname, s.lastname, s.branch_id, s.role, b.name as branch_name, r.name as role_name');
$CI->db->from(db_prefix().'staff s');
$CI->db->join(db_prefix().'customers_groups b', 'b.id = s.branch_id', 'left');
$CI->db->join(db_prefix().'roles r', 'r.roleid = s.role', 'left');

// Apply filters
if (!empty($selected_branch_id)) {
    $CI->db->where_in('s.branch_id', $selected_branch_id);
}
if (!empty($selected_roles)) {
    $CI->db->where_in('s.role', $selected_roles);
}
if (!empty($search)) {
    $CI->db->group_start();
    $CI->db->like('s.firstname', $search);
    $CI->db->or_like('s.lastname', $search);
    $CI->db->or_like('s.email', $search);
    $CI->db->group_end();
}

// Ordering & pagination
$CI->db->order_by($order_column, $order_dir);
if ($length != -1) {
    $CI->db->limit($length, $start);
}

$staffList = $CI->db->get()->result_array();

// ====================
// Prepare output
// ====================
$output = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => []
];

$i = $start + 1;
foreach ($staffList as $s) {
    // Count active tasks (status != COMPLETE)
    $activeTasks = $CI->db->from(db_prefix().'tasks t')
        ->join(db_prefix().'task_assigned ta', 'ta.taskid = t.id', 'inner')
        ->where('ta.staffid', $s['staffid'])
        ->where('t.status !=', $CI->tasks_model::STATUS_COMPLETE)
        ->count_all_results();

    $output['data'][] = [
        $i++,
        '<a href="'.admin_url('staff/member/'.$s['staffid']).'">'.e($s['firstname'].' '.$s['lastname']).'</a>',
        e($s['branch_name'] ?? ''),
        e($s['role_name'] ?? ''),
        $activeTasks
    ];
}

echo json_encode($output);
exit;
