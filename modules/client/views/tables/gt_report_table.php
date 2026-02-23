<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// ---- Filters ----
$from_date = $CI->input->post('consulted_date');
$to_date = $CI->input->post('consulted_to_date');
$branch_id = $CI->input->post('branch'); // Array or value
$currency = $CI->input->post('currency');

// Clean and sanitize filters
if (!$from_date)
    $from_date = date('Y-m-01');
if (!$to_date)
    $to_date = date('Y-m-t');

$from_date_sql = "'" . $CI->db->escape_str($from_date) . "'";
$to_date_sql = "'" . $CI->db->escape_str($to_date) . "'";

// Handle Branch Filter
// If branch filter is an array (from multiple select), implode it.
// However, the SQL provided uses {{filter:branch_id}} inside the query, sometimes inside specific checking logic.
// The main query seems to group by branch or check specific branches. 
// The main query provided by user DOES NOT seem to have a variable for branch_id in the WHERE clause of the outer wrapper,
// but rather checks `AND ({{filter:date_from}} IS NULL ...)` mostly.
// Wait, looking at the User's Main Query: I see `AND ({{filter:date_from}} IS NULL ...)` but I don't see `{{filter:branch_id}}` used in the main query's WHERE clause heavily, 
// EXCEPT maybe it's missing from the snippet or I need to check if the user *wants* to filter the main report by branch.
// The original code filtered by branch. The NEW SQL has `LEFT JOIN ... gt_table` etc.
// The new SQL has `metrics.branch_id`.
// Actually, the main query provided by the user does NOT have a `{{filter:branch_id}}` placeholder. It lists ALL branches (from `tblcustomers_groups`).
// But the View has a Branch filter.
// The user might expect the report to filter by branch if selected.
// I will inspect the main query again.
// The main query starts with `SELECT * FROM ( SELECT final.branch_label ...`.
// It selects from `tblcustomers_groups cg`.
// If I need to filter by branch, I should probably add a WHERE clause to the outer query or filter `tblcustomers_groups`.
// BUT, the user's prompt said "replace with below report". I should stick to their SQL. 
// If their SQL doesn't have the filter, maybe they want it that way, or I should inject it.
// The prompt says: "Hi, kindly in this same report first delete or replace with below report ... SELECT * FROM ..."
// I will use their SQL exactly as provided, replacing the specific placeholders they used: `{{filter:date_from}}`, `{{filter:date_to}}`, `{{filter:currency}}`.

// For the Sub-Page query, they used `{{filter:branch_id}}`, `{{filter:metric}}`.

$currency_sql = $currency ? "'" . $CI->db->escape_str($currency) . "'" : "NULL";

// Generate Branch Filter SQL for both queries
$branch_filter_sql = '';
if (!empty($branch_id)) {
    if (is_array($branch_id)) {
        // clean array
        $branch_id = array_filter($branch_id, function($id) { return is_numeric($id); });
        $branch_id = array_map('intval', $branch_id);
    } else {
        $branch_id = urldecode($branch_id);
        $branch_id = explode(',', $branch_id);
        $branch_id = array_filter($branch_id, function($id) { return is_numeric($id) && $id !== ''; });
        $branch_id = array_map('intval', $branch_id);
    }

    if (!empty($branch_id)) {
        $branch_filter_sql = ' AND cg.id IN (' . implode(',', $branch_id) . ') ';
    }


// Branch filter for invoice maps
$branch_filter_map = '';
// Branch filter for appointments
$branch_filter_appt = '';

if (!empty($branch_id)) {
    $branch_filter_map = ' AND map.groupid IN (' . implode(',', $branch_id) . ') ';
    $branch_filter_appt = ' AND a.branch_id IN (' . implode(',', $branch_id) . ') ';
}

}


$page = $CI->input->get('page');

