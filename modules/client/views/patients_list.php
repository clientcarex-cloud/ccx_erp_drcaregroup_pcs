<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup { font-size: 1.6rem !important; }
	.tw-bg-white {
		--tw-bg-opacity: 1 !important;
	}
</style>
<?php
if($master_data){
	extract($master_data);
}
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <ul class="nav nav-tabs" role="tablist">
				<?php if (staff_can('view', 'customers') || staff_can('view_own', 'customers')) { ?>
				  <li role="presentation" class="active">
					<a href="#patients-tab" aria-controls="patients-tab" role="tab" data-toggle="tab">
					  <?= _l('patients'); ?>
					</a>
				  </li>
				<?php } if (staff_can('view_appointments', 'customers') || staff_can('view_global_appointments', 'customers')) { ?>
					<li role="presentation" class="<?php if (staff_cant('view', 'customers') && staff_cant('view_own', 'customers')) { ?>active<?php }?>">
					  <a href="#appointments-tab" aria-controls="appointments-tab" role="tab" data-toggle="tab">
						<?= _l('appointments'); ?>
					  </a>
					</li>
				  <?php } ?>
				</ul>
				
                <div class="tab-content">
                    <!-- Tab 1: Patients -->
                    <div role="tabpanel" class="tab-pane <?php if (staff_can('view', 'customers') || staff_can('view_own', 'customers')) { ?>active <?php }?>" id="patients-tab" style="margin-top: -40px">
                        <br>
                        
						<div id="summaryCards" class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-6 tw-gap-2">
							<!-- Summary Cards Will Load Here -->
						</div>

                        <br>
                        <div class="panel_s">
                            <div class="panel-body">
							<div class="row">
							<div class="row mb-3 align-items-center">
								<div class="col-md-6" style="margin-top: 7px;">
									<h4 class="no-margin"><?= _l('patient_list'); ?></h4>
								</div>
								<div class="col-md-6 text-right d-flex justify-content-end gap-2">
									<?php if (staff_can('create', 'customers')) { ?>
										<a href="<?= admin_url('client/client/add_client'); ?>" class="btn btn-primary">
											<i class="fa-regular fa-plus tw-mr-1"></i>
											<?= _l('new_client'); ?>
										</a>
									<?php } ?>
									<?php if (staff_can('create', 'customers')) { ?>
										<a href="<?= admin_url('client/client/import'); ?>" class="btn btn-default">
											<i class="fa-solid fa-upload tw-mr-1"></i>
											<?= _l('import_customers'); ?>
										</a>
									<?php } ?>
								</div>
							</div>

							</div>
                               
                                <hr class="hr-panel-heading" />
								<div class="row align-items-end">
								<?php
								if (staff_can('branch_filter', 'customers')) {
								?>
							  <div class="col-md-3">
							 <?= render_select(
									'groupid', // name
									$branch,   // options array
									['id', 'name'], // option keys
									_l('branch') . '*', // label
									isset($current_branch_id) ? $current_branch_id : ($patient['groupid'] ?? ''), // selected
									[
										'id' => 'branch_id', // 👈 Add your ID here
										'data-none-selected-text' => _l('dropdown_non_selected_tex'),
										'required' => 'required'
									]
								) ?>

							  </div>
							  <?php
								}
								?>
							  <div class="col-md-3">
								<label><?= _l('from_date'); ?></label>
								<input type="date" class="form-control" name="from_date" id="from_date" value="2011-01-01">
							  </div>
							  <div class="col-md-3">
								<label><?= _l('to_date'); ?></label>
								<input type="date" class="form-control" name="to_date" id="to_date" value="<?= date('Y-m-d'); ?>">
							  </div>
							  <div class="col-md-2" style="margin-top: 24px">
								<button id="filterBtn" class="btn btn-success w-100"><?= _l('Search'); ?></button>
							  </div>
							</div>
							<br>
                                <div class="clearfix"></div>
								
                               <?= render_datatable([
								_l('S.No'),           // Name
								_l('patient_name'),           // Name
								_l('age'),                    // Age
								_l('gender'),                // Gender
								_l('mobile'),                // Contact Number
								_l('treatment'),             // Treatment
								_l('assigned_doctor'),       // Assigned Doctor
								_l('source'),                // Source
								_l('last_calling_date'),     // Last Calling Date
								_l('next_calling_date'),     // Next Calling Date
								_l('current_status'),        // Current Status
								_l('patient_status'),        // Current Status
								_l('registration_start_date'), // Reg End Date
								_l('registration_end_date'),// Reg Start Date
								_l('status')// Status
							], 'patients'); ?>

                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Appointments -->
                    <div role="tabpanel" class="tab-pane <?php if (staff_can('view_appointments', 'customers') AND (staff_cant('view', 'customers') && staff_cant('view_own', 'customers'))) { ?>active <?php }?>" id="appointments-tab" style="margin-top: -40px">
                        <br>
						<div id="appointmentSummaryCards" class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-6 tw-gap-2">
							<!-- Filled via JS -->
							
						</div>

						<br>
						
						<div class="panel_s">
                            <div class="panel-body">
							<div class="row">
							<div class="row mb-3 align-items-center">
								<div class="col-md-6" style="margin-top: 7px;">
									<h4 class="no-margin"><?= _l('appointments'); ?><?php
									if(staff_can('view_appointments_calendar', 'customers')){
										?>
										
										<a href="<?= admin_url('client/doctor_calendar_view') ?>" 
										   title="Switch to Appointments" 
										   class="btn btn-sm btn-primary">
										   <i class="fa-solid fa-grip-vertical"></i>
										</a>
										
										<?php
									}
									?></h4>
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

    <?php if(count($final_doctor_list) > 0 && count($final_doctor_list) != 1): ?>
        <div class="col-md-4">
            <?= render_select(
                'appointment_branch_id',
                $branch,
                ['id', 'name'],
                _l('branch') . '*',
                isset($current_branch_id) ? $current_branch_id : ($patient['groupid'] ?? ''),
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
        <label for="appointment_status"><?= _l('status'); ?></label>
        <select class="form-control appointment_status" name="appointment_status" id="appointment_status">
            <option value=""><?= _l('select_response'); ?></option>
            <?php
                $allowed_status_names = ['Only Consulted', 'Visited'];
                $allowed_status_names_lower = array_map('strtolower', $allowed_status_names);
                foreach ($statuses as $status) {
                    if (in_array(strtolower($status['name']), $allowed_status_names_lower)) {
                        echo '<option value="'.$status['id'].'">'.$status['name'].'</option>';
                    }
                }
            ?>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <label><?= _l('from_date'); ?></label>
        <input type="date" class="form-control" name="consulted_date" id="consulted_date" value="<?= date('Y-m-d'); ?>">
    </div>
    <div class="col-md-3">
        <label><?= _l('to_date'); ?></label>
        <input type="date" class="form-control" id="consulted_to_date" value="<?= date('Y-m-d'); ?>">
    </div>
	<div class="col-md-3">
                <?= render_select('appointment_type_id', $appointment_type, ['appointment_type_id', 'appointment_type_name'], _l('appointment_type') . '*', '', ['data-none-selected-text' => _l('dropdown_non_selected_tex'), 'required' => 'required']) ?>
            </div>
    <div class="col-md-2 d-flex align-items-end" style="margin-top: 26px;">
        <button id="searchAppointmentsBtn" class="btn btn-success w-100"><?= _l('Search'); ?></button>
    </div>
</div>
							<br>

                                <?= render_datatable([
                                    //_l('mr_no'),
                                    //_l('visit_id'),
                                    _l('patient_name'),
                                    _l('patient_mobile'),
                                    _l('assigned_doctor'),
                                    _l('appointment_date'),
                                    _l('consulted_date'),
                                    _l('consultation_duration'),
                                    _l('treatment'),
                                    _l('appointment_type'),
                                    _l('registration_end_date'),
                                    //_l('enquiry_type'),
                                    _l('branch'),
                                    _l('consultation_fee'),
                                    _l('payment_status'),
                                    _l('action'),
                                ], 'appointments'); ?>
								
								</div>
								<div id="paymentFormContainer" style="display: none;">
    <div class="text-end mb-3" style="margin-top: 10px">
        <button type="button" class="btn btn-secondary" id="backToPaymentsBtn">← <?php echo _l('back'); ?>
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
                <input type="number" name="amount" class="form-control" placeholder="<?= _l('amount'); ?>" value="<?= htmlspecialchars($amount ?? '') ?>">
            </div>
            <div style="flex: 1; max-width: 40%;">
                <select class="form-control selectpicker" name="paymentmode" data-width="100%" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                    <!-- populate options dynamically -->
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
          form.removeAttribute('target'); // default behavior
        } else if (clickedButton.value === 'pay_print') {
          form.setAttribute('target', '_blank'); // open in new tab
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
<script>
$(function () {
    <?php if (isset($clientid) && $clientid): ?>
        // If clientid is set, show patient modal popup
        //$('#client-model-auto').modal('show');
		$('#client-model-auto').modal({
			backdrop: 'static',  // disables click outside to close
			keyboard: false      // disables ESC key to close
		});
    <?php else: ?>
        // Only load the Patients table on first load
        initDataTable('.table-patients', '<?= admin_url('client/get_patient_list'); ?>', [1], [1]);
        initDataTable('.table-appointments', '<?= admin_url('client/appointments'); ?>', [1], [1]);
		
		loadClientSummary();
		loadAppointmentSummary();
    <?php endif; ?>

    // Lazy load Appointments tab table
    let appointmentsInitialized = false;

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        let target = $(e.target).attr("href");

        if (target === '#appointments-tab' && !appointmentsInitialized) {
            let fromDate = $('#consulted_date').val();
            let toDate = $('#consulted_to_date').val();
            
            initDataTable(
                '.table-appointments',
                '<?= admin_url("client/appointments/1/") ?>' + fromDate + '/' + toDate,
                [1],
                [1]
            );
            appointmentsInitialized = true;
        }
    });
});
</script>



