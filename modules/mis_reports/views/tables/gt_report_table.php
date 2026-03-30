<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// ---- Filters (support GET params, POST, and controller-passed variables) ----
$from_date = $CI->input->get_post('consulted_date');
if (!$from_date && isset($consulted_from_date))
  $from_date = $consulted_from_date;
$to_date = $CI->input->get_post('consulted_to_date');
if (!$to_date && isset($consulted_to_date))
  $to_date = $consulted_to_date;
$branch_id = $CI->input->get_post('branch'); // Array of branch IDs from multi-select
$currency = $CI->input->get_post('currency');

// Clean and sanitize filters
if (!$from_date)
  $from_date = date('Y-m-01');
if (!$to_date)
  $to_date = date('Y-m-t');

// If no branches selected, return empty result
if (empty($branch_id) || !is_array($branch_id)) {
  $output = ['data' => []];
  header('Content-Type: application/json');
  echo json_encode($output);
  exit;
}

// Build branch filter SQL clause
$branch_filter_sql = '';
$clean_branch_ids = array_filter($branch_id, 'is_numeric');
$clean_branch_ids = array_map('intval', $clean_branch_ids);
if (!empty($clean_branch_ids)) {
  $branch_filter_sql = ' AND cg.id IN (' . implode(',', $clean_branch_ids) . ')';
}

// ---- Fetch total paid amounts per branch from invoice payment records ----
$from_date_esc = $CI->db->escape_str($from_date);
$to_date_esc   = $CI->db->escape_str($to_date);

$paid_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_paid
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
    ) sub
    GROUP BY sub.branch_id
";
$paid_result = $CI->db->query($paid_sql)->result_array();
$paid_lookup = []; // keyed by branch_id => total_paid
foreach ($paid_result as $pr) {
    $paid_lookup[(int) $pr['branch_id']] = (float) $pr['total_paid'];
}

// ---- Fetch goals from tblreport_goals for the date range ----
$from_ts = strtotime($from_date);
$to_ts   = strtotime($to_date);

$from_year  = (int) date('Y', $from_ts);
$from_month = (int) date('n', $from_ts);
$to_year    = (int) date('Y', $to_ts);
$to_month   = (int) date('n', $to_ts);

$goals_lookup = []; // keyed by branch_id => ['gt_goal' => X, 'enquiry_goal' => X, 'renewal_goal' => X, 'referral_goal' => X]
$goals_table = db_prefix() . 'report_goals';

// Build month/year conditions
$month_conditions = [];
$y = $from_year;
$m = $from_month;
while ($y < $to_year || ($y == $to_year && $m <= $to_month)) {
    $month_conditions[] = "(g.year = $y AND g.month = $m)";
    $m++;
    if ($m > 12) { $m = 1; $y++; }
}

// ---- Common EXISTS clause: patient has 'First Appointment' on the payment date ----
$first_appt_exists = "
    EXISTS (
        SELECT 1 FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        WHERE a.userid = inv.clientid
          AND atype.appointment_type_name = 'First Appointment'
          AND (DATE(a.appointment_date) = DATE(pr.date) OR DATE(a.created_at) = DATE(pr.date))
    )
";

// ---- Common EXISTS clause: patient has NON-First-Appointment on the payment date (for Renewal) ----
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

// ---- Common source filter clauses ----
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


// =======================================================================================
// ENQUIRY SECTION QUERIES (NP = First Appointment, excluding referral sources)
// =======================================================================================

// ---- NP Visits: unique patients with 'First Appointment', excluding referral lead sources ----
$np_paycat_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.userid) AS np_visits
    FROM (
        SELECT a.userid, map.groupid AS branch_id
        FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        JOIN tblcustomer_groups map ON map.customer_id = a.userid
        LEFT JOIN tblclients_new_fields nf ON nf.userid = a.userid
        WHERE atype.appointment_type_name = 'First Appointment'
          AND a.appointment_date >= '$from_date_esc'
          AND a.appointment_date <= '$to_date_esc 23:59:59'
          AND NOT EXISTS (
              SELECT 1 FROM tblgoal_lead_sources gls
              WHERE gls.source_id = nf.patient_source_id
                AND gls.category = 'referral'
          )
    ) sub
    GROUP BY sub.branch_id
";
$np_paycat_result = $CI->db->query($np_paycat_sql)->result_array();
$np_paycat_lookup = [];
foreach ($np_paycat_result as $r) {
    $np_paycat_lookup[(int) $r['branch_id']] = (int) $r['np_visits'];
}

