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

// Removed sub-page SQL. Always run main report logic.
// If no branches selected, return empty result

// Main Report Logic
// If no branches selected, return empty result
if (empty($branch_id) || !is_array($branch_id)) {
  $output = ['data' => []];
  header('Content-Type: application/json');
  echo json_encode($output);
  exit;
}

// Main Report Logic
$sql = "
        SELECT *
        FROM (
                SELECT
                final.branch_label AS `Branch`,
                CAST(ROUND(IFNULL(final.gt, 0), 0) AS SIGNED) AS `GT`,
                CAST(ROUND(IFNULL(final.prog, 0), 0) AS SIGNED) AS `PROG`,
                CAST(ROUND(IFNULL(final.np_visit, 0), 0) AS SIGNED) AS `NP Visit`,
                CAST(ROUND(IFNULL(final.np_reg, 0), 0) AS SIGNED) AS `NP Registration`,
                CAST(ROUND(IFNULL(final.reg_percent, 0), 0) AS SIGNED) AS `Registration %`,
                CAST(ROUND(IFNULL(final.con_fee, 0), 0) AS SIGNED) AS `Consultation Fee`,
                CAST(ROUND(IFNULL(final.np_paid, 0), 0) AS SIGNED) AS `NP Paid`,
                CAST(ROUND(IFNULL(final.enq_gt, 0), 0) AS SIGNED) AS `Enquiry Projection`,
                CAST(ROUND(IFNULL(final.enq_due, 0), 0) AS SIGNED) AS `Enquiry Due`,
                CAST(ROUND(IFNULL(final.enq_tv, 0), 0) AS SIGNED) AS `Enquiry Ticket Value`,
                CAST(ROUND(IFNULL(final.ren_visited, 0), 0) AS SIGNED) AS `Renewal Visits`,
                CAST(ROUND(IFNULL(final.ren_registered, 0), 0) AS SIGNED) AS `Renewals`,
                CAST(ROUND(IFNULL(final.ren_percent, 0), 0) AS SIGNED) AS `Renewal %`,
                CAST(ROUND(IFNULL(final.ren_paid, 0), 0) AS SIGNED) AS `Renewal Paid`,
                CAST(ROUND(IFNULL(final.ren_due, 0), 0) AS SIGNED) AS `Renewal Due`,
                CAST(ROUND(IFNULL(final.ren_gt, 0), 0) AS SIGNED) AS `Renewal Projection`,
                CAST(ROUND(IFNULL(final.ren_tv, 0), 0) AS SIGNED) AS `Renewal Ticket Value`,
                CAST(ROUND(IFNULL(final.ref_visited, 0), 0) AS SIGNED) AS `Referral Visits`,
                CAST(ROUND(IFNULL(final.ref_reg, 0), 0) AS SIGNED) AS `Referral Registrations`,
                CAST(ROUND(IFNULL(final.ref_percent, 0), 0) AS SIGNED) AS `Referral %`,
                CAST(ROUND(IFNULL(final.ref_paid, 0), 0) AS SIGNED) AS `Referral Paid`,
                CAST(ROUND(IFNULL(final.ref_due, 0), 0) AS SIGNED) AS `Referral Due`,
                CAST(ROUND(IFNULL(final.ref_gt, 0), 0) AS SIGNED) AS `Referral Projection`,
                CAST(ROUND(IFNULL(final.ref_tv, 0), 0) AS SIGNED) AS `Referral Ticket Value`,
                CAST(ROUND(IFNULL(final.refund_amount, 0), 0) AS SIGNED) AS `Refund Amount`,
                1 AS sort_order
            FROM (
                SELECT
                    metrics.branch_id,
                    metrics.branch_name AS branch_label,
                    IFNULL(payments_table.payments_received, 0) AS gt,
                    metrics.prog,
                    metrics.np_visit,
                    metrics.np_reg,
                    metrics.reg_percent,
                    metrics.con_fee,
                    metrics.np_paid,
                    metrics.enq_gt,
                    metrics.enq_paid,
                    metrics.enq_due,
                    metrics.enq_tv,
                    metrics.ren_visited,
                    metrics.ren_registered,
                    metrics.ren_percent,
                    metrics.ren_paid,
                    metrics.ren_due,
                    metrics.ren_gt,
                    metrics.ren_tv,
                    metrics.ref_visited,
                    metrics.ref_reg,
                    metrics.ref_percent,
                    metrics.ref_paid,
                    metrics.ref_due,
                    metrics.ref_gt,
                    metrics.ref_tv,
                    metrics.refund_amount
                FROM (
                    SELECT
                        cg.id AS branch_id,
                        cg.name AS branch_name,
                        IFNULL(gt_table.gt, 0) + IFNULL(con_fee_table.con_fee, 0) AS prog,
                        IFNULL(np_visit_table.np_visit, 0) AS np_visit,
                        IFNULL(np_reg_table.np_reg, 0) AS np_reg,
                        CASE WHEN IFNULL(np_visit_table.np_visit, 0) = 0 THEN 0 ELSE ROUND(IFNULL(np_reg_table.np_reg, 0) / np_visit_table.np_visit * 100) END AS reg_percent,
                        IFNULL(con_fee_table.con_fee, 0) AS con_fee,
                        IFNULL(np_paid_table.np_paid, 0) AS np_paid,
                        IFNULL(enquiry_table.enq_gt, 0) AS enq_gt,
                        IFNULL(enquiry_table.enq_paid, 0) AS enq_paid,
                        IFNULL(enquiry_table.enq_gt, 0) - IFNULL(enquiry_table.enq_paid, 0) AS enq_due,
                        CASE WHEN IFNULL(np_reg_table.np_reg, 0) = 0 THEN 0 ELSE ROUND((IFNULL(con_fee_table.con_fee, 0) + IFNULL(np_paid_table.np_paid, 0)) / np_reg_table.np_reg, 0) END AS enq_tv,
                        IFNULL(ren_visit_table.ren_visited, 0) AS ren_visited,
                        IFNULL(ren_reg_table.ren_registered, 0) AS ren_registered,
                        CASE WHEN IFNULL(ren_visit_table.ren_visited, 0) = 0 THEN 0 ELSE ROUND(IFNULL(ren_reg_table.ren_registered, 0) / ren_visit_table.ren_visited * 100) END AS ren_percent,
                        IFNULL(ren_money_table.ren_paid, 0) AS ren_paid,
                        IFNULL(ren_money_table.ren_gt, 0) - IFNULL(ren_money_table.ren_paid, 0) AS ren_due,
                        IFNULL(ren_money_table.ren_gt, 0) AS ren_gt,
                        CASE WHEN IFNULL(ren_reg_table.ren_registered, 0) = 0 THEN 0 ELSE ROUND(IFNULL(ren_money_table.ren_paid, 0) / ren_reg_table.ren_registered, 0) END AS ren_tv,
                        IFNULL(ref_visit_table.ref_visited, 0) AS ref_visited,
                        IFNULL(ref_reg_table.ref_reg, 0) AS ref_reg,
                        CASE WHEN IFNULL(ref_visit_table.ref_visited, 0) = 0 THEN 0 ELSE ROUND(IFNULL(ref_reg_table.ref_reg, 0) / ref_visit_table.ref_visited * 100) END AS ref_percent,
                        IFNULL(ref_money_table.ref_paid, 0) AS ref_paid,
                        IFNULL(ref_money_table.ref_gt, 0) - IFNULL(ref_money_table.ref_paid, 0) AS ref_due,
                        IFNULL(ref_money_table.ref_gt, 0) AS ref_gt,
                        CASE WHEN IFNULL(ref_reg_table.ref_reg, 0) = 0 THEN 0 ELSE ROUND(IFNULL(ref_money_table.ref_paid, 0) / ref_reg_table.ref_reg, 0) END AS ref_tv,
                        0 AS refund_amount
                    FROM tblcustomers_groups cg
                    LEFT JOIN (
                        SELECT map.groupid AS branch_id, SUM(pay.amount) AS gt
                        FROM tblcustomer_groups map
                        JOIN tblinvoices inv ON inv.clientid = map.customer_id
                        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY map.groupid
                    ) gt_table ON gt_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT map.groupid AS branch_id, SUM(pay.amount) AS con_fee
                        FROM tblcustomer_groups map
                        JOIN tblinvoices inv ON inv.clientid = map.customer_id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        WHERE item.description = 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY map.groupid
                    ) con_fee_table ON con_fee_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT a.branch_id, COUNT(*) AS np_visit
                        FROM tblappointment a
                        WHERE a.visit_status = 1
                          AND a.appointment_type_id IN (18, 2)
                          AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        GROUP BY a.branch_id
                    ) np_visit_table ON np_visit_table.branch_id = cg.id
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
                              AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                              AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                        ) sub
                        GROUP BY sub.branch_id
                    ) np_reg_table ON np_reg_table.branch_id = cg.id
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
                              AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                              AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                        ) sub
                        JOIN tblinvoices inv ON inv.clientid = sub.userid
                        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY sub.branch_id
                    ) np_paid_table ON np_paid_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT sub.branch_id, SUM(inv.total) AS enq_gt, SUM(IFNULL(pay.amount, 0)) AS enq_paid
                        FROM (
                            SELECT DISTINCT a.branch_id, a.userid
                            FROM tblappointment a
                            JOIN tblinvoices inv ON inv.clientid = a.userid
                            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                            WHERE a.visit_status = 1
                              AND a.appointment_type_id IN (18, 2)
                              AND item.description <> 'Consultation Fee'
                              AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        ) sub
                        JOIN tblinvoices inv ON inv.clientid = sub.userid
                        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY sub.branch_id
                    ) enquiry_table ON enquiry_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT a.branch_id, COUNT(*) AS ren_visited
                        FROM tblappointment a
                        WHERE a.visit_status = 1
                          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
                          AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        GROUP BY a.branch_id
                    ) ren_visit_table ON ren_visit_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT sub.branch_id, COUNT(*) AS ren_registered
                        FROM (
                            SELECT DISTINCT a.branch_id, a.userid
                            FROM tblappointment a
                            JOIN tblinvoices inv ON inv.clientid = a.userid
                            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                            WHERE a.visit_status = 1
                              AND a.appointment_type_id IN (6, 11, 17, 24, 32)
                              AND item.description <> 'Consultation Fee'
                              AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        ) sub
                        GROUP BY sub.branch_id
                    ) ren_reg_table ON ren_reg_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT visit.branch_id, SUM(inv.total) AS ren_gt, SUM(IFNULL(pay.amount, 0)) AS ren_paid
                        FROM (
                            SELECT DISTINCT a.branch_id, a.userid
                            FROM tblappointment a
                            WHERE a.visit_status = 1
                              AND a.appointment_type_id IN (6, 11, 17, 24, 32)
                              AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        ) visit
                        JOIN tblinvoices inv ON inv.clientid = visit.userid
                        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY visit.branch_id
                    ) ren_money_table ON ren_money_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT rc.branch_id, COUNT(DISTINCT a.userid) AS ref_visited
                        FROM (
                            SELECT DISTINCT map.groupid AS branch_id, c.userid
                            FROM tblleads l
                            JOIN tblclients c ON c.leadid = l.id
                            JOIN tblcustomer_groups map ON map.customer_id = c.userid
                            WHERE l.refer_id > 0
                        ) rc
                        JOIN tblappointment a ON a.userid = rc.userid
                        WHERE a.visit_status = 1
                          AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59'))
                        GROUP BY rc.branch_id
                    ) ref_visit_table ON ref_visit_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT rc.branch_id, COUNT(*) AS ref_reg
                        FROM (
                            SELECT DISTINCT map.groupid AS branch_id, inv.clientid
                            FROM tblinvoices inv
                            JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                            JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                            JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
                            WHERE item.description <> 'Consultation Fee'
                              AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                        ) rc
                        GROUP BY rc.branch_id
                    ) ref_reg_table ON ref_reg_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT map.groupid AS branch_id, SUM(inv.total) AS ref_gt, SUM(IFNULL(pay.amount, 0)) AS ref_paid
                        FROM tblinvoices inv
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql)
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        GROUP BY map.groupid
                    ) ref_money_table ON ref_money_table.branch_id = cg.id
                    WHERE 1=1 $branch_filter_sql
                ) metrics
                LEFT JOIN (
                    SELECT cg.id AS branch_id, COALESCE(SUM(pr.amount), 0) AS payments_received
                    FROM tblcustomers_groups cg
                    LEFT JOIN tblcustomer_groups map ON map.groupid = cg.id
                    LEFT JOIN tblinvoices inv ON inv.clientid = map.customer_id
                    LEFT JOIN tblinvoicepaymentrecords pr ON pr.invoiceid = inv.id
                        AND ($from_date_sql IS NULL OR pr.date >= $from_date_sql)
                        AND ($to_date_sql IS NULL OR pr.date <= $to_date_sql)
                    WHERE map.customer_id IS NOT NULL
                      AND inv.status <> 5
                      AND ($currency_sql IS NULL OR inv.currency = $currency_sql)
                    GROUP BY cg.id
                ) payments_table ON payments_table.branch_id = metrics.branch_id
            ) final
        ) outer_q
        WHERE
            LENGTH(COALESCE($from_date_sql, '')) > 0
            AND LENGTH(COALESCE($to_date_sql, '')) > 0
        ORDER BY outer_q.sort_order, outer_q.Branch;
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
  'gt' => 0,
  'prog' => 0,
  'np_visit' => 0,
  'np_reg' => 0,
  'con_fee' => 0,
  'np_paid' => 0,
  'enq_gt' => 0,
  'enq_due' => 0,
  'ren_visited' => 0,
  'ren_registered' => 0,
  'ren_paid' => 0,
  'ren_due' => 0,
  'ren_gt' => 0,
  'ref_visited' => 0,
  'ref_reg' => 0,
  'ref_paid' => 0,
  'ref_due' => 0,
  'ref_gt' => 0,
  'refund_amount' => 0
];

