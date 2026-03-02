<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// 1. Get active payment modes
$CI->db->select('id, name');
$CI->db->from(db_prefix() . 'payment_modes');
$CI->db->where('active', 1);
$payment_modes_result = $CI->db->get()->result_array();

$payment_modes = [];
$payment_mode_ids = [];
foreach ($payment_modes_result as $mode) {
    if (strtolower($mode['name']) != "free") {
        $payment_modes[] = $mode['name'];
        $payment_mode_ids[$mode['id']] = $mode['name'];
    }
}

// 2. Get all branches
$branches = $CI->db->get(db_prefix() . 'customers_groups')->result_array();

$data = [];
$grand_totals = array_fill_keys($payment_modes, 0);
$grand_total_payment = 0;

foreach ($branches as $branch) {
    $row = [];
    $row[] = $branch['name']; // Branch name

    // Initialize per-mode totals
    $totals = array_fill_keys($payment_modes, 0);
    $total_payment = 0;

    // 3. Get customers in this branch
    $CI->db->select('customer_id');
    $CI->db->from(db_prefix() . 'customer_groups');
    $CI->db->where('groupid', $branch['id']);
    $customer_ids = array_column($CI->db->get()->result_array(), 'customer_id');

    if (!empty($customer_ids)) {
        // 4. Get invoice IDs for these customers
        $CI->db->select('id');
        $CI->db->from(db_prefix() . 'invoices');
        $CI->db->where_in('clientid', $customer_ids);
        $invoice_ids = array_column($CI->db->get()->result_array(), 'id');

        if (!empty($invoice_ids)) {
            // 5. Get payments for these invoices, WITH date filters
            $CI->db->select('ip.amount, pm.name as payment_mode');
            $CI->db->from(db_prefix() . 'invoicepaymentrecords as ip');
            $CI->db->join(db_prefix() . 'payment_modes as pm', 'pm.id = ip.paymentmode', 'left');
            $CI->db->where_in('ip.invoiceid', $invoice_ids);
            $CI->db->where('ip.date >=', $consulted_from_date . ' 00:00:00');
            $CI->db->where('ip.date <=', $consulted_to_date . ' 23:59:59');

            $payments = $CI->db->get()->result_array();

            foreach ($payments as $payment) {
                $mode = $payment['payment_mode'];
                $amount = (float) $payment['amount'];

                if (isset($totals[$mode])) {
                    $totals[$mode] += $amount;
                }

                $total_payment += $amount;
            }
        }
    }

    // 6. Append all payment modes
    foreach ($payment_modes as $mode_name) {
        $row[] = app_format_money_custom($totals[$mode_name], 1);
        $grand_totals[$mode_name] += $totals[$mode_name];
    }

    // 7. Append total
    $row[] = app_format_money_custom($total_payment, 1);
    $grand_total_payment += $total_payment;

    $data[] = $row;
}

// 8. Grand Total row
$grand_row = ['<strong>Grand Total</strong>'];
foreach ($payment_modes as $mode_name) {
    $grand_row[] = '<strong>' . app_format_money_custom($grand_totals[$mode_name], 1) . '</strong>';
}
$grand_row[] = '<strong>' . app_format_money_custom($grand_total_payment, 1) . '</strong>';
$data[] = $grand_row;

// 9. Output JSON for DataTables
$output = [
    'draw' => intval($_POST['draw'] ?? 1),
    'recordsTotal' => count($data),
    'recordsFiltered' => count($data),
    'data' => $data
];

echo json_encode($output);
exit;
