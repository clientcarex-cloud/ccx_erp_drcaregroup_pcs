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
                            action="<?= admin_url('mis_reports/reports/date_to_date_call_logs_feedback'); ?>">
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
                            _l('called_by'),
                            _l('call_type'),
                            _l('next_calling_date'),
                            _l('better_patient'),
                            _l('pharmacy_medicine_days'),
                            _l('patient_took_medicine_days'),
                            _l('created_date'),
                            _l('comments'),
                        ], 'call-logs-feedback'); ?>

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
        initDataTable('.table-call-logs-feedback', '<?= admin_url("mis_reports/reports/$type/1/") ?>' + from + '/' + to, [0], [0]);
    });

    $(function () {
        // Search Button Click (form submit reloads, but also support AJAX reload)
        $('#searchBtn').on('click', function () {
            var from = $('#consulted_date').val();
            var to = $('#consulted_to_date').val();
            if ($.fn.DataTable.isDataTable('.table-call-logs-feedback')) {
                $('.table-call-logs-feedback').DataTable().ajax.url(
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