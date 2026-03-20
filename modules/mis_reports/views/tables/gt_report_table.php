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

$goals_lookup = []; // keyed by branch_id => ['gt_goal' => X, 'enquiry_goal' => X]
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

// ---- Fetch NP Visits from Appointment Type ('First Appointment') ----
$np_appt_sql = "
    SELECT a.branch_id, COUNT(DISTINCT a.userid) AS np_visits
    FROM tblappointment a
    JOIN tblappointment_type at ON at.appointment_type_id = a.appointment_type_id
    WHERE at.appointment_type_name = 'First Appointment'
      AND a.appointment_date >= '$from_date_esc 00:00:00'
      AND a.appointment_date <= '$to_date_esc 23:59:59'
    GROUP BY a.branch_id
";
$np_appt_result = $CI->db->query($np_appt_sql)->result_array();
$np_appt_lookup = [];
foreach ($np_appt_result as $r) {
    $np_appt_lookup[(int) $r['branch_id']] = (int) $r['np_visits'];
}

// ---- Fetch NP Visits from Payment Category ('First Appointment') ----
$np_paycat_sql = "
    SELECT sub.branch_id, COUNT(DISTINCT sub.clientid) AS np_visits
    FROM (
        SELECT DISTINCT pr.id, map.groupid AS branch_id, inv.clientid
        FROM tblinvoicepaymentrecords pr
        JOIN tblinvoices inv ON inv.id = pr.invoiceid
        JOIN tblappointment_type at ON at.appointment_type_id = inv.appointment_type_id
        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE at.appointment_type_name = 'First Appointment'
          AND pr.date >= '$from_date_esc'
          AND pr.date <= '$to_date_esc'
    ) sub
    GROUP BY sub.branch_id
";
$np_paycat_result = $CI->db->query($np_paycat_sql)->result_array();
$np_paycat_lookup = [];
foreach ($np_paycat_result as $r) {
    $np_paycat_lookup[(int) $r['branch_id']] = (int) $r['np_visits'];
}

// ---- Fetch NP Registration: unique patients with a package (excl. Consultation Fee) ----
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
    ) sub
    GROUP BY sub.branch_id
";
$np_reg_result = $CI->db->query($np_reg_sql)->result_array();
$np_reg_lookup = [];
foreach ($np_reg_result as $r) {
    $np_reg_lookup[(int) $r['branch_id']] = (int) $r['np_reg'];
}

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
            $goals_lookup[$bid] = ['gt_goal' => 0, 'enquiry_goal' => 0];
        }
        if (isset($goals_lookup[$bid][$type])) {
            $goals_lookup[$bid][$type] = (float) $g['total_amount'];
        }
    }
}

// ---- Simple branch query (all formulas removed) ----
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

// Initialize totals
$total_gt_goal = 0;
$total_gt_achieved = 0;
$total_enquiry_goal = 0;

