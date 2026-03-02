<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .tw-bg-white {
        --tw-bg-opacity: 1 !important;
    }
</style>
<?php
if (isset($master_data) && $master_data) {
    extract($master_data);
}
?>
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

                        <!-- ===== Filter Row (same as client/patients_list) ===== -->
                        <div class="row align-items-end">
                            <?php
                            $branchOptions = $branch ?? [];
                            if (!empty($accessible_branch_ids ?? [])) {
                                $allowedIds = array_map('intval', (array) $accessible_branch_ids);
                                $branchOptions = array_values(array_filter($branchOptions, static function ($branchItem) use ($allowedIds) {
                                    return isset($branchItem['id']) && in_array((int) $branchItem['id'], $allowedIds, true);
                                }));
                            }
                            ?>
                            <div class="col-md-3">
                                <?= render_select(
                                    'groupid[]',
                                    $branchOptions,
                                    ['id', 'name'],
                                    _l('branch') . '*',
                                    isset($selected_branch_id) && !empty($selected_branch_id) ? $selected_branch_id : (isset($current_branch_id) ? [$current_branch_id] : []),
                                    [
                                        'id' => 'branch_id',
                                        'multiple' => 'true',
                                        'data-actions-box' => 'true',
                                        'data-selected-text-format' => 'count > 2',
                                        'data-live-search' => 'true',
                                        'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                                        'required' => 'required'
                                    ],
                                    [],
                                    '',
                                    '',
                                    true
                                ) ?>
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
    const BRANCH_SELECT_ID = '#branch_id';

    function getSelectedBranchValues() {
        const raw = $(BRANCH_SELECT_ID).val();
        if (!raw) {
            return [];
        }
        if (Array.isArray(raw)) {
            return raw.filter(function (value) {
                return value !== null && value !== undefined && value !== '';
            });
        }
        return raw ? [raw] : [];
    }

    function getSelectedBranchParam() {
        const values = getSelectedBranchValues();
        return values.length ? values.join(',') : '';
    }

    function buildPcsTableUrl(from, to) {
        const safeFrom = from || '';
        const safeTo = to || '';
        return '<?= admin_url("pcs_patients/table/null/") ?>' + safeFrom + '/' + safeTo;
    }

    $(function () {
        // Initial load — no date filter, no branch filter
        var patientsTable = initDataTable('.table-patients', '<?= admin_url("pcs_patients/table"); ?>', [0], [0]);
        if (!patientsTable || !patientsTable.on) {
            patientsTable = $('.table-patients').DataTable();
        }

        // Send branch_ids via POST with every DataTable AJAX request
        if (patientsTable && patientsTable.on) {
            patientsTable.on('preXhr.dt', function (e, settings, data) {
                data.branch_ids = getSelectedBranchParam();
            });
        }

        $(BRANCH_SELECT_ID).selectpicker('refresh');

        // Search button → reload table with selected filters
        $('#filterBtn').click(function () {
            const from = $('#from_date').val();
            const to = $('#to_date').val();

            if ($.fn.DataTable.isDataTable('.table-patients')) {
                const dataUrl = buildPcsTableUrl(from, to);
                var tableInstance = $('.table-patients').DataTable();
                tableInstance.ajax.url(dataUrl).load();
            }
        });
    });
</script>