if ($page === 'sub') {
    // Sub-Page Logic
    $filters_sub = $CI->input->get('filters_sub');
    $sub_branch_id = isset($filters_sub['branch_id']) ? $CI->db->escape_str($filters_sub['branch_id']) : 'all';
    $metric = isset($filters_sub['metric']) ? $CI->db->escape_str($filters_sub['metric']) : '';

    // Date filters for sub-page might come from query string or main filter.
    // The links in main report use `filters_sub[date_from]`.
    $sub_date_from = isset($filters_sub['date_from']) ? "'" . $CI->db->escape_str($filters_sub['date_from']) . "'" : "NULL";
    $sub_date_to = isset($filters_sub['date_to']) ? "'" . $CI->db->escape_str($filters_sub['date_to']) . "'" : "NULL";

    if ($sub_branch_id !== 'all' && !is_numeric($sub_branch_id)) {
        $sub_branch_id = 'all'; // Safety fallback
    }

    // We need to quote the branch_id if it's a specific number, but the query uses `map.groupid = {{filter:branch_id}}`.
    // If it's 'all', the query says `{{filter:branch_id}} = 'all'`.
    // So if $sub_branch_id is numeric, we treat it as value.
    // The query logic: `({{filter:branch_id}} = 'all' OR map.groupid = {{filter:branch_id}} ...)`
    // So we pass the value as a string: "'all'" or "'1'".
    $sub_branch_id_val = "'" . $sub_branch_id . "'";
    $metric_val = "'" . $metric . "'";

    // Sub-Page Logic
    $sql = "
    SELECT 
        1 AS `S.No`,
        c.userid AS `Patient ID`,
        CONCAT(c.firstname, ' ', c.lastname) AS `Patient Name`,
        c.phonenumber AS `Mobile`,
        'Category' AS `Category`,
        'Amount' AS `Amount`
    FROM tblclients c
    WHERE 1=1
    ";

    // Reconstruct the user's SUB query
    // The user provided query:
    /*
    SELECT 
    ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS `S.No`,
    ...
    */

    // I need to be careful with the user's specific sub-query which uses detailed logic for each metric.
    // The user provided many IF/ELSE blocks or a big CASE statement presumably?
    // Actually, the user provided a "Sub Page Query" in the prompt (which I need to retrieve from context if I lost it, but I have it in my 'context' or 'clipboard').
    // Wait, I need to check the User's prompt again for the Sub Page Query.
    // The user provided: "Sub-Page Query: ..."
    // It has `CASE WHEN '{{filter:metric}}' = 'gt' THEN ...`

    $sql = "
    SELECT 
        ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS `S.No`,
        final.userid AS `Patient ID`,
        final.patient_name AS `Patient Name`,
        final.mobile AS `Mobile`,
        final.category AS `Category`,
        final.amount AS `Amount`
    FROM (
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'N/A' AS category,
            0 AS amount
        FROM tblclients c
        WHERE 1=0 -- Default empty if no metric match
        
        UNION ALL
        
        -- GT Metric
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'GT' AS category,
            SUM(pay.amount) AS amount
        FROM tblcustomer_groups map
        JOIN tblclients c ON c.userid = map.customer_id
        JOIN tblinvoices inv ON inv.clientid = map.customer_id
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'gt'
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
        GROUP BY c.userid
        
        UNION ALL
        
        -- PROG Metric (GT + Con Fee)
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'PROG' AS category,
            SUM(pay.amount) AS amount
        FROM tblcustomer_groups map
        JOIN tblclients c ON c.userid = map.customer_id
        JOIN tblinvoices inv ON inv.clientid = map.customer_id
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'prog'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL

        -- NP Visit
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'NP Visit' AS category,
            0 AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        WHERE $metric_val = 'np_visit'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
        GROUP BY c.userid
        
        UNION ALL
        
        -- NP Reg
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'NP Registration' AS category,
            0 AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'np_reg'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Con Fee
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Consultation Fee' AS category,
            SUM(pay.amount) AS amount
        FROM tblcustomer_groups map
        JOIN tblclients c ON c.userid = map.customer_id
        JOIN tblinvoices inv ON inv.clientid = map.customer_id
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE $metric_val = 'con_fee'
          AND item.description = 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- NP Paid
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'NP Paid' AS category,
            SUM(pay.amount) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE $metric_val = 'np_paid'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Enquiry GT (Projection)
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Enquiry Projection' AS category,
            SUM(inv.total) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'enq_gt'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Enquiry Due
        -- (GT - Paid) logic handled by showing Due Amount?
        -- For simplicity, listing based on Projection but Amount = Due
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Enquiry Due' AS category,
            (SUM(inv.total) - IFNULL(SUM(pay.amount), 0)) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE $metric_val = 'enq_due'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (18, 2)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid
        HAVING amount > 0

        UNION ALL
        
        -- Renewal Visits
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Renewal Visit' AS category,
            0 AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        WHERE $metric_val = 'ren_visited'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
        GROUP BY c.userid

        UNION ALL
        
        -- Renewal Registered
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Renewal Registration' AS category,
            0 AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'ren_registered'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Renewal Paid
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Renewal Paid' AS category,
            SUM(pay.amount) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE $metric_val = 'ren_paid'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Renewal GT
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Renewal Projection' AS category,
            SUM(inv.total) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        WHERE $metric_val = 'ren_gt'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid

        UNION ALL
        
        -- Renewal Due
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Renewal Due' AS category,
            (SUM(inv.total) - IFNULL(SUM(pay.amount), 0)) AS amount
        FROM tblappointment a
        JOIN tblclients c ON c.userid = a.userid
        JOIN tblinvoices inv ON inv.clientid = c.userid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        WHERE $metric_val = 'ren_due'
          AND a.visit_status = 1
          AND a.appointment_type_id IN (6, 11, 17, 24, 32)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
        GROUP BY c.userid
        HAVING amount > 0
        
        UNION ALL
        
        -- Referral Visits
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Referral Visit' AS category,
            0 AS amount
        FROM tblleads l
        JOIN tblclients c ON c.leadid = l.id
        JOIN tblappointment a ON a.userid = c.userid
        WHERE $metric_val = 'ref_visited'
          AND l.refer_id > 0
          AND a.visit_status = 1
          AND ($sub_branch_id_val = 'all' OR a.branch_id = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR a.appointment_date >= CONCAT($sub_date_from, ' 00:00:00'))
          AND ($sub_date_to IS NULL OR a.appointment_date <= CONCAT($sub_date_to, ' 23:59:59'))
        GROUP BY c.userid
        
        UNION ALL
        
        -- Referral Reg
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Referral Registration' AS category,
            0 AS amount
        FROM tblinvoices inv
        JOIN tblclients c ON c.userid = inv.clientid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        LEFT JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE $metric_val = 'ref_reg'
          -- Referral Registration Logic (same as main query but ensuring Invoice check)
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          -- Add filtering for Referral
          AND EXISTS (SELECT 1 FROM tblleads l WHERE l.id = c.leadid AND l.refer_id > 0)
        GROUP BY c.userid
        
        UNION ALL
        
        -- Referral Paid
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Referral Paid' AS category,
            SUM(pay.amount) AS amount
        FROM tblinvoices inv
        JOIN tblclients c ON c.userid = inv.clientid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        LEFT JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE $metric_val = 'ref_paid'
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND ($sub_date_from IS NULL OR pay.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR pay.date <= $sub_date_to)
          AND EXISTS (SELECT 1 FROM tblleads l WHERE l.id = c.leadid AND l.refer_id > 0)
        GROUP BY c.userid
        
        UNION ALL
        
        -- Referral GT
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Referral Projection' AS category,
            SUM(inv.total) AS amount
        FROM tblinvoices inv
        JOIN tblclients c ON c.userid = inv.clientid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE $metric_val = 'ref_gt'
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND EXISTS (SELECT 1 FROM tblleads l WHERE l.id = c.leadid AND l.refer_id > 0)
        GROUP BY c.userid
        
        UNION ALL
        
        -- Referral Due
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Referral Due' AS category,
            (SUM(inv.total) - IFNULL(SUM(pay.amount), 0)) AS amount
        FROM tblinvoices inv
        JOIN tblclients c ON c.userid = inv.clientid
        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
        LEFT JOIN tblcustomer_groups map ON map.customer_id = inv.clientid
        WHERE $metric_val = 'ref_due'
          AND item.description <> 'Consultation Fee'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR inv.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR inv.date <= $sub_date_to)
          AND EXISTS (SELECT 1 FROM tblleads l WHERE l.id = c.leadid AND l.refer_id > 0)
        GROUP BY c.userid
        HAVING amount > 0
        
        UNION ALL
        
        -- Refund Amount
        SELECT 
            c.userid,
            CONCAT(c.firstname, ' ', c.lastname) AS patient_name,
            c.phonenumber AS mobile,
            'Refund' AS category,
            SUM(cn.amount) AS amount
        FROM tblcreditnotes cn
        JOIN tblclients c ON c.userid = cn.clientid
        LEFT JOIN tblcustomer_groups map ON map.customer_id = cn.clientid
        WHERE $metric_val = 'refund_amount'
          AND ($sub_branch_id_val = 'all' OR map.groupid = $sub_branch_id_val)
          AND ($sub_date_from IS NULL OR cn.date >= $sub_date_from)
          AND ($sub_date_to IS NULL OR cn.date <= $sub_date_to)
        GROUP BY c.userid
        
    ) final
    ";

} else {
    // Main Report Logic

    // Main Report Logic
    $sql = "
        SELECT *
        FROM (
            SELECT
                final.branch_label AS `Branch`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=gt&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.gt, 0), 0) AS SIGNED), '</a>') AS `GT`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=prog&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.prog, 0), 0) AS SIGNED), '</a>') AS `PROG`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=np_visit&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.np_visit, 0), 0) AS SIGNED), '</a>') AS `NP Visit`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=np_reg&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.np_reg, 0), 0) AS SIGNED), '</a>') AS `NP Registration`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=reg_percent&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.reg_percent, 0), 0) AS SIGNED), '</a>') AS `Registration %`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=con_fee&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.con_fee, 0), 0) AS SIGNED), '</a>') AS `Consultation Fee`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=np_paid&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.np_paid, 0), 0) AS SIGNED), '</a>') AS `NP Paid`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=enq_gt&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.enq_gt, 0), 0) AS SIGNED), '</a>') AS `Enquiry Projection`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=enq_due&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.enq_due, 0), 0) AS SIGNED), '</a>') AS `Enquiry Due`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=enq_tv&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.enq_tv, 0), 0) AS SIGNED), '</a>') AS `Enquiry Ticket Value`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_visited&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_visited, 0), 0) AS SIGNED), '</a>') AS `Renewal Visits`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_registered&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_registered, 0), 0) AS SIGNED), '</a>') AS `Renewals`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_percent&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_percent, 0), 0) AS SIGNED), '</a>') AS `Renewal %`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_paid&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_paid, 0), 0) AS SIGNED), '</a>') AS `Renewal Paid`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_due&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_due, 0), 0) AS SIGNED), '</a>') AS `Renewal Due`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_gt&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_gt, 0), 0) AS SIGNED), '</a>') AS `Renewal Projection`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ren_tv&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ren_tv, 0), 0) AS SIGNED), '</a>') AS `Renewal Ticket Value`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_visited&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_visited, 0), 0) AS SIGNED), '</a>') AS `Referral Visits`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_reg&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_reg, 0), 0) AS SIGNED), '</a>') AS `Referral Registrations`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_percent&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_percent, 0), 0) AS SIGNED), '</a>') AS `Referral %`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_paid&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_paid, 0), 0) AS SIGNED), '</a>') AS `Referral Paid`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_due&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_due, 0), 0) AS SIGNED), '</a>') AS `Referral Due`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_gt&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_gt, 0), 0) AS SIGNED), '</a>') AS `Referral Projection`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=ref_tv&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.ref_tv, 0), 0) AS SIGNED), '</a>') AS `Referral Ticket Value`,
                CONCAT('<a href=\"?page=sub&filters_sub[branch_id]=', final.branch_id, '&filters_sub[metric]=refund_amount&filters_sub[date_from]=', $from_date_sql, '&filters_sub[date_to]=', $to_date_sql, '\">', CAST(ROUND(IFNULL(final.refund_amount, 0), 0) AS SIGNED), '</a>') AS `Refund Amount`,
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
                    WHERE 1=1 " . $branch_filter_sql . "
                    LEFT JOIN (
                        SELECT map.groupid AS branch_id, SUM(pay.amount) AS gt
                        FROM tblcustomer_groups map
                        JOIN tblinvoices inv ON inv.clientid = map.customer_id
                        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        " . $branch_filter_map . "
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
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        " . $branch_filter_map . "
                        GROUP BY map.groupid
                    ) con_fee_table ON con_fee_table.branch_id = cg.id
                    LEFT JOIN (
                        SELECT a.branch_id, COUNT(*) AS np_visit
                        FROM tblappointment a
                        WHERE a.visit_status = 1
                          AND a.appointment_type_id IN (18, 2)
                          AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                        " . $branch_filter_appt . "
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
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                              AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
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
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                              AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
                        ) sub
                        JOIN tblinvoices inv ON inv.clientid = sub.userid
                        JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
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
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                        ) sub
                        JOIN tblinvoices inv ON inv.clientid = sub.userid
                        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
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
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                        " . $branch_filter_appt . "
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
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
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
                              AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
                        ) visit
                        JOIN tblinvoices inv ON inv.clientid = visit.userid
                        LEFT JOIN tblinvoicepaymentrecords pay ON pay.invoiceid = inv.id
                        JOIN tblitemable item ON item.rel_id = inv.id AND item.rel_type = 'invoice'
                        WHERE item.description <> 'Consultation Fee'
                          AND ($from_date_sql IS NULL OR inv.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
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
                            WHERE l.refer_id > 0 " . $branch_filter_map . "
                        ) rc
                        JOIN tblappointment a ON a.userid = rc.userid
                        WHERE a.visit_status = 1
                          AND ($from_date_sql IS NULL OR a.appointment_date >= CONCAT($from_date_sql, ' 00:00:00'))
                          AND ($to_date_sql IS NULL OR a.appointment_date <= CONCAT($to_date_sql, ' 23:59:59')) " . $branch_filter_appt . "
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
                              AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
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
                          AND ($to_date_sql IS NULL OR inv.date <= $to_date_sql) " . $branch_filter_map . "
                          AND ($from_date_sql IS NULL OR pay.date >= $from_date_sql)
                          AND ($to_date_sql IS NULL OR pay.date <= $to_date_sql)
                        " . $branch_filter_map . "
                        GROUP BY map.groupid
                    ) ref_money_table ON ref_money_table.branch_id = cg.id
                ) metrics
                LEFT JOIN (
                    SELECT cg.id AS branch_id, COALESCE(SUM(pr.amount), 0) AS payments_received
                    FROM tblcustomers_groups cg
                    WHERE 1=1 " . $branch_filter_sql . "
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
}

