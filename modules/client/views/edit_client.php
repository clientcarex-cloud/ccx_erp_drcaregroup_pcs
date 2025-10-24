<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<br><br>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
			
           
            <?php
              extract($master_data);
              $patient = json_decode(json_encode($client_data), true);
              if ($appointment_data) {
                $appointment_data = $appointment_data[0];
              }
              $appointment_data = json_decode(json_encode($appointment_data), true);
            ?>

            <form method="post" action="<?= admin_url('client/update_client'); ?>">
              <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
              <?= form_hidden('userid', $patient['userid']); ?>

              <div class="form-group">
                <?= render_input('contact_number', 'contact_number', $patient['phonenumber'], 'text', [
					'required' => true,
					'readonly' => true
				]); ?>

              </div>

              <h4 class="customer-profile-group-heading"><?= _l('section_title_patient_information'); ?></h4>

              <div class="row" style="padding: 15px">
			  <?php
    $patient_inactive_fields = array_map('trim', explode(",", $patient_inactive_fields));
    $fields = [];
    // Collect only active fields for patient_name section
    if (!in_array('company', $patient_inactive_fields) || !in_array('salutation', $patient_inactive_fields) || !in_array('gender', $patient_inactive_fields)) {
        ob_start(); ?>
        <label class="form-label"><span style="color: #f00">* </span><?= _l('patient_name') ?></label>
        <div class="mb-3" style="display: flex; gap: 0;">
			<?php if (!in_array('salutation', $patient_inactive_fields)) { ?>
				<div style="flex: 1; max-width: 17%;">
					<select class="form-control" style="padding:0; margin:0" name="salutation">
					<option value="Master" <?= ($patient['salutation'] ?? '') == 'Master' ? 'selected' : '' ?>>Master</option>
						<option value="Baby" <?= ($patient['salutation'] ?? '') == 'Baby' ? 'selected' : '' ?>>Baby</option>
						<option value="Mr." <?= ($patient['salutation'] ?? '') == 'Mr.' ? 'selected' : '' ?>>Mr.</option>
						<option value="Mrs." <?= ($patient['salutation'] ?? '') == 'Mrs.' ? 'selected' : '' ?>>Mrs.</option>
						<option value="Ms." <?= ($patient['salutation'] ?? '') == 'Ms.' ? 'selected' : '' ?>>Ms.</option>
					</select>
				</div>
			<?php } ?>
			<?php if (!in_array('company', $patient_inactive_fields)) { ?>
				<div style="flex: 1; max-width: 66%;">
					<input type="text" class="form-control" name="company" placeholder="<?= _l('enter_patient_name') ?>" value="<?= htmlspecialchars($patient['company'] ?? '') ?>" required>
				</div>
			<?php } ?>
			<?php if (!in_array('gender', $patient_inactive_fields)) { ?>
				<div style="flex: 1; max-width: 23%;">
					<select class="form-control"  style="padding:0; margin:0" name="gender">
						<option value="Male" <?= ($patient['gender'] ?? '') == 'Male' ? 'selected' : '' ?>><?= _l('male') ?></option>
						<option value="Female" <?= ($patient['gender'] ?? '') == 'Female' ? 'selected' : '' ?>><?= _l('female') ?></option>
						<option value="Other" <?= ($patient['gender'] ?? '') == 'Other' ? 'selected' : '' ?>><?= _l('other') ?></option>
					</select>
				</div>
			<?php } ?>
		</div>
        <?php
        $fields[] = ob_get_clean();
    }
	?>
	
	<?php
