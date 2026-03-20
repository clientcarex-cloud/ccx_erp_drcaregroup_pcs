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
                            <?= _l($title); ?>&emsp;
                        </h4>

                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        <form method="post" id="referral-filter-form"
                            action="<?= admin_url('mis_reports/patient_referral_reward_report'); ?>">
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
                                    <br>
                                    <button type="button" id="searchBtn" class="btn btn-success" style="width: 100%;margin-top: 5px">
                                        <?= _l('search'); ?>
                                    </button>
                                </div>

                            </div>
                        </form>
                        <br>
                        <?= render_datatable([
                            '#',
                            _l('lead_name'),
                            _l('lead_add_edit_phonenumber'),
                            _l('lead_add_edit_status'),
                            _l('referrer_patient_name'),
                            _l('branch'),
                            _l('leads_dt_datecreated'),
                        ], 'patient-referral-reward'); ?>

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
        var to   = $('#consulted_to_date').val();
        var baseUrl = '<?= admin_url("mis_reports/patient_referral_reward_report/") ?>';
        initDataTable('.table-patient-referral-reward', baseUrl + from + '/' + to, [0], [0]);

        $('#searchBtn').on('click', function () {
            var from = $('#consulted_date').val();
            var to   = $('#consulted_to_date').val();
            if ($.fn.DataTable.isDataTable('.table-patient-referral-reward')) {
                $('.table-patient-referral-reward').DataTable().ajax.url(baseUrl + from + '/' + to).load();
            } else {
                initDataTable('.table-patient-referral-reward', baseUrl + from + '/' + to, [0], [0]);
            }
        });
    });
</script>

</body>
</html>
