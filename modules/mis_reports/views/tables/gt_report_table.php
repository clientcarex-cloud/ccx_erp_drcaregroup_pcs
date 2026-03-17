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

$from_date_sql = "'" . $CI->db->escape_str($from_date) . "'";
$to_date_sql = "'" . $CI->db->escape_str($to_date) . "'";

// Build branch filter SQL clause
$branch_filter_sql = '';
if (!empty($branch_id) && is_array($branch_id)) {
  $clean_branch_ids = array_filter($branch_id, 'is_numeric');
  $clean_branch_ids = array_map('intval', $clean_branch_ids);
  if (!empty($clean_branch_ids)) {
    $branch_filter_sql = ' AND cg.id IN (' . implode(',', $clean_branch_ids) . ')';
  }
}

$currency_sql = $currency ? "'" . $CI->db->escape_str($currency) . "'" : "NULL";

// If no branches selected, return empty result
if (empty($branch_id) || !is_array($branch_id)) {
  $output = ['data' => []];
  header('Content-Type: application/json');
  echo json_encode($output);
  exit;
}

// ---- Date calculations for projections ----
$from_ts = strtotime($from_date);
$to_ts = strtotime($to_date);
$today_ts = strtotime(date('Y-m-d'));

// Day of month = how many days have passed within the range (capped at today)
$effective_end = min($to_ts, $today_ts);
$day_of_month = max(1, (int) (($effective_end - $from_ts) / 86400) + 1);

// Total days in the selected range
$total_days = max(1, (int) (($to_ts - $from_ts) / 86400) + 1);