// Format data for DataTable
foreach ($result as $row) {
  $bid = (int) $row['branch_id'];

  // Fetch goals for this branch
  $branch_goals = isset($goals_lookup[$bid]) ? $goals_lookup[$bid] : ['gt_goal' => 0, 'enquiry_goal' => 0];
  $gt_goal = (int) $branch_goals['gt_goal'];
  $enquiry_goal = (int) $branch_goals['enquiry_goal'];

  // Fetch paid amount for this branch
  $gt_achieved = isset($paid_lookup[$bid]) ? round($paid_lookup[$bid]) : 0;
  $gt_achieved_pct = ($gt_goal > 0) ? round(($gt_achieved / $gt_goal) * 100) : 0;

  // GT Projection = (GT Achieved / current day of month) * total days in month
  $current_day   = (int) date('j', strtotime($to_date));
  $days_in_month = (int) date('t', strtotime($to_date));
  $gt_projection = ($current_day > 0) ? round(($gt_achieved / $current_day) * $days_in_month) : 0;

  // Accumulate totals
  $total_gt_goal += $gt_goal;
  $total_gt_achieved += $gt_achieved;
  $total_gt_projection = (isset($total_gt_projection) ? $total_gt_projection : 0) + $gt_projection;
  $total_np_appt = (isset($total_np_appt) ? $total_np_appt : 0) + (isset($np_appt_lookup[$bid]) ? $np_appt_lookup[$bid] : 0);
  $total_np_paycat = (isset($total_np_paycat) ? $total_np_paycat : 0) + (isset($np_paycat_lookup[$bid]) ? $np_paycat_lookup[$bid] : 0);
  $np_reg = isset($np_reg_lookup[$bid]) ? $np_reg_lookup[$bid] : 0;
  $total_np_reg = (isset($total_np_reg) ? $total_np_reg : 0) + $np_reg;
  $total_enquiry_goal += $enquiry_goal;

  $output['data'][] = [
    $row['branch_name'],         // Branch
    $gt_goal,                    // GT Goal
    $gt_achieved,                // GT Achieved
    $gt_achieved_pct . '%',      // GT Achieved %
    $gt_projection,              // GT Projection
    isset($np_appt_lookup[$bid]) ? $np_appt_lookup[$bid] : 0,     // NP Visits (Appt Type)
    isset($np_paycat_lookup[$bid]) ? $np_paycat_lookup[$bid] : 0, // NP Visits (Pay Cat)
    $np_reg,                     // NP Registration
    0,                           // NP Registration %
    0,                           // Enquiry Consultation Fee
    0,                           // NP Paid
    0,                           // NP Ticket Value
    0,                           // Enquiry Due Collected
    0,                           // Enquiry GT
    $enquiry_goal,               // Enquiry Goal
    0,                           // Enquiry Projection
    0,                           // Renewal Visits
    0,                           // Renewed
    0,                           // Renewed %
    0,                           // Follow-up Consultation Fee
    0,                           // Renewal Paid
    // ── Pending columns (amber) ──
    0,                           // Renewal Due
    0,                           // Renewal Projection
    0,                           // Renewal Ticket Value
    0,                           // Referral Visits
    0,                           // Referral Registrations
    0,                           // Referral %
    0,                           // Referral Paid
    0,                           // Referral Due
    0,                           // Referral Projection
    0,                           // Referral Ticket Value
    0,                           // Refund Amount
  ];
}

// Totals row
$total_gt_achieved_pct = ($total_gt_goal > 0) ? round(($total_gt_achieved / $total_gt_goal) * 100) : 0;
$output['totals'] = [
  '<strong>Grand Total</strong>',
  '<strong>' . $total_gt_goal . '</strong>',             // GT Goal
  '<strong>' . $total_gt_achieved . '</strong>',         // GT Achieved
  '<strong>' . $total_gt_achieved_pct . '%</strong>',    // GT Achieved %
  '<strong>' . $total_gt_projection . '</strong>',       // GT Projection
  '<strong>' . $total_np_appt . '</strong>',              // NP Visits (Appt Type)
  '<strong>' . $total_np_paycat . '</strong>',            // NP Visits (Pay Cat)
  '<strong>' . $total_np_reg . '</strong>',                // NP Registration
  '<strong>0</strong>',                                   // NP Registration %
  '<strong>0</strong>',                                   // Enquiry Consultation Fee
  '<strong>0</strong>',                                   // NP Paid
  '<strong>0</strong>',                                   // NP Ticket Value
  '<strong>0</strong>',                                   // Enquiry Due Collected
  '<strong>0</strong>',                                   // Enquiry GT
  '<strong>' . $total_enquiry_goal . '</strong>',         // Enquiry Goal
  '<strong>0</strong>',                                   // Enquiry Projection
  '<strong>0</strong>',                                   // Renewal Visits
  '<strong>0</strong>',                                   // Renewed
  '<strong>0</strong>',                                   // Renewed %
  '<strong>0</strong>',                                   // Follow-up Consultation Fee
  '<strong>0</strong>',                                   // Renewal Paid
  // ── Pending columns (amber) ──
  '<strong>0</strong>',                                   // Renewal Due
  '<strong>0</strong>',                                   // Renewal Projection
  '<strong>0</strong>',                                   // Renewal Ticket Value
  '<strong>0</strong>',                                   // Referral Visits
  '<strong>0</strong>',                                   // Referral Registrations
  '<strong>0</strong>',                                   // Referral %
  '<strong>0</strong>',                                   // Referral Paid
  '<strong>0</strong>',                                   // Referral Due
  '<strong>0</strong>',                                   // Referral Projection
  '<strong>0</strong>',                                   // Referral Ticket Value
  '<strong>0</strong>',                                   // Refund Amount
];

header('Content-Type: application/json');
echo json_encode($output);
exit;