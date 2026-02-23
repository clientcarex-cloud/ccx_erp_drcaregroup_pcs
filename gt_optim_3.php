<?php

$file = '/Users/fahadahmed/CCX Dev/PCS_Autism/modules/client/views/tables/gt_report_table.php';
$content = file_get_contents($file);

// 1. Inject Branch Filter into all Subqueries correctly
// In the PHP code, we add string replacements to inject the branch filter into all the subqueries.
$add_vars_code = "
// Branch filter for invoice maps
\$branch_filter_map = '';
// Branch filter for appointments
\$branch_filter_appt = '';

if (!empty(\$branch_id)) {
    \$branch_filter_map = ' AND map.groupid IN (' . implode(',', \$branch_id) . ') ';
    \$branch_filter_appt = ' AND a.branch_id IN (' . implode(',', \$branch_id) . ') ';
}
";

$content = str_replace("\$branch_filter_sql = ' AND cg.id IN (' . implode(',', \$branch_id) . ') ';\n    }",
"\$branch_filter_sql = ' AND cg.id IN (' . implode(',', \$branch_id) . ') ';\n    }\n\n" . $add_vars_code, $content);

// 2. We replace all `GROUP BY map.groupid` with $branch_filter_map injected before it
$content = preg_replace('/(\s+)GROUP BY map\.groupid/i', '$1" . $branch_filter_map . "$1GROUP BY map.groupid', $content);

// 3. We replace all `GROUP BY a.branch_id` with $branch_filter_appt injected before it
$content = preg_replace('/(\s+)GROUP BY a\.branch_id/i', '$1" . $branch_filter_appt . "$1GROUP BY a.branch_id', $content);

// 4. We replace all `GROUP BY sub.branch_id` with $branch_filter_appt in the inner query where a.branch_id is defined
// For instance: `AND (\$to_date_sql IS NULL OR a.appointment_date <= CONCAT(\$to_date_sql, ' 23:59:59'))`
$content = preg_replace('/(AND \(\$to_date_sql IS NULL OR a\.appointment_date <= CONCAT\(\$to_date_sql, \' 23:59:59\'\)\))/i', '$1 " . $branch_filter_appt . "', $content);

// 5. Special cases like refund_amount which uses tblcreditnotes cn and map.groupid
// Wait, the original subquery 1 didn't have refund amount join explicitly. Ah, the very first subquery is gt_table:
// SELECT map.groupid AS branch_id, SUM(pay.amount) AS gt ... GROUP BY map.groupid
// So step 2 covers GT, con_fee, ref_money_table, etc.

// 6. Subquery for ref_visited
$content = preg_replace('/(WHERE l\.refer_id > 0)/i', '$1 " . $branch_filter_map . "', $content);

// 7. Subquery for ref_reg_table
// SELECT DISTINCT map.groupid AS branch_id, inv.clientid ... WHERE item.description <> ... AND ...
$content = preg_replace('/(AND \(\$to_date_sql IS NULL OR inv\.date <= \$to_date_sql\))/i', '$1 " . $branch_filter_map . "', $content);
// We will replace all occurrences, but safely.

file_put_contents($file, $content);
echo "Injected branch variables across all JOIN subqueries for drastic performance boost.\n";