// ---- NP Registration: First Appointment patients (excl referral) with non-Consultation-Fee package ----
$np_reg_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.clientid) AS np_reg
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, inv.clientid
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $first_appt_exists
          AND $not_referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$np_reg_result = $CI->db->query($np_reg_sql)->result_array();
$np_reg_lookup = [];
foreach ($np_reg_result as $r) {
    $np_reg_lookup[(int) $r['branch_id']] = (int) $r['np_reg'];
}

// ---- Enquiry Consultation Fee: Consultation Fee for First Appointment patients (excl referral) ----
$enq_confee_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_confee
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description = 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $first_appt_exists
          AND $not_referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$enq_confee_result = $CI->db->query($enq_confee_sql)->result_array();
$enq_confee_lookup = [];
foreach ($enq_confee_result as $r) {
    $enq_confee_lookup[(int) $r['branch_id']] = (float) $r['total_confee'];
}

// ---- NP Paid: total paid for First Appointment patients (excl referral), excluding Consultation Fee ----
$np_paid_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_np_paid
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $first_appt_exists
          AND $not_referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$np_paid_result = $CI->db->query($np_paid_sql)->result_array();
$np_paid_lookup = [];
foreach ($np_paid_result as $r) {
    $np_paid_lookup[(int) $r['branch_id']] = (float) $r['total_np_paid'];
}

// ---- Enquiry Due Collected: payments on OLD invoices (created before date range) for First Appointment patients (excl referral) ----
$enq_due_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_due_collected
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND inv.date < '$from_date_esc'
          AND $first_appt_exists
          AND $not_referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$enq_due_result = $CI->db->query($enq_due_sql)->result_array();
$enq_due_lookup = [];
foreach ($enq_due_result as $r) {
    $enq_due_lookup[(int) $r['branch_id']] = (float) $r['total_due_collected'];
}


// =======================================================================================
// RENEWAL SECTION QUERIES (Follow-up/non-First-Appointment patients)
// =======================================================================================

// ---- Renewal Visits: unique patients with non-First-Appointment ----
$renewal_visits_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.userid) AS renewal_visits
    FROM (
        SELECT a.userid, map.groupid AS branch_id
        FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        JOIN tblcustomer_groups map ON map.customer_id = a.userid
        WHERE atype.appointment_type_name <> 'First Appointment'
          AND a.appointment_date >= '$from_date_esc'
          AND a.appointment_date <= '$to_date_esc 23:59:59'
    ) sub
    GROUP BY sub.branch_id
";
$renewal_visits_result = $CI->db->query($renewal_visits_sql)->result_array();
$renewal_visits_lookup = [];
foreach ($renewal_visits_result as $r) {
    $renewal_visits_lookup[(int) $r['branch_id']] = (int) $r['renewal_visits'];
}

// ---- Renewed: follow-up patients who have a non-Consultation-Fee package ----
$renewed_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.clientid) AS renewed
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, inv.clientid
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $followup_appt_exists
    ) sub
    GROUP BY sub.branch_id
";
$renewed_result = $CI->db->query($renewed_sql)->result_array();
$renewed_lookup = [];
foreach ($renewed_result as $r) {
    $renewed_lookup[(int) $r['branch_id']] = (int) $r['renewed'];
}

// ---- Follow-up Consultation Fee: Consultation Fee for follow-up patients ----
$renewal_confee_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_confee
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description = 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $followup_appt_exists
    ) sub
    GROUP BY sub.branch_id
";
$renewal_confee_result = $CI->db->query($renewal_confee_sql)->result_array();
$renewal_confee_lookup = [];
foreach ($renewal_confee_result as $r) {
    $renewal_confee_lookup[(int) $r['branch_id']] = (float) $r['total_confee'];
}

// ---- Renewal Paid: non-Consultation-Fee payments for follow-up patients ----
$renewal_paid_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_renewal_paid
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $followup_appt_exists
    ) sub
    GROUP BY sub.branch_id
";
$renewal_paid_result = $CI->db->query($renewal_paid_sql)->result_array();
$renewal_paid_lookup = [];
foreach ($renewal_paid_result as $r) {
    $renewal_paid_lookup[(int) $r['branch_id']] = (float) $r['total_renewal_paid'];
}

