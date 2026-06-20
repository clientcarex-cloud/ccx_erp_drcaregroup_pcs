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
        flex-wrap: wrap;
    }
    .btn-export {
        color: #fff;
        border: none;
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-export:hover {
        color: #fff;
        transform: translateY(-1px);
    }
    .btn-export:active {
        transform: translateY(0);
    }
    .btn-export-excel {
        background: linear-gradient(135deg, #1B8B5A 0%, #27ae60 100%);
    }
    .btn-export-excel:hover {
        background: linear-gradient(135deg, #157a4a 0%, #219a52 100%);
        box-shadow: 0 4px 12px rgba(27, 139, 90, 0.3);
    }
    .btn-export-json {
        background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
    }
    .btn-export-json:hover {
        background: linear-gradient(135deg, #1f6fa0 0%, #2a80b9 100%);
        box-shadow: 0 4px 12px rgba(41, 128, 185, 0.3);
    }
    .btn-export-zip {
        background: linear-gradient(135deg, #d35400 0%, #e67e22 100%);
    }
    .btn-export-zip:hover {
        background: linear-gradient(135deg, #b8470b 0%, #cf6f17 100%);
        box-shadow: 0 4px 12px rgba(211, 84, 0, 0.3);
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
                    <h3><i class="fa fa-download" style="margin-right: 8px;"></i> Export Patients Data</h3>
                    <p>Download complete patient records — zero missing data</p>
                </div>

                <div class="export-card-body">

                    <div class="export-info-box">
                        <i class="fa fa-info-circle"></i>
                        Select a date range to export all patient records registered within that period.
                        The export includes <strong>45 columns</strong> covering demographics, contact info, clinical data, call logs, invoices, payments, and profile links.
                        <br><strong>Export All Branches (ZIP)</strong> generates a separate file for every branch automatically and bundles them into a single ZIP — leave the branch filter empty for all branches, or pick specific ones.
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

                        <!-- Hidden fields -->
                        <input type="hidden" name="branch_ids" id="branch_ids_hidden" value="">
                        <input type="hidden" name="export_format" id="export_format" value="excel">

                        <div class="export-columns-preview">
                            <h5><i class="fa fa-columns" style="margin-right: 4px;"></i> Columns in Export</h5>
                            <div class="columns-grid">
                                <?php
                                $cols = array(
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
                                );
                                foreach ($cols as $c) {
                                    echo '<span class="col-tag">' . $c . '</span>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="export-actions">
                            <button type="button" class="btn-export btn-export-excel" id="exportExcelBtn">
                                <i class="fa fa-file-excel-o"></i> Export Excel (CSV)
                            </button>
                            <button type="button" class="btn-export btn-export-zip" id="exportZipBtn">
                                <i class="fa fa-file-archive-o"></i> Export All Branches (ZIP)
                            </button>
                            <button type="button" class="btn-export btn-export-json" id="exportJsonBtn">
                                <i class="fa fa-code"></i> Export JSON
                            </button>
                            <a href="<?= admin_url('pcs_patients'); ?>" class="btn-back">
                                <i class="fa fa-arrow-left" style="margin-right: 4px;"></i> Back
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
    /**
     * Validate form and prepare hidden fields before submission.
     * Returns true if valid, false otherwise.
     */
    function validateAndPrepare() {
        // Sync branch selection into the hidden field
        var branchSelect = $('select[name="branch_export[]"]');
        var selected = branchSelect.val();
        var branchIds = (selected && selected.length > 0) ? selected.join(',') : '';
        $('#branch_ids_hidden').val(branchIds);

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
        return true;
    }

    /**
     * Handle export button click — set format FIRST, then validate & submit.
     */
    function handleExport(format, $btn) {
        // Set format before anything else
        $('#export_format').val(format);

        if (!validateAndPrepare()) {
            return;
        }

        // Show loading state on the clicked button
        var originalHtml = $btn.html();
        var loadingText = (format === 'zip')
            ? '<i class="fa fa-spinner fa-spin"></i> Building branch files...'
            : '<i class="fa fa-spinner fa-spin"></i> Generating...';
        $btn.prop('disabled', true).html(loadingText);
        $('#exportExcelBtn, #exportZipBtn, #exportJsonBtn').not($btn).prop('disabled', true);

        // Submit the form
        $('#export-form')[0].submit();

        // Re-enable buttons after a delay (file download is streamed, page doesn't navigate).
        // ZIP builds one file per branch, so give it more time before resetting.
        setTimeout(function () {
            $btn.prop('disabled', false).html(originalHtml);
            $('#exportExcelBtn, #exportZipBtn, #exportJsonBtn').prop('disabled', false);
        }, format === 'zip' ? 20000 : 8000);
    }

    $('#exportExcelBtn').on('click', function () {
        handleExport('excel', $(this));
    });

    $('#exportZipBtn').on('click', function () {
        handleExport('zip', $(this));
    });

    $('#exportJsonBtn').on('click', function () {
        handleExport('json', $(this));
    });
});
</script>
