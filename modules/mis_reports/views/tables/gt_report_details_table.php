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
        SELECT 1 FROM ".db_prefix()."appointment a
        JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        WHERE a.userid = inv.clientid
          AND atype.appointment_type_name = 'First Appointment'
          AND (DATE(a.appointment_date) = DATE(pr.date) OR DATE(a.created_at) = DATE(pr.date))
    )
";

$followup_appt_exists = "
    EXISTS (
        SELECT 1 FROM ".db_prefix()."appointment a
        JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        WHERE a.userid = inv.clientid
          AND atype.appointment_type_name <> 'First Appointment'
          AND (DATE(a.appointment_date) = DATE(pr.date) OR DATE(a.created_at) = DATE(pr.date))
    )
    AND NOT EXISTS (
        SELECT 1 FROM ".db_prefix()."appointment a2
        JOIN ".db_prefix()."appointment_type atype2 ON atype2.appointment_type_id = a2.appointment_type_id
        WHERE a2.userid = inv.clientid
          AND atype2.appointment_type_name = 'First Appointment'
          AND (DATE(a2.appointment_date) = DATE(pr.date) OR DATE(a2.created_at) = DATE(pr.date))
    )
";

$referral_source_filter = "
    EXISTS (
        SELECT 1 FROM ".db_prefix()."goal_lead_sources gls
        JOIN ".db_prefix()."clients_new_fields nf_src ON nf_src.userid = inv.clientid
        WHERE gls.source_id = nf_src.patient_source_id
          AND gls.category = 'referral'
    )
";

$not_referral_source_filter = "
    NOT EXISTS (
        SELECT 1 FROM ".db_prefix()."goal_lead_sources gls
        JOIN ".db_prefix()."clients_new_fields nf_src ON nf_src.userid = inv.clientid
        WHERE gls.source_id = nf_src.patient_source_id
          AND gls.category = 'referral'
    )
";

$where_clause = "1=1";