// ---- Main Report SQL ----
$sql = "
    SELECT
        cg.name AS branch_name,
        cg.id AS branch_id,

        /* GT Achieved: total payments received in date range */
        CAST(ROUND(IFNULL(payments_table.payments_received, 0), 0) AS SIGNED) AS gt_achieved,

        /* NP Visits: first-appointment count of unique patients (confirmed visits) */
        CAST(ROUND(IFNULL(np_visit_table.np_visit, 0), 0) AS SIGNED) AS np_visits,

        /* NP Registration: distinct patients with packages (Mr.no/having packages) */
        CAST(ROUND(IFNULL(np_reg_table.np_reg, 0), 0) AS SIGNED) AS np_registration,

        /* NP Registration % */
        CASE WHEN IFNULL(np_visit_table.np_visit, 0) = 0 THEN 0
             ELSE CAST(ROUND(IFNULL(np_reg_table.np_reg, 0) / np_visit_table.np_visit * 100, 0) AS SIGNED)
        END AS np_registration_pct,

        /* Enquiry Consultation Fee: first appointment consultation fee */
        CAST(ROUND(IFNULL(con_fee_table.con_fee, 0), 0) AS SIGNED) AS enquiry_con_fee,

        /* NP Paid: first appointment, exclude consultation fee (Total of paid amt) */
        CAST(ROUND(IFNULL(np_paid_table.np_paid, 0), 0) AS SIGNED) AS np_paid,

        /* Enquiry Due Collected: payments where payment date > registration date, sum amount */
        CAST(ROUND(IFNULL(enq_due_table.enq_due_collected, 0), 0) AS SIGNED) AS enq_due_collected,

        /* Renewal Visits */
        CAST(ROUND(IFNULL(ren_visit_table.ren_visited, 0), 0) AS SIGNED) AS renewal_visits,

        /* Renewed: packages renewed with payment status = paid */
        CAST(ROUND(IFNULL(ren_reg_table.ren_registered, 0), 0) AS SIGNED) AS renewed,

        /* Follow-up Consultation Fee: visit type = Follow-up OR Renewal Appointment */
        CAST(ROUND(IFNULL(followup_con_fee_table.followup_con_fee, 0), 0) AS SIGNED) AS followup_con_fee,

        /* Renewal Paid: visit type = Renewal AND payment > 0, sum paid amount */
        CAST(ROUND(IFNULL(ren_money_table.ren_paid, 0), 0) AS SIGNED) AS renewal_paid

    FROM tblcustomers_groups cg

    /* GT Achieved: all payments received for the branch in the date range */
    LEFT JOIN (
        SELECT cg2.id AS branch_id, COALESCE(SUM(pr.amount), 0) AS payments_received
        FROM tblcustomers_groups cg2
        LEFT JOIN tblcustomer_groups map ON map.groupid = cg2.id
        LEFT JOIN tblinvoices inv ON inv.clientid = map.customer_id
        LEFT JOIN tblinvoicepaymentrecords pr ON pr.invoiceid = inv.id
            AND pr.date >= $from_date_sql
            AND pr.date <= $to_date_sql
        WHERE map.customer_id IS NOT NULL
          AND inv.status <> 5
          AND ($currency_sql IS NULL OR inv.currency = $currency_sql)
        GROUP BY cg2.id
    ) payments_table ON payments_table.branch_id = cg.id

    /* NP Visits: appointments with visit_status=1 and NP appointment types */
    LEFT JOIN (
        SELECT a.branch_id, COUNT(*) AS np_visit
        FROM tblappointment a
        WHERE a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
          AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
        GROUP BY a.branch_id
    ) np_visit_table ON np_visit_table.branch_id = cg.id

    /* NP Registration: distinct patients who have a package (invoice, non-consultation-fee) */
    LEFT JOIN (
        SELECT sub.branch_id, COUNT(*) AS np_reg
        FROM (
            SELECT DISTINCT a.branch_id, a.userid
            FROM tblappointment a
            JOIN tblinvoices inv ON inv.clientid = a.userid
            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (18, 2)
              AND item.description <> 'Consultation Fee'
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
              AND inv.date >= $from_date_sql
              AND inv.date <= $to_date_sql
        ) sub
        GROUP BY sub.branch_id
    ) np_reg_table ON np_reg_table.branch_id = cg.id

    /* Enquiry Consultation Fee: sum of consultation fee payments for NP visits */
    LEFT JOIN (
        SELECT map.groupid AS branch_id, SUM(pay.amount) AS con_fee
        FROM tblcustomer_groups map
        JOIN tblinvoices inv ON inv.clientid = map.customer_id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE item.description = 'Consultation Fee'
          AND inv.date >= $from_date_sql
          AND inv.date <= $to_date_sql
          AND pay.date >= $from_date_sql
          AND pay.date <= $to_date_sql
        GROUP BY map.groupid
    ) con_fee_table ON con_fee_table.branch_id = cg.id

    /* NP Paid: payments for registered new patients (non-consultation-fee) */
    LEFT JOIN (
        SELECT sub.branch_id, SUM(pay.amount) AS np_paid
        FROM (
            SELECT DISTINCT a.branch_id, a.userid
            FROM tblappointment a
            JOIN tblinvoices inv ON inv.clientid = a.userid
            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (18, 2)
              AND item.description <> 'Consultation Fee'
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
              AND inv.date >= $from_date_sql
              AND inv.date <= $to_date_sql
        ) sub
        JOIN tblinvoices inv ON inv.clientid = sub.userid
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE item.description <> 'Consultation Fee'
          AND inv.date >= $from_date_sql
          AND inv.date <= $to_date_sql
          AND pay.date >= $from_date_sql
          AND pay.date <= $to_date_sql
        GROUP BY sub.branch_id
    ) np_paid_table ON np_paid_table.branch_id = cg.id

    /* Enquiry Due Collected: payments where payment date > registration/invoice date */
    LEFT JOIN (
        SELECT sub.branch_id, SUM(sub.due_amount) AS enq_due_collected
        FROM (
            SELECT a.branch_id, pay.amount AS due_amount
            FROM tblappointment a
            JOIN tblinvoices inv ON inv.clientid = a.userid
            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
            JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (18, 2)
              AND item.description <> 'Consultation Fee'
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
              AND pay.date >= $from_date_sql
              AND pay.date <= $to_date_sql
              AND DATEDIFF(pay.date, inv.date) > 0
        ) sub
        GROUP BY sub.branch_id
    ) enq_due_table ON enq_due_table.branch_id = cg.id

    /* Renewal Visits: Renewal / Follow-up (Renewal related) / pre-renewal types,
       count 1 per unique Patient + Package only */
    LEFT JOIN (
        SELECT sub.branch_id, COUNT(*) AS ren_visited
        FROM (
            SELECT DISTINCT a.branch_id, a.userid, item.description
            FROM tblappointment a
            JOIN tblinvoices inv ON inv.clientid = a.userid
            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
            JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (6, 11, 17, 24, 32)
              AND item.description <> 'Consultation Fee'
              AND pay.amount > 0
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT(DATE_ADD($to_date_sql, INTERVAL 15 DAY), ' 23:59:59')
        ) sub
        GROUP BY sub.branch_id
    ) ren_visit_table ON ren_visit_table.branch_id = cg.id

    /* Renewed: packages renewed with payment status = paid */
    LEFT JOIN (
        SELECT sub.branch_id, COUNT(*) AS ren_registered
        FROM (
            SELECT DISTINCT a.branch_id, a.userid
            FROM tblappointment a
            JOIN tblinvoices inv ON inv.clientid = a.userid
            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
            JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (6, 11, 17, 24, 32)
              AND item.description <> 'Consultation Fee'
              AND inv.status = 2
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
        ) sub
        GROUP BY sub.branch_id
    ) ren_reg_table ON ren_reg_table.branch_id = cg.id

    /* Follow-up Consultation Fee: Visit Type = Follow-up OR Renewal, sum consultation fee */
    LEFT JOIN (
        SELECT a.branch_id, SUM(pay.amount) AS followup_con_fee
        FROM tblappointment a
        JOIN tblcustomer_groups map ON map.customer_id = a.userid AND map.groupid = a.branch_id
        JOIN tblinvoices inv ON inv.clientid = a.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND item.description = 'Consultation Fee'
          AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
          AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
          AND pay.date >= $from_date_sql
          AND pay.date <= $to_date_sql
        GROUP BY a.branch_id
    ) followup_con_fee_table ON followup_con_fee_table.branch_id = cg.id

    /* Renewal Paid: Visit Type = Renewal AND Payment > 0, sum Paid Amount */
    LEFT JOIN (
        SELECT visit.branch_id, SUM(pay.amount) AS ren_paid
        FROM (
            SELECT DISTINCT a.branch_id, a.userid
            FROM tblappointment a
            WHERE a.visit_status = 1
              AND a.appointment_type_id IN (6, 11, 17, 24, 32)
              AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
              AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
        ) visit
        JOIN tblinvoices inv ON inv.clientid = visit.userid
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE item.description <> 'Consultation Fee'
          AND pay.amount > 0
          AND inv.date >= $from_date_sql
          AND inv.date <= $to_date_sql
          AND pay.date >= $from_date_sql
          AND pay.date <= $to_date_sql
        GROUP BY visit.branch_id
    ) ren_money_table ON ren_money_table.branch_id = cg.id

    WHERE 1=1 $branch_filter_sql
    ORDER BY cg.name;
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

