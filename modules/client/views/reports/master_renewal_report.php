<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }

    #calendar {
        max-width: 100%;
        margin: 0 auto;
    }

    #calendar .fc-event {
        border: none;
        font-weight: 500;
    }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?= _l($title); ?>&emsp;


                        </h4>

                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        <form method="post" action="<?= admin_url('client/reports/' . $type); ?>">
                            <div class="row">
                                <!-- From Date -->
                                <div class="col-md-2">
                                    <?php
                                    $posted_date = $this->input->post('consulted_date');
                                    $default_date = date('Y-m-d');
                                    $consulted_date_value = $posted_date ? $posted_date : $default_date;
                                    ?>
                                    <label for="consulted_date" class="control-label"><?= _l('from_date'); ?></label>
                                    <input class="form-control" type="date" id="consulted_date" name="consulted_date"
                                        value="<?= html_escape($consulted_date_value) ?>">
                                </div>

                                <!-- To Date -->
                                <div class="col-md-2">
                                    <?php
                                    $posted_date = $this->input->post('consulted_to_date');
                                    $consulted_to_date_value = $posted_date ? $posted_date : $default_date;
                                    ?>
                                    <label for="consulted_to_date" class="control-label"><?= _l('to_date'); ?></label>
                                    <input class="form-control" type="date" id="consulted_to_date"
                                        name="consulted_to_date" value="<?= html_escape($consulted_to_date_value) ?>">
                                </div>

                                <!-- Branch -->
                                <div class="col-md-3">
                                    <label for="branch" class="control-label"><?= _l('branch'); ?></label>
                                    <select name="branch" id="branch" class="selectpicker" multiple data-width="100%"
                                        data-none-selected-text="<?= _l('all_branches'); ?>" data-actions-box="true">
                                        <?php foreach ($branch as $b) { ?>
                                            <option value="<?= $b['id']; ?>" <?= (in_array($b['id'], $selected_branch_id) ? 'selected' : ''); ?>><?= $b['name']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>


                                <!-- Submit -->
                                <div class="col-md-2">
                                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
                                        value="<?= $this->security->get_csrf_hash(); ?>" />
                                    <br>
                                    <button type="button" id="searchAppointmentsBtn" class="btn btn-success"
                                        style="width: 100%; margin-top: 5px;">
                                        <?= _l('search'); ?>
                                    </button>
                                </div>

                            </div>
                        </form>

                        <br>

                        <!-- Custom Table with Grouped Headers -->
                        <div class="table-responsive">
                            <table class="table table-striped table-master_renewal_report">
                                <thead>
                                    <tr>
                                        <th rowspan="2"><?= _l('Branch Name'); ?></th>
                                        <th rowspan="2"><?= _l('Total Renewals'); ?></th>
                                        <th rowspan="2"><?= _l('Active Renewals'); ?></th>
                                        <th rowspan="2"><?= _l('Inactive Renewals'); ?></th>
                                        <th rowspan="2"><?= _l('Acute Renewals'); ?></th>
                                        <th rowspan="2"><?= _l('Package Amount'); ?></th>
                                        <th rowspan="2"><?= _l('Visited'); ?></th>
                                        <th rowspan="2"><?= _l('Visited(%)'); ?></th>
                                        <th rowspan="2"><?= _l('Reg'); ?></th>
                                        <th rowspan="2"><?= _l('Reg(%)'); ?></th>
                                        <th colspan="5" class="text-center"
                                            style="background-color: #333; color: white;">RY(Renewal)</th>
                                        <th rowspan="2"><?= _l('Pending(%)'); ?></th>
                                        <th colspan="3" class="text-center"
                                            style="background-color: #333; color: white;">RY Due(To Be Renewal)</th>
                                    </tr>
                                    <tr>
                                        <!-- RY Subcols -->
                                        <th><?= _l('Package Amount'); ?></th>
                                        <th><?= _l('Paid Amount'); ?></th>
                                        <th><?= _l('Due Amount'); ?></th>
                                        <th><?= _l('TV'); ?></th>
                                        <th><?= _l('Reg'); ?></th>

                                        <!-- RY Due Subcols -->
                                        <th><?= _l('Package Amount'); ?></th>
                                        <th><?= _l('Paid Amount'); ?></th>
                                        <th><?= _l('Due Amount'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<!-- FullCalendar CSS & JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>

<script>
    $(function () {
        let fromDate = $('#consulted_date').val() || 'null';
        let toDate = $('#consulted_to_date').val() || 'null';
        let appointmentType = $('#appointment_type').val() || 'null';

        // Branch
        let branchId = $('#branch').val();
        if (Array.isArray(branchId)) branchId = branchId.join(',');
        if (!branchId) branchId = 'null';

        let doctorId = $('#doctor_id').val() || 'null';
        let url = '<?= admin_url("client/reports/$type/1/"); ?>';

        initDataTable('.table-master_renewal_report', url, [1], [1], {
            'consulted_date': '#consulted_date',
            'consulted_to_date': '#consulted_to_date',
            'appointment_type': '#appointment_type',
            'branch': '#branch',
            'doctor_id': '#doctor_id',
        });
    });
</script>



<script>
    $(function () {


        // Search Button Click
        $('#searchAppointmentsBtn').on('click', function (e) {
            e.preventDefault(); // Prevent default form submission
            let from = $('#consulted_date').val();
            let to = $('#consulted_to_date').val();
            let appointmentType = $('#appointment_type').val() || 'null';

            let branchId = $('#branch').val();
            if (Array.isArray(branchId)) branchId = branchId.join(',');
            if (!branchId) branchId = 'null';

            if (!from || !to) {
                alert_float('warning', 'Please select both from date and to date.');
                return;
            }

            if (branchId === 'null') {
                alert_float('warning', 'Please select at least one branch.');
                return;
            }

            let doctorId = $('#doctor_id').val() || 'null';

            if ($.fn.DataTable.isDataTable('.table-master_renewal_report')) {
                $('.table-master_renewal_report').DataTable().ajax.reload();
            } else {
                let url = '<?= admin_url("client/reports/$type/1/"); ?>';
                initDataTable('.table-master_renewal_report', url, [1], [1], {
                    'consulted_date': '#consulted_date',
                    'consulted_to_date': '#consulted_to_date',
                    'appointment_type': '#appointment_type',
                    'branch': '#branch',
                    'doctor_id': '#doctor_id',
                });
            }
        });
    });
</script>
<?php if (isset($client_modal))
    echo $client_modal; ?>

<script>
    $(function () {
        <?php if (isset($clientid) && $clientid): ?>
            $('#client-model-auto').modal({
                backdrop: 'static',  // disables click outside to close
                keyboard: false      // disables ESC key to close
            });
        <?php endif; ?>
    });
</script>

</body>

</html>