$has_valid_dob = !empty($patient['dob']) && $patient['dob'] != '1970-01-01';
// Get selected values as array
$selected_languages = isset($patient['default_language']) 
                      ? array_map('trim', explode(',', $patient['default_language'])) 
                      : [];
    // Map of field name => field HTML
    $fields_map = [
	
					'age_gender' => '
						<div class="form-group">
						  <label class="form-label"><span style="color: #f00">* </span>' . _l('age_dob_gender') . '</label>
						  <div class="row" style="margin-bottom: 0;">

							<div class="col-md-4" style="padding-right:2px;">
							  <select id="ageDobSelector" class="form-control" name="dob_type" required>
								<option value="age" ' . (!$has_valid_dob ? 'selected' : '') . '>Age</option>
								<option value="dob" ' . ($has_valid_dob ? 'selected' : '') . '>DOB</option>
							  </select>
							</div>

							<div class="col-md-8" style="padding-left:2px; padding-right:2px;">
							  <input type="' . ($has_valid_dob ? 'date' : 'text') . '"
									 id="ageDobInput"
									 class="form-control"
									 required>
							</div>
						  </div>

						  <!-- Hidden fields to store actual values -->
						  <input type="hidden" name="age" id="hiddenAge" value="' . htmlspecialchars($patient['age'] ?? '') . '">
						  <input type="hidden" name="dob" id="hiddenDob" value="' . ($has_valid_dob ? htmlspecialchars($patient['dob']) : '') . '">
						</div>
						',

					'area' => '<label class="form-label"><span style="color: #f00">* </span>' . _l('area') . '</label>
					<input type="text" class="form-control" name="area" placeholder="' . _l('enter_area') . '" value="' . htmlspecialchars($patient['area'] ?? '') . '" required>',
				

				'contact_number' => '
					<div class="form-group">
					  <label class="form-label"><span style="color: #f00">* </span>' . _l('contact_number') . '</label>
					  <div class="row no-gutters" style="margin-bottom: 0;">
						<div class="col-md-4" style="padding-right:2px;">
						  <select name="calling_code" id="calling_code" class="form-control selectpicker" data-live-search="true" required>
							<option value="">' . _l('select_country_code') . '</option>'
							. array_reduce(get_all_countries(), function ($options, $country) use ($lead) {
								$selected = isset($lead) && $lead->country == $country['country_id']
								  ? 'selected'
								  : ($country['short_name'] == 'India' ? 'selected' : '');
								return $options . '<option value="' . $country['calling_code'] . '" ' . $selected . '>
								  +' . $country['calling_code'] . ' (' . $country['short_name'] . ')
								</option>';
							  }, '') . '
						  </select>
						</div>

						<div class="col-md-8" style="padding-left:2px;">
						  <input type="text" class="form-control" name="contact_number" placeholder="' . _l('enter_contact_number') . '"
						  value="' . htmlspecialchars($patient['phonenumber'] ?? $contact_number) . '" required readonly>
						</div>
					  </div>
					</div>
					',
				'alt_number1' => '<label class="form-label">' . _l('alternative_number1') . '</label>
					<input type="text" class="form-control" name="alt_number1" placeholder="' . _l('enter_alt_number1') . '" value="' . htmlspecialchars($patient['alt_number1'] ?? '') . '">
					<input type="hidden" name="alt_number2" value="0">',
				'email_id' => '<label class="form-label">' . _l('email') . '</label>
					<input type="text" class="form-control" name="email_id" placeholder="' . _l('enter_email') . '" value="' . htmlspecialchars($patient['email_id'] ?? '') . '">',
				'marital_status' => render_select(
					'marital_status',
					[
						['id' => 'Single', 'name' => _l('single')],
						['id' => 'Married', 'name' => _l('married')],
						['id' => 'Divorced', 'name' => _l('divorced')],
						['id' => 'Widowed', 'name' => _l('widowed')],
					],
					['id', 'name'],
					_l('marital_status'),
					$patient['marital_status'] ?? '',
					['data-none-selected-text' => _l('dropdown_non_selected_tex')]
				),

				'language_select' => '
					<div class="form-group">
					  <label class="form-label"><span style="color: #f00">*</span> ' . _l('speaking_languages') . '</label>
					  <div class="row" style="margin-bottom: 0;">
						<div class="col-md-12" style="padding-right: 0;">
						  ' . render_select(
							'default_language[]',
							$languages,
							['languages_name', 'languages_name'],
							'',
							$selected_languages,
							[
							  'multiple' => true,
							  'data-none-selected-text' => _l('dropdown_non_selected_tex'),
							  'required' => 'required'
							],
							[],
							'',
							'form-control selectpicker'
						  ) . '
						</div>
					  </div>
					</div>
				',



				'country_select' => '
				  <div class="form-group">
					<label class="form-label"><span style="color: #f00">*</span> ' . _l('country') . '</label>
					<div class="row" style="margin-bottom: 0;">
					  <div class="col-md-12" style="padding-right: 0;">
						<select name="country" id="country" class="form-control selectpicker" data-live-search="true" required>
						  <option value="">' . _l('select_country') . '</option>'
						  . implode('', array_map(function ($country) use ($lead) {
							$selected = (isset($lead) && $lead->country == $country['country_id']) || ($country['short_name'] == 'India') ? 'selected' : '';
							return '<option value="' . $country['country_id'] . '" ' . $selected . '>' . $country['short_name'] . '</option>';
						  }, get_all_countries())) .
						'</select>
					  </div>
					</div>
				  </div>
				',
				'state' => render_select(
					'state',
					$states,
					['state_id', 'state_name'],
					'<span style="color: #f00">*</span> ' . _l('state'),
					($patient['state'] ?? ''),
					[
						'data-none-selected-text' => _l('dropdown_non_selected_tex'),
						'id' => 'state',
						'required' => 'required'
					]
				),

				'city' => render_select(
					'city',
					$cities,
					['city_id', 'city_name'],
					'<span style="color: #f00">*</span> ' . _l('city'),
					($patient['city'] ?? ''),
					[
						'data-none-selected-text' => _l('dropdown_non_selected_tex'),
						'id' => 'city',
						'required' => 'required'
					]
				),

				'pincode' => render_select(
					'pincode',
					$pincodes,
					['pincode_id', 'pincode_name'],
					'<span style="color: #f00">*</span> ' . _l('pincode'),
					($patient['pincode'] ?? ''),
					[
						'data-none-selected-text' => _l('dropdown_non_selected_tex'),
						'id' => 'pincode',
						'required' => 'required'
					]
				),

				'patient_source_id' => render_select(
					'patient_source_id',
					$patient_source,
					['id', ['name']],
					'<span style="color: #f00">*</span> '._l('patient_source'),
					!empty($patient['patient_source_id']) 
						? $patient['patient_source_id'] 
						: ($enquiry_type[0]['patient_source_id'] ?? ''),
					['data-none-selected-text' => _l('dropdown_non_selected_tex'), 'required' => 'required']
				),

			];

    // Add all other active fields
    foreach ($fields_map as $key => $html) {
        if (!in_array($key, $patient_inactive_fields)) {
            $fields[] = $html;
        }
    }

    // Render fields in rows of 3 columns
    echo '<div class="row">';
