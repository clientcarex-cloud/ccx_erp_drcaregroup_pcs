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
    .goal-type-referral { background: #e2d5f1; color: #4a235a; }
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
                                        'gt_goal'       => ['GT Goal', 'goal-type-gt'],
                                        'enquiry_goal'  => ['Enquiry Goal', 'goal-type-enquiry'],
                                        'renewal_goal'  => ['Renewal Goal', 'goal-type-renewal'],
                                        'referral_goal' => ['Referral Goal', 'goal-type-referral'],
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

        <!-- Goals with Lead Sources Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin" style="margin-bottom:15px !important;">
                            <i class="fa fa-link" style="color:#8e44ad;"></i>
                            Goals with Lead Sources
                        </h4>
                        <hr class="hr-panel-heading" />
                        <p class="text-muted" style="margin-bottom:15px;">Assign lead sources to goal categories. Each source can only belong to <strong>one</strong> category.</p>

                        <div class="row" id="leadSourceCardsContainer">
                            <?php
                            $categories = [
                                'referral' => ['label' => 'Referral', 'color' => '#8e44ad', 'bg' => '#f5eef8', 'icon' => 'fa-users'],
                                'enquiry'  => ['label' => 'Enquiry',  'color' => '#2980b9', 'bg' => '#ebf5fb', 'icon' => 'fa-phone'],
                                'renewal'  => ['label' => 'Renewal',  'color' => '#e67e22', 'bg' => '#fef5e7', 'icon' => 'fa-refresh'],
                            ];
                            foreach ($categories as $cat_key => $cat_info) { ?>
                            <div class="col-md-4">
                                <div class="goal-source-card" style="border-top: 3px solid <?= $cat_info['color']; ?>; background: <?= $cat_info['bg']; ?>;">
                                    <h5 style="color: <?= $cat_info['color']; ?>; font-weight:700; margin-bottom:12px;">
                                        <i class="fa <?= $cat_info['icon']; ?>"></i> <?= $cat_info['label']; ?>
                                    </h5>
                                    <div class="input-group" style="margin-bottom:10px;">
                                        <select class="form-control goal-source-dropdown" id="dropdown_<?= $cat_key; ?>" data-category="<?= $cat_key; ?>" style="width:100%;">
                                            <option value=""></option>
                                            <?php foreach ($leads_sources as $src) { ?>
                                                <option value="<?= $src['id']; ?>"><?= htmlspecialchars($src['name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <ul class="selected-sources-list" id="list_<?= $cat_key; ?>" data-category="<?= $cat_key; ?>"></ul>
                                    <button type="button" class="btn btn-sm btn-save-source" style="background:<?= $cat_info['color']; ?>; color:#fff; margin-top:8px;" data-category="<?= $cat_key; ?>">
                                        <i class="fa fa-save"></i> Save
                                    </button>
                                    <span class="source-save-status" id="status_<?= $cat_key; ?>"></span>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php init_tail(); ?>

<style>
    .goal-source-card {
        border-radius: 6px;
        padding: 18px;
        margin-bottom: 15px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .btn-save-source {
        border: none;
        border-radius: 4px;
        padding: 5px 16px;
        font-weight: 600;
        font-size: 13px;
    }
    .btn-save-source:hover { opacity: 0.85; }
    .source-save-status {
        display: inline-block;
        margin-left: 8px;
        font-weight: 600;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .source-save-status.show { opacity: 1; }
    .source-save-status.success { color: #27ae60; }
    .source-save-status.error { color: #e74c3c; }

    /* Selected sources list */
    .selected-sources-list {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 220px;
        overflow-y: auto;
    }
    .selected-sources-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 10px;
        margin-bottom: 4px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        font-size: 13px;
        transition: background 0.15s;
    }
    .selected-sources-list li:hover {
        background: #f9f9f9;
    }
    .selected-sources-list li .source-name {
        flex: 1;
        font-weight: 500;
    }
    .selected-sources-list li .remove-source {
        cursor: pointer;
        color: #e74c3c;
        font-size: 15px;
        font-weight: 700;
        margin-left: 10px;
        line-height: 1;
        opacity: 0.7;
        transition: opacity 0.15s;
    }
    .selected-sources-list li .remove-source:hover {
        opacity: 1;
    }
    .selected-sources-list .empty-msg {
        color: #aaa;
        font-style: italic;
        font-size: 12px;
        padding: 6px 0;
    }
</style>

<script>
$(function () {
    var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

    // ============================================
    // GOALS WITH LEAD SOURCES
    // ============================================
    var allSources = <?= json_encode($leads_sources); ?>;
    // Build a lookup: id -> name
    var sourceMap = {};
    allSources.forEach(function (s) { sourceMap[s.id] = s.name; });

    // Track selected IDs per category
    var selected = { referral: [], enquiry: [], renewal: [] };

    // Init Select2 as single-select dropdown
    $('.goal-source-dropdown').select2({
        placeholder: 'Select a lead source to add...',
        width: '100%',
        allowClear: true
    });

    // On dropdown selection, add to list
    $(document).on('change', '.goal-source-dropdown', function () {
        var $dd = $(this);
        var cat = $dd.data('category');
        var val = $dd.val();
        if (!val) return;
        val = String(val);

        // Prevent duplicates within same category
        if (selected[cat].indexOf(val) !== -1) {
            $dd.val('').trigger('change.select2');
            return;
        }

        selected[cat].push(val);
        renderList(cat);
        syncDropdowns();

        // Reset dropdown
        $dd.val('').trigger('change.select2');
    });

    // Remove item from list
    $(document).on('click', '.remove-source', function () {
        var cat = $(this).data('category');
        var id = String($(this).data('id'));
        selected[cat] = selected[cat].filter(function (v) { return v !== id; });
        renderList(cat);
        syncDropdowns();
    });

    // Render the selected list for a category
    function renderList(cat) {
        var $list = $('#list_' + cat);
        $list.empty();
        if (selected[cat].length === 0) {
            $list.append('<li class="empty-msg">No sources assigned</li>');
            return;
        }
        selected[cat].forEach(function (id) {
            var name = sourceMap[id] || ('Source #' + id);
            $list.append(
                '<li>' +
                    '<span class="source-name">' + $('<span>').text(name).html() + '</span>' +
                    '<span class="remove-source" data-category="' + cat + '" data-id="' + id + '" title="Remove">&times;</span>' +
                '</li>'
            );
        });
    }

    // Sync dropdowns: hide options already used in ANY category
    function syncDropdowns() {
        var allUsed = {};
        $.each(selected, function (cat, ids) {
            ids.forEach(function (id) { allUsed[id] = cat; });
        });

        $('.goal-source-dropdown').each(function () {
            var currentCat = $(this).data('category');
            $(this).find('option').each(function () {
                var optVal = $(this).val();
                if (!optVal) return; // skip placeholder
                if (allUsed[optVal]) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
        });
    }

    // Load existing assignments on page load
    function loadLeadSourceAssignments() {
        $.ajax({
            url: admin_url + 'mis_reports/get_goal_lead_sources',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $.each(res.data, function (cat, ids) {
                        selected[cat] = ids.map(String);
                        renderList(cat);
                    });
                    syncDropdowns();
                }
            }
        });
    }
    loadLeadSourceAssignments();

    // Save button for each category
    $('.btn-save-source').on('click', function () {
        var $btn = $(this);
        var category = $btn.data('category');
        var sourceIds = selected[category] || [];
        var $status = $('#status_' + category);

        var postData = {};
        postData[csrfName] = csrfHash;
        postData['category'] = category;
        for (var i = 0; i < sourceIds.length; i++) {
            postData['source_ids[' + i + ']'] = sourceIds[i];
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: admin_url + 'mis_reports/save_goal_lead_sources',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                if (res.success) {
                    alert_float('success', res.message);
                    $status.text('✓ Saved').removeClass('error').addClass('success show');
                    if (res.csrf_hash) csrfHash = res.csrf_hash;
                } else {
                    alert_float('danger', res.message || 'Save failed.');
                    $status.text('✗ Error').removeClass('success').addClass('error show');
                }
                setTimeout(function () { $status.removeClass('show'); }, 3000);
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                alert_float('danger', 'Failed to save.');
                $status.text('✗ Error').removeClass('success').addClass('error show');
                setTimeout(function () { $status.removeClass('show'); }, 3000);
            }
        });
    });

    // ============================================
    // EXISTING GOALS TABLE LOGIC
    // ============================================

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
