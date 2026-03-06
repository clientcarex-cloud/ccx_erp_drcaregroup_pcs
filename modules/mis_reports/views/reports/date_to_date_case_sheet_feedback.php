<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .swal2-popup {
        font-size: 1.6rem !important;
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
                        <form method="post"
                            action="<?= admin_url('mis_reports/reports/date_to_date_case_sheet_feedback'); ?>">
                            <div class="row align-items-end">

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

                                <div class="col-md-2">
                                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>"
                                        value="<?= $this->security->get_csrf_hash(); ?>" />
                                    <br>
                                    <button type="submit" class="btn btn-success" style="width: 100%;margin-top: 5px">
                                        <?= _l('search'); ?>
                                    </button>
                                </div>

                            </div>
                        </form>
                        <br>
                        <?= render_datatable([
                            '#',
                            _l('patient_name'),
                            _l('mr_no'),
                            _l('consulted_date'),
                            _l('doctor_name'),
                            _l('clinical_observation'),
                            _l('suggested_diagnostics'),
                            _l('medicine_period'),
                            _l('pharmacy_medicine_days'),
                            _l('prescription'),
                            _l('action'),
                        ], 'casesheet-feedback'); ?>

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function () {
        var from = $('#consulted_date').val();
        var to = $('#consulted_to_date').val();
        initDataTable('.table-casesheet-feedback', '<?= admin_url("mis_reports/reports/$type/1/") ?>' + from + '/' + to, [0], [0]);
    });

    $(function () {
        $('#searchBtn').on('click', function () {
            var from = $('#consulted_date').val();
            var to = $('#consulted_to_date').val();
            if ($.fn.DataTable.isDataTable('.table-casesheet-feedback')) {
                $('.table-casesheet-feedback').DataTable().ajax.url(
                    '<?= admin_url("mis_reports/reports/$type/1/") ?>' + from + '/' + to
                ).load();
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
                    backdrop: 'static',
                    keyboard: false
                });
    <?php endif; ?>
});
</script>

</body>

</html>