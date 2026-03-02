<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

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

                        <form method="post" action="<?= admin_url('mis_reports/reports/' . $type); ?>">
                            <div class="row align-items-end">

                                <!-- From Date -->
                                <div class="col-md-3">
                                    <?php
                                    $posted_date = $this->input->post('consulted_date');
                                    $default_date = date('Y-m-d');
                                    $consulted_date_value = $posted_date ? $posted_date : $default_date;
                                    ?>
                                    <label for="consulted_date" class="control-label">
                                        <?= _l('from_date'); ?>
                                    </label>
                                    <input class="form-control" type="date" id="consulted_date" name="consulted_date"
                                        value="<?= html_escape($consulted_date_value) ?>">
                                </div>

                                <!-- To Date -->
                                <div class="col-md-3">
                                    <?php
                                    $posted_date = $this->input->post('consulted_to_date');
                                    $consulted_to_date_value = $posted_date ? $posted_date : $default_date;
                                    ?>
                                    <label for="consulted_to_date" class="control-label">
                                        <?= _l('to_date'); ?>
                                    </label>
                                    <input class="form-control" type="date" id="consulted_to_date"
                                        name="consulted_to_date" value="<?= html_escape($consulted_to_date_value) ?>">
                                </div>

                                <!-- Submit -->
                                <div class="col-md-3">
                                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
                                        value="<?= $this->security->get_csrf_hash(); ?>" />
                                    <br>
                                    <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 5px;">
                                        <?= _l('search'); ?>
                                    </button>
                                </div>

                            </div>
                        </form>

                        <br>
                        <?= render_datatable([
                            _l('branch_name'),
                            _l('total_renewals'),
                            _l('active_renewals'),
                            _l('inactive'),
                            _l('renewals'),
                            _l('package_amount'),
                        ], 'new_renewal_report'); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function () {
        let fromDate = $('#consulted_date').val() || 'null';
        let toDate = $('#consulted_to_date').val() || 'null';

        let url = '<?= admin_url("mis_reports/reports/$type/1/") ?>'
            + fromDate + '/'
            + toDate + '/null/null/null';

        initDataTable('.table-new_renewal_report', url, [1], [1]);
    });
</script>

</body>

</html>