// Format data for DataTable (main report only)
foreach ($result as $row) {
  // Accumulate sums
  $totals['gt'] += (int) $row['GT'];
  $totals['prog'] += (int) $row['PROG'];
  $totals['np_visit'] += (int) $row['NP Visit'];
  $totals['np_reg'] += (int) $row['NP Registration'];
  $totals['con_fee'] += (int) $row['Consultation Fee'];
  $totals['np_paid'] += (int) $row['NP Paid'];
  $totals['enq_gt'] += (int) $row['Enquiry Projection'];
  $totals['enq_due'] += (int) $row['Enquiry Due'];
  $totals['ren_visited'] += (int) $row['Renewal Visits'];
  $totals['ren_registered'] += (int) $row['Renewals'];
  $totals['ren_paid'] += (int) $row['Renewal Paid'];
  $totals['ren_due'] += (int) $row['Renewal Due'];
  $totals['ren_gt'] += (int) $row['Renewal Projection'];
  $totals['ref_visited'] += (int) $row['Referral Visits'];
  $totals['ref_reg'] += (int) $row['Referral Registrations'];
  $totals['ref_paid'] += (int) $row['Referral Paid'];
  $totals['ref_due'] += (int) $row['Referral Due'];
  $totals['ref_gt'] += (int) $row['Referral Projection'];
  $totals['refund_amount'] += (int) $row['Refund Amount'];

  $branch_id_row = isset($row['branch_id']) ? $row['branch_id'] : ''; // Need branch_id from SQL

  $output['data'][] = [
    $row['Branch'],
    $row['GT'],
    $row['PROG'],
    '<a href="#" class="metric-drilldown" data-metric="np_visit" data-branch="' . html_escape($branch_id_row) . '">' . $row['NP Visit'] . '</a>',
    '<a href="#" class="metric-drilldown" data-metric="np_reg" data-branch="' . html_escape($branch_id_row) . '">' . $row['NP Registration'] . '</a>',
    $row['Registration %'],
    $row['Consultation Fee'],
    $row['NP Paid'],
    $row['Enquiry Projection'],
    $row['Enquiry Due'],
    $row['Enquiry Ticket Value'],
    '<a href="#" class="metric-drilldown" data-metric="ren_visited" data-branch="' . html_escape($branch_id_row) . '">' . $row['Renewal Visits'] . '</a>',
    '<a href="#" class="metric-drilldown" data-metric="ren_registered" data-branch="' . html_escape($branch_id_row) . '">' . $row['Renewals'] . '</a>',
    $row['Renewal %'],
    $row['Renewal Paid'],
    $row['Renewal Due'],
    $row['Renewal Projection'],
    $row['Renewal Ticket Value'],
    '<a href="#" class="metric-drilldown" data-metric="ref_visited" data-branch="' . html_escape($branch_id_row) . '">' . $row['Referral Visits'] . '</a>',
    '<a href="#" class="metric-drilldown" data-metric="ref_reg" data-branch="' . html_escape($branch_id_row) . '">' . $row['Referral Registrations'] . '</a>',
    $row['Referral %'],
    $row['Referral Paid'],
    $row['Referral Due'],
    $row['Referral Projection'],
    $row['Referral Ticket Value'],
    $row['Refund Amount'],
  ];
}

