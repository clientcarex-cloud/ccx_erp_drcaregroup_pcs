<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();

$output = [
    'draw'            => (int) $CI->input->post('draw'),
    'recordsTotal'    => 0,
    'recordsFiltered' => 0,
    'aaData'          => [],
];

$search_value = trim($CI->input->post('search')['value'] ?? '');
$start        = (int) ($CI->input->post('start') ?? 0);
$length       = (int) ($CI->input->post('length') ?? 10);

$CI->db->start_cache();

$CI->db->select([
    'casesheet.id AS casesheet_id',
    'patients.userid',
    'patient_meta.mr_no',
    'patients.company as patient_name',
    'casesheet.created_at',
    'casesheet.staffid',
    'prescription.medicine_given_by',
]);

$CI->db->from(db_prefix() . 'casesheet casesheet');
$CI->db->join(db_prefix() . 'clients patients', 'patients.userid = casesheet.userid', 'left');

$prescriptionSubQuery = '(SELECT 
        casesheet_id,
        SUBSTRING_INDEX(
            GROUP_CONCAT(medicine_given_by ORDER BY COALESCE(medicine_given_date, created_datetime) DESC),
            ",",
            1
        ) AS medicine_given_by
    FROM ' . db_prefix() . 'patient_prescription
    WHERE casesheet_id IS NOT NULL
    GROUP BY casesheet_id) prescription';

$CI->db->join($prescriptionSubQuery, 'prescription.casesheet_id = casesheet.id', 'inner', false);
$CI->db->join(db_prefix() . 'clients_new_fields patient_meta', 'patient_meta.userid = patients.userid', 'left');
$CI->db->join(db_prefix() . 'customer_groups branch', 'branch.customer_id = patients.userid', 'left');

$from_date = !empty($consulted_from_date) ? to_sql_date($consulted_from_date) : null;
$to_date   = !empty($consulted_to_date) ? to_sql_date($consulted_to_date) : null;

if ($from_date && $to_date) {
    if ($from_date > $to_date) {
        [$from_date, $to_date] = [$to_date, $from_date];
    }
    $CI->db->where('casesheet.created_at >=', $from_date . ' 00:00:00');
    $CI->db->where('casesheet.created_at <=', $to_date . ' 23:59:59');
} elseif ($from_date) {
    $CI->db->where('casesheet.created_at >=', $from_date . ' 00:00:00');
    $CI->db->where('casesheet.created_at <=', $from_date . ' 23:59:59');
} elseif ($to_date) {
    $CI->db->where('casesheet.created_at >=', $to_date . ' 00:00:00');
    $CI->db->where('casesheet.created_at <=', $to_date . ' 23:59:59');
} else {
    $today = date('Y-m-d');
    $CI->db->where('casesheet.created_at >=', $today . ' 00:00:00');
    $CI->db->where('casesheet.created_at <=', $today . ' 23:59:59');
}

$branch_filter = is_numeric($branch_id) ? (int) $branch_id : 0;
if ($branch_filter > 0) {
    $CI->db->where('branch.groupid', $branch_filter);
}

$CI->db->stop_cache();

$output['recordsTotal'] = $CI->db->count_all_results('', false);

if ($search_value !== '') {
    $CI->db->group_start();
    $CI->db->like('patients.company', $search_value);
    $CI->db->or_like('patients.phonenumber', $search_value);
    $CI->db->or_like('patient_meta.mr_no', $search_value);
    $CI->db->group_end();

    $output['recordsFiltered'] = $CI->db->count_all_results('', false);
} else {
    $output['recordsFiltered'] = $output['recordsTotal'];
}

$CI->db->order_by('casesheet.created_at', 'DESC');
$CI->db->limit($length, $start);
$results = $CI->db->get()->result_array();

$CI->db->flush_cache();

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
