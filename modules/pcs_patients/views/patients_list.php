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
                        <div class="clearfix"></div>

                        <!-- Filters -->
                        <div class="row align-items-end" style="margin-bottom: 15px;">
                            <!-- Branch -->
                            <div class="col-md-3">
                                <?php
                                echo render_select(
                                    'branch_filter[]',
                                    $branch,
                                    ['id', ['name']],
                                    _l('lead_branch'),
                                    '',
                                    [
                                        'multiple' => true,
                                        'data-actions-box' => true,
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                    ]
                                );
                                ?>
                            </div>

                            <!-- From Date -->
                            <div class="col-md-2">
                                <label for="from_date_filter" class="control-label"><?= _l('from_date'); ?></label>
                                <input class="form-control" type="date" id="from_date_filter" name="from_date_filter"
                                    value="">
                            </div>

                            <!-- To Date -->
                            <div class="col-md-2">
                                <label for="to_date_filter" class="control-label"><?= _l('to_date'); ?></label>
                                <input class="form-control" type="date" id="to_date_filter" name="to_date_filter"
                                    value="">
                            </div>

                            <!-- Search Button -->
                            <div class="col-md-2">
                                <br>
                                <button type="button" id="filterSearchBtn" class="btn btn-success"
                                    style="width: 100%; margin-top: 5px;"><?= _l('search'); ?></button>
                            </div>

                            <!-- Reset Button -->
                            <div class="col-md-2">
                                <br>
                                <button type="button" id="filterResetBtn" class="btn btn-default"
                                    style="width: 100%; margin-top: 5px;"><?= _l('reset'); ?></button>
                            </div>
                        </div>

                        <?= render_datatable([
                            _l('S.No'),
                            _l('patient_name'),
                            _l('branch'),
                            _l('mr_no'),
                            _l('age'),
                            _l('gender'),
                            _l('mobile'),
                            _l('treatment'),
                            _l('assigned_doctor'),
                            _l('source'),
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
        var patientsTable = initDataTable('.table-patients', '<?= admin_url('pcs_patients/get_patient_list'); ?>', [0], [0], 'undefined', [0, 'desc']);
        if (!patientsTable || !patientsTable.on) {
            patientsTable = $('.table-patients').DataTable();
        }

        if (patientsTable && patientsTable.on) {
            patientsTable.on('preXhr.dt', function (e, settings, data) {
                var branchSelect = $('select[name="branch_filter[]"]');
                var branchIds = branchSelect.val() && branchSelect.val().length > 0 ? branchSelect.val().join(',') : '';
                data.branch_ids = branchIds;
                data.from_date_filter = $('#from_date_filter').val() || '';
                data.to_date_filter = $('#to_date_filter').val() || '';
            });
        }

        // Search button - reload table with filters
        $('#filterSearchBtn').on('click', function () {
            if ($.fn.DataTable.isDataTable('.table-patients')) {
                $('.table-patients').DataTable().ajax.reload();
            }
        });

        // Reset button - clear filters and reload
        $('#filterResetBtn').on('click', function () {
            $('select[name="branch_filter[]"]').val([]).trigger('change');
            if ($('select[name="branch_filter[]"]').data('selectpicker')) {
                $('select[name="branch_filter[]"]').selectpicker('refresh');
            }
            $('#from_date_filter').val('');
            $('#to_date_filter').val('');
            if ($.fn.DataTable.isDataTable('.table-patients')) {
                $('.table-patients').DataTable().ajax.reload();
            }
        });
        // Toggle mask/unmask for phone numbers in DataTable
        $(document).on('click', '.table-patients .toggle-mask-btn', function(e) {
            e.preventDefault();
            var $span = $(this).siblings('.masked-number');
            var $icon = $(this).find('i');
            if (!$span.length) return;
            var isMasked = ($span.text().trim() === $span.attr('data-masked'));
            if (isMasked) {
                $span.text($span.attr('data-full'));
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                $span.text($span.attr('data-masked'));
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });
    });
</script>