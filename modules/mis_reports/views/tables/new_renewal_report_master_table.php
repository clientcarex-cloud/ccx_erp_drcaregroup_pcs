<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

// Get date filters from POST data or set defaults
$from_date = !empty($consulted_from_date) ? to_sql_date($consulted_from_date) : date('Y-m-01');
$to_date = !empty($consulted_to_date) ? to_sql_date($consulted_to_date) : date('Y-m-d');

$data = [];
$totals = [
    'total_renewals' => 0,
    'active_renewals' => 0,
    'inactive_renewals' => 0,
    'renewals' => 0,
    'package_amount' => 0,
];

// Get all branches
$CI->db->select('id, name');
$branches = $CI->db->get(db_prefix() . 'customers_groups')->result_array();

foreach ($branches as $branch) {
    $branch_id = $branch['id'];
    $branch_name = $branch['name'];

    // Get customer IDs for the current branch
    $customer_ids = array_column(
        $CI->db->select('customer_id')
            ->where('groupid', $branch_id)
            ->get(db_prefix() . 'customer_groups')
            ->result_array(),
        'customer_id'
    );

    // Initialize branch values
    $total_renewals = 0;
    $active_renewals = 0;
    $inactive_renewals = 0;
    $renewals = 0;
    $package_amount = 0.00;

    if (!empty($customer_ids)) {

        // --- Step 1: Find previous invoice details for clients (invoices before the date range)
        $subquery_prev_inv = $CI->db->select('inv_prev.clientid, MAX(inv_prev.duedate) AS previous_duedate')
            ->from(db_prefix() . 'invoices as inv_prev')
            ->join(db_prefix() . 'itemable as item_prev', 'item_prev.rel_id = inv_prev.id AND item_prev.rel_type = "invoice"')
            ->where('inv_prev.date <', $from_date)
            ->where('item_prev.description !=', 'Consultation Fee')
            ->where_in('inv_prev.clientid', $customer_ids)
            ->group_by('inv_prev.clientid')
            ->get_compiled_select();

        // --- Step 2: Get client IDs with renewals within the date range
        $renewal_client_ids_query = $CI->db->select('DISTINCT inv.clientid', false)
            ->from(db_prefix() . 'invoices as inv')
            ->join('(' . $subquery_prev_inv . ') AS T_prev', 'T_prev.clientid = inv.clientid', 'inner')
            ->join(db_prefix() . 'itemable as item', 'item.rel_id = inv.id AND item.rel_type = "invoice"')
            ->where('inv.date >=', $from_date)
            ->where('inv.date <=', $to_date)
            ->where('item.description !=', 'Consultation Fee')
            ->where_in('inv.clientid', $customer_ids)
            ->get();

        $renewal_client_ids = array_column($renewal_client_ids_query->result_array(), 'clientid');

        if (!empty($renewal_client_ids)) {
            // --- Step 3: Get renewal summary (total, active, inactive, package amount)
            // Active = invoice date <= previous package duedate (renewed before expiry)
            // Inactive = invoice date > previous package duedate (renewed after expiry)
            $renewal_data_query = $CI->db->select('
                    COUNT(DISTINCT inv.clientid) AS total_renewals,
                    SUM(CASE WHEN inv.date <= T_prev.previous_duedate THEN 1 ELSE 0 END) AS active_renewals,
                    SUM(CASE WHEN inv.date > T_prev.previous_duedate THEN 1 ELSE 0 END) AS inactive_renewals,
                    SUM(inv.total) AS package_amount
                ')
                ->from(db_prefix() . 'invoices as inv')
                ->join('(' . $subquery_prev_inv . ') AS T_prev', 'T_prev.clientid = inv.clientid', 'inner')
                ->join(db_prefix() . 'itemable as item', 'item.rel_id = inv.id AND item.rel_type = "invoice"')
                ->where('inv.date >=', $from_date)
                ->where('inv.date <=', $to_date)
                ->where('item.description !=', 'Consultation Fee')
                ->where_in('inv.clientid', $renewal_client_ids)
                ->get();

            $renewal_data = $renewal_data_query->row();

            $total_renewals = (int) $renewal_data->total_renewals;
            $active_renewals = (int) $renewal_data->active_renewals;
            $inactive_renewals = (int) $renewal_data->inactive_renewals;
            $renewals = $active_renewals + $inactive_renewals;
            $package_amount = (float) $renewal_data->package_amount;
        }

        // Update totals
        $totals['total_renewals'] += $total_renewals;
        $totals['active_renewals'] += $active_renewals;
        $totals['inactive_renewals'] += $inactive_renewals;
        $totals['renewals'] += $renewals;
        $totals['package_amount'] += $package_amount;
    }

    // Add branch row
    $data[] = [
        $branch_name,
        $total_renewals,
        $active_renewals,
        $inactive_renewals,
        $renewals,
        number_format($package_amount, 2),
    ];
}

// Add totals row
$data[] = [
    '<b>Grand Total</b>',
    '<b>' . $totals['total_renewals'] . '</b>',
    '<b>' . $totals['active_renewals'] . '</b>',
    '<b>' . $totals['inactive_renewals'] . '</b>',
    '<b>' . $totals['renewals'] . '</b>',
    '<b>' . number_format($totals['package_amount'], 2) . '</b>',
];

// Final JSON
echo json_encode([
    'data' => $data,
]);

exit;