<?php if (isset($client_modal)) echo $client_modal; ?>
<script>
$(function () {
    <?php if (isset($clientid) && $clientid): ?>
        $('#client-model-auto').modal({
			backdrop: 'static',  // disables click outside to close
			keyboard: false      // disables ESC key to close
		});
    <?php endif; ?>
});

function confirmBooking(id) { 
    if (confirm("Are you sure you want to confirm this visit?")) {
        $.post("<?= site_url('client/confirm_booking/0/yes'); ?>", { id: id }, function(response) {
            if (response.success) {
                alert_float("success", response.message || "Visit confirmed.");
                setTimeout(function() {
                    window.location.href = "<?= admin_url('client/get_patient_list?tab=appointments-tab'); ?>";
                }, 1000); // Wait 1 second before redirecting
            } else {
                alert_float("danger", response.message || "Failed to confirm Visit.");
            }
        }, 'json');
    }
}



</script>
<script>
  $(document).ready(function () {
    // Check if there's a hash in the URL
    var hash = window.location.hash;

    if (hash) {
      // Activate the tab that matches the hash
      $('.nav-tabs a[href="' + hash + '"]').tab('show');
    }
  });
  
</script>

<script>
function loadClientSummary(from_date = '', to_date = '', branch_id = '') {
    $.ajax({
        url: admin_url + 'client/get_client_summary',
        type: 'POST',
        data: {
            from_date: from_date,
            to_date: to_date,
            branch_id: branch_id
        },
        dataType: 'json',
        success: function (res) {
			$('#summaryCards').html(`
				
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="registered">
					<span class="tw-font-semibold tw-mr-1">${res.registered}</span>
					<span class="text-primary"><?= _l('registered_patients'); ?></span>
				</div>
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="not_registered">
					<span class="tw-font-semibold tw-mr-1">${res.not_registered}</span>
					<span class="text-warning"><?= _l('not_registered_patients'); ?></span>
				</div>
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="new_patients">
					<span class="tw-font-semibold tw-mr-1">${res.new_patients}</span>
					<span class="text-success"><?= _l('new_patients'); ?></span>
				</div>
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="renewal">
					<span class="tw-font-semibold tw-mr-1">${res.renewal_patients}</span>
					<span class="text-primary"><?= _l('renewal_patients'); ?></span>
				</div>
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="no_due">
					<span class="tw-font-semibold tw-mr-1">${res.no_due_registered_patients}</span>
					<span class="text-success"><?= _l('no_due_patients'); ?></span>
				</div>
				<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card" data-filter="due">
					<span class="tw-font-semibold tw-mr-1">${res.due_patients}</span>
					<span class="text-danger"><?= _l('due_patients'); ?></span>
				</div>
			`);

			// Add click handler
			$('.summary-card').on('click', function () {
				const filterType = $(this).data('filter');
				const from = $('#from_date').val();
				const to = $('#to_date').val();
				const branch_id = $('#groupid').val();

				// Reload DataTable with custom summary filter
				if ($.fn.DataTable.isDataTable('.table-patients')) {
					$('.table-patients').DataTable().ajax.url(
						'<?= admin_url("client/get_patient_list/null/") ?>' + from + '/' + to + '/' + branch_id + '?summary_filter=' + filterType
					).load();
				}
			});
		}

    });
}