// ---- Renewal Due Collected: payments on OLD invoices for follow-up patients ----
$renewal_due_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_due
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND inv.date < '$from_date_esc'
          AND $followup_appt_exists
    ) sub
    GROUP BY sub.branch_id
";
$renewal_due_result = $CI->db->query($renewal_due_sql)->result_array();
$renewal_due_lookup = [];
foreach ($renewal_due_result as $r) {
    $renewal_due_lookup[(int) $r['branch_id']] = (float) $r['total_due'];
}


// =======================================================================================
// REFERRAL SECTION QUERIES (First Appointment patients whose source = referral)
// =======================================================================================

// ---- Referral Visits: unique patients with First Appointment whose source IS referral ----
$ref_visits_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.userid) AS ref_visits
    FROM (
        SELECT a.userid, map.groupid AS branch_id
        FROM tblappointment a
        JOIN tblappointment_type atype ON atype.appointment_type_id = a.appointment_type_id
        JOIN tblcustomer_groups map ON map.customer_id = a.userid
        LEFT JOIN tblclients_new_fields nf ON nf.userid = a.userid
        WHERE atype.appointment_type_name = 'First Appointment'
          AND a.appointment_date >= '$from_date_esc'
          AND a.appointment_date <= '$to_date_esc 23:59:59'
          AND EXISTS (
              SELECT 1 FROM tblgoal_lead_sources gls
              WHERE gls.source_id = nf.patient_source_id
                AND gls.category = 'referral'
          )
    ) sub
    GROUP BY sub.branch_id
";
$ref_visits_result = $CI->db->query($ref_visits_sql)->result_array();
$ref_visits_lookup = [];
foreach ($ref_visits_result as $r) {
    $ref_visits_lookup[(int) $r['branch_id']] = (int) $r['ref_visits'];
}

// ---- Referral Registrations: referral patients with non-Consultation-Fee package ----
$ref_reg_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.clientid) AS ref_reg
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, inv.clientid
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $first_appt_exists
          AND $referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$ref_reg_result = $CI->db->query($ref_reg_sql)->result_array();
$ref_reg_lookup = [];
foreach ($ref_reg_result as $r) {
    $ref_reg_lookup[(int) $r['branch_id']] = (int) $r['ref_reg'];
}

// ---- Referral Paid: non-Consultation-Fee payments for referral patients ----
$ref_paid_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_ref_paid
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE item.description <> 'Consultation Fee'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND $first_appt_exists
          AND $referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$ref_paid_result = $CI->db->query($ref_paid_sql)->result_array();
$ref_paid_lookup = [];
foreach ($ref_paid_result as $r) {
    $ref_paid_lookup[(int) $r['branch_id']] = (float) $r['total_ref_paid'];
}

// ---- Referral Due Collected: payments on OLD invoices for referral patients ----
$ref_due_sql = "
    SELECT sub.branch_id, COALESCE(SUM(sub.amount), 0) AS total_due
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, pr.amount
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
          AND inv.date < '$from_date_esc'
          AND $first_appt_exists
          AND $referral_source_filter
    ) sub
    GROUP BY sub.branch_id
";
$ref_due_result = $CI->db->query($ref_due_sql)->result_array();
$ref_due_lookup = [];
foreach ($ref_due_result as $r) {
    $ref_due_lookup[(int) $r['branch_id']] = (float) $r['total_due'];
}


// =======================================================================================
// REFUND SECTION
// =======================================================================================

// ---- Refund Amount: total refunds from credit notes ----
$refund_sql = "
    SELECT map.groupid AS branch_id, COALESCE(SUM(cr.amount), 0) AS total_refund
    FROM tblcreditnote_refunds cr
    JOIN tblcreditnotes cn ON cn.id = cr.credit_note_id
    JOIN tblcustomer_groups map ON map.customer_id = cn.clientid
    WHERE cr.refunded_on >= '$from_date_esc'
      AND cr.refunded_on <= '$to_date_esc'
    GROUP BY map.groupid
";
$refund_result = $CI->db->query($refund_sql)->result_array();
$refund_lookup = [];
foreach ($refund_result as $r) {
    $refund_lookup[(int) $r['branch_id']] = (float) $r['total_refund'];
}


// =======================================================================================
// GOALS LOOKUP
// =======================================================================================

