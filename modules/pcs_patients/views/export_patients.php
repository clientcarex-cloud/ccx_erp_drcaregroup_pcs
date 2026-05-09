<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .export-page-wrapper {
        max-width: 720px;
        margin: 30px auto;
    }
    .export-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .export-card-header {
        background: linear-gradient(135deg, #1B8B5A 0%, #27ae60 100%);
        padding: 28px 32px;
        color: #fff;
    }
    .export-card-header h3 {
        margin: 0 0 6px 0;
        font-size: 22px;
        font-weight: 600;
    }
    .export-card-header p {
        margin: 0;
        font-size: 14px;
        opacity: 0.85;
    }
    .export-card-body {
        padding: 32px;
    }
    .export-field-group {
        margin-bottom: 22px;
    }
    .export-field-group label {
        font-weight: 600;
        font-size: 13px;
        color: #333;
        margin-bottom: 6px;
        display: block;
    }
    .export-field-group label .required-star {
        color: #e74c3c;
    }
    .export-field-group .form-control {
        border-radius: 6px;
        border: 1.5px solid #dce1e6;
        height: 42px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    .export-field-group .form-control:focus {
        border-color: #27ae60;
        box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
    }
    .export-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    .btn-export {
        background: linear-gradient(135deg, #1B8B5A 0%, #27ae60 100%);
        color: #fff;
        border: none;
        padding: 12px 32px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-export:hover {
        background: linear-gradient(135deg, #157a4a 0%, #219a52 100%);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(27, 139, 90, 0.3);
    }
    .btn-export:active {
        transform: translateY(0);
    }
    .btn-export i {
        font-size: 16px;
    }
    .btn-back {
        color: #666;
        font-size: 14px;
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1.5px solid #dce1e6;
        transition: all 0.2s;
    }
    .btn-back:hover {
        background: #f5f5f5;
        color: #333;
        text-decoration: none;
    }
    .export-info-box {
        background: #f0faf4;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 14px 18px;
        margin-bottom: 24px;
        font-size: 13px;
        color: #155724;
        line-height: 1.5;
    }
    .export-info-box i {
        margin-right: 6px;
        color: #27ae60;
    }
    .export-columns-preview {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 16px 20px;
        margin-top: 20px;
    }
    .export-columns-preview h5 {
        font-size: 13px;
        font-weight: 600;
        color: #555;
        margin: 0 0 10px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .export-columns-preview .columns-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .export-columns-preview .col-tag {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 3px 10px;
        font-size: 11px;
        color: #495057;
        white-space: nowrap;
    }
</style>

<div id="wrapper">
    <div class="content">
        <div class="export-page-wrapper">

            <div class="export-card">
                <div class="export-card-header">
                    <h3><i class="fa fa-file-excel-o" style="margin-right: 8px;"></i> Export Patients Data</h3>
                    <p>Download complete patient records as an Excel file — zero missing data</p>
                </div>

                <div class="export-card-body">

                    <div class="export-info-box">
                        <i class="fa fa-info-circle"></i>
                        Select a date range to export all patient records registered within that period.
                        The export includes <strong>45 columns</strong> covering demographics, contact info, clinical data, call logs, invoices, payments, and profile links.
                    </div>

                    <?php echo form_open(admin_url('pcs_patients/export_patients'), ['id' => 'export-form']); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="export-field-group">
                                    <label>From Date <span class="required-star">*</span></label>
                                    <input type="date" class="form-control" id="from_date" name="from_date" required
                                        value="<?= date('Y-m-01'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-field-group">
                                    <label>To Date <span class="required-star">*</span></label>
                                    <input type="date" class="form-control" id="to_date" name="to_date" required
                                        value="<?= date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="export-field-group">
                            <label>Branch (Optional)</label>
                            <?php
                            echo render_select(
                                'branch_export[]',
                                $branch,
                                ['id', ['name']],
                                '',
                                '',
                                [
                                    'multiple' => true,
                                    'data-actions-box' => true,
                                    'data-none-selected-text' => 'All Branches',
                                ]
                            );
                            ?>
                        </div>

                        <!-- Hidden field to send branch_ids as comma-separated -->
                        <input type="hidden" name="branch_ids" id="branch_ids_hidden" value="">

                        <div class="export-columns-preview">
                            <h5><i class="fa fa-columns" style="margin-right: 4px;"></i> Columns in Export</h5>
                            <div class="columns-grid">
                                <?php
                                $cols = [
                                    'Patient ID', 'Name', 'Salutation', 'MR No', 'Branch',
                                    'Age', 'Gender', 'DOB', 'Marital Status',
                                    'Phone', 'WhatsApp', 'Alt Number 1', 'Alt Number 2',
                                    'Email', 'Address', 'City', 'State', 'Pincode', 'Area',
                                    'Source', 'Treatment', 'Doctor', 'Registered By',
                                    'Reg. Start', 'Reg. End', 'Date Created',
                                    'Current Status', 'Patient Status', 'Journey Status',
                                    'Last Call Date', 'Next Call Date', 'Call Comments',
                                    'Invoice Count', 'Gross Total', 'Net Total',
                                    'Total Paid', 'Due Amount', 'Payment Count',
                                    'First Invoice', 'Last Invoice', 'Last Payment',
                                    'Is Refunded', 'Profile Link'
                                ];
                                foreach ($cols as $c) {
                                    echo '<span class="col-tag">' . $c . '</span>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="export-actions">
                            <button type="submit" class="btn-export" id="exportBtn">
                                <i class="fa fa-download"></i> Export to Excel
                            </button>
                            <a href="<?= admin_url('pcs_patients'); ?>" class="btn-back">
                                <i class="fa fa-arrow-left" style="margin-right: 4px;"></i> Back to Patients
                            </a>
                        </div>

                    <?php echo form_close(); ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function () {
    // Sync branch multi-select into hidden field before submit
    $('#export-form').on('submit', function () {
        var branchSelect = $('select[name="branch_export[]"]');
        var selected = branchSelect.val();
        var branchIds = (selected && selected.length > 0) ? selected.join(',') : '';
        $('#branch_ids_hidden').val(branchIds);

        // Validate dates
        var from = $('#from_date').val();
        var to = $('#to_date').val();
        if (!from || !to) {
            alert('Please select both From Date and To Date.');
            return false;
        }
        if (from > to) {
            alert('From Date cannot be after To Date.');
            return false;
        }

        // Show loading state
        var $btn = $('#exportBtn');
        $btn.prop('disabled', true);
        $btn.html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        // Re-enable after 10 seconds (download should have started)
        setTimeout(function () {
            $btn.prop('disabled', false);
            $btn.html('<i class="fa fa-download"></i> Export to Excel');
        }, 10000);

        return true;
    });
});
</script>
