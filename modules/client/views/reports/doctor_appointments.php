<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }

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
                        <h4 class="no-margin">
                            <?= _l($title); ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>



                        <div class="row">
                            <?php
                            $logged_in_staff_id = get_staff_user_id();

                            // Filter $doctors to include only the logged-in staff member
                            $filtered_doctors = array_filter($doctors, function ($doctor) use ($logged_in_staff_id) {
                                return $doctor['staffid'] == $logged_in_staff_id;
                            });

                            // If match found, use filtered list and preselect it
                            if (!empty($filtered_doctors)) {
                                $final_doctor_list = array_values($filtered_doctors); // Reindex
                                $selected_doctor_id = $logged_in_staff_id;
                            } else {
                                $final_doctor_list = $doctors;
                                $selected_doctor_id = ''; // No default
                            }
                            ?>

                            <?php if (count($final_doctor_list) > 0 && count($final_doctor_list) != 1): ?>
                                <div class="col-md-4">
                                    <?= render_select(
                                        'appointment_branch_id',
                                        $branch,
                                        ['id', 'name'],
                                        _l('branch') . '*',
                                        isset($current_branch_id) ? $current_branch_id : '',
                                        [],
                                        [],
                                        '',
                                        ['id' => 'appointment_branch_id', 'required' => 'required', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    ) ?>
                                </div>
                                <div class="col-md-4">
                                    <?= render_select(
                                        'enquiry_doctor_id',
                                        $final_doctor_list,
                                        ['staffid', ['firstname', 'lastname']],
                                        _l('doctor'),
                                        $selected_doctor_id,
                                        ['id' => 'enquiry_doctor_id', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    ) ?>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label for="appointment_status">
                                    <?= _l('status'); ?>
                                </label>
                                <select class="form-control appointment_status" name="appointment_status"
                                    id="appointment_status">
                                    <option value="">
                                        <?= _l('select_response'); ?>
                                    </option>
                                    <option value="Only Consulted">Only Consulted</option>
                                    <option value="Visited">Visited</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <label>
                                    <?= _l('from_date'); ?>
                                </label>
                                <input type="date" class="form-control" name="consulted_date" id="consulted_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label>
                                    <?= _l('to_date'); ?>
                                </label>
                                <input type="date" class="form-control" name="consulted_to_date" id="consulted_to_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <?= render_select('appointment_type_id', $appointment_type, ['appointment_type_id', 'appointment_type_name'], _l('appointment_type') . '*', '', ['data-none-selected-text' => _l('dropdown_non_selected_tex'), 'required' => 'required']) ?>
                            </div>
                            <div class="col-md-2 d-flex align-items-end" style="margin-top: 26px;">
                                <button id="searchAppointmentsBtn" class="btn btn-success w-100">
                                    <?= _l('Search'); ?>
                                </button>
                            </div>
                        </div>
                        <br>

                        <!-- Table -->
                        <?php echo render_datatable([
                            _l('patient_name'),
                            _l('patient_mobile'),
                            _l('assigned_doctor'),
                            _l('appointment_date'),
                            _l('consulted_date'),
                            _l('consultation_duration'),
                            _l('treatment'),
                            _l('appointment_type'),
                            _l('branch'),
                            _l('registration_end_date'),
                        ], 'doctor-appointments custom-excel-table'); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<?php if (isset($client_modal)) echo $client_modal; ?>

<script>
    $(document).ready(function () {
        <?php if (isset($clientid) && $clientid): ?>
            // If clientid is set, show patient modal popup
            $('#client-model-auto').modal({
                backdrop: 'static',  // disables click outside to close
                keyboard: false      // disables ESC key to close
            });
        <?php endif; ?>

        let from = $('#consulted_date').val();
        let to = $('#consulted_to_date').val();
        let enquiry_doctor_id = $('#enquiry_doctor_id').val() || '0';
        let appointment_type_id = $('#appointment_type_id').val() || '0';
        let branch_id = $('#appointment_branch_id').val() || '0';


        var url = `<?= admin_url("client/reports/doctor_appointments/null/") ?>${from}/${to}/${enquiry_doctor_id}/All/${branch_id}/0/${appointment_type_id}`;

        var table = initDataTable(
            '.table-doctor-appointments',
            url,
            [1],
            [1]
        );

        $('#searchAppointmentsBtn').click(function () {
            from = $('#consulted_date').val();
            to = $('#consulted_to_date').val();
            enquiry_doctor_id = $('#enquiry_doctor_id').val() || '0';
            appointment_type_id = $('#appointment_type_id').val() || '0';
            branch_id = $('#appointment_branch_id').val() || '0';

            let visit_status = $('#appointment_status option:selected').text().trim();
            visit_status = visit_status && visit_status !== 'Select Response' ? visit_status.replace(/\s+/g, '_') : 'All';

            if ($.fn.DataTable.isDataTable('.table-doctor-appointments')) {
                const newUrl = `<?= admin_url("client/reports/doctor_appointments/null/") ?>${from}/${to}/${enquiry_doctor_id}/${visit_status}/${branch_id}/0/${appointment_type_id}`;
                $('.table-doctor-appointments').DataTable().ajax.url(newUrl).load();
            }
        });
    });
</script>

</body>

</html>