if (!empty($month_conditions)) {
    $month_where = implode(' OR ', $month_conditions);
    $goals_sql = "SELECT g.branch_id, g.goal_type, SUM(g.amount) AS total_amount
                  FROM `$goals_table` g
                  WHERE ($month_where)
                  GROUP BY g.branch_id, g.goal_type";
    $goals_result = $CI->db->query($goals_sql)->result_array();
    foreach ($goals_result as $g) {
        $bid  = (int) $g['branch_id'];
        $type = $g['goal_type'];
        if (!isset($goals_lookup[$bid])) {
            $goals_lookup[$bid] = ['gt_goal' => 0, 'enquiry_goal' => 0, 'renewal_goal' => 0, 'referral_goal' => 0];
        }
        if (isset($goals_lookup[$bid][$type])) {
            $goals_lookup[$bid][$type] = (float) $g['total_amount'];
        }
    }
}

// ---- Simple branch query ----
$sql = "
    SELECT cg.name AS branch_name, cg.id AS branch_id
    FROM tblcustomers_groups cg
    WHERE 1=1 $branch_filter_sql
    ORDER BY cg.name
";

$draw = (int) ($CI->input->post('draw') ?? 1);
$result = $CI->db->query($sql)->result_array();
$total = count($result);

$output = [
  'draw' => $draw,
  'recordsTotal' => $total,
  'recordsFiltered' => $total,
  'data' => []
];

// Initialize grand totals
$total_gt_goal = 0;
$total_gt_achieved = 0;
$total_gt_projection = 0;
$total_np_paycat = 0;
$total_np_reg = 0;
$total_enq_confee = 0;
$total_np_paid = 0;
$total_enq_due = 0;
$total_enquiry_goal = 0;
$total_renewal_visits = 0;
$total_renewed = 0;
$total_renewal_confee = 0;
$total_renewal_paid = 0;
$total_renewal_due = 0;
$total_renewal_goal = 0;
$total_ref_visits = 0;
$total_ref_reg = 0;
$total_ref_paid = 0;
$total_ref_due = 0;
$total_referral_goal = 0;
$total_refund = 0;

// Date calculations for projections
$current_day   = (int) date('j', strtotime($to_date));
$days_in_month = (int) date('t', strtotime($to_date));

