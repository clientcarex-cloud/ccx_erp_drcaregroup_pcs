<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();
$output = [];
$output['aaData'] = [];
$output['draw'] = intval($CI->input->post('draw'));

$search_value = $CI->input->post('search')['value'] ?? '';
$start  = $CI->input->post('start');
$length = $CI->input->post('length');

$start  = is_numeric($start) ? (int) $start : 0;
$length = is_numeric($length) ? (int) $length : 10;

$CI->db->start_cache();

$CI->db->select([
    'appointments.appointment_id',
    'appointments.enquiry_doctor_id',
    'appointments.visit_id',
    'appointments.userid',
    'appointments.consulted_date',
    'appointments.consultation_fee_id',
    'new.mr_no',
    'new.registration_end_date',
    'patients.company as patient_name',
    'patients.phonenumber as patient_mobile',
    'appointments.appointment_date',
    'appointments.consultation_duration',
    'appointments.visit_status',
    'enquiry_type.enquiry_type_name',
    'appointment_type.appointment_type_name',
    'branch.name as branch_name'
]);

$CI->db->from(db_prefix() . 'appointment appointments');

$CI->db->join(db_prefix() . 'clients patients', 'patients.userid = appointments.userid', 'left');
$CI->db->join(db_prefix() . 'enquiry_type enquiry_type', 'enquiry_type.enquiry_type_id = appointments.enquiry_type_id', 'left');
$CI->db->join(db_prefix() . 'appointment_type appointment_type', 'appointment_type.appointment_type_id = appointments.appointment_type_id', 'left');
$CI->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = patients.userid', 'left');
$CI->db->join(db_prefix() . 'customers_groups branch', 'branch.id = appointments.branch_id', 'left');
$CI->db->join(db_prefix() . 'staff staff', 'staff.staffid = appointments.enquiry_doctor_id', 'left');

if (!staff_can('view_global_appointments', 'customers')) {
	// Filter by doctor
	if (!empty($staff_data) && in_array(strtolower($staff_data->role_name), ['doctor', 'service doctor'])) {
		
		//if(staff_cant('view_global_appointments', 'customers')){
			$CI->db->where('appointments.enquiry_doctor_id', $staff_data->staffid);	
			//$CI->db->where('appointments.branch_id', $branch_id);	
		//}
	} elseif (isset($enquiry_doctor_id) && is_numeric($enquiry_doctor_id) && intval($enquiry_doctor_id) > 0) {
		$CI->db->where('appointments.enquiry_doctor_id', intval($enquiry_doctor_id));
		//$CI->db->where('appointments.branch_id', $branch_id);	
	}
}


$summary_filter = $CI->input->post('summary_filter');

if ($summary_filter == 'missed') {
	$CI->db->where('appointments.visit_status', 0);
	$CI->db->where('appointments.appointment_date <', date('Y-m-d'));
} elseif ($summary_filter == 'consulted') {
	$CI->db->where('appointments.visit_status', 1);
	$CI->db->where('appointments.consulted_date IS NOT NULL', null, false);
}
// 'all' — no additional filter


$branch_id = intval($branch_id);
if ($branch_id > 0) {
    $CI->db->where('appointments.branch_id', $branch_id); // or 'appointments.branch_id' based on your DB schema
}


if (!empty($visit_status)) {
	if (strcasecmp($visit_status, 'Visited') == 0) {
		$CI->db->where('visit_status', 1);
	}
	if (strcasecmp($visit_status, 'Only Consulted') == 0) {
		 $CI->db->where('consulted_date IS NOT NULL');
	}
 
}

if (!empty($appointment_type_id)) {
		$CI->db->where('appointments.appointment_type_id', $appointment_type_id);
	
}

// Filter by date
if (!empty($consulted_from_date) && !empty($consulted_to_date)) {
    $from_date = to_sql_date($consulted_from_date);
    $to_date   = to_sql_date($consulted_to_date);

    $CI->db->group_start();
    $period = new DatePeriod(
        new DateTime($from_date),
        new DateInterval('P1D'),
        (new DateTime($to_date))->modify('+1 day')
    );

    foreach ($period as $date) {
        $d = $date->format('Y-m-d');
        $CI->db->or_like('appointments.consulted_date', $d, 'after');
        $CI->db->or_like('appointments.appointment_date', $d, 'after');
    }
    $CI->db->group_end();

} elseif (!empty($consulted_from_date)) {
    $sql_date = to_sql_date($consulted_from_date);
    $CI->db->group_start();
    $CI->db->like('appointments.consulted_date', $sql_date, 'after');
    $CI->db->or_like('appointments.appointment_date', $sql_date, 'after');
    $CI->db->group_end();

} else {
    // Default to today's date
    $today = date('Y-m-d');
    $CI->db->group_start();
    $CI->db->like('appointments.consulted_date', $today, 'after');
    $CI->db->or_like('appointments.appointment_date', $today, 'after');
    $CI->db->group_end();
}


