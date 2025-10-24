<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();

$output = [];
$output['aaData'] = [];
$output['draw'] = intval($CI->input->post('draw'));

$search_value = $CI->input->post('search')['value'] ?? '';
$start        = (int) $CI->input->post('start') ?? 0;
$length       = (int) $CI->input->post('length') ?? 10;

$CI->db->start_cache();

$CI->db->select([
	'patients.userid',
    'new.mr_no',
    'patients.company as patient_name',
    'casesheet.created_at',
    'casesheet.staffid',
    'prescription.prescription_data',
    'prescription.medicine_given_by',
]);

$CI->db->from(db_prefix() . 'casesheet casesheet');

$CI->db->join(db_prefix() . 'clients patients', 'patients.userid = casesheet.userid', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields AS cn', 'cn.userid = patients.userid');
$CI->db->join(db_prefix() . 'patient_prescription prescription', 'prescription.casesheet_id = casesheet.id', 'inner');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = patients.userid', 'left');
$CI->db->join(db_prefix() . 'customer_groups branch', 'branch.customer_id = patients.userid', 'left');

//$CI->db->where('cn.mr_no IS NOT NULL', null, false);

if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = to_sql_date($consulted_from_date);
    $to_date   = to_sql_date($consulted_to_date);

    $CI->db->where('DATE(casesheet.created_at) >=', $from_date);
    $CI->db->where('DATE(casesheet.created_at) <=', $to_date);

} elseif (!empty($consulted_from_date)) {
    $sql_date = to_sql_date($consulted_from_date);
    $CI->db->where('DATE(casesheet.created_at)', $sql_date);

} else {
    $today = date('Y-m-d');
    $CI->db->where('DATE(casesheet.created_at)', $today);
}

$branch_id = intval($branch_id);
if ($branch_id > 0) {
    $CI->db->where('branch.groupid', $branch_id);
}


// Global search
if (!empty($search_value)) {
    $CI->db->group_start();
    $CI->db->like('patients.company', $search_value);
    $CI->db->or_like('patients.phonenumber', $search_value);
    $CI->db->or_like('new.mr_no', $search_value);
    $CI->db->group_end();
}

$CI->db->stop_cache();
$recordsFiltered = $CI->db->count_all_results();

$CI->db->limit($length, $start);

$CI->db->order_by("casesheet.created_at", "DESC");
$query = $CI->db->get();
$results = $query->result_array();

$CI->db->flush_cache();
$CI->db->from(db_prefix() . 'casesheet');
$recordsTotal = $CI->db->count_all_results();

$output['recordsTotal'] = $recordsTotal;
$output['recordsFiltered'] = $recordsFiltered;

// Start building rows
foreach ($results as $aRow) {
    $row = [];
    $url = admin_url('client/pharmacy/' . $aRow['userid']);
	$row[] = '<b><a href="' . $url . '">' . $aRow['patient_name'] . '</a></b>';
    $row[] = $aRow['mr_no'];
	$row[] = _d($aRow['created_at']);
    $row[] = get_staff_full_name($aRow['staffid']);
	if($aRow['medicine_given_by']){
	   $row[] = 'Given';
	}else{
		$row[] = 'Not Given';
	}
	if($aRow['medicine_given_by']){
	   $row[] =  get_staff_full_name($aRow['medicine_given_by']);
	}else{
		$row[] = '';
	}
		

	
    

    $output['aaData'][] = $row;
}



// ✅ Return JSON
header('Content-Type: application/json');
echo json_encode($output);
exit;
