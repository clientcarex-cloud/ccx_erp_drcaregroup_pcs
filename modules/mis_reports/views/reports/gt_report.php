<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }
    /* Highlight pending/WIP columns (index 20-30) with amber background */
    .table-unit-gt-report thead th:nth-child(n+22),
    .table-unit-gt-report tbody td:nth-child(n+22),
    .table-unit-gt-report tfoot th:nth-child(n+22) {
        background-color: #fff3cd !important;
        color: #856404 !important;
    }
    /* Info icon styling */
    .col-info-icon {
        display: inline-block;
        margin-left: 4px;
        color: #84919d;
        font-size: 12px;
        cursor: help;
        vertical-align: middle;
    }
    .col-info-icon:hover {
        color: #3498db;
    }
    /* Custom tooltip styling */
    .tooltip-inner {
        max-width: 320px !important;
        text-align: left !important;
        font-size: 12px !important;
        padding: 8px 12px !important;
        line-height: 1.5 !important;
        background-color: #2c3e50 !important;
        border-radius: 6px !important;
    }
    .tooltip.in {
        opacity: 1 !important;
    }
</style>

<?php
// Note: removed sub-page mode. The report now only supports the main report with form filters.
?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l($title); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>

                        <!-- Filter Form -->
                        <div class="row">
                            <form method="post" id="unitGTForm">
                                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
                                    value="<?= $this->security->get_csrf_hash(); ?>" />

                                <div class="col-md-3">
                                    <?php
                                    $selected_branches = $this->input->post('branch') ?? (isset($branch_id) ? [$branch_id] : []);
                                    echo render_select(
                                        'branch[]',
                                        $branch,
                                        ['id', ['name']],
                                        '<span style="color:red;">*</span> ' . _l('lead_branch'),
                                        $selected_branches,
                                        [
                                            'multiple' => true,
                                            'data-actions-box' => true,
                                            'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                            'required' => 'required'
                                        ]
                                    );
                                    ?>
                                </div>

                                <div class="col-md-2">
                                    <label><?php echo _l('from_date'); ?></label>
                                    <input class="form-control" type="date" id="consulted_date" name="consulted_date"
                                        value="<?= html_escape(set_value('consulted_date') ?: date('Y-m-d')) ?>">
                                </div>

                                <div class="col-md-2">
                                    <label><?php echo _l('to_date'); ?></label>
                                    <input class="form-control" type="date" id="consulted_to_date"
                                        name="consulted_to_date"
                                        value="<?= html_escape(set_value('consulted_to_date') ?: date('Y-m-d')) ?>">
                                </div>

                                <div class="col-md-2">
                                    <br>
                                    <button type="submit" class="btn btn-success" style="margin-top: 5px;"
                                        id="searchAppointmentsBtn">Submit</button>
                                </div>
                            </form>
                        </div>

                        <br>

                        <!-- Table -->
                        <?php

                        defined('BASEPATH') or exit('No direct script access allowed');

                        // Helper to append info icon with tooltip
                        function gt_col($title, $formula) {
                            $escaped = htmlspecialchars($formula, ENT_QUOTES, 'UTF-8');
                            return $title . ' <i class="fa fa-info-circle col-info-icon" data-toggle="tooltip" data-placement="top" data-html="true" title="' . $escaped . '"></i>';
                        }

                        // Main report columns with formula tooltips
                        $columns = [
                            _l('branch'),
                            gt_col('GT Goal', 'Monthly GT target set in <b>Report Goals</b> settings for the selected branch.'),
                            gt_col('GT Achieved', 'Total paid amount from <b>all invoice payment records</b> within the selected date range for the branch.'),
                            gt_col('GT Achieved %', '<b>(GT Achieved ÷ GT Goal) × 100</b><br>Percentage of the GT target achieved.'),
                            gt_col('GT Projection', '<b>(GT Achieved ÷ Current Day) × Total Days in Month</b><br>Projected GT by end of month based on current run-rate.'),
                            gt_col('NP Visits', 'Count of <b>unique patients</b> with Appointment Type = <b>First Appointment</b> within the date range.<br>Excludes patients whose lead source is categorized as <b>Referral</b> in Report Goals settings.'),
                            gt_col('NP Registration', 'Count of <b>unique First Appointment patients</b> who have a non-Consultation-Fee package in their invoice.'),
                            gt_col('NP Reg %', '<b>(NP Registration ÷ NP Visits) × 100</b><br>Registration conversion rate for new patients.'),
                            gt_col('Enquiry Consultation Fee', 'Total paid amount for invoices with <b>Consultation Fee</b> package for <b>First Appointment</b> patients.'),
                            gt_col('NP Paid', 'Total paid amount for <b>First Appointment</b> patients, <b>excluding Consultation Fee</b>.'),
                            gt_col('NP Ticket Value', '<b>NP Paid ÷ NP Visits</b><br>Average revenue per new patient (excluding consultation fee).'),
                            gt_col('Enquiry Due Collected', 'Overdue payments collected from past enquiry patients.'),
                            gt_col('Enquiry GT', '<b>Enquiry Due Collected + NP Paid</b><br>Total enquiry revenue.'),
                            gt_col('Enquiry Achieved %', '<b>(Enquiry GT ÷ Enquiry Goal) × 100</b><br>Percentage of enquiry target achieved.'),
                            gt_col('Enquiry Goal', 'Monthly enquiry target set in <b>Report Goals</b> settings.'),
                            gt_col('Enquiry Projection', '<b>(Enquiry GT ÷ Current Day) × Total Days in Month</b><br>Projected enquiry revenue by end of month.'),
                            gt_col('Renewal Visits', 'Count of renewal/follow-up patient visits in the date range.'),
                            gt_col('Renewed', 'Count of patients who renewed their package.'),
                            gt_col('Renewed %', '<b>(Renewed ÷ Renewal Visits) × 100</b><br>Renewal conversion rate.'),
                            gt_col('Follow-up Consultation Fee', 'Total consultation fee paid by follow-up/renewal patients.'),
                            gt_col('Renewal Paid', 'Total package amount paid by renewal patients.'),
                            // ── Pending columns (highlighted amber) ──
                            gt_col('Renewal Due', 'Outstanding dues from renewal patients.'),
                            gt_col('Renewal Projection', '<b>(Renewal Paid ÷ Current Day) × Total Days</b><br>Projected renewal revenue.'),
                            gt_col('Renewal Ticket Value', '<b>Renewal Paid ÷ Renewal Visits</b><br>Average revenue per renewal patient.'),
                            gt_col('Referral Visits', 'Count of patients whose lead source is categorized as <b>Referral</b> in Report Goals settings.'),
                            gt_col('Referral Registrations', 'Referral patients who registered with a package.'),
                            gt_col('Referral %', '<b>(Referral Registrations ÷ Referral Visits) × 100</b>'),
                            gt_col('Referral Paid', 'Total paid by referral patients.'),
                            gt_col('Referral Due', 'Outstanding dues from referral patients.'),
                            gt_col('Referral Projection', '<b>(Referral Paid ÷ Current Day) × Total Days</b>'),
                            gt_col('Referral Ticket Value', '<b>Referral Paid ÷ Referral Visits</b>'),
                            gt_col('Refund Amount', 'Total refunds processed in the date range.'),
                        ];

                        echo render_datatable($columns, 'unit-gt-report');
                        ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function () {
        // Initialize tooltips on column info icons
        $('[data-toggle="tooltip"]').tooltip({ container: 'body', html: true });

        // Main report: load only on form submit with branch validation
        var gtTableInitialized = false;

        $('#unitGTForm').on('submit', function (e) {
            e.preventDefault();
            var branches = $('[name="branch[]"]').val();
            if (!branches || branches.length === 0) {
                alert_float('warning', 'Please select at least one branch.');
                return;
            }

            var from = $('#consulted_date').val();
            var to = $('#consulted_to_date').val();
            var branchParam = branches.map(function (b) { return 'branch[]=' + encodeURIComponent(b); }).join('&');
            var ajaxUrl = '<?= admin_url("mis_reports/reports/" . $type . "/1/") ?>' + from + '/' + to + '?' + branchParam;

            var tableSelector = '.table-unit-gt-report';
            var $table = $(tableSelector);

            if (gtTableInitialized && $.fn.DataTable.isDataTable(tableSelector)) {
                $table.DataTable().ajax.url(ajaxUrl).load();
            } else {
                // Ensure tfoot exists for the totals row before initializing Datatable
                if ($table.find('tfoot').length === 0) {
                    var tfootHtml = '<tfoot><tr>';
                    var numCols = $table.find('thead th').length;
                    for (var i = 0; i < numCols; i++) {
                        tfootHtml += '<th></th>';
                    }
                    tfootHtml += '</tr></tfoot>';
                    $table.append(tfootHtml);
                }

                initDataTable(tableSelector, ajaxUrl, [0], [0]);

                // Add an event listener to capture totals from the server response
                $table.on('xhr.dt', function (e, settings, json, xhr) {
                    if (json && json.totals) {
                        var $tfoot = $table.find('tfoot tr');
                        // Ensure we wait for the table draw to finish before manipulating the layout
                        setTimeout(function () {
                            $.each(json.totals, function (index, value) {
                                $tfoot.find('th').eq(index).html(value);
                            });
                        }, 100);
                    }
                });

                gtTableInitialized = true;
            }
        });
    });

</script>

</body>

</html>