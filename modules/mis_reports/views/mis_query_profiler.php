<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    body { background: #0d1117; color: #c9d1d9; }
    .profiler-wrap { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; }
    .profiler-header { background: linear-gradient(135deg, #161b22, #1c2333); border: 1px solid #30363d; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
    .profiler-header h2 { margin: 0 0 8px; color: #58a6ff; font-size: 22px; }
    .profiler-header p { margin: 0; color: #8b949e; font-size: 13px; }
    .profiler-header .total-time { font-size: 32px; font-weight: 700; color: #f0883e; margin-top: 10px; }
    .query-card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
    .query-card:hover { border-color: #58a6ff; }
    .query-card.slow { border-left: 4px solid #f85149; }
    .query-card.medium { border-left: 4px solid #d29922; }
    .query-card.fast { border-left: 4px solid #3fb950; }
    .query-card .card-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: #0d1117; cursor: pointer; }
    .query-card .card-head h4 { margin: 0; font-size: 14px; color: #c9d1d9; }
    .badge-time { font-size: 13px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
    .badge-slow { background: #f8514922; color: #f85149; }
    .badge-medium { background: #d2992222; color: #d29922; }
    .badge-fast { background: #3fb95022; color: #3fb950; }
    .card-body-inner { padding: 16px 18px; border-top: 1px solid #21262d; }
    .sql-block { background: #0d1117; border: 1px solid #21262d; border-radius: 6px; padding: 14px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; color: #79c0ff; font-size: 12px; margin-bottom: 12px; max-height: 300px; overflow-y: auto; }
    .explain-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
    .explain-table th { background: #21262d; color: #58a6ff; padding: 6px 10px; text-align: left; border: 1px solid #30363d; }
    .explain-table td { padding: 6px 10px; border: 1px solid #30363d; color: #c9d1d9; }
    .explain-table tr:hover td { background: #1c2333; }
    .section-label { color: #8b949e; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; font-weight: 600; }
    .error-box { background: #f8514915; border: 1px solid #f85149; border-radius: 6px; padding: 12px; color: #f85149; margin-bottom: 12px; }
    .row-count { color: #8b949e; font-size: 12px; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 16px; }
    .summary-item { background: #0d1117; border: 1px solid #30363d; border-radius: 8px; padding: 14px; text-align: center; }
    .summary-item .val { font-size: 24px; font-weight: 700; }
    .summary-item .lbl { font-size: 11px; color: #8b949e; text-transform: uppercase; margin-top: 4px; }
    .db-info { background: #0d1117; border: 1px solid #30363d; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
    .db-info h4 { color: #58a6ff; margin: 0 0 10px; font-size: 14px; }
    .db-info td { padding: 4px 8px; font-size: 12px; }
    .db-info td:first-child { color: #8b949e; width: 200px; }
    .collapsible { display: none; }
    .collapsible.show { display: block; }
    .filter-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-top: 14px; }
    .filter-form label { color: #8b949e; font-size: 11px; display: block; margin-bottom: 4px; }
    .filter-form input, .filter-form select { background: #0d1117; border: 1px solid #30363d; color: #c9d1d9; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-family: inherit; }
    .btn-run { background: #238636; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; }
    .btn-run:hover { background: #2ea043; }
</style>

<div id="wrapper">
<div class="content">
<div class="profiler-wrap">

    <div class="profiler-header">
        <h2>🔍 MIS Reports Query Profiler</h2>
        <p>Real-time MySQL query profiling for MIS Report tables</p>
        <p style="margin-top:6px; color:#f0883e;">⚠️ Remove this page after debugging.</p>

        <form method="GET" action="" class="filter-form">
            <div>
                <label>Report Table</label>
                <select name="report" style="min-width:250px;">
                    <option value="payment_detail_report" <?= ($prof_report ?? '') === 'payment_detail_report' ? 'selected' : '' ?>>Payment Detail Report</option>
                    <option value="branch_registration_report" <?= ($prof_report ?? '') === 'branch_registration_report' ? 'selected' : '' ?>>Branch Registration Report</option>
                    <option value="gt_report" <?= ($prof_report ?? '') === 'gt_report' ? 'selected' : '' ?>>GT Report</option>
                    <option value="renewal_report" <?= ($prof_report ?? '') === 'renewal_report' ? 'selected' : '' ?>>Renewal Report</option>
                    <option value="appointment_reports" <?= ($prof_report ?? '') === 'appointment_reports' ? 'selected' : '' ?>>Appointment Reports</option>
                </select>
            </div>
            <div>
                <label>From Date</label>
                <input type="date" name="from_date" value="<?= e($prof_from_date ?? date('Y-m-01')) ?>">
            </div>
            <div>
                <label>To Date</label>
                <input type="date" name="to_date" value="<?= e($prof_to_date ?? date('Y-m-d')) ?>">
            </div>
            <div>
                <label>Branch IDs (comma-sep)</label>
                <input type="text" name="branch_ids" value="<?= e($prof_branch_ids ?? '') ?>" placeholder="e.g. 1,2,3">
            </div>
            <div>
                <label>Page Size</label>
                <input type="number" name="page_size" value="<?= (int)($prof_page_size ?? 25) ?>" min="1" max="500" style="width:70px;">
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit" class="btn-run">▶ Run Profiler</button>
            </div>
        </form>
    </div>

    <?php if (!empty($profiler_results)): ?>

    <!-- DB Info -->
    <div class="db-info">
        <h4>📊 Database & Table Info</h4>
        <table>
            <?php foreach ($db_info as $key => $val): ?>
            <tr><td><?= e($key) ?></td><td><?= e($val) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Summary -->
    <div class="profiler-header">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="val" style="color:#f0883e;"><?= number_format($total_time_ms, 1) ?>ms</div>
                <div class="lbl">Total Time</div>
            </div>
            <div class="summary-item">
                <div class="val" style="color:#58a6ff;"><?= count($profiler_results) ?></div>
                <div class="lbl">Queries Run</div>
            </div>
            <div class="summary-item">
                <div class="val" style="color:<?= $slowest_ms > 1000 ? '#f85149' : ($slowest_ms > 300 ? '#d29922' : '#3fb950') ?>;"><?= number_format($slowest_ms, 1) ?>ms</div>
                <div class="lbl">Slowest Query</div>
            </div>
        </div>
    </div>

    <!-- Query Cards -->
    <?php foreach ($profiler_results as $idx => $q): ?>
    <?php
        $speed_class = 'fast'; $badge_class = 'badge-fast';
        if ($q['time_ms'] > 1000) { $speed_class = 'slow'; $badge_class = 'badge-slow'; }
        elseif ($q['time_ms'] > 300) { $speed_class = 'medium'; $badge_class = 'badge-medium'; }
    ?>
    <div class="query-card <?= $speed_class ?>">
        <div class="card-head" onclick="toggleCard(<?= $idx ?>)">
            <h4>
                #<?= $idx + 1 ?> — <?= e($q['label']) ?>
                <span class="row-count">(<?= $q['row_count'] ?> rows)</span>
                <?php if (!empty($q['error'])): ?><span style="color:#f85149;">❌ ERROR</span><?php endif; ?>
            </h4>
            <span class="badge-time <?= $badge_class ?>"><?= number_format($q['time_ms'], 1) ?> ms</span>
        </div>
        <div class="collapsible" id="card-<?= $idx ?>">
            <div class="card-body-inner">
                <?php if (!empty($q['error'])): ?>
                <div class="error-box"><div class="section-label">MySQL Error</div><?= e($q['error']) ?></div>
                <?php endif; ?>
                <div class="section-label">SQL Query</div>
                <div class="sql-block"><?= e($q['sql']) ?></div>
                <?php if (!empty($q['explain'])): ?>
                <div class="section-label">EXPLAIN Output</div>
                <div style="overflow-x:auto;">
                <table class="explain-table">
                    <thead><tr>
                    <?php foreach (array_keys($q['explain'][0]) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($q['explain'] as $erow): ?>
                    <tr><?php foreach ($erow as $val): ?><td><?= e($val ?? 'NULL') ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Raw copy block -->
    <div class="query-card fast">
        <div class="card-head" onclick="toggleCard('raw')">
            <h4>📋 Copy-Paste Raw Output</h4>
            <span class="badge-time badge-fast">
                <button onclick="event.stopPropagation(); copyRaw();" style="background:#238636; color:#fff; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-size:11px;">Copy</button>
            </span>
        </div>
        <div class="collapsible show" id="card-raw">
            <div class="card-body-inner">
                <textarea id="rawOutput" readonly style="width:100%; height:400px; background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:6px; padding:10px; font-family:monospace; font-size:11px; resize:vertical;"><?php
$rawLines = [];
$rawLines[] = "=== MIS REPORTS QUERY PROFILER OUTPUT ===";
$rawLines[] = "Date: " . date('Y-m-d H:i:s');
$rawLines[] = "Report: " . ($prof_report ?? 'payment_detail_report');
$rawLines[] = "Date Range: " . ($prof_from_date ?? '') . " to " . ($prof_to_date ?? '');
$rawLines[] = "Total Time: " . number_format($total_time_ms, 1) . "ms";
$rawLines[] = "Total Queries: " . count($profiler_results);
$rawLines[] = "Slowest: " . number_format($slowest_ms, 1) . "ms";
$rawLines[] = "";
$rawLines[] = "--- DB INFO ---";
foreach ($db_info as $k => $v) { $rawLines[] = "$k: $v"; }
$rawLines[] = "";
foreach ($profiler_results as $i => $q) {
    $rawLines[] = "--- QUERY #" . ($i+1) . ": " . $q['label'] . " ---";
    $rawLines[] = "Time: " . number_format($q['time_ms'], 1) . "ms | Rows: " . $q['row_count'];
    if (!empty($q['error'])) { $rawLines[] = "ERROR: " . $q['error']; }
    $rawLines[] = "SQL: " . $q['sql'];
    if (!empty($q['explain'])) {
        $rawLines[] = "EXPLAIN:";
        foreach ($q['explain'] as $er) { $rawLines[] = "  " . json_encode($er); }
    }
    $rawLines[] = "";
}
echo e(implode("\n", $rawLines));
?></textarea>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>
</div>
</div>

<?php init_tail(); ?>
<script>
function toggleCard(id) { var el = document.getElementById('card-' + id); if (el) el.classList.toggle('show'); }
function copyRaw() {
    var ta = document.getElementById('rawOutput');
    ta.select(); ta.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(ta.value).then(function() { alert('Copied!'); });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.query-card.slow .collapsible, .query-card.medium .collapsible').forEach(function(el) { el.classList.add('show'); });
});
</script>
</body>
</html>