foreach ($fields as $i => $fieldHtml) {
    // Start new row every 3 fields
    if ($i > 0 && $i % 3 == 0) {
        echo '</div><div class="row">';
    }
    echo '<div class="col-md-4">' . $fieldHtml . '</div>';
}
if($total_due == 0){
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
}else{
	?>
	  <small style="color:red; display:block; margin-top:30px;">
            Note: Branch change option will be visible only when there are no dues.
        </small>
<?php
}
echo '</div>'; // close last row
    ?>
	
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('ageDobSelector');
    const input = document.getElementById('ageDobInput');
    const hiddenAge = document.getElementById('hiddenAge');
    const hiddenDob = document.getElementById('hiddenDob');

    // Detect initially selected type
    function initializeInput() {
      if (selector.value === 'dob') {
        input.type = 'date';
        input.value = hiddenDob.value;
        input.max = new Date().toISOString().split('T')[0];
      } else {
        input.type = 'text';
        input.value = hiddenAge.value;
        input.placeholder = 'Enter Age';
        input.removeAttribute('max');
      }
    }

    // On selector change (dob <-> age)
    selector.addEventListener('change', function () {
      // Save current input value to hidden field before switching
      if (input.type === 'date') {
        hiddenDob.value = input.value;
      } else {
        hiddenAge.value = input.value;
      }

      // Switch input type
      if (this.value === 'dob') {
        input.type = 'date';
        input.value = hiddenDob.value;
        input.max = new Date().toISOString().split('T')[0];
        input.placeholder = '';
      } else {
        input.type = 'text';
        input.value = hiddenAge.value;
        input.placeholder = 'Enter Age';
        input.removeAttribute('max');
      }
    });

    // On input change, always update hidden field
    input.addEventListener('input', function () {
      if (selector.value === 'dob') {
        hiddenDob.value = this.value;
      } else {
        hiddenAge.value = this.value;
      }
    });

    initializeInput(); // initial render
  });
