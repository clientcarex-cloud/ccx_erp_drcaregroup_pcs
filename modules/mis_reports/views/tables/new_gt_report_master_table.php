<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// ---- Filters ----
$from_date = $CI->input->get_post('consulted_date');
if (!$from_date && isset($consulted_from_date))
    $from_date = $consulted_from_date;
$to_date = $CI->input->get_post('consulted_to_date');
if (!$to_date && isset($consulted_to_date))
    $to_date = $consulted_to_date;
$branch_id = $CI->input->get_post('branch');

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

// If no branches selected, return empty result
if (empty($branch_id) || !is_array($branch_id)) {
    $output = ['data' => []];
    header('Content-Type: application/json');
    echo json_encode($output);
    exit;
}

// Main SQL: simplified GT report with only the 7 required columns
$sql = "
    SELECT
        cg.name AS `Branch`,
        CAST(ROUND(IFNULL(payments_table.payments_received, 0), 0) AS SIGNED) AS `Grand Total`,
        CAST(ROUND(IFNULL(np_visit_table.np_visit, 0), 0) AS SIGNED) AS `New Patients Visits`,
        CAST(ROUND(IFNULL(np_reg_table.np_reg, 0), 0) AS SIGNED) AS `New Patient Registration`,
        CASE WHEN IFNULL(np_visit_table.np_visit, 0) = 0 THEN 0
             ELSE CAST(ROUND(IFNULL(np_reg_table.np_reg, 0) / np_visit_table.np_visit * 100, 0) AS SIGNED)
        END AS `Registration %`,
        CAST(ROUND(IFNULL(con_fee_table.con_fee, 0), 0) AS SIGNED) AS `Consultation Fee`,
        CAST(ROUND(IFNULL(np_paid_table.np_paid, 0), 0) AS SIGNED) AS `NP Paid`
    FROM tblcustomers_groups cg

    /* Grand Total: all payments received for the branch in the date range */
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
        GROUP BY cg2.id
    ) payments_table ON payments_table.branch_id = cg.id

    /* New Patient Visits: appointments with visit_status=1 and NP appointment types */
    LEFT JOIN (
        SELECT a.branch_id, COUNT(*) AS np_visit
        FROM tblappointment a
        WHERE a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00')
          AND a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')
        GROUP BY a.branch_id
    ) np_visit_table ON np_visit_table.branch_id = cg.id

    /* New Patient Registration: distinct patients who have a package (invoice, non-consultation-fee) */
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

    /* Consultation Fee: sum of payments against consultation-fee invoices */
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

// Initialize totals
$totals = [
    'gt' => 0,
    'np_visit' => 0,
    'np_reg' => 0,
    'con_fee' => 0,
    'np_paid' => 0,
];

foreach ($result as $row) {
    $totals['gt'] += (int) $row['Grand Total'];
    $totals['np_visit'] += (int) $row['New Patients Visits'];
    $totals['np_reg'] += (int) $row['New Patient Registration'];
    $totals['con_fee'] += (int) $row['Consultation Fee'];
    $totals['np_paid'] += (int) $row['NP Paid'];

    $output['data'][] = [
        $row['Branch'],
        $row['Grand Total'],
        $row['New Patients Visits'],
        $row['New Patient Registration'],
        $row['Registration %'],
        $row['Consultation Fee'],
        $row['NP Paid'],
    ];
}

// Grand Total row
$total_reg_percent = ($totals['np_visit'] > 0) ? round(($totals['np_reg'] / $totals['np_visit']) * 100) : 0;

$output['totals'] = [
    '<strong>Grand Total</strong>',
    '<strong>' . $totals['gt'] . '</strong>',
    '<strong>' . $totals['np_visit'] . '</strong>',
    '<strong>' . $totals['np_reg'] . '</strong>',
    '<strong>' . $total_reg_percent . '%</strong>',
    '<strong>' . $totals['con_fee'] . '</strong>',
    '<strong>' . $totals['np_paid'] . '</strong>',
];

header('Content-Type: application/json');
echo json_encode($output);
exit;
