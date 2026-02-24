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
                        <div class="row mbot15">
                            <div class="col-md-3">
                                <label for="filter_from_date"><?= _l('from_date'); ?> *</label>
                                <div class="input-group date">
                                    <input type="text" id="filter_from_date" name="filter_from_date" class="form-control datepicker" value="<?= date('Y-m-01'); ?>" autocomplete="off" required>
                                    <div class="input-group-addon">
                                        <i class="fa fa-calendar calendar-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_to_date"><?= _l('to_date'); ?> *</label>
                                <div class="input-group date">
                                    <input type="text" id="filter_to_date" name="filter_to_date" class="form-control datepicker" value="<?= date('Y-m-d'); ?>" autocomplete="off" required>
                                    <div class="input-group-addon">
                                        <i class="fa fa-calendar calendar-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="branch_ids"><?= _l('branch'); ?></label>
                                <select id="branch_ids" name="branch_ids[]" class="selectpicker" multiple data-width="100%" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                    <?php foreach ($branches as $branch) { ?>
                                        <option value="<?= $branch['id']; ?>"><?= $branch['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_select('doctor_id', $doctors, ['staffid', ['firstname', 'lastname']], 'doctor'); ?>
                            </div>
                        </div>
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
        var patientsTable = initDataTable('.table-patients', '<?= admin_url('client/get_patient_list'); ?>', [0], [0], 'undefined', [0, 'desc']);
        if (!patientsTable || !patientsTable.on) {
            patientsTable = $('.table-patients').DataTable();
        }

        if (patientsTable && patientsTable.on) {
            patientsTable.on('preXhr.dt', function (e, settings, data) {
                // Remove all custom filters since we just want the complete table
                data.branch_ids = $('#branch_ids').val();
                data.from_date_filter = $('#filter_from_date').val();
                data.to_date_filter = $('#filter_to_date').val();
                data.doctor_id = $('#doctor_id').val();
            });
        }

        $('#filter_from_date, #filter_to_date, #branch_ids, #doctor_id').on('change', function() {
            if ($('#filter_from_date').val() !== '' && $('#filter_to_date').val() !== '') {
                $('.table-patients').DataTable().ajax.reload();
            } else if ($('#filter_from_date').val() === '' && $('#filter_to_date').val() === '') {
                $('.table-patients').DataTable().ajax.reload();
            } else {
                // If only one date is filled but not the other, wait for both
            }
        });
    });
</script>