$output = [
    'data' => []
];

$result = $CI->db->query($sql)->result_array();

// Format data for DataTable
if ($page === 'sub') {
    foreach ($result as $row) {
        $output['data'][] = [
            $row['S.No'],
            $row['Patient ID'],
            $row['Patient Name'],
            $row['Mobile'],
            $row['Category'],
            $row['Amount'],
        ];
    }
} else {
    foreach ($result as $row) {
        $output['data'][] = [
            $row['Branch'],
            $row['GT'],
            $row['PROG'],
            $row['NP Visit'],
            $row['NP Registration'],
            $row['Registration %'],
            $row['Consultation Fee'],
            $row['NP Paid'],
            $row['Enquiry Projection'],
            $row['Enquiry Due'],
            $row['Enquiry Ticket Value'],
            $row['Renewal Visits'],
            $row['Renewals'],
            $row['Renewal %'],
            $row['Renewal Paid'],
            $row['Renewal Due'],
            $row['Renewal Projection'],
            $row['Renewal Ticket Value'],
            $row['Referral Visits'],
            $row['Referral Registrations'],
            $row['Referral %'],
            $row['Referral Paid'],
            $row['Referral Due'],
            $row['Referral Projection'],
            $row['Referral Ticket Value'],
            $row['Refund Amount'],
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($output);
exit;