// Initial load
$(document).ready(function () {
    // Initial summary load
    //loadClientSummary();

    $('#filterBtn').click(function () {
        const from = $('#from_date').val();
        const to = $('#to_date').val();
		const branch_id = $('#groupid').val();
		const appointment_type_id = $('#appointment_type_id').val();
		
        // Reload summary cards
        loadClientSummary(from, to, branch_id);

        // Reload DataTable with filtered data
        if ($.fn.DataTable.isDataTable('.table-patients')) {
            $('.table-patients').DataTable().ajax.url(
                '<?= admin_url("client/get_patient_list/null/") ?>' + from + '/' + to + '/' + branch_id
            ).load();  
			
			/* $('.table-appointments').DataTable().ajax.url(
                '<?= admin_url("client/appointments/") ?>' + from + '/' + to + '/NULL/NULL/NULL/NULL/' + appointment_type_id
            ).load(); */
			
        }
    });
});


</script>
<!-- Appointments -->
<script>
function loadAppointmentSummary(from_date = '', to_date = '', enquiry_doctor_id = '', branch_id = '', appointment_type_id = '') {
	
    $.ajax({
        url: admin_url + 'client/get_appointment_summary',
        type: 'POST',
        data: {
            from_date: from_date,
            to_date: to_date,
            enquiry_doctor_id: enquiry_doctor_id,
            branch_id: branch_id,
            appointment_type_id: appointment_type_id,
        },
        dataType: 'json',
        success: function (res) {
            $('#appointmentSummaryCards').html(`
			<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card tw-cursor-pointer hover:tw-shadow" data-filter="all">
				<span class="tw-font-semibold tw-mr-1">${res.total}</span>
				<span class="text-success"><?= _l('appointments'); ?></span>
			</div>
			<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card tw-cursor-pointer hover:tw-shadow" data-filter="missed">
				<span class="tw-font-semibold tw-mr-1">${res.missed}</span>
				<span class="text-danger"><?= _l('missed'); ?></span>
			</div>
			<div class="tw-border tw-rounded-lg tw-px-4 tw-py-3 tw-flex tw-items-center tw-bg-white summary-card tw-cursor-pointer hover:tw-shadow" data-filter="consulted">
				<span class="tw-font-semibold tw-mr-1">${res.consulted}</span>
				<span class="text-success"><?= _l('consulted'); ?></span>
			</div>
		`);


            $('.summary-card').on('click', function () {
                const filterType = $(this).data('filter');
                const from = $('#from_date').val();
                const to = $('#to_date').val();
                const doctor_id = $('#enquiry_doctor_id').val();
                const branch_id = $('#groupid').val();
                const appointment_type_id = $('#appointment_type_id').val();

                if ($.fn.DataTable.isDataTable('.table-appointments')) {
                    let table = $('.table-appointments').DataTable();
                    table.settings()[0].ajax.data = function (d) {
                        d.from_date = from;
                        d.to_date = to;
                        d.enquiry_doctor_id = doctor_id;
                        d.branch_id = branch_id;
                        d.summary_filter = filterType;
                        d.appointment_type_id = appointment_type_id;
                    };
                    table.ajax.reload();
                }
            });
        }
    });
}




