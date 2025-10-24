<?php init_head(); ?>
<style>
    .dropzone {
        min-height: 0px !important;
    }

    .img-container img {
        max-width: 100%;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="clearfix"></div>
            <div class="tw-mt-12 sm:tw-mt-0 col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tab-content">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                                        <div class="scroller arrow-left" style="display: none;"><i
                                                class="fa fa-angle-left"></i></div>
                                        <div class="scroller arrow-right" style="display: none;"><i
                                                class="fa fa-angle-right"></i></div>
                                        <div class="horizontal-tabs">
                                            <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                                <li role="presentation" class="active">
                                                    <a href="#banner_image" role="tab" data-toggle="tab">
                                                        <?php echo _l('banner_image'); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="tab-content mtop15">
                                        <div role="tabpanel" class="tab-pane active" id="banner_image">
                                            <div class="alert alert-info alert-dismissible alert-dismissible-2">
                                                <a href="#" class="close" data-dismiss="alert"
                                                    aria-label="close">&times;</a>
                                                <span>
                                                    <?php echo _l('banner_display_message'); ?>
                                                </span>
                                            </div>
                                            <?php echo form_open_multipart($this->uri->uri_string(), ['id' => 'banner-image-form'], ['id' => $banner_image->id ?? '']); ?>
                                            <div class="row">
                                                <?php echo render_input('title', 'banner_title', $banner_image->title ?? '', 'text', [], [], 'col-md-12'); ?>
                                                <?php echo render_date_input('start_date', 'start_date', isset($banner_image->start_date) ? _d($banner_image->start_date) : '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                                                <?php echo render_date_input('end_date', 'end_date', isset($banner_image->end_date) ? _d($banner_image->end_date) : '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                                                <div class="col-md-12">
                                                    <div class="dropzone dropzone-manual dropzone-banner">
                                                        <label for="" class="form-label">
                                                            <?php echo _l('image'); ?>
                                                        </label>
                                                        <div id="dropzoneDragArea" class="dz-default dz-message">
                                                            <span>
                                                                <?php echo _l('add_banner_image'); ?>
                                                            </span>
                                                        </div>
                                                        <div class="dropzone-previews"></div>
                                                        <span class="text-muted">->
                                                            <?php echo _l('allowed_extension_note_for_banner'); ?>
                                                        </span><br>
                                                        <span class="text-muted">->
                                                            <?php echo _l('recommended_banner_image_is', '1600 x 300'); ?>
                                                        </span>
                                                        <?php if (isset($banner_image)) { ?>
                                                            <img src="<?php echo base_url('uploads/banner/') . $banner_image->detail; ?>"
                                                                class="img img-responsive mtop10">
                                                            <a class="btn btn-danger mtop10 pull-right"
                                                                onclick="openCropImagePopup()"><i
                                                                    class="fa-solid fa-crop"></i>
                                                                <?php echo _l('crop_and_save'); ?></a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr />
                                            <div class="row">
											<div class="col-md-12">
                                                    <div class="panel_s">
                                                        <div class="panel-body">
                                                            <p class="tw-text-lg tw-font-medium">
                                                                <?php echo _l('branch'); ?>
                                                            </p>
                                                            <?php echo render_select('branch_id', $branches, ['id', 'name'], 'select_branch', (isset($banner_image) && is_serialized($banner_image->branch_id)) ? unserialize($banner_image->branch_id) : '', ['data-actions-box' => true, 'multiple' => false], [], '', '', false); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                               <div class="col-md-12">
													<div class="panel_s">
														<div class="panel-body">
															<p class="tw-text-lg tw-font-medium">
																<?php echo _l('admin_area'); ?>
															</p>

															<!-- Role Select -->
															<div class="col-md-6">
																<?php
																echo render_select(
																	'roleid[]',
																	$roles,
																	['roleid', 'name'],
																	'select_role',
																	(isset($banner_image) && is_serialized($banner_image->roleid)) ? unserialize($banner_image->roleid) : '',
																	['data-actions-box' => true, 'multiple' => true, 'class' => 'selectpicker', 'id' => 'role-select']
																);
																?>
															</div>

															<!-- Staff Select -->
															<div class="col-md-6">
    <?php
																echo render_select(
																	'staff_ids[]',
																	$staff,
																	['staffid', ['firstname', 'lastname']],
																	'select_staff_members',
																	(isset($banner_image) && is_serialized($banner_image->staff_ids)) ? unserialize($banner_image->staff_ids) : '',
																	['data-actions-box' => true, 'multiple' => true, 'class' => 'selectpicker', 'id' => 'staff-select']
																);
																?>
</div>








														</div>
													</div>
												</div>
                                                <div class="col-md-12">
                                                    <div class="panel_s">
                                                        <div class="panel-body">
                                                            <p class="tw-text-lg tw-font-medium">
                                                                <?php echo _l('clients_area'); ?>
                                                            </p>
                                                            <?php echo render_select('client_ids[]', $clients, ['userid', 'company'], 'select_clients', (isset($banner_image) && is_serialized($banner_image->client_ids)) ? unserialize($banner_image->client_ids) : '', ['data-actions-box' => true, 'multiple' => true], [], '', '', false); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label><?php echo _l('has_action'); ?></label>
                                                    <div class="onoffswitch" data-toggle="tooltip" data-title="<?php echo _l('has_action'); ?>">
                                                        <input type="checkbox" name="has_action" class="onoffswitch-checkbox" id="has_action" <?= (isset($banner_image) && $banner_image->has_action == '1') ? 'checked' : ''; ?>>
                                                        <label class="onoffswitch-label" for="has_action"></label>
                                                    </div>
                                                </div>
                                                <div class="has_action hide">
                                                    <?= render_input('action_label', 'action_label', $banner_image->action_label ?? '', '', [], [], 'col-md-6 mtop10'); ?>
                                                    <div class="col-md-6 mtop10">
                                                        <?= render_color_picker('label_color', _l('label_color'), $banner_image->label_color ?? ''); ?>
                                                    </div>
                                                    <?= render_input('action_url', 'action_url', $banner_image->action_url ?? '', '', [], [], 'col-md-12'); ?>
                                                    <div class="col-md-12">
                                                        <label><?php echo _l('open_new_tab'); ?></label>
                                                        <div class="onoffswitch" data-toggle="tooltip" data-title="<?php echo _l('open_new_tab'); ?>">
                                                            <input type="checkbox" name="action_target" class="onoffswitch-checkbox" id="action_target" <?= (isset($banner_image) && $banner_image->action_target == '1') ? 'checked' : ''; ?>>
                                                            <label class="onoffswitch-label" for="action_target"></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit" class="btn btn-primary" id="save-banner-image">
                            <?php echo _l('save'); ?>
                        </button>
                    </div>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal -->
<div id="crop_image_modal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-xxl">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _l('crop_image'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <?php if (isset($banner_image)) { ?>
                        <img src="<?php echo base_url('uploads/banner/') . $banner_image->detail; ?>"
                            class="img img-responsive mtop10" id="image"
                            data-imagename="<?php echo $banner_image->detail; ?>">
                    <?php } ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="cropButton"><?php echo _l('save'); ?></button>
            </div>
        </div>

    </div>
</div>

<?php init_tail(); ?>

<script>
    function openCropImagePopup() {
        const cropImagePopup = $('#crop_image_modal');

        cropImagePopup.modal('show');

        const image = document.getElementById('image');
        var cropBoxData;
        var canvasData;
        var cropper;

        cropper = new Cropper(image, {
            viewMode: 2,
            aspectRatio: 1600 / 300,
            minContainerWidth: 1465,
            minContainerHeight: 300,
            ready: function () {
                cropper.setCropBoxData(cropBoxData).setCanvasData(canvasData);
            },
        });

        document.getElementById('cropButton').addEventListener('click', function () {
            const croppedCanvas = cropper.getCroppedCanvas({
              width: 1600,
              height: 300,
            });

            const croppedImageDataURL = croppedCanvas.toDataURL('image/jpeg');

            $.ajax({
                type: 'POST',
                url: `${admin_url}banner/saveCroppedImage`,
                data: {
                    image: croppedImageDataURL,
                    image_name: $('#image').data('imagename'),
                },
                dataType: 'json',
            }).done(function (res) {
              cropBoxData = cropper.getCropBoxData();
              canvasData = cropper.getCanvasData();
              cropper.destroy();
              cropImagePopup.modal('hide');
              window.location.reload();
            });
        });
    }

    $(function() {
        <?php if (isset($banner_image)): ?>
            $('#has_action').trigger('change');
            $('#has_news_ticker').trigger('change');
        <?php endif; ?>
    });
</script>

<script>
    const roleStaffMap = <?php echo $role_staff_map; ?>;

    $(document).ready(function () {
        $('select[name="roleid[]"]').on('change', function () {
            const selectedRoles = $(this).val() || [];
            let staffToSelect = new Set();

            selectedRoles.forEach(roleId => {
                if (roleStaffMap[roleId]) {
                    roleStaffMap[roleId].forEach(staffId => {
                        staffToSelect.add(staffId);
                    });
                }
            });

            // Select the relevant staff members
            const staffSelect = $('select[name="staff_ids[]"]');
            staffSelect.val([...staffToSelect]);
            staffSelect.selectpicker('refresh');
        });
    });
</script>
<script>
 $(function () {
    $('select[name="branch_id"]').on('change', function () {
        var selectedBranches = $(this).val(); // Get selected branch IDs

        $.ajax({
            url: admin_url + 'banner/get_staff_by_branch',
            type: 'POST',
            data: { branch_ids: selectedBranches },
            dataType: 'json',
            success: function (data) {
                console.log('Staff data received:', data);

                // Target the select element
                var staffSelect = $('select[name="staff_ids[]"]'); // If you used the 'staff-select' ID in PHP
                // Or, use $('select[name="staff_ids[]"]') if you kept the original name

                staffSelect.empty(); // Clear existing options

                // Loop through the staff data and append new options
                $.each(data, function (i, staff) {
                    var name = staff.full_name || (staff.firstname + ' ' + staff.lastname);
                    staffSelect.append($('<option>', {
                        value: staff.staffid,
                        text: name
                    }));
                });

                console.log('Updated options:', staffSelect.html()); // Check the updated options

                // Refresh the selectpicker to update the UI
                staffSelect.selectpicker('refresh');
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.log('Response Text:', xhr.responseText);
            }
        });
    });
});



</script>


