<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?= _l($title); ?> - <?= htmlspecialchars(ucwords(str_replace('_', ' ', $cell_type))); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        
                        <?php
                        $columns = [
                            _l('Patient Name'),
                            _l('Mr. No'),
                            _l('Source'),
                            _l('Treatment'),
                            _l('Appt. Type'),
                            _l('Created Date'),
                            _l('First Visit Date'),
                            _l('Appointment Date'),
                            _l('Visited Date'),
                            _l('Consulted Date'),
                            _l('Doctor'),
                            _l('Registration Date'),
                            _l('Package Amount'),
                            _l('Paid Amount'),
                            _l('Payment Date'),
                            _l('Payment Type'),
                        ];
                        
                        render_datatable($columns, 'gt-report-details');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function () {
        var ajaxUrl = '<?= admin_url("mis_reports/reports/gt_report_details/" . $branch_id . "/" . $from_date . "/" . $to_date . "/" . $cell_type); ?>';
        initDataTable('.table-gt-report-details', ajaxUrl, [], []);
    });
</script>
</body>
</html>
