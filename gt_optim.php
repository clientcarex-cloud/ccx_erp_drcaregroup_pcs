<?php

$original = file_get_contents('/Users/fahadahmed/CCX Dev/PCS_Autism/modules/client/views/tables/gt_report_table.php');

$top_addition = "
// Generate Branch Filter SQL for both queries
\$branch_filter_sql = '';
if (!empty(\$branch_id)) {
    if (is_array(\$branch_id)) {
        // clean array
        \$branch_id = array_filter(\$branch_id, function(\$id) { return is_numeric(\$id); });
        \$branch_id = array_map('intval', \$branch_id);
    } else {
        \$branch_id = urldecode(\$branch_id);
        \$branch_id = explode(',', \$branch_id);
        \$branch_id = array_filter(\$branch_id, function(\$id) { return is_numeric(\$id) && \$id !== ''; });
        \$branch_id = array_map('intval', \$branch_id);
    }

    if (!empty(\$branch_id)) {
        \$branch_filter_sql = ' AND cg.id IN (' . implode(',', \$branch_id) . ') ';
    }
}
";

// We insert it right after the $currency_sql
$original = str_replace(
    "\$currency_sql = \$currency ? \"'\" . \$CI->db->escape_str(\$currency) . \"'\" : \"NULL\";",
    "\$currency_sql = \$currency ? \"'\" . \$CI->db->escape_str(\$currency) . \"'\" : \"NULL\";\n" . $top_addition,
    $original
);

// We inject the branch filter into the outer WHERE of the main query
// The main query defines cg as tblcustomers_groups cg
$original = str_replace(
    "FROM tblcustomers_groups cg\n                    LEFT JOIN (",
    "FROM tblcustomers_groups cg\n                    LEFT JOIN (",
    $original
); // Ensure it's there

// Then we look for the end of the query: "WHERE\n            LENGTH(COALESCE(\$from_date_sql, '')) > 0"
$original = str_replace(
    "FROM tblcustomers_groups cg",
    "FROM tblcustomers_groups cg\n                    WHERE 1=1 '. \$branch_filter_sql . '",
    $original
);

file_put_contents('/Users/fahadahmed/CCX Dev/PCS_Autism/modules/client/views/tables/gt_report_table.php', $original);
echo "Injected branch filter into the base tblcustomer_groups table scan.\n";
