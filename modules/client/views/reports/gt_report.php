<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }
    .metric-drilldown {
        color: #03a9f4;
        font-weight: bold;
        text-decoration: none;
    }
    .metric-drilldown:hover {
        color: #0288d1;
        text-decoration: underline;
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

                        // Main report columns (sub-page removed)
                        $columns = [
                            _l('branch'),
                            'GT',
                            'PROG',
                            'NP Visit',
                            'NP Registration',
                            'Registration %',
                            'Consultation Fee',
                            'NP Paid',
                            'Enquiry Projection',
                            'Enquiry Due',
                            'Enquiry Ticket Value',
                            'Renewal Visits',
                            'Renewals',
                            'Renewal %',
                            'Renewal Paid',
                            'Renewal Due',
                            'Renewal Projection',
                            'Renewal Ticket Value',
                            'Referral Visits',
                            'Referral Registrations',
                            'Referral %',
                            'Referral Paid',
                            'Referral Due',
                            'Referral Projection',
                            'Referral Ticket Value',
                            'Refund Amount',
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
<!-- Metric Details Modal -->
<div class="modal fade" id="metricDetailsModal" tabindex="-1" role="dialog" aria-labelledby="metricDetailsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="metricDetailsModalLabel">Patient Details</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="metricDetailsTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>MR No</th>
                                <th>Patient Name</th>
                                <th>Phone Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        // Main report: load only on form submit with branch validation
        var gtTableInitialized = false;
        var metricTable = null;

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
            var ajaxUrl = '<?= admin_url("client/reports/" . $type . "/1/") ?>' + from + '/' + to + '?' + branchParam;

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

        // Click listener for metric drilldown links in the datatable (and footer)
        $(document).on('click', '.metric-drilldown', function (e) {
            e.preventDefault();
            var metric = $(this).data('metric');
            var branchId = $(this).data('branch'); // Can be empty if clicked from Grand Total row
            var selectedBranches = $('[name="branch[]"]').val(); // Fallback for Grand Total row
            var fromDate = $('#consulted_date').val();
            var toDate = $('#consulted_to_date').val();
            var metricName = $(this).closest('td, th').index(); // Attempt to get column index for title

            // Update modal title
            var columnHeaders = $('.table-unit-gt-report thead th').map(function() { return $(this).text(); }).get();
            if(metricName >= 0 && columnHeaders[metricName]) {
                 $('#metricDetailsModalLabel').text('Patient Details - ' + columnHeaders[metricName]);
            } else {
                 $('#metricDetailsModalLabel').text('Patient Details');
            }

            // Show modal loading state
            $('#metricDetailsModal').modal('show');
            if (metricTable !== null) {
                metricTable.destroy();
                $('#metricDetailsTable tbody').empty();
            }

            $.post("<?= admin_url('client/get_gt_report_details') ?>", {
                metric: metric,
                branch_id: branchId,
                branches: selectedBranches,
                from_date: fromDate,
                to_date: toDate,
                "<?= $this->security->get_csrf_token_name() ?>": "<?= $this->security->get_csrf_hash() ?>"
            }, function (res) {
                var data = JSON.parse(res);
                metricTable = $('#metricDetailsTable').DataTable({
                    data: data.data,
                    destroy: true,
                    paging: true,
                    searching: true,
                    info: true
                });
            });
        });

    });

</script>

</body>

</html>