// Initialize totals array
$totals = [
  'gt_achieved' => 0,
  'np_visits' => 0,
  'np_registration' => 0,
  'enquiry_con_fee' => 0,
  'np_paid' => 0,
  'enq_due_collected' => 0,
  'renewal_visits' => 0,
  'renewed' => 0,
  'followup_con_fee' => 0,
  'renewal_paid' => 0,
];

// Format data for DataTable
foreach ($result as $row) {
  $gt_achieved = (int) $row['gt_achieved'];
  $np_visits = (int) $row['np_visits'];
  $np_registration = (int) $row['np_registration'];
  $np_reg_pct = (int) $row['np_registration_pct'];
  $enquiry_con_fee = (int) $row['enquiry_con_fee'];
  $np_paid = (int) $row['np_paid'];
  $enq_due_collected = (int) $row['enq_due_collected'];
  $renewal_visits = (int) $row['renewal_visits'];
  $renewed = (int) $row['renewed'];
  $followup_con_fee = (int) $row['followup_con_fee'];
  $renewal_paid = (int) $row['renewal_paid'];

  // Computed columns
  $gt_goal = 0; // Placeholder — no goal data source yet
  $gt_achieved_pct = ($gt_goal > 0) ? round(($gt_achieved / $gt_goal) * 100) : 0;
  $gt_projection = round(($gt_achieved / $day_of_month) * $total_days);
  $np_ticket_value = ($np_visits > 0) ? round($np_paid / $np_visits) : 0;
  $enquiry_gt = $np_paid + $enq_due_collected;
  $enquiry_goal = 0; // Placeholder — no goal data source yet
  $enquiry_projection = round(($enquiry_gt / $day_of_month) * $total_days);
  $renewed_pct = ($renewal_visits > 0) ? round(($renewed / $renewal_visits) * 100) : 0;

  // Accumulate sums
  $totals['gt_achieved'] += $gt_achieved;
  $totals['np_visits'] += $np_visits;
  $totals['np_registration'] += $np_registration;
  $totals['enquiry_con_fee'] += $enquiry_con_fee;
  $totals['np_paid'] += $np_paid;
  $totals['enq_due_collected'] += $enq_due_collected;
  $totals['renewal_visits'] += $renewal_visits;
  $totals['renewed'] += $renewed;
  $totals['followup_con_fee'] += $followup_con_fee;
  $totals['renewal_paid'] += $renewal_paid;

  $output['data'][] = [
    $row['branch_name'],         // Branch
    $gt_goal,                    // GT Goal
    $gt_achieved,                // GT Achieved
    $gt_achieved_pct,            // GT Achieved %
    $gt_projection,              // GT Projection
    $np_visits,                  // NP Visits
    $np_registration,            // NP Registration
    $np_reg_pct,                 // NP Registration %
    $enquiry_con_fee,            // Enquiry Consultation Fee
    $np_paid,                    // NP Paid
    $np_ticket_value,            // NP Ticket Value
    $enq_due_collected,          // Enquiry Due Collected
    $enquiry_gt,                 // Enquiry GT
    $enquiry_goal,               // Enquiry Goal
    $enquiry_projection,         // Enquiry Projection
    $renewal_visits,             // Renewal Visits
    $renewed,                    // Renewed
    $renewed_pct,                // Renewed %
    $followup_con_fee,           // Follow-up Consultation Fee
    $renewal_paid,               // Renewal Paid
  ];
}

