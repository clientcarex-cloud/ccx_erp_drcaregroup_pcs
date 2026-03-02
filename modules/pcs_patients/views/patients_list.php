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
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#patients-tab" aria-controls="patients-tab" role="tab" data-toggle="tab">
                            <?= _l('patients'); ?>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#appointments-tab" aria-controls="appointments-tab" role="tab" data-toggle="tab">
                            <?= _l('appointments'); ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab 1: Patients -->
                    <div role="tabpanel" class="tab-pane active" id="patients-tab" style="margin-top: -40px">
                        <br>
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
                                                <a href="<?= admin_url('client/client/add_client'); ?>"
                                                    class="btn btn-primary">
                                                    <i class="fa-regular fa-plus tw-mr-1"></i>
                                                        <?= _l('new_client'); ?>
                                                </a>
                                                  <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <hr class="hr-panel-heading" />
                                <div class="clearfix"></div>

                                <!-- Patient Filters -->
                                <div class="row align-items-end" style="margin-bottom: 15px;">
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
                                    <div class="col-md-2">
                                        <label for="from_date_filter"
                                            class="control-label"><?= _l('from_date'); ?></label>
                                        <input class="form-control" type="date" id="from_date_filter"
                                            name="from_date_filter" value="">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="to_date_filter" class="control-label"><?= _l('to_date'); ?></label>
                                        <input class="form-control" type="date" id="to_date_filter"
                                            name="to_date_filter" value="">
                                    </div>
                                    <div class="col-md-2">
                                        <br>
                                        <button type="button" id="filterSearchBtn" class="btn btn-success"
                                            style="width: 100%; margin-top: 5px;">
                                            <?= _l('search'); ?>
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <br>
                                        <button type="button" id="filterResetBtn" class="btn btn-default"
                                            style="width: 100%; margin-top: 5px;">
                                            <?= _l('reset'); ?>
                                        </button>
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

                    <!-- Tab 2: Appointments -->
                    <div role="tabpanel" class="tab-pane" id="appointments-tab" style="margin-top: -40px">
                        <br>
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="row mb-3 align-items-center">
                                        <div class="col-md-6" style="margin-top: 7px;">
                                            <h4 class="no-margin">
                                                <?= _l('appointments'); ?></h4>
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
                                <div id="appointment_table_div">

                                    <div class="row">
                                        <?php
                                        $logged_in_staff_id = get_staff_user_id();
                                        $filtered_doctors = array_filter($doctors, function ($doctor) use ($logged_in_staff_id) {
                                            return $doctor['staffid'] == $logged_in_staff_id;
                                        });

                                        if (!empty($filtered_doctors)) {
                                            $final_doctor_list = array_values($filtered_doctors);
                                            $selected_doctor_id = $logged_in_staff_id;
                                        } else {
                                            $final_doctor_list = $doctors;
                                            $selected_doctor_id = '';
                                        }
                                        ?>

                                        <?php if (count($final_doctor_list) > 0 && count($final_doctor_list) != 1): ?>
                                                <div class="col-md-4">
                                                    <?= render_select(
                                                        'appointment_branch_id',
                                                        $branch,
                                                        ['id', 'name'],
                                                        _l('branch') . '*',
                                                        '',
                                                        [],
                                                        [],
                                                        '',
                                                        ['id' => 'appointment_branch_id', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
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
                                            <label for="appointment_status"><?= _l('status'); ?></label>
                                            <select class="form-control appointment_status" name="appointment_status" id="appointment_status">
                                                <option value=""><?= _l('select_response'); ?></option>
                                                <?php
                                                $allowed_status_names = ['Only Consulted', 'Visited'];
                                                $allowed_status_names_lower = array_map('strtolower', $allowed_status_names);
                                                foreach ($statuses as $status) {
                                                    if (in_array(strtolower($status['name']), $allowed_status_names_lower)) {
                                                        echo '<option value="' . $status['id'] . '">' . $status['name'] . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                                
                                                                                </div>

                                               
                                    <div class="row">
                                        <div class=
                                                "col-md-3">

                                                                                        <label><?= _l('from_date'); ?></label>

                                                                                           <input type="date" class="form-control" name="consulted_date" id="consulted_date" value="<?= date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label><?= _l('to_date'); ?></label>
                                            <input type="date" class="form-control" id="consulted_to_date" value="<?= date('Y-m-d'); ?>">
                                        </div>

                                                                                       <div class="col-md-3">
                                            <?= render_select('appointment_type_id', $appointment_type, ['appointment_type_id', 'appointment_type_name'], _l('appointment_type') . '*', '', ['data-none-selected-text' => _l('dropdown_non_selected_tex')]) ?>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end" style="margin-top: 26px;">
                                            <button id="searchAppointmentsBtn" class="btn btn-success w-100"><?= _l('Search'); ?></button>
                                                    </div>
                                                </div>
                                                <br>
            
                                                <?= render_datatable([
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
                                                    _l('consultation_fee'),
                                                    _l('payment_status'),
                                                    _l('action'),
                                                ], 'appointments'); ?>
            
                                            </div>
                                            <div id="paymentFormContainer" style="display: none;">
                                                <div class="text-end mb-3" style="margin-top: 10px">
                                                    <button type="button" class="btn btn-secondary" id="backToPaymentsBtn">←
                                            <?php echo _l('back'); ?>
                                                    </button>
                                                </div>
            
                                                <?= form_open(admin_url('invoices/record_payment'), [
                                                    'id' => 'record_payment_form',
                                                ]); ?>
          
                                                  <?= form_hidden('invoiceid', '', ['id' => 'invoiceid']) ?>
         
                                                   <div class="row" style="width: 99%">
                                                    <div class="form-group col-md-2">
                                                        <label for="paymentmode" class="control-label"><?= _l('record_payment_date'); ?></label>
                                                        <?php $today = date('d-m-Y'); ?>
                                                        <input type="text" name="date" class="form-control" value="<?= $today; ?>" readonly>
                                                    </div>
    
                                                            <div class="form-group col-md-4">
                                                        <label class="control-label d-block"><?= _l('Amount Received / Payment Mode'); ?></label>
                                                        <div style="display: flex; gap: 0;">
                                                <div style="flex: 1; max-width: 60%;">
                                                                <input type="number" name="amount" class="form-control" placeholder="<?= _l('amount'); ?>">
                                                            </div>
                                                            <div style="flex: 1; max-width: 40%;">
                                                                <select class="form-control selectpicker" name="paymentmode" data-width="100%" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                                                </select>
                                                </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group col-md-3">
                                                        <?= render_input('note', 'remarks'); ?>
                                                    </div>

                                                    <div class="form-group col-md-3">
                                                        <button type="submit" class="btn btn-success" name="submit_action" value="pay" style="margin-top: 24px"><?= _l('pay'); ?></button>
                                                        <button type="submit" class="btn btn-success" name="submit_action" value="pay_print" style="margin-top: 24px"><?= _l('pay_print'); ?></button>
                                                    </div>
                                                </div>
    
                                                        <?= form_close(); ?>
  
                                                      </div>

                                            <script>
                                                document.addEventListener('DOMContentLoaded', function () {
                                                    const form = document.getElementById('record_payment_form');
                                                    form.addEventListener('submit', function (e) {
                                            const clickedButton = document.activeElement;
                                                        if (clickedButton.name === 'submit_action') {
                                                            if (clickedButton.value === 'pay') {
                                                    form.removeAttribute('target');
                                                            } else if (clickedButton.value === 'pay_print') {
                                                                form.setAttribute('target', '_blank');
                                                            }
                                            }
                                                    });
                                                });
                                </script>
      
                                              </div>
                                    </div>
                                </div> <!-- End Appointments Tab -->
                            </div>
                        </div>
        </div>
                </div>
  </div>
           
                      <?php init_tail(); ?>
           
           <!-- Patients Tab JS   -->
          <script>
               $(function () {
                    var pati   entsTable = initDataTable('.table-patients', '<?= admin_url('pcs_patients/get_patient_list'); ?>', [0], [0], 'undefined', [0, 'desc']);
                if (!patient    sTable || !patientsTable.on) {
                   pati     entsTable = $('.table-patients').DataTable();
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

                    $('#filterSearchBtn').on('click', function () {
                        if ($.fn.DataTable.isDataTable('.table-patients')) {
                            $('.table-patients').DataTable().ajax.reload();
                       }
                   });
           
                   $('#    filterResetBtn').on('click', function () {
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
               });
           </script>
            
           <!-- Appointments Ta          b JS -->
  <script>
               $(function () {
                               // Lazy load appointments table on tab switch
        let appointm            entsInitialized = false;
          
                    $('a[dat  a-toggle="tab"]').on('shown.bs.tab', function (e) {
                        let target = $(e.target).attr("href");

                        if (target === '#appointments-tab' && !appointmentsInitialized) {
                            let fromDate = $('#consulted_date').val();
                            let toDate = $('#consulted_to_date').val();
       
                                 initDataTable(
                    '.table-appointments',
                                '<?= admin_url("pcs_patients/appointments/1/") ?>' + fromDate + '/' + toDate,
                                [1],
                                [1]
                            );
                            appointmentsInitialized = true;
                        }
        });
            
                    // Appointments search
                    $('#searchAppointmentsBtn').click(function () {
                        const from = $('#consulted_date').val();
                        const to = $('#consulted_to_date').val();
          
                          let enquiry_doctor_id = $('#enquiry_doctor_id').val();
                        enquiry_doctor_id = enquiry_doctor_id ? enquiry_doctor_id : '0';

                        let appointment_type_id = $('#appointment_type_id').val();
                        let branch_id = $('#appointment_branch_id').val();
                        branch_id = branch_id ? branch_id : '0';
      
                              let visit_status = $('#appointment_status option:selected').text().trim();
                        visit_status = visit_status && visit_status !== 'Select Response' ? visit_status.replace(/\s+/g, '_') : 'All';
            
            if ($.fn.DataTable.isDataTable('.table-appointments')) {
                   
         const url = `<?= admin_url("pcs_patients/appointments/1/") ?>${from}/${to}/${enquiry_doctor_id}/${visit_status}/${branch_id}/0/${appointment_type_id}`;
                            $('.table-appointments').DataTable().ajax.url(url).load();
            }
        });
    });

    function confirmBooking(id) {
        if (confirm("Are you sure you want to confirm this visit?")) {
            $.post("<?= site_url('client/confirm_booking/0/yes'); ?>", { id: id }, function (response) {
                if (response.success) {
                    alert_float("success", response.message || "Visit confirmed.");
                    setTimeout(function () {
                        window.location.href = "<?= admin_url('pcs_patients'); ?>#appointments-tab";
                    }, 1000);
                } else {
                    alert_float("danger", response.message || "Failed to confirm Visit.");
                }
            }, 'json');
        }
    }

    function showPaymentForm(invoiceId) {
        $('#paymentFormContainer').show();
        $('.table-responsive').hide();
        $('#appointment_table_div').hide();

        $('#record_payment_form')[0].reset();
        $('#record_payment_form input[name="invoiceid"]').val(invoiceId);

        $.get(admin_url + 'client/get_invoice_data/' + invoiceId, function (response) {
            let data;
            if (typeof response === 'string') {
                try { data = JSON.parse(response); } catch (e) { alert_float('danger', 'Invalid JSON response.'); return; }
            } else {
                data = response;
            }

            if (!data.amount_left) { alert_float('danger', 'Amount left to pay not found.'); return; }

            $('input[name="amount"]').val(data.amount_left);
            $('input[name="amount"]').attr('max', data.amount_left);

            let paymentModesSelect = $('select[name="paymentmode"]');
            paymentModesSelect.empty().append('<option value=""></option>');

            if (Array.isArray(data.payment_modes)) {
                let selectedByDefault = false;
                data.payment_modes.forEach(function (mode) {
                    let selected = '';
                    if (!selectedByDefault && mode.name.toLowerCase().includes('bank')) {
                        selected = 'selected';
                        selectedByDefault = true;
                    }
                    paymentModesSelect.append('<option value="' + mode.id + '" ' + selected + '>' + mode.name + '</option>');
                });
                paymentModesSelect.selectpicker('refresh');
            }

            $('#paymentFormContainer').show();
        }).fail(function () {
            alert_float('danger', 'Failed to load payment data.');
        });
    }

    $('#backToPaymentsBtn').on('click', function () {
        $('#paymentFormContainer').hide();
        $('.table-responsive').show();
        $('#appointment_table_div').show();
    });
</script>

<script>
    // Re-activate tab from URL hash
    $(document).ready(function () {
        var hash = window.location.hash;
        if (hash) {
            $('.nav-tabs a[href="' + hash + '"]').tab('show');
        }
    });

    // Auto-reload after payment form submit
    if (document.getElementById('record_payment_form')) {
        document.getElementById('record_payment_form').addEventListener('submit', function () {
            setTimeout(function () { location.reload(); }, 500);
        });
    }
</script>

</body>
</html>