// Format data for DataTable
foreach ($result as $row) {
  $bid = (int) $row['branch_id'];

  // ---- Goals ----
  $branch_goals = isset($goals_lookup[$bid]) ? $goals_lookup[$bid] : ['gt_goal' => 0, 'enquiry_goal' => 0, 'renewal_goal' => 0, 'referral_goal' => 0];
  $gt_goal       = (int) $branch_goals['gt_goal'];
  $enquiry_goal  = (int) $branch_goals['enquiry_goal'];
  $renewal_goal  = (int) $branch_goals['renewal_goal'];
  $referral_goal = (int) $branch_goals['referral_goal'];

  // ===================== GT Section =====================
  $gt_achieved = isset($paid_lookup[$bid]) ? round($paid_lookup[$bid]) : 0;
  $gt_achieved_pct = ($gt_goal > 0) ? round(($gt_achieved / $gt_goal) * 100) : 0;
  $gt_projection = ($current_day > 0) ? round(($gt_achieved / $current_day) * $days_in_month) : 0;

  // ===================== Enquiry Section =====================
  $np_visits  = isset($np_paycat_lookup[$bid]) ? $np_paycat_lookup[$bid] : 0;
  $np_reg     = isset($np_reg_lookup[$bid]) ? $np_reg_lookup[$bid] : 0;
  $np_reg_pct = ($np_visits > 0) ? round(($np_reg / $np_visits) * 100) . '%' : '0%';
  $enq_confee = isset($enq_confee_lookup[$bid]) ? round($enq_confee_lookup[$bid]) : 0;
  $np_paid    = isset($np_paid_lookup[$bid]) ? round($np_paid_lookup[$bid]) : 0;
  $np_ticket  = ($np_visits > 0) ? round($np_paid / $np_visits) : 0;
  $enq_due    = isset($enq_due_lookup[$bid]) ? round($enq_due_lookup[$bid]) : 0;
  $enq_gt     = $enq_due + $np_paid;
  $enq_achieved_pct = ($enquiry_goal > 0) ? round(($enq_gt / $enquiry_goal) * 100) . '%' : '0%';
  $enq_projection   = ($current_day > 0) ? round(($enq_gt / $current_day) * $days_in_month) : 0;

  // ===================== Renewal Section =====================
  $ren_visits  = isset($renewal_visits_lookup[$bid]) ? $renewal_visits_lookup[$bid] : 0;
  $renewed     = isset($renewed_lookup[$bid]) ? $renewed_lookup[$bid] : 0;
  $renewed_pct = ($ren_visits > 0) ? round(($renewed / $ren_visits) * 100) . '%' : '0%';
  $ren_confee  = isset($renewal_confee_lookup[$bid]) ? round($renewal_confee_lookup[$bid]) : 0;
  $ren_paid    = isset($renewal_paid_lookup[$bid]) ? round($renewal_paid_lookup[$bid]) : 0;
  $ren_due     = isset($renewal_due_lookup[$bid]) ? round($renewal_due_lookup[$bid]) : 0;
  $ren_projection  = ($current_day > 0) ? round(($ren_paid / $current_day) * $days_in_month) : 0;
  $ren_ticket      = ($ren_visits > 0) ? round($ren_paid / $ren_visits) : 0;

  // ===================== Referral Section =====================
  $ref_visits  = isset($ref_visits_lookup[$bid]) ? $ref_visits_lookup[$bid] : 0;
  $ref_reg     = isset($ref_reg_lookup[$bid]) ? $ref_reg_lookup[$bid] : 0;
  $ref_reg_pct = ($ref_visits > 0) ? round(($ref_reg / $ref_visits) * 100) . '%' : '0%';
  $ref_paid    = isset($ref_paid_lookup[$bid]) ? round($ref_paid_lookup[$bid]) : 0;
  $ref_due     = isset($ref_due_lookup[$bid]) ? round($ref_due_lookup[$bid]) : 0;
  $ref_projection  = ($current_day > 0) ? round(($ref_paid / $current_day) * $days_in_month) : 0;
  $ref_ticket      = ($ref_visits > 0) ? round($ref_paid / $ref_visits) : 0;

  // ===================== Refund =====================
  $refund = isset($refund_lookup[$bid]) ? round($refund_lookup[$bid]) : 0;

  // ===================== Accumulate Grand Totals =====================
  $total_gt_goal += $gt_goal;
  $total_gt_achieved += $gt_achieved;
  $total_gt_projection += $gt_projection;
  $total_np_paycat += $np_visits;
  $total_np_reg += $np_reg;
  $total_enq_confee += $enq_confee;
  $total_np_paid += $np_paid;
  $total_enq_due += $enq_due;
  $total_enquiry_goal += $enquiry_goal;
  $total_renewal_visits += $ren_visits;
  $total_renewed += $renewed;
  $total_renewal_confee += $ren_confee;
  $total_renewal_paid += $ren_paid;
  $total_renewal_due += $ren_due;
  $total_renewal_goal += $renewal_goal;
  $total_ref_visits += $ref_visits;
  $total_ref_reg += $ref_reg;
  $total_ref_paid += $ref_paid;
  $total_ref_due += $ref_due;
  $total_referral_goal += $referral_goal;
  $total_refund += $refund;

  // ===================== Build Row =====================
  $output['data'][] = [
    $row['branch_name'],         // Branch
    $gt_goal,                    // GT Goal
    $gt_achieved,                // GT Achieved
    $gt_achieved_pct . '%',      // GT Achieved %
    $gt_projection,              // GT Projection
    $np_visits,                  // NP Visits
    $np_reg,                     // NP Registration
    $np_reg_pct,                 // NP Reg %
    $enq_confee,                 // Enquiry Consultation Fee
    $np_paid,                    // NP Paid
    $np_ticket,                  // NP Ticket Value
    $enq_due,                    // Enquiry Due Collected
    $enq_gt,                     // Enquiry GT
    $enq_achieved_pct,           // Enquiry Achieved %
    $enquiry_goal,               // Enquiry Goal
    $enq_projection,             // Enquiry Projection
    $ren_visits,                 // Renewal Visits
    $renewed,                    // Renewed
    $renewed_pct,                // Renewed %
    $ren_confee,                 // Follow-up Consultation Fee
    $ren_paid,                   // Renewal Paid
    $ren_due,                    // Renewal Due
    $ren_projection,             // Renewal Projection
    $ren_ticket,                 // Renewal Ticket Value
    $ref_visits,                 // Referral Visits
    $ref_reg,                    // Referral Registrations
    $ref_reg_pct,                // Referral %
    $ref_paid,                   // Referral Paid
    $ref_due,                    // Referral Due
    $ref_projection,             // Referral Projection
    $ref_ticket,                 // Referral Ticket Value
    $refund,                     // Refund Amount
  ];
}