// Calculate totals row with correct percentages and computed values
$total_np_reg_pct = ($totals['np_visits'] > 0) ? round(($totals['np_registration'] / $totals['np_visits']) * 100) : 0;
$total_np_ticket_value = ($totals['np_visits'] > 0) ? round($totals['np_paid'] / $totals['np_visits']) : 0;
$total_enquiry_gt = $totals['np_paid'] + $totals['enq_due_collected'];
$total_gt_projection = round(($totals['gt_achieved'] / $day_of_month) * $total_days);
$total_enquiry_projection = round(($total_enquiry_gt / $day_of_month) * $total_days);
$total_renewed_pct = ($totals['renewal_visits'] > 0) ? round(($totals['renewed'] / $totals['renewal_visits']) * 100) : 0;

$output['totals'] = [
  '<strong>Grand Total</strong>',
  '<strong>0</strong>',                                              // GT Goal
  '<strong>' . $totals['gt_achieved'] . '</strong>',                 // GT Achieved
  '<strong>0</strong>',                                              // GT Achieved %
  '<strong>' . $total_gt_projection . '</strong>',                   // GT Projection
  '<strong>' . $totals['np_visits'] . '</strong>',                   // NP Visits
  '<strong>' . $totals['np_registration'] . '</strong>',             // NP Registration
  '<strong>' . $total_np_reg_pct . '</strong>',                      // NP Registration %
  '<strong>' . $totals['enquiry_con_fee'] . '</strong>',             // Enquiry Consultation Fee
  '<strong>' . $totals['np_paid'] . '</strong>',                     // NP Paid
  '<strong>' . $total_np_ticket_value . '</strong>',                 // NP Ticket Value
  '<strong>' . $totals['enq_due_collected'] . '</strong>',           // Enquiry Due Collected
  '<strong>' . $total_enquiry_gt . '</strong>',                      // Enquiry GT
  '<strong>0</strong>',                                              // Enquiry Goal
  '<strong>' . $total_enquiry_projection . '</strong>',              // Enquiry Projection
  '<strong>' . $totals['renewal_visits'] . '</strong>',              // Renewal Visits
  '<strong>' . $totals['renewed'] . '</strong>',                     // Renewed
  '<strong>' . $total_renewed_pct . '</strong>',                     // Renewed %
  '<strong>' . $totals['followup_con_fee'] . '</strong>',            // Follow-up Consultation Fee
  '<strong>' . $totals['renewal_paid'] . '</strong>',                // Renewal Paid
];

header('Content-Type: application/json');
echo json_encode($output);
exit;