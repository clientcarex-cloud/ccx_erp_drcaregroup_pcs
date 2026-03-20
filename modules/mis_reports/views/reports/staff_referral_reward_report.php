<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup { font-size: 1.6rem !important; }
</style>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">
<div class="panel_s">
<div class="panel-body">
<h4 class="no-margin">
    Staff Referral Reward Report
</h4>

<hr class="hr-panel-heading" />
<div class="clearfix"></div>
<form method="post" id="reportFilterForm">
<div class="row align-items-end">

    <!-- Branch (Multi-Select) -->
    <div class="col-md-2">
        <?php
        $selected_branches = isset($branch_id) ? [$branch_id] : [];
        echo render_select(
            'branch[]',
            $branch,
            ['id', ['name']],
            '<span style="color:red;">*</span> ' . _l('lead_branch'),
            $selected_branches,
            [
                'data-none-selected-text' => _l('dropdown_non_selected_tex'),
                'multiple' => true,
                'data-actions-box' => true,
            ]
        );
        ?>
    </div>

    <!-- From Date -->
    <div class="col-md-2">
        <label for="from_date" class="control-label">From Date</label>
        <input class="form-control" type="date" id="from_date" name="from_date" value="<?= date('Y-m-d') ?>">
    </div>

    <!-- To Date -->
    <div class="col-md-2">
        <label for="to_date" class="control-label">To Date</label>
        <input class="form-control" type="date" id="to_date" name="to_date" value="<?= date('Y-m-d') ?>">
    </div>

    <!-- Submit -->
    <div class="col-md-2">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>" />
        <br>
        <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 5px;">Search</button>
    </div>

</div>
</form>

<?php echo render_datatable([
    '#',
    'Referrer Staff',
    'Lead Name',
    'Lead Phone',
    'Lead Email',
    'Lead Source',
    'Lead Status',
    'Lead Created Date',
    'Converted',
    'Patient Name',
    'MR No',
], 'staff-referral-reward'); ?>

</div>
</div>
</div>
</div>
</div>
</div>

<?php init_tail(); ?>

<script>
$(function(){
    $('.selectpicker').selectpicker();
    initReportTable();

    $('#reportFilterForm').on('submit', function(e) {
        e.preventDefault();
        initReportTable();
    });
});

function initReportTable() {
    var fromDate = $('#from_date').val() || '';
    var toDate = $('#to_date').val() || '';

    var branchSelect = $('select[name="branch[]"]');
    var branchIds = branchSelect.val() && branchSelect.val().length > 0 ?
                   encodeURIComponent(branchSelect.val().join(',')) :
                   '';

    var url = '<?= admin_url("mis_reports/staff_referral_reward_report") ?>'
        + '?from_date=' + encodeURIComponent(fromDate)
        + '&to_date=' + encodeURIComponent(toDate)
        + '&branch=' + branchIds;

    if ($.fn.DataTable.isDataTable('.table-staff-referral-reward')) {
        $('.table-staff-referral-reward').DataTable().destroy();
    }

    initDataTable('.table-staff-referral-reward', url, undefined, undefined, undefined, [0, 'asc']);
}
</script>

</body>
</html>
