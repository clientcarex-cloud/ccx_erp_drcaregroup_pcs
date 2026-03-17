<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .goals-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .goals-table th,
    .goals-table td {
        border: 1px solid #ddd;
        padding: 8px 10px;
        text-align: center;
        font-size: 13px;
    }
    .goals-table thead th {
        background: #34495e;
        color: #fff;
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .goals-table thead th.month-header {
        background: #2c3e50;
    }
    .goals-table tbody td:first-child {
        background: #f7f9fa;
        font-weight: 600;
        text-align: left;
        white-space: nowrap;
    }
    .goals-table tbody tr:hover {
        background: #eef5fc;
    }
    .goals-table input[type="number"] {
        width: 90px;
        padding: 4px 6px;
        border: 1px solid #ccc;
        border-radius: 3px;
        text-align: right;
        font-size: 13px;
        transition: border-color 0.2s;
    }
    .goals-table input[type="number"]:focus {
        border-color: #3498db;
        outline: none;
        box-shadow: 0 0 3px rgba(52, 152, 219, 0.3);
    }
    .goal-type-label {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .goal-type-gt { background: #d4edda; color: #155724; }
    .goal-type-enquiry { background: #cce5ff; color: #004085; }
    .goal-type-renewal { background: #fff3cd; color: #856404; }
    .filter-bar {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-bottom: 5px;
    }
    .filter-bar .form-group { margin-bottom: 0; }
    .save-status {
        display: inline-block;
        margin-left: 10px;
        font-weight: 600;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .save-status.show { opacity: 1; }
    .save-status.success { color: #27ae60; }
    .save-status.error { color: #e74c3c; }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-bullseye" style="color:#e67e22;"></i>
                            <?= _l($title); ?>
                            <a href="<?= admin_url('mis_reports/reports/mis_reports'); ?>" class="btn btn-default btn-sm pull-right">
                                <i class="fa fa-arrow-left"></i> Back to Reports
                            </a>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <!-- Filters -->
                        <div class="filter-bar">
                            <div class="form-group" style="min-width:220px;">
                                <label>Branch <span style="color:red;">*</span></label>
                                <?php
                                echo render_select(
                                    'goal_branch',
                                    $branch,
                                    ['id', ['name']],
                                    '',
                                    '',
                                    ['required' => 'required']
                                );
                                ?>
                            </div>

                            <div class="form-group">
                                <label>Year</label>
                                <select class="form-control selectpicker" id="goal_year" data-width="120px">
                                    <?php
                                    $current_year = (int) date('Y');
                                    for ($y = $current_year - 2; $y <= $current_year + 2; $y++) {
                                        $sel = ($y === $current_year) ? 'selected' : '';
                                        echo "<option value=\"$y\" $sel>$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <br>
                                <button type="button" class="btn btn-info" id="loadGoalsBtn" style="margin-top:3px;">
                                    <i class="fa fa-search"></i> Load
                                </button>
                            </div>

                            <div class="form-group">
                                <br>
                                <button type="button" class="btn btn-success" id="saveGoalsBtn" style="margin-top:3px;" disabled>
                                    <i class="fa fa-save"></i> Save Goals
                                </button>
                                <span class="save-status" id="saveStatus"></span>
                            </div>
                        </div>

                        <!-- Goals Table -->
                        <div id="goalsTableContainer" style="display:none; overflow-x:auto;">
                            <table class="goals-table" id="goalsTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="min-width:120px;">Goal Type</th>
                                        <?php
                                        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                        foreach ($months as $m) {
                                            echo "<th class=\"month-header\">$m</th>";
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $goal_types = [
                                        'gt_goal'      => ['GT Goal', 'goal-type-gt'],
                                        'enquiry_goal' => ['Enquiry Goal', 'goal-type-enquiry'],
                                        'renewal_goal' => ['Renewal Goal', 'goal-type-renewal'],
                                    ];
                                    foreach ($goal_types as $type_key => $type_info) {
                                        echo '<tr data-goal-type="' . $type_key . '">';
                                        echo '<td><span class="goal-type-label ' . $type_info[1] . '">' . $type_info[0] . '</span></td>';
                                        for ($m = 1; $m <= 12; $m++) {
                                            echo '<td><input type="number" min="0" step="1" class="goal-input" data-month="' . $m . '" data-type="' . $type_key . '" value="0"></td>';
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="goalsPlaceholder" class="text-center text-muted" style="padding: 40px 0;">
                            <i class="fa fa-bullseye fa-3x" style="color:#ddd;"></i>
                            <p style="margin-top:10px;">Select a branch and year, then click <strong>Load</strong> to view & edit goals.</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(function () {
    var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

    // Load goals
    $('#loadGoalsBtn').on('click', function () {
        var branchId = $('#goal_branch').val();
        var year = $('#goal_year').val();

        if (!branchId) {
            alert_float('warning', 'Please select a branch.');
            return;
        }

        $.ajax({
            url: admin_url + 'mis_reports/get_report_goals',
            type: 'GET',
            data: { branch_id: branchId, year: year },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    // Reset all inputs
                    $('.goal-input').val(0);

                    // Fill values
                    $.each(res.goals, function (key, val) {
                        // key = "month_goaltype" e.g. "3_gt_goal"
                        var parts = key.split('_');
                        var month = parts[0];
                        var goalType = parts.slice(1).join('_');

                        $('.goal-input[data-month="' + month + '"][data-type="' + goalType + '"]').val(Math.round(val));
                    });

                    $('#goalsTableContainer').show();
                    $('#goalsPlaceholder').hide();
                    $('#saveGoalsBtn').prop('disabled', false);
                }
            },
            error: function () {
                alert_float('danger', 'Failed to load goals.');
            }
        });
    });

    // Save goals
    $('#saveGoalsBtn').on('click', function () {
        var branchId = $('#goal_branch').val();
        var year = $('#goal_year').val();

        if (!branchId) {
            alert_float('warning', 'Please select a branch.');
            return;
        }

        var goals = [];
        $('.goal-input').each(function () {
            var $input = $(this);
            goals.push({
                branch_id: branchId,
                year: year,
                month: $input.data('month'),
                goal_type: $input.data('type'),
                amount: parseFloat($input.val()) || 0
            });
        });

        var postData = {};
        postData[csrfName] = csrfHash;

        // Build goals array for POST
        for (var i = 0; i < goals.length; i++) {
            postData['goals[' + i + '][branch_id]'] = goals[i].branch_id;
            postData['goals[' + i + '][year]'] = goals[i].year;
            postData['goals[' + i + '][month]'] = goals[i].month;
            postData['goals[' + i + '][goal_type]'] = goals[i].goal_type;
            postData['goals[' + i + '][amount]'] = goals[i].amount;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: admin_url + 'mis_reports/save_report_goals',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Goals');
                if (res.success) {
                    alert_float('success', res.message);
                    // Update CSRF hash for next request
                    if (res.csrf_hash) csrfHash = res.csrf_hash;
                } else {
                    alert_float('danger', res.message || 'Save failed.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Goals');
                alert_float('danger', 'Failed to save goals.');
            }
        });
    });
});
</script>

</body>
</html>