// Calculate percentages and ticket values correctly for the totals row
$total_reg_percent = ($totals['np_visit'] > 0) ? round(($totals['np_reg'] / $totals['np_visit']) * 100) : 0;
$total_enq_tv = ($totals['np_reg'] > 0) ? round(($totals['con_fee'] + $totals['np_paid']) / $totals['np_reg']) : 0;
$total_ren_percent = ($totals['ren_visited'] > 0) ? round(($totals['ren_registered'] / $totals['ren_visited']) * 100) : 0;
$total_ren_tv = ($totals['ren_registered'] > 0) ? round($totals['ren_paid'] / $totals['ren_registered']) : 0;
$total_ref_percent = ($totals['ref_visited'] > 0) ? round(($totals['ref_reg'] / $totals['ref_visited']) * 100) : 0;
$total_ref_tv = ($totals['ref_reg'] > 0) ? round($totals['ref_paid'] / $totals['ref_reg']) : 0;

$output['totals'] = [
  '<strong>Grand Total</strong>',
  '<strong>' . $totals['gt'] . '</strong>',
  '<strong>' . $totals['prog'] . '</strong>',
  '<strong>' . $totals['np_visit'] . '</strong>',
  '<strong>' . $totals['np_reg'] . '</strong>',
  '<strong>' . $total_reg_percent . '</strong>',
  '<strong>' . $totals['con_fee'] . '</strong>',
  '<strong>' . $totals['np_paid'] . '</strong>',
  '<strong>' . $totals['enq_gt'] . '</strong>',
  '<strong>' . $totals['enq_due'] . '</strong>',
  '<strong>' . $total_enq_tv . '</strong>',
  '<strong>' . $totals['ren_visited'] . '</strong>',
  '<strong>' . $totals['ren_registered'] . '</strong>',
  '<strong>' . $total_ren_percent . '</strong>',
  '<strong>' . $totals['ren_paid'] . '</strong>',
  '<strong>' . $totals['ren_due'] . '</strong>',
  '<strong>' . $totals['ren_gt'] . '</strong>',
  '<strong>' . $total_ren_tv . '</strong>',
  '<strong>' . $totals['ref_visited'] . '</strong>',
  '<strong>' . $totals['ref_reg'] . '</strong>',
  '<strong>' . $total_ref_percent . '</strong>',
  '<strong>' . $totals['ref_paid'] . '</strong>',
  '<strong>' . $totals['ref_due'] . '</strong>',
  '<strong>' . $totals['ref_gt'] . '</strong>',
  '<strong>' . $total_ref_tv . '</strong>',
  '<strong>' . $totals['refund_amount'] . '</strong>',
];

header('Content-Type: application/json');
echo json_encode($output);
exit;