// Global search
if (!empty($search_value)) {
    $CI->db->group_start();
    $CI->db->like('patients.company', $search_value);
    $CI->db->or_like('patients.phonenumber', $search_value);
    $CI->db->or_like('appointments.visit_id', $search_value);
    $CI->db->or_like('appointment_type.appointment_type_name', $search_value);
    $CI->db->or_like('enquiry_type.enquiry_type_name', $search_value);
    $CI->db->or_like('new.mr_no', $search_value);
	$CI->db->or_like('staff.firstname', $search_value); // 👈 First name
    $CI->db->or_like('staff.lastname', $search_value);  // 👈 Last name
    $CI->db->group_end();
}

$CI->db->stop_cache();
$recordsFiltered = $CI->db->count_all_results();

$CI->db->order_by('appointments.appointment_id', 'DESC');
$CI->db->limit($length, $start);
$query = $CI->db->get();

$results = $query->result_array();

$CI->db->flush_cache();
$CI->db->from(db_prefix() . 'appointment');
$recordsTotal = $CI->db->count_all_results();

$output['recordsTotal'] = $recordsTotal;
$output['recordsFiltered'] = $recordsFiltered;

// Start building rows
foreach ($results as $aRow) {
    $CI->db->select('i.*, 
        (SELECT SUM(amount) FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid = i.id) as paid_amount,
        (SELECT id FROM ' . db_prefix() . 'invoicepaymentrecords WHERE invoiceid = i.id ORDER BY id DESC LIMIT 1) as payment_id');
    $CI->db->from(db_prefix() . 'itemable as item');
    $CI->db->join(db_prefix() . 'invoices as i', 'item.rel_id = i.id AND item.rel_type = "invoice"', 'left');
    $CI->db->join(db_prefix() . 'appointment as ap', 'ap.invoice_id = i.id', 'right');
    $CI->db->where([
        'ap.appointment_id' => $aRow['appointment_id'],
        'item.description' => 'Consultation Fee',
        'i.clientid'       => $aRow['userid']
    ]);
    $check_payment = $CI->db->get()->row();

    $CI->db->select('COUNT(*) as total_appointments');
    $CI->db->from(db_prefix() . 'appointment');
    $CI->db->where('userid', $aRow['userid']);
    $total_appointments = (int) $CI->db->get()->row()->total_appointments;

    $appointment_label = ($total_appointments <= 1)
        ? '<span class="label label-info">First Appointment (' . $total_appointments . ')</span>'
        : '<span class="label label-success">Follow up Appointment (' . $total_appointments . ')</span>';

    $row = [];
    $url = admin_url('client/get_patient_list/' . $aRow['userid']);
    //$row[] = $aRow['mr_no'];
    //$row[] = $aRow['visit_id'];
    $row[] = '<b><a href="' . $url . '">' . $aRow['patient_name'] . '</a></b>';
    $row[] = (staff_can('mobile_masking', 'customers') && !is_admin())
        ? mask_last_5_digits_2($aRow['patient_mobile'])
        : $aRow['patient_mobile'];
    $row[] = get_staff_full_name($aRow['enquiry_doctor_id']);
    $row[] = _d($aRow['appointment_date']);
    $row[] = _d($aRow['consulted_date']);
	
	$duration = (int) $aRow['consultation_duration'];
	$minutes = floor($duration / 60);
	$seconds = $duration % 60;
	if($duration>0){
		$row[] = $minutes . ' min ' . $seconds . ' sec';
	}else{
		$row[] = '-';
	}
	
    $row[] = get_treatments_by_userid($aRow['userid'], $aRow['appointment_id']);
    $row[] = $aRow['appointment_type_name'];
    $row[] = !empty($aRow['branch_name']) ? ucfirst($aRow['branch_name']) : '-';
    $row[] = _d($aRow['registration_end_date']);
    //$row[] = $aRow['enquiry_type_name'];


    $output['aaData'][] = $row;
}

function mask_last_5_digits_2($number)
{
    $reversed = strrev($number);
    $masked = '';
    $digitCount = 0;
    for ($i = 0; $i < strlen($reversed); $i++) {
        $char = $reversed[$i];
        if (ctype_digit($char)) {
            $masked .= ($digitCount++ < 5) ? '*' : $char;
        } else {
            $masked .= $char;
        }
    }
    return strrev($masked);
}

function get_treatments_by_userid($userid, $id)
{
    $CI =& get_instance();
    $CI->db->select('i.description');
    $CI->db->from(db_prefix() . 'appointment pt');
    $CI->db->join(db_prefix() . 'items i', 'i.id = pt.treatment_id', 'left');
    $CI->db->where('pt.appointment_id', $id);
    $CI->db->where('pt.userid', $userid);
    $CI->db->group_by('i.description');
    $results = $CI->db->get()->result_array();
    return $results ? implode(', ', array_column($results, 'description')) : '-';
}

// ✅ Return JSON
header('Content-Type: application/json');
echo json_encode($output);
exit;
