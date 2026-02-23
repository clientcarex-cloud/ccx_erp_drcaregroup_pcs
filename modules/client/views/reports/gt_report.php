<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }
</style>

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
                                        'branch[]', // use [] to return array in POST
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

                        // Define columns with labels
                        $is_sub = $this->input->get('page') == 'sub';

                        if ($is_sub) {
                            $columns = [
                                'S.No',
                                'Patient ID',
                                'Patient Name',
                                'Mobile',
                                'Category',
                                'Amount'
                            ];
                        } else {
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
                        }

                        // Render the table
                        echo render_datatable($columns, 'unit-gt-report'); // .table-unit-gt-report
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
        var gtTableInitialized = false;
        var urlParams = new URLSearchParams(window.location.search);
        var isSubPage = urlParams.get('page') === 'sub';

        function reloadUnitGTReport() {
            var url = '<?= admin_url("client/reports/gt_report") ?>';

            if (isSubPage) {
                url += window.location.search;
            }

            var formData = $('#unitGTForm').serialize();

            if (gtTableInitialized && $.fn.DataTable.isDataTable('.table-unit-gt-report')) {
                var dt = $('.table-unit-gt-report').DataTable();
                dt.ajax.url(url + (url.indexOf('?') !== -1 ? '&' : '?') + formData).load();
            } else {
                initDataTable('.table-unit-gt-report', url + (url.indexOf('?') !== -1 ? '&' : '?') + formData, [0], [0]);
                gtTableInitialized = true;
            }
        }

        // Auto-load for sub-page (filters come from URL)
        if (isSubPage) {
            reloadUnitGTReport();
        }

        // On form submit only for main report
        $('#unitGTForm').on('submit', function (e) {
            e.preventDefault();
            var branches = $('[name="branch[]"]').val();
            if (!branches || branches.length === 0) {
                alert_float('warning', 'Please select at least one branch.');
                return;
            }
            reloadUnitGTReport();
        });
    });

</script>

</body>

</html>