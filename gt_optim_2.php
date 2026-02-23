<?php

$original = file_get_contents('/Users/fahadahmed/CCX Dev/PCS_Autism/modules/client/views/tables/gt_report_table.php');

// Replace the string manipulation we just did with a properly formatted one
$original = str_replace(
    "FROM tblcustomers_groups cg\n                    WHERE 1=1 '. \$branch_filter_sql . '",
    "FROM tblcustomers_groups cg\n                    WHERE 1=1 \" . \$branch_filter_sql . \"",
    $original
);

file_put_contents('/Users/fahadahmed/CCX Dev/PCS_Autism/modules/client/views/tables/gt_report_table.php', $original);
echo "Fixed quotes for branch_filter_sql\n";