// ===================== Grand Totals Row =====================
$total_gt_achieved_pct = ($total_gt_goal > 0) ? round(($total_gt_achieved / $total_gt_goal) * 100) : 0;
$total_enq_gt = $total_enq_due + $total_np_paid;
$total_enq_achieved_pct = ($total_enquiry_goal > 0) ? round(($total_enq_gt / $total_enquiry_goal) * 100) : 0;
$total_enq_projection = ($current_day > 0) ? round(($total_enq_gt / $current_day) * $days_in_month) : 0;
$total_renewed_pct = ($total_renewal_visits > 0) ? round(($total_renewed / $total_renewal_visits) * 100) : 0;
$total_ren_projection = ($current_day > 0) ? round(($total_renewal_paid / $current_day) * $days_in_month) : 0;
$total_ren_ticket = ($total_renewal_visits > 0) ? round($total_renewal_paid / $total_renewal_visits) : 0;
$total_ref_reg_pct = ($total_ref_visits > 0) ? round(($total_ref_reg / $total_ref_visits) * 100) : 0;
$total_ref_projection = ($current_day > 0) ? round(($total_ref_paid / $current_day) * $days_in_month) : 0;
$total_ref_ticket = ($total_ref_visits > 0) ? round($total_ref_paid / $total_ref_visits) : 0;

$output['totals'] = [
  '<strong>Grand Total</strong>',
  '<strong>' . $total_gt_goal . '</strong>',                                       // GT Goal
  '<strong>' . $total_gt_achieved . '</strong>',                                   // GT Achieved
  '<strong>' . $total_gt_achieved_pct . '%</strong>',                              // GT Achieved %
  '<strong>' . $total_gt_projection . '</strong>',                                 // GT Projection
  '<strong>' . $total_np_paycat . '</strong>',                                     // NP Visits
  '<strong>' . $total_np_reg . '</strong>',                                        // NP Registration
  '<strong>' . (($total_np_paycat > 0) ? round(($total_np_reg / $total_np_paycat) * 100) : 0) . '%</strong>',  // NP Reg %
  '<strong>' . $total_enq_confee . '</strong>',                                    // Enquiry Consultation Fee
  '<strong>' . $total_np_paid . '</strong>',                                       // NP Paid
  '<strong>' . (($total_np_paycat > 0) ? round($total_np_paid / $total_np_paycat) : 0) . '</strong>',          // NP Ticket Value
  '<strong>' . $total_enq_due . '</strong>',                                       // Enquiry Due Collected
  '<strong>' . $total_enq_gt . '</strong>',                                        // Enquiry GT
  '<strong>' . $total_enq_achieved_pct . '%</strong>',                             // Enquiry Achieved %
  '<strong>' . $total_enquiry_goal . '</strong>',                                  // Enquiry Goal
  '<strong>' . $total_enq_projection . '</strong>',                                // Enquiry Projection
  '<strong>' . $total_renewal_visits . '</strong>',                                // Renewal Visits
  '<strong>' . $total_renewed . '</strong>',                                       // Renewed
  '<strong>' . $total_renewed_pct . '%</strong>',                                  // Renewed %
  '<strong>' . $total_renewal_confee . '</strong>',                                // Follow-up Consultation Fee
  '<strong>' . $total_renewal_paid . '</strong>',                                  // Renewal Paid
  '<strong>' . $total_renewal_due . '</strong>',                                   // Renewal Due
  '<strong>' . $total_ren_projection . '</strong>',                                // Renewal Projection
  '<strong>' . $total_ren_ticket . '</strong>',                                    // Renewal Ticket Value
  '<strong>' . $total_ref_visits . '</strong>',                                    // Referral Visits
  '<strong>' . $total_ref_reg . '</strong>',                                       // Referral Registrations
  '<strong>' . $total_ref_reg_pct . '%</strong>',                                  // Referral %
  '<strong>' . $total_ref_paid . '</strong>',                                      // Referral Paid
  '<strong>' . $total_ref_due . '</strong>',                                       // Referral Due
  '<strong>' . $total_ref_projection . '</strong>',                                // Referral Projection
  '<strong>' . $total_ref_ticket . '</strong>',                                    // Referral Ticket Value
  '<strong>' . $total_refund . '</strong>',                                        // Refund Amount
];

header('Content-Type: application/json');
echo json_encode($output);
exit;