</script>


              </div>
			<br>
			  <br>
			  <br>
              <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
                <a href="<?= admin_url('client/get_patient_list'); ?>" class="btn btn-default"><?= _l('cancel'); ?></a>
              </div>
			  <br>
			  <br>
			  <br>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
$(document).ready(function() {
    $('select[name="state"]').on('change', function() {
        var state_id = $(this).val();

        $.ajax({
            url: admin_url + 'leads/get_cities_by_state',
            type: 'POST',
            data: {
                state_id: state_id,
                <?= $this->security->get_csrf_token_name(); ?>: "<?= $this->security->get_csrf_hash(); ?>"
            },
            dataType: 'json',
            success: function(response) {
                var $citySelect = $('select[name="city"]');
                $citySelect.empty().append('<option value=""><?= _l('dropdown_non_selected_tex'); ?></option>');

                $.each(response, function(i, city) {
                    $citySelect.append($('<option>', {
                        value: city.city_id,
                        text: city.city_name
                    }));
                });

                $citySelect.selectpicker('refresh');
            }
        });
    });
});
</script>
<script>
$(document).ready(function () {
  $('#state').on('change', function () {
    let stateId = $(this).val();
    if (stateId) {
      $.ajax({
        url: admin_url + 'client/get_cities_by_state/' + stateId,
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          let cityOptions = '<option value=""><?= _l('dropdown_non_selected_tex'); ?></option>';
          $.each(data, function (i, city) {
            cityOptions += '<option value="' + city.city_id + '">' + city.city_name + '</option>';
          });
          $('#city').html(cityOptions);
          $('#city').selectpicker('refresh'); // Required for Bootstrap select
        },
        error: function () {
          alert('Failed to fetch cities');
        }
      });
    } else {
      $('#city').html('<option value=""><?= _l('dropdown_non_selected_tex'); ?></option>').selectpicker('refresh');
    }
  });
});
</script>
<script>
$(document).ready(function () {
  $('#city').on('change', function () {
    let cityId = $(this).val();
    if (cityId) {
      $.ajax({
        url: admin_url + 'client/get_pincodes_by_city/' + cityId,
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          let pincodeOptions = '<option value=""><?= _l('dropdown_non_selected_tex'); ?></option>';
          $.each(data, function (i, pin) {
            pincodeOptions += '<option value="' + pin.pincode_id + '">' + pin.pincode_name + '</option>';
          });
          $('#pincode').html(pincodeOptions).selectpicker('refresh');
        },
        error: function () {
          alert('Failed to fetch pincodes');
        }
      });
    } else {
      $('#pincode').html('<option value=""><?= _l('dropdown_non_selected_tex'); ?></option>').selectpicker('refresh');
    }
  });
});
</script>

</body>
</html>
