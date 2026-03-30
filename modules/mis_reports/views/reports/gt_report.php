<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }
    /* Info icon styling */
    .col-info-icon {
        display: inline-block;
        margin-left: 4px;
        color: rgba(255,255,255,0.6);
        font-size: 12px;
        cursor: help;
        vertical-align: middle;
    }
    .col-info-icon:hover {
        color: #fff;
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

    /* ── Section Toggle Buttons ── */
    .gt-section-toggles {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 15px;
        align-items: center;
    }
    .gt-section-toggles .section-label {
        font-size: 13px;
        font-weight: 600;
        color: #555;
        margin-right: 6px;
    }
    .gt-section-btn {
        border: none;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s ease;
        letter-spacing: 0.3px;
        position: relative;
        outline: none;
    }
    .gt-section-btn .fa {
        margin-right: 5px;
        font-size: 11px;
    }
    .gt-section-btn.active {
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .gt-section-btn:not(.active) {
        opacity: 0.5;
        box-shadow: none;
    }
    /* Section colors */
    .gt-section-btn[data-section="gt"]       { background: #3498db; color: #fff; }
    .gt-section-btn[data-section="enquiry"]  { background: #2ecc71; color: #fff; }
    .gt-section-btn[data-section="renewal"]  { background: #e67e22; color: #fff; }
    .gt-section-btn[data-section="referral"] { background: #9b59b6; color: #fff; }
    .gt-section-btn[data-section="refund"]   { background: #e74c3c; color: #fff; }

    /* ── Color-coded column headers ── */
    .table-unit-gt-report thead th {
        white-space: nowrap;
        font-size: 12px;
        padding: 8px 10px !important;
    }
    /* GT columns (1-4) */
    .table-unit-gt-report thead th:nth-child(n+2):nth-child(-n+5) {
        background: #3498db !important;
        color: #fff !important;
    }
    /* Enquiry columns (5-15) → th 6 to 16 */
    .table-unit-gt-report thead th:nth-child(n+6):nth-child(-n+16) {
        background: #2ecc71 !important;
        color: #fff !important;
    }
    /* Renewal columns (16-26) → th 17 to 27 */
    .table-unit-gt-report thead th:nth-child(n+17):nth-child(-n+27) {
        background: #e67e22 !important;
        color: #fff !important;
    }
    /* Referral columns (27-36) → th 28 to 37 */
    .table-unit-gt-report thead th:nth-child(n+28):nth-child(-n+37) {
        background: #9b59b6 !important;
        color: #fff !important;
    }
    /* Refund column (37) → th 38 */
    .table-unit-gt-report thead th:nth-child(38) {
        background: #e74c3c !important;
        color: #fff !important;
    }

    /* Subtle row tinting for data cells */
    .table-unit-gt-report tbody td { font-size: 12px; }
    .table-unit-gt-report tfoot th { font-size: 12px; }
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

                        <!-- Section Toggle Buttons -->
                        <div class="gt-section-toggles">
                            <span class="section-label"><i class="fa fa-columns"></i> Sections:</span>
                            <button type="button" class="gt-section-btn active" data-section="gt">
                                <i class="fa fa-eye"></i> GT
                            </button>
                            <button type="button" class="gt-section-btn active" data-section="enquiry">
                                <i class="fa fa-eye"></i> Enquiry
                            </button>
                            <button type="button" class="gt-section-btn active" data-section="renewal">
                                <i class="fa fa-eye"></i> Renewal
                            </button>
                            <button type="button" class="gt-section-btn active" data-section="referral">
                                <i class="fa fa-eye"></i> Referral
                            </button>
                            <button type="button" class="gt-section-btn active" data-section="refund">
                                <i class="fa fa-eye"></i> Refund
                            </button>
                        </div>

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
                            // ── GT Section (cols 1-4) ──
                            gt_col('GT Goal', 'Monthly GT target set in <b>Report Goals</b> settings for the selected branch.'),
                            gt_col('GT Achieved', 'Total paid amount from <b>all invoice payment records</b> within the selected date range for the branch.'),
                            gt_col('GT Achieved %', '<b>(GT Achieved ÷ GT Goal) × 100</b><br>Percentage of the GT target achieved.'),
                            gt_col('GT Projection', '<b>(GT Achieved ÷ Current Day) × Total Days in Month</b><br>Projected GT by end of month based on current run-rate.'),
                            // ── Enquiry Section (cols 5-15) ──
                            gt_col('NP Visits', 'Count of <b>unique patients</b> with Appointment Type = <b>First Appointment</b> within the date range.<br>Excludes patients whose lead source is categorized as <b>Referral</b> in Report Goals.'),
                            gt_col('NP Registration', 'Count of <b>unique First Appointment patients</b> (excl referral) who have a non-Consultation-Fee package in their invoice.'),
                            gt_col('NP Reg %', '<b>(NP Registration ÷ NP Visits) × 100</b><br>Registration conversion rate for new patients.'),
                            gt_col('NP Paid', 'Total paid for <b>First Appointment</b> patients (excl referral), <b>excluding Consultation Fee</b>.'),
                            gt_col('NP Ticket Value', '<b>NP Paid ÷ NP Visits</b><br>Average revenue per new patient (excluding consultation fee).'),
                            gt_col('Enquiry Consultation Fee', 'Total <b>Consultation Fee</b> paid by <b>First Appointment</b> patients (excl referral sources).'),
                            gt_col('Enquiry Due Collected', 'Payments made in this period on <b>older invoices</b> (created before date range) for First Appointment patients.'),
                            gt_col('Enquiry Goal', 'Monthly enquiry target set in <b>Report Goals</b> settings.'),
                            gt_col('Enquiry GT', '<b>Enquiry Due Collected + NP Paid</b><br>Total enquiry revenue.'),
                            gt_col('Enquiry Achieved %', '<b>(Enquiry GT ÷ Enquiry Goal) × 100</b><br>Percentage of enquiry target achieved.'),
                            gt_col('Enquiry Projection', '<b>(Enquiry GT ÷ Current Day) × Total Days in Month</b><br>Projected enquiry revenue by end of month.'),
                            // ── Renewal Section (cols 16-26) ──
                            gt_col('Renewal Visits', 'Count of <b>unique patients</b> with <b>non-First-Appointment</b> (follow-up) visits in the date range.'),
                            gt_col('Renewed', 'Count of follow-up patients who have a <b>non-Consultation-Fee package</b> in their invoice.'),
                            gt_col('Renewed %', '<b>(Renewed ÷ Renewal Visits) × 100</b><br>Renewal conversion rate.'),
                            gt_col('Follow-up Consultation Fee', 'Total <b>Consultation Fee</b> paid by follow-up (non-First-Appointment) patients.'),
                            gt_col('Renewal Paid', 'Total paid by follow-up patients, <b>excluding Consultation Fee</b>.'),
                            gt_col('Renewal Due', 'Payments made in this period on <b>older invoices</b> for follow-up patients.'),
                            gt_col('Renewal Goal', 'Monthly renewal target set in <b>Report Goals</b> settings.'),
                            gt_col('Renewal GT', '<b>Renewal Due + Renewal Paid</b><br>Total renewal revenue.'),
                            gt_col('Renewal Achieved %', '<b>(Renewal GT ÷ Renewal Goal) × 100</b><br>Percentage of renewal target achieved.'),
                            gt_col('Renewal Projection', '<b>(Renewal GT ÷ Current Day) × Total Days</b><br>Projected renewal revenue by end of month.'),
                            gt_col('Renewal Ticket Value', '<b>Renewal Paid ÷ Renewal Visits</b><br>Average revenue per renewal patient.'),
                            // ── Referral Section (cols 27-36) ──
                            gt_col('Referral Visits', 'Count of <b>unique patients</b> with <b>First Appointment</b> whose lead source is categorized as <b>Referral</b> in Report Goals.'),
                            gt_col('Referral Registrations', 'Referral patients who have a <b>non-Consultation-Fee package</b> in their invoice.'),
                            gt_col('Referral %', '<b>(Referral Registrations ÷ Referral Visits) × 100</b><br>Referral registration conversion rate.'),
                            gt_col('Referral Paid', 'Total paid by referral patients, <b>excluding Consultation Fee</b>.'),
                            gt_col('Referral Due', 'Payments made in this period on <b>older invoices</b> for referral patients.'),
                            gt_col('Referral Goal', 'Monthly referral target set in <b>Report Goals</b> settings.'),
                            gt_col('Referral GT', '<b>Referral Due + Referral Paid</b><br>Total referral revenue.'),
                            gt_col('Referral Achieved %', '<b>(Referral GT ÷ Referral Goal) × 100</b><br>Percentage of referral target achieved.'),
                            gt_col('Referral Projection', '<b>(Referral GT ÷ Current Day) × Total Days</b><br>Projected referral revenue by end of month.'),
                            gt_col('Referral Ticket Value', '<b>Referral Paid ÷ Referral Visits</b><br>Average revenue per referral patient.'),
                            // ── Refund (col 37) ──
                            gt_col('Refund Amount', 'Total <b>credit note refunds</b> processed in the date range.'),
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

        // ── Section column index mapping (0-indexed) ──
        var sectionColumns = {
            gt:       [1, 2, 3, 4],
            enquiry:  [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            renewal:  [16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26],
            referral: [27, 28, 29, 30, 31, 32, 33, 34, 35, 36],
            refund:   [37]
        };

        // ── Section toggle button handler ──
        $('.gt-section-btn').on('click', function () {
            var $btn = $(this);
            var section = $btn.data('section');
            var isActive = $btn.hasClass('active');

            // Toggle state
            $btn.toggleClass('active');
            var icon = $btn.find('.fa');
            if ($btn.hasClass('active')) {
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }

            // Toggle columns if table is initialized
            var tableSelector = '.table-unit-gt-report';
            if ($.fn.DataTable.isDataTable(tableSelector)) {
                var dt = $(tableSelector).DataTable();
                var cols = sectionColumns[section];
                var show = $btn.hasClass('active');
                for (var i = 0; i < cols.length; i++) {
                    dt.column(cols[i]).visible(show);
                }
            }
        });

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

                // Apply initial section visibility based on button state
                setTimeout(function () {
                    $('.gt-section-btn').each(function () {
                        var $btn = $(this);
                        if (!$btn.hasClass('active')) {
                            var section = $btn.data('section');
                            var dt = $(tableSelector).DataTable();
                            var cols = sectionColumns[section];
                            for (var i = 0; i < cols.length; i++) {
                                dt.column(cols[i]).visible(false);
                            }
                        }
                    });
                }, 200);
            }
        });
    });

</script>

</body>

</html>