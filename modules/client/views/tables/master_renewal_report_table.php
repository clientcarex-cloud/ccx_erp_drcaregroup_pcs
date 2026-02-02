<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->database();

// Get date filters from POST data or set defaults
$from_date = !empty($consulted_from_date) ? to_sql_date($consulted_from_date) : '2011-01-01';
$to_date = !empty($consulted_to_date) ? to_sql_date($consulted_to_date) : date('Y-m-d'); // Default to today if not set? Or end of month?

$data = [];
$totals = [
    'total_renewals' => 0,
    'active_renewals' => 0,
    'inactive_renewals' => 0,
    'acute_renewals' => 0,
    'package_amount' => 0,
    'visited_clients' => 0,
    'reg_clients' => 0,

    // RY (Renewal) - Past
    'ry_package_amount' => 0,
    'ry_paid_amount' => 0,
    'ry_due_amount' => 0,
    'ry_tv' => 0,
    'ry_reg' => 0,

    // RY Due (To Be Renewal) - Future
    'ry_due_package_amount' => 0,
    'ry_due_paid_amount' => 0,
    'ry_due_due_amount' => 0,
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
    $b_total_renewals = 0;
    $b_active_renewals = 0;
    $b_inactive_renewals = 0;
    $b_acute_renewals = 0;
    $b_package_amount = 0.00;
    $b_visited_clients = 0;
    $b_reg_clients = 0;

    $b_ry_package_amount = 0.00;
    $b_ry_paid_amount = 0.00;
    $b_ry_due_amount = 0.00;
    $b_ry_tv = 0;
    $b_ry_reg = 0;

    $b_ry_due_package_amount = 0.00;
    $b_ry_due_paid_amount = 0.00;
    $b_ry_due_due_amount = 0.00;


    if (!empty($customer_ids)) {

        // --- Step 1: Find previous invoice details for clients
        $subquery_prev_inv = $CI->db->select('inv_prev.clientid, MAX(inv_prev.duedate) AS previous_duedate')
            ->from(db_prefix() . 'invoices as inv_prev')
            ->join(db_prefix() . 'itemable as item_prev', 'item_prev.rel_id = inv_prev.id AND item_prev.rel_type = "invoice"')
            ->where('inv_prev.date <', $from_date)
            ->where('item_prev.description !=', 'Consultation Fee')
            ->where_in('inv_prev.clientid', $customer_ids)
            ->group_by('inv_prev.clientid')
            ->get_compiled_select();

        // --- Step 2: Get all renewal invoices for the branch in date range
        $CI->db->select('inv.id, inv.clientid, inv.date, inv.duedate, inv.total')
            ->from(db_prefix() . 'invoices as inv')
            ->join('(' . $subquery_prev_inv . ') AS T_prev', 'T_prev.clientid = inv.clientid', 'inner')
            ->join(db_prefix() . 'itemable as item', 'item.rel_id = inv.id AND item.rel_type = "invoice"')
            ->where('inv.date >=', $from_date)
            ->where('inv.date <=', $to_date)
            ->where('item.description !=', 'Consultation Fee')
            ->where_in('inv.clientid', $customer_ids);

        // Include previous duedate in selection for active/inactive calc
        $CI->db->select('T_prev.previous_duedate');

        $renewals = $CI->db->get()->result_array();

        // Identify all unique client IDs involved
        $renewal_client_ids = array_unique(array_column($renewals, 'clientid'));

        // --- Step 3: Get Payments for these invoices
        $invoice_ids = array_column($renewals, 'id');
        $payments = [];
        if (!empty($invoice_ids)) {
            $CI->db->select('invoiceid, SUM(amount) as amount');
            $CI->db->from(db_prefix() . 'invoicepaymentrecords');
            $CI->db->where_in('invoiceid', $invoice_ids);
            $CI->db->group_by('invoiceid');
            $payments_res = $CI->db->get()->result_array();
            foreach ($payments_res as $p) {
                $payments[$p['invoiceid']] = $p['amount'];
            }
        }

        // --- Step 4: Visits & Reg
        // Visited: Has appointment visit_status=1 in range
        $visited_client_ids = [];
        if (!empty($renewal_client_ids)) {
            $visit_query = $CI->db->select('userid')
                ->from(db_prefix() . 'appointment')
                ->where('visit_status', 1)
                ->where_in('userid', $renewal_client_ids)
                ->where('branch_id', $branch_id)
                ->where('appointment_date >=', $from_date . ' 00:00:00')
                ->where('appointment_date <=', $to_date . ' 23:59:00')
                ->get();
            $visited_client_ids = array_column($visit_query->result_array(), 'userid');
        }
        $b_visited_clients = count($visited_client_ids);

        // Reg: Renewal Clients who are also Visited? OR Registered in general?
        // Based on "Reg" usually meaning "Registered MR No" or "Conversion", but image shows Reg count ~ Visited.
        // Image A10: Visited 0, Reg 1.
        // Image A14: Visited 1, Reg 3.
        // Just assuming Reg = Intersection for now, or maybe check 'mr_no' exists?
        // Let's use Intersection(Renewal, Visited) as 'Reg'?
        // BUT A10: Visited 0, Reg 1 -> Impossible if intersection.
        // Maybe "Visited" = Appt Visited. "Reg" = Patient Registered (mr_no != '').
        // Let's check mr_no for renewal clients.
        $reg_client_ids = [];
        if (!empty($renewal_client_ids)) {
            $CI->db->select('userid');
            $CI->db->from(db_prefix() . 'clients_new_fields');
            $CI->db->where_in('userid', $renewal_client_ids);
            $CI->db->where("mr_no IS NOT NULL AND mr_no != ''");
            $reg_res = $CI->db->get()->result_array();
            $reg_client_ids = array_column($reg_res, 'userid');
        }
        $b_reg_clients = count($reg_client_ids);


        // --- Step 5: Process each renewal invoice
        $processed_clients = [];
        // Note: Total Renewals is count of clients or invoices? 
        // renewal_report_table used COUNT(DISTINCT clientid).
        // Let's stick to Client Count for main columns, but sums for amounts.

        $today = date('Y-m-d');

        foreach ($renewals as $inv) {
            $inv_id = $inv['id'];
            $client_id = $inv['clientid'];
            $pkg_amount = (float) $inv['total'];
            $paid = (float) ($payments[$inv_id] ?? 0);
            $due = $pkg_amount - $paid;

            // Logic for Acute
            // DATEDIFF(duedate, date) < 45
            $is_acute = false;
            $start = strtotime($inv['date']);
            $end = strtotime($inv['duedate']);
            if ($end > $start) {
                $days = ($end - $start) / (60 * 60 * 24);
                if ($days < 45) {
                    $is_acute = true;
                }
            }
            if ($is_acute) {
                $b_acute_renewals++; // This might overcount if client has multiple? Assuming 1 per period usually.
            }

            // Logic for active/inactive (Timely vs Late)
            $is_active = ($inv['date'] <= $inv['previous_duedate']);
            if ($is_active) {
                if (!in_array($client_id, $processed_clients))
                    $b_active_renewals++;
            } else {
                if (!in_array($client_id, $processed_clients))
                    $b_inactive_renewals++;
            }

            // Add to processed to avoid double counting active/inactive per client? 
            // Actually, if a client has multiple, we count them once?
            // "Total Renewals" = distinct clients.
            // If client has 1 active and 1 inactive invoice?
            // Let's just increment based on invoices if Total Renewals is invoices?
            // Standard is usually Clients.
            // But sums are absolute.

            // Using ID check to only count 1 per client for Active/Inactive calc?
            // Just counting invoices for Active/Inactive totals to align with sums?
            // However, Image A10 Total 14 = 13(Active) + 1(Inactive).
            // Let's assume unique clients for Total.
            // For Active/Inactive, let's count invoices (which usually maps 1-1).

            $b_package_amount += $pkg_amount;

            // RY vs RY Due
            // RY (Renewal - Past): duedate < today
            if ($inv['duedate'] < $today) {
                $b_ry_package_amount += $pkg_amount;
                $b_ry_paid_amount += $paid;
                $b_ry_due_amount += $due;
                $b_ry_tv++; // Invoice count
                if (in_array($client_id, $reg_client_ids))
                    $b_ry_reg++; // Registered count in this bucket
            }
            // RY Due (To Be Renewal - Future): duedate >= today
            else {
                $b_ry_due_package_amount += $pkg_amount;
                $b_ry_due_paid_amount += $paid;
                $b_ry_due_due_amount += $due;
            }

            $processed_clients[] = $client_id;
        }

        $b_total_renewals = count(array_unique($processed_clients));

    }

    // Calculations
    $b_visited_percent = ($b_total_renewals > 0) ? ($b_visited_clients / $b_total_renewals * 100) : 0;
    $b_reg_percent = ($b_visited_clients > 0) ? ($b_reg_clients / $b_visited_clients * 100) : 0; // Reg/Visited ?? Or Reg/Total?
    // Image: Visited 0, Reg 1. Reg% ? 
    // If Reg can be > Visited, then Reg/Visited doesn't make sense.
    // Maybe Reg% = Reg / Total?
    // Let's just display number for now, allow % to be 0 if div/0.

    // Update Totals
    $totals['total_renewals'] += $b_total_renewals;
    $totals['active_renewals'] += $b_active_renewals;
    $totals['inactive_renewals'] += $b_inactive_renewals;
    $totals['acute_renewals'] += $b_acute_renewals;
    $totals['package_amount'] += $b_package_amount;
    $totals['visited_clients'] += $b_visited_clients;
    $totals['reg_clients'] += $b_reg_clients;

    $totals['ry_package_amount'] += $b_ry_package_amount;
    $totals['ry_paid_amount'] += $b_ry_paid_amount;
    $totals['ry_due_amount'] += $b_ry_due_amount;
    $totals['ry_tv'] += $b_ry_tv;
    $totals['ry_reg'] += $b_ry_reg;

    $totals['ry_due_package_amount'] += $b_ry_due_package_amount;
    $totals['ry_due_paid_amount'] += $b_ry_due_paid_amount;
    $totals['ry_due_due_amount'] += $b_ry_due_due_amount;


    // Add branch row
    $data[] = [
        $branch_name,
        $b_total_renewals,
        $b_active_renewals,
        $b_inactive_renewals,
        $b_acute_renewals,
        number_format($b_package_amount, 2),
        $b_visited_clients,
        number_format(($b_total_renewals > 0 ? $b_visited_clients / $b_total_renewals * 100 : 0), 2) . '%',
        $b_reg_clients,
        number_format(($b_total_renewals > 0 ? $b_reg_clients / $b_total_renewals * 100 : 0), 2) . '%', // Using Reg/Total for now

        // RY
        number_format($b_ry_package_amount, 2),
        number_format($b_ry_paid_amount, 2),
        number_format($b_ry_due_amount, 2),
        $b_ry_tv,
        $b_ry_reg,

        // Pending % (Placeholder 0 for now)
        '0%',

        // RY Due
        number_format($b_ry_due_package_amount, 2),
        number_format($b_ry_due_paid_amount, 2),
        number_format($b_ry_due_due_amount, 2),
    ];
}

