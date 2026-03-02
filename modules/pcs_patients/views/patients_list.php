<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .tw-bg-white {
        --tw-bg-opacity: 1 !important;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-6" style="margin-top: 7px;">
                                    <h4 class="no-margin">
                                        <?= _l('patient_list'); ?>
                                    </h4>
                                </div>
                                <div class="col-md-6 text-right d-flex justify-content-end gap-2">
                                    <?php if (staff_can('create', 'customers')) { ?>
                                        <a href="<?= admin_url('client/client/add_client'); ?>" class="btn btn-primary">
                                            <i class="fa-regular fa-plus tw-mr-1"></i>
                                            <?= _l('new_client'); ?>
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <!-- ===== Filter Row (same pattern as MIS GT report) ===== -->
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <?php
                                $selected_branches = [];
                                echo render_select(
                                    'branch[]',
                                    $branch ?? [],
                                    ['id', ['name']],
                                    '<span style="color:red;">*</span> ' . _l('branch'),
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
                            <div class="col-md-3">
                                <label><?= _l('from_date'); ?></label>
                                <input type="date" class="form-control" name="from_date" id="from_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label><?= _l('to_date'); ?></label>
                                <input type="date" class="form-control" name="to_date" id="to_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2" style="margin-top: 24px">
                                <button id="filterBtn" class="btn btn-success w-100"><?= _l('Search'); ?></button>
                            </div>
                        </div>
                        <br>

                        <div class="clearfix"></div>

                        <?= render_datatable([
                            _l('S.No'),
                            _l('patient_name'),
                            _l('mr_no'),
                            _l('age'),
                            _l('gender'),
                            _l('mobile'),
                            _l('treatment'),
                            _l('assigned_doctor'),
                            _l('source'),
                            _l('branch'),
                            _l('last_calling_date'),
                            _l('next_calling_date'),
                            _l('current_status'),
                            _l('patient_status'),
                            _l('registration_start_date'),
                            _l('registration_end_date'),
                            _l('status')
                        ], 'patients'); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function () {
        var pcsTableInitialized = false;
        var BASE_URL = '<?= admin_url("pcs_patients/table/null/") ?>';

        $('#filterBtn').click(function () {
            var branches = $('[name="branch[]"]').val();
            if (!branches || branches.length === 0) {
                alert_float('warning', 'Please select at least one branch.');
                return;
            }

            var from = $('#from_date').val();
            var to = $('#to_date').val();
            var branchParam = branches.map(function (b) {
                return 'branch[]=' + encodeURIComponent(b);
            }).join('&');

            var ajaxUrl = BASE_URL + from + '/' + to + '?' + branchParam;
            var tableSelector = '.table-patients';

            if (pcsTableInitialized && $.fn.DataTable.isDataTable(tableSelector)) {
                $(tableSelector).DataTable().ajax.url(ajaxUrl).load();
            } else {
                initDataTable(tableSelector, ajaxUrl, [0], [0]);
                pcsTableInitialized = true;
            }
        });
    });
</script>