$(document).ready(function () {
    // Initial load
    
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

		//loadClientSummary(from, to);
		loadAppointmentSummary(from, to, enquiry_doctor_id, branch_id, appointment_type_id);

		if ($.fn.DataTable.isDataTable('.table-appointments')) {
			const url = `<?= admin_url("client/appointments/1/") ?>${from}/${to}/${enquiry_doctor_id}/${visit_status}/${branch_id}/0/${appointment_type_id}`;
			$('.table-appointments').DataTable().ajax.url(url).load();
		}
	});

});



function showPaymentForm(invoiceId) {
  $('#paymentFormContainer').show();
  $('.table-responsive').hide(); // hide table
  $('#appointment_table_div').hide(); // hide table

  $('#record_payment_form')[0].reset();
  $('#record_payment_form input[name="invoiceid"]').val(invoiceId);

  $.get(admin_url + 'client/get_invoice_data/' + invoiceId, function(response) {
    let data;

    if (typeof response === 'string') {
      try {
        data = JSON.parse(response);
      } catch (e) {
        alert_float('danger', 'Invalid JSON response.');
        return;
      }
    } else {
      data = response;
    }

    if (!data.amount_left) {
      alert_float('danger', 'Amount left to pay not found in response.');
      return;
    }

    $('input[name="amount"]').val(data.amount_left);
    $('input[name="amount"]').attr('max', data.amount_left);
    //$('input[name="date"]').val(data.date || '');

    let paymentModesSelect = $('select[name="paymentmode"]');
    paymentModesSelect.empty().append('<option value=""></option>');

    if (Array.isArray(data.payment_modes)) {
      let selectedByDefault = false;

      data.payment_modes.forEach(function(mode) {
        let selected = '';
        if (!selectedByDefault && mode.name.toLowerCase().includes('bank')) {
          selected = 'selected';
          selectedByDefault = true;
        }

        paymentModesSelect.append(
          '<option value="' + mode.id + '" ' + selected + '>' + mode.name + '</option>'
        );
      });

      paymentModesSelect.selectpicker('refresh');
    } else {
      alert_float('warning', 'No valid payment modes available.');
    }

    $('#paymentFormContainer').show();
  }).fail(function(xhr) {
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
document.getElementById('record_payment_form').addEventListener('submit', function () {
    // Wait briefly to ensure the new tab opens
    setTimeout(function () {
      location.reload(); // Refresh current page
    }, 500);
  }); 
</script>

</body>
</html>