// Add totals row
$data[] = [
    '<b>Totals</b>',
    '<b>' . $totals['total_renewals'] . '</b>',
    '<b>' . $totals['active_renewals'] . '</b>',
    '<b>' . $totals['inactive_renewals'] . '</b>',
    '<b>' . $totals['acute_renewals'] . '</b>',
    '<b>' . number_format($totals['package_amount'], 2) . '</b>',
    '<b>' . $totals['visited_clients'] . '</b>',
    '', // percent
    '<b>' . $totals['reg_clients'] . '</b>',
    '', // percent

    '<b>' . number_format($totals['ry_package_amount'], 2) . '</b>',
    '<b>' . number_format($totals['ry_paid_amount'], 2) . '</b>',
    '<b>' . number_format($totals['ry_due_amount'], 2) . '</b>',
    '<b>' . $totals['ry_tv'] . '</b>',
    '<b>' . $totals['ry_reg'] . '</b>',

    '', // percent

    '<b>' . number_format($totals['ry_due_package_amount'], 2) . '</b>',
    '<b>' . number_format($totals['ry_due_paid_amount'], 2) . '</b>',
    '<b>' . number_format($totals['ry_due_due_amount'], 2) . '</b>',
];

echo json_encode([
    'data' => $data,
]);

exit;