if ($base_type == 'appointment') {
    // -------------------------------------------------------------
    // APPOINTMENT BASE
    // -------------------------------------------------------------
    $where_clause .= " AND map.groupid = $branch_id";
    $where_clause .= " AND a.appointment_date >= '$from_date_esc' AND a.appointment_date <= '$to_date_esc 23:59:59'";
    
    if ($cell_type == 'np_visits') {
        $where_clause .= " AND atype.appointment_type_name = 'First Appointment'";
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM ".db_prefix()."goal_lead_sources gls WHERE gls.source_id = cn.patient_source_id AND gls.category = 'referral')";
    } elseif ($cell_type == 'ren_visits') {
        $where_clause .= " AND atype.appointment_type_name <> 'First Appointment'";
    } elseif ($cell_type == 'ref_visits') {
        $where_clause .= " AND atype.appointment_type_name = 'First Appointment'";
        $where_clause .= " AND EXISTS (SELECT 1 FROM ".db_prefix()."goal_lead_sources gls WHERE gls.source_id = cn.patient_source_id AND gls.category = 'referral')";
    }

    $select_sql = "
        SELECT 
            c.company as patient_name, cn.mr_no, sources.name as source_name, items.description as treatment_name,
            atype.appointment_type_name as appt_type_name, c.datecreated as created_date,
            a.appointment_date as appointment_date, a.visited_date as visited_date, a.consulted_date as consulted_date,
            CONCAT(staff.firstname, ' ', staff.lastname) as doctor_name, cn.registration_start_date as registration_date,
            0 as package_amount, 0 as paid_amount, NULL as payment_date, '' as payment_type
        FROM ".db_prefix()."appointment a
        JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        JOIN ".db_prefix()."customer_groups map ON map.customer_id = a.userid
        JOIN ".db_prefix()."clients c ON c.userid = a.userid
        LEFT JOIN ".db_prefix()."clients_new_fields cn ON cn.userid = a.userid
        LEFT JOIN ".db_prefix()."leads_sources sources ON sources.id = cn.patient_source_id
        LEFT JOIN ".db_prefix()."items items ON items.id = a.treatment_id
        LEFT JOIN ".db_prefix()."staff staff ON staff.staffid = a.doctor_id
        WHERE $where_clause
    ";
    $group_by = "GROUP BY a.userid";
    
} elseif ($base_type == 'payment') {
    // -------------------------------------------------------------
    // PAYMENT BASE
    // -------------------------------------------------------------
    $where_clause .= " AND map.groupid = $branch_id";
    $where_clause .= " AND pr.date >= '$from_date_esc' AND pr.date <= '$to_date_esc'";

    if ($cell_type == 'gt_achieved') {
        // All payments
    } elseif ($cell_type == 'np_reg') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $first_appt_exists AND $not_referral_source_filter";
    } elseif ($cell_type == 'enq_confee') {
        $where_clause .= " AND item.description = 'Consultation Fee' AND $first_appt_exists AND $not_referral_source_filter";
    } elseif ($cell_type == 'np_paid') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $first_appt_exists AND $not_referral_source_filter";
    } elseif ($cell_type == 'enq_due') {
        $where_clause .= " AND pr.date > inv.date AND $not_referral_source_filter AND EXISTS (
            SELECT 1 FROM ".db_prefix()."appointment a
            JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
            WHERE a.userid = inv.clientid AND atype.appointment_type_name = 'First Appointment'
        )";
    } elseif ($cell_type == 'enq_gt') {
        $where_clause .= " AND ((item.description <> 'Consultation Fee' AND $first_appt_exists AND $not_referral_source_filter) OR (pr.date > inv.date AND $not_referral_source_filter AND EXISTS (
            SELECT 1 FROM ".db_prefix()."appointment a
            JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
            WHERE a.userid = inv.clientid AND atype.appointment_type_name = 'First Appointment'
        )))";
    } elseif ($cell_type == 'renewed') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $followup_appt_exists";
    } elseif ($cell_type == 'ren_confee') {
        $where_clause .= " AND item.description = 'Consultation Fee' AND $followup_appt_exists";
    } elseif ($cell_type == 'ren_paid') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $followup_appt_exists";
    } elseif ($cell_type == 'ren_due') {
        $where_clause .= " AND inv.date < '$from_date_esc' AND $followup_appt_exists";
    } elseif ($cell_type == 'ren_gt') {
        $where_clause .= " AND ((item.description <> 'Consultation Fee' AND $followup_appt_exists) OR (inv.date < '$from_date_esc' AND $followup_appt_exists))";
    } elseif ($cell_type == 'ref_reg') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $first_appt_exists AND $referral_source_filter";
    } elseif ($cell_type == 'ref_paid') {
        $where_clause .= " AND item.description <> 'Consultation Fee' AND $first_appt_exists AND $referral_source_filter";
    } elseif ($cell_type == 'ref_due') {
        $where_clause .= " AND inv.date < '$from_date_esc' AND $first_appt_exists AND $referral_source_filter";
    } elseif ($cell_type == 'ref_gt') {
        $where_clause .= " AND ((item.description <> 'Consultation Fee' AND $first_appt_exists AND $referral_source_filter) OR (inv.date < '$from_date_esc' AND $first_appt_exists AND $referral_source_filter))";
    }

    $select_sql = "
        SELECT 
            c.company as patient_name, cn.mr_no, sources.name as source_name, items.description as treatment_name,
            atype.appointment_type_name as appt_type_name, c.datecreated as created_date,
            a.appointment_date as appointment_date, a.visited_date as visited_date, a.consulted_date as consulted_date,
            CONCAT(staff.firstname, ' ', staff.lastname) as doctor_name, cn.registration_start_date as registration_date,
            inv.total as package_amount, pr.amount as paid_amount, pr.date as payment_date, pm.name as payment_type
        FROM ".db_prefix()."invoicepaymentrecords pr
        JOIN ".db_prefix()."invoices inv ON inv.id = pr.invoiceid
        JOIN ".db_prefix()."customer_groups map ON map.customer_id = inv.clientid
        JOIN ".db_prefix()."clients c ON c.userid = inv.clientid
        LEFT JOIN ".db_prefix()."itemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice' 
        LEFT JOIN ".db_prefix()."clients_new_fields cn ON cn.userid = c.userid
        LEFT JOIN ".db_prefix()."leads_sources sources ON sources.id = cn.patient_source_id
        LEFT JOIN ".db_prefix()."payment_modes pm ON pm.id = pr.paymentmode
        LEFT JOIN ".db_prefix()."appointment a ON a.appointment_id = (
             SELECT a_sub.appointment_id FROM ".db_prefix()."appointment a_sub 
             WHERE a_sub.userid = c.userid AND DATE(a_sub.appointment_date) <= pr.date
             ORDER BY a_sub.appointment_date DESC LIMIT 1
        )
        LEFT JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        LEFT JOIN ".db_prefix()."items items ON items.id = a.treatment_id
        LEFT JOIN ".db_prefix()."staff staff ON staff.staffid = a.doctor_id
        WHERE $where_clause
    ";
    
    if (in_array($cell_type, ['np_reg', 'renewed', 'ref_reg'])) {
        $group_by = "GROUP BY c.userid";
    } else {
        $group_by = "GROUP BY pr.id";
    }
} elseif ($base_type == 'refund') {
    // -------------------------------------------------------------
    // REFUND BASE
    // -------------------------------------------------------------
    $where_clause .= " AND map.groupid = $branch_id";
    $where_clause .= " AND cr.refunded_on >= '$from_date_esc' AND cr.refunded_on <= '$to_date_esc'";
    
    $select_sql = "
        SELECT 
            c.company as patient_name, cn.mr_no, sources.name as source_name, items.description as treatment_name,
            atype.appointment_type_name as appt_type_name, c.datecreated as created_date,
            a.appointment_date as appointment_date, a.visited_date as visited_date, a.consulted_date as consulted_date,
            CONCAT(staff.firstname, ' ', staff.lastname) as doctor_name, cn.registration_start_date as registration_date,
            0 as package_amount, cr.amount as paid_amount, cr.refunded_on as payment_date, pm.name as payment_type
        FROM ".db_prefix()."creditnote_refunds cr
        JOIN ".db_prefix()."creditnotes cn_inv ON cn_inv.id = cr.credit_note_id
        JOIN ".db_prefix()."customer_groups map ON map.customer_id = cn_inv.clientid
        JOIN ".db_prefix()."clients c ON c.userid = cn_inv.clientid
        LEFT JOIN ".db_prefix()."clients_new_fields cn ON cn.userid = c.userid
        LEFT JOIN ".db_prefix()."leads_sources sources ON sources.id = cn.patient_source_id
        LEFT JOIN ".db_prefix()."payment_modes pm ON pm.id = cr.payment_mode
        LEFT JOIN ".db_prefix()."appointment a ON a.appointment_id = (
             SELECT a_sub.appointment_id FROM ".db_prefix()."appointment a_sub 
             WHERE a_sub.userid = c.userid AND DATE(a_sub.appointment_date) <= cr.refunded_on
             ORDER BY a_sub.appointment_date DESC LIMIT 1
        )
        LEFT JOIN ".db_prefix()."appointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        LEFT JOIN ".db_prefix()."items items ON items.id = a.treatment_id
        LEFT JOIN ".db_prefix()."staff staff ON staff.staffid = a.doctor_id
        WHERE $where_clause
    ";
    $group_by = "GROUP BY cr.id";


if ($search) {
    $search_esc = $CI->db->escape_like_str($search);
    $final_sql = "
        SELECT filtered.* FROM (
            $select_sql $group_by
        ) AS filtered
        WHERE filtered.patient_name LIKE '%$search_esc%'
           OR filtered.mr_no LIKE '%$search_esc%'
           OR filtered.doctor_name LIKE '%$search_esc%'
    ";
} else {
    $final_sql = "$select_sql $group_by";
}

$count_query = $CI->db->query("SELECT COUNT(*) as count FROM ($final_sql) count_tbl");
$total = $count_query->row()->count;

$final_sql .= " ORDER BY created_date DESC";
if ($length > 0) {
    $final_sql .= " LIMIT $start, $length";
}

$results = $CI->db->query($final_sql)->result_array();

$data = [];
foreach ($results as $row) {
    $data[] = [
        isset($row['patient_name']) ? e($row['patient_name']) : '',
        isset($row['mr_no']) ? e($row['mr_no']) : '',
        isset($row['source_name']) ? e($row['source_name']) : '',
        isset($row['treatment_name']) ? e($row['treatment_name']) : '',
        isset($row['appt_type_name']) ? e($row['appt_type_name']) : '',
        isset($row['created_date']) ? _d($row['created_date']) : '',
        '', // First Visit placeholder
        isset($row['appointment_date']) ? _d($row['appointment_date']) : '',
        isset($row['visited_date']) ? _d($row['visited_date']) : '',
        isset($row['consulted_date']) ? _d($row['consulted_date']) : '',
        isset($row['doctor_name']) ? e($row['doctor_name']) : '',
        isset($row['registration_date']) ? _d($row['registration_date']) : '',
        isset($row['package_amount']) && $row['package_amount'] ? app_format_money_custom($row['package_amount'], 1) : '',
        isset($row['paid_amount']) && $row['paid_amount'] ? app_format_money_custom($row['paid_amount'], 1) : '',
        isset($row['payment_date']) ? _d($row['payment_date']) : '',
        isset($row['payment_type']) ? e($row['payment_type']) : '',
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
