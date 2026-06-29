<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ── Stats ──
$total_alumni = (int)$conn->query("SELECT COUNT(*) as c FROM alumni")->fetch_assoc()['c'];
$total_male   = (int)$conn->query("SELECT COUNT(*) as c FROM alumni WHERE gender='ذكر'")->fetch_assoc()['c'];
$total_female = (int)$conn->query("SELECT COUNT(*) as c FROM alumni WHERE gender='انثى'")->fetch_assoc()['c'];
$total_majors = (int)$conn->query("SELECT COUNT(DISTINCT major) as c FROM alumni WHERE major IS NOT NULL AND major != ''")->fetch_assoc()['c'];
$total_terms  = (int)$conn->query("SELECT COUNT(DISTINCT graduation_term) as c FROM alumni WHERE graduation_term IS NOT NULL")->fetch_assoc()['c'];
$honor_count  = (int)$conn->query("SELECT COUNT(*) as c FROM alumni WHERE honor_rank IS NOT NULL AND TRIM(honor_rank) != '' AND honor_rank != 'null' AND honor_rank != '0'")->fetch_assoc()['c'];

// ── Alumni per term — split by gender ──
$term_result = $conn->query("
    SELECT graduation_term,
        SUM(CASE WHEN gender='ذكر'  THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender='انثى' THEN 1 ELSE 0 END) as female
    FROM alumni
    WHERE graduation_term IS NOT NULL
    GROUP BY graduation_term
    ORDER BY graduation_term ASC
");
$term_labels = []; $term_male = []; $term_female = [];
while ($r = $term_result->fetch_assoc()) {
    $term_labels[] = $r['graduation_term'];
    $term_male[]   = (int)$r['male'];
    $term_female[] = (int)$r['female'];
}

// ── Top majors — split by gender ──
$major_result = $conn->query("
    SELECT major,
        SUM(CASE WHEN gender='ذكر'  THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender='انثى' THEN 1 ELSE 0 END) as female,
        COUNT(*) as total
    FROM alumni WHERE major IS NOT NULL AND major != ''
    GROUP BY major ORDER BY COUNT(*) DESC LIMIT 5
");
$major_labels = []; $major_data = []; $major_colors = [];
$major_male = []; $major_female = [];
$color_map = [
    'تقنية المعلومات' => '#e53935',
    'علوم الحاسب'     => '#1e88e5',
    'هندسة الحاسب'    => '#fb8c00',
];
$fallback_colors = ['#8e24aa','#00897b','#6d4c41'];
$fi = 0;
while ($r = $major_result->fetch_assoc()) {
    $major_labels[] = $r['major'];
    $major_data[]   = (int)$r['total'];
    $major_male[]   = (int)$r['male'];
    $major_female[] = (int)$r['female'];
    $color = $color_map[$r['major']] ?? $fallback_colors[$fi++ % count($fallback_colors)];
    $major_colors[] = $color;
}

// ── Gender chart data ──
$gender_labels = ['ذكر', 'انثى'];
$gender_data   = [$total_male, $total_female];

// ── Honor rank breakdown ──
$honor_result = $conn->query("SELECT honor_rank, COUNT(*) as c FROM alumni WHERE honor_rank IS NOT NULL AND TRIM(honor_rank) != '' AND honor_rank != 'null' AND honor_rank != '0' GROUP BY honor_rank ORDER BY c DESC");
$honor_labels = []; $honor_data = [];
while ($r = $honor_result->fetch_assoc()) {
    $honor_labels[] = $r['honor_rank'];
    $honor_data[]   = (int)$r['c'];
}

// ── Major distribution split by gender ──
$major_gender_result = $conn->query("
    SELECT major,
        SUM(CASE WHEN gender='ذكر'  THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender='انثى' THEN 1 ELSE 0 END) as female
    FROM alumni
    WHERE major IS NOT NULL AND major != ''
    GROUP BY major
    ORDER BY COUNT(*) DESC
    LIMIT 5
");
$mg_labels = []; $mg_male = []; $mg_female = []; $mg_colors = [];
while ($r = $major_gender_result->fetch_assoc()) {
    $mg_labels[] = $r['major'];
    $mg_male[]   = (int)$r['male'];
    $mg_female[] = (int)$r['female'];
    $mg_colors[] = $color_map[$r['major']] ?? $fallback_colors[$fi++ % count($fallback_colors)];
}
$hm_result = $conn->query("
    SELECT major, honor_rank, gender, COUNT(*) as c
    FROM alumni
    WHERE honor_rank IS NOT NULL AND TRIM(honor_rank) != '' AND honor_rank != 'null' AND honor_rank != '0'
      AND major IS NOT NULL AND TRIM(major) != ''
    GROUP BY major, honor_rank, gender
    ORDER BY major, honor_rank, gender
");
$honor_by_major = [];
while ($r = $hm_result->fetch_assoc()) {
    $maj = $r['major'];
    $rnk = $r['honor_rank'];
    $gen = $r['gender'];
    if (!isset($honor_by_major[$maj]))        $honor_by_major[$maj] = [];
    if (!isset($honor_by_major[$maj][$rnk]))  $honor_by_major[$maj][$rnk] = ['ذكر' => 0, 'انثى' => 0];
    $honor_by_major[$maj][$rnk][$gen] = (int)$r['c'];
}
// Collect all unique honor ranks across all majors (for consistent x-axis)
$all_honor_ranks = [];
foreach ($honor_by_major as $ranks) {
    foreach (array_keys($ranks) as $rk) {
        if (!in_array($rk, $all_honor_ranks)) $all_honor_ranks[] = $rk;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js"></script>
    <style>
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }
        .stat-card  { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 8px var(--shadow); transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-icon  { font-size:26px; width:48px; height:48px; background:var(--green-light); border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .stat-info  { display:flex; flex-direction:column; }
        .stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .stat-value { font-size:26px; font-weight:700; color:var(--green-dark); line-height:1.1; }

        .charts-grid-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:20px; margin-bottom:28px; }
        .charts-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; align-items:stretch; }
        @media(max-width:900px) { .charts-grid-3,.charts-grid-2 { grid-template-columns:1fr; } }
        .chart-card  { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; box-shadow:0 2px 8px var(--shadow); }
        .chart-title { font-size:13px; font-weight:600; color:var(--text); margin-bottom:16px; }
        .chart-wrap  { position:relative; height:220px; }
        .chart-wrap-tall { position:relative; height:300px; }

        /* Honor-by-major mini charts */
        .honor-major-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; margin-bottom:28px; }
        .mini-chart-label { font-size:12px; font-weight:700; color:var(--text); margin-bottom:12px; display:flex; align-items:center; gap:6px; }
        .mini-chart-wrap { position:relative; height:160px; }

        .actions-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; margin-bottom:28px; }
        .action-card  { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; display:flex; align-items:center; gap:14px; text-decoration:none; color:var(--text); box-shadow:0 2px 8px var(--shadow); transition:all .2s; }
        .action-card:hover { border-color:var(--green); transform:translateY(-2px); }
        .action-icon  { font-size:22px; width:44px; height:44px; background:var(--green-light); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .action-title { font-size:13px; font-weight:600; }
        .action-desc  { font-size:11px; color:var(--muted); margin-top:2px; }
        .section-label { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:14px; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p class="page-sub">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?> 👋 — <?php echo date('l, F j, Y'); ?></p>
            </div>
            <a class="btn btn-primary" href="alumni.php">View All Alumni</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-info">
                    <div class="stat-label">Total Alumni</div>
                    <div class="stat-value"><?php echo number_format($total_alumni); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨</div>
                <div class="stat-info">
                    <div class="stat-label">Male — ذكر</div>
                    <div class="stat-value"><?php echo number_format($total_male); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👩</div>
                <div class="stat-info">
                    <div class="stat-label">Female — انثى</div>
                    <div class="stat-value"><?php echo number_format($total_female); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <div class="stat-label">Majors</div>
                    <div class="stat-value"><?php echo number_format($total_majors); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <div class="stat-label">Terms</div>
                    <div class="stat-value"><?php echo number_format($total_terms); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏅</div>
                <div class="stat-info">
                    <div class="stat-label">Honor Students</div>
                    <div class="stat-value"><?php echo number_format($honor_count); ?></div>
                </div>
            </div>
        </div>

        <!-- Row 1: Term stacked bar — full width -->
        <div class="chart-card" style="margin-bottom:20px;">
            <div class="chart-title">📊 Alumni per Term — by Gender</div>
            <div class="chart-wrap-tall">
                <canvas id="termChart"></canvas>
            </div>
        </div>

        <!-- Row 2: Gender + Honor doughnuts -->
        <div class="charts-grid-3" style="grid-template-columns:1fr 1fr;">
            <div class="chart-card">
                <div class="chart-title"> Gender Distribution</div>
                <div class="chart-wrap">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-title">🏅 Honor Rank Breakdown</div>
                <div class="chart-wrap">
                    <canvas id="honorChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 3: Major Bar + Honor by Major -->
        <div class="charts-grid-2">
            <div class="chart-card" style="display:flex;flex-direction:column;">
                <div class="chart-title">📈 Major Distribution</div>
                <div style="position:relative;height:220px;">
                    <canvas id="majorBarChart"></canvas>
                </div>
                <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:14px;">
                    <div class="chart-title">👥 Major Distribution — by Gender</div>
                    <div style="position:relative;height:220px;">
                        <canvas id="majorGenderChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Honor students per major — one mini chart per major -->
            <div class="chart-card" style="display:flex;flex-direction:column;gap:16px;">
                <div class="chart-title">🏅 Honor Students by Major & Gender</div>
                <?php
                $major_colors_map = [
                    'تقنية المعلومات' => '#e53935',
                    'علوم الحاسب'     => '#1e88e5',
                    'هندسة الحاسب'    => '#fb8c00',
                ];
                $fi2 = 0;
                $fb2 = ['#8e24aa','#00897b','#6d4c41'];
                foreach ($honor_by_major as $major => $ranks):
                    $safe_id = 'hmc_' . md5($major);
                    $mc = $major_colors_map[$major] ?? $fb2[$fi2++ % 3];
                ?>
                <div style="border:1px solid var(--border);border-radius:var(--radius-md);padding:12px;background:var(--off-white);flex:1;">
                    <div class="mini-chart-label">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?php echo $mc; ?>;flex-shrink:0;"></span>
                        <?php echo htmlspecialchars($major); ?>
                    </div>
                    <div style="position:relative;height:130px;">
                        <canvas id="<?php echo $safe_id; ?>"></canvas>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-label">Quick Actions</div>
        <div class="actions-grid">
            <a class="action-card" href="alumni.php">
                <div class="action-icon">🎓</div>
                <div class="action-text">
                    <div class="action-title">Alumni Records</div>
                    <div class="action-desc">View, search & manage all graduates</div>
                </div>
            </a>
            <a class="action-card" href="add_alumni.php">
                <div class="action-icon">➕</div>
                <div class="action-text">
                    <div class="action-title">Add Alumni</div>
                    <div class="action-desc">Manually add a new graduate</div>
                </div>
            </a>
            <a class="action-card" href="import_alumni.php">
                <div class="action-icon">📥</div>
                <div class="action-text">
                    <div class="action-title">Import Excel</div>
                    <div class="action-desc">Import alumni data from Excel file</div>
                </div>
            </a>
            <a class="action-card" href="export_alumni.php">
                <div class="action-icon">📤</div>
                <div class="action-text">
                    <div class="action-title">Generate Report</div>
                    <div class="action-desc">Generate & download alumni data as PDF</div>
                </div>
            </a>
        </div>

    </main>
</div>

<script>
// ── Register datalabels plugin globally ──
Chart.register(ChartDataLabels);

// ── Shared tooltip pct helper ──
function pctTooltip(context) {
    const ds    = context.chart.data.datasets;
    const idx   = context.dataIndex;
    const total = ds.reduce((s, d) => s + (Number(d.data[idx]) || 0), 0);
    const val   = context.parsed.y ?? context.parsed;
    const pct   = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
    return (context.dataset.label || context.label) + ': ' + val + ' (' + pct + '%)';
}

// ═══════════════════════════════════════════════
// 1. Alumni per Term — Stacked bar (Male / Female)
// ═══════════════════════════════════════════════
const termMale   = <?php echo json_encode($term_male); ?>;
const termFemale = <?php echo json_encode($term_female); ?>;

new Chart(document.getElementById('termChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($term_labels); ?>,
        datasets: [
            {
                label: 'ذكر',
                data: termMale,
                backgroundColor: '#1e88e5',
                stack: 'gender',
                barThickness: 28
            },
            {
                label: 'انثى',
                data: termFemale,
                backgroundColor: '#e91e8c',
                stack: 'gender',
                barThickness: 28
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 4 } },
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 12 }, boxWidth: 14 }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const idx   = context.dataIndex;
                        const total = termMale[idx] + termFemale[idx];
                        const val   = context.parsed.y;
                        const pct   = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                        return context.dataset.label + ': ' + val + ' (' + pct + '%)';
                    },
                    footer: function(items) {
                        const idx   = items[0].dataIndex;
                        const total = termMale[idx] + termFemale[idx];
                        return 'Total: ' + total;
                    }
                }
            },
            datalabels: {
                color: '#fff',
                font: { size: 11, weight: 'bold' },
                textShadowBlur: 4,
                textShadowColor: 'rgba(0,0,0,0.4)',
                formatter: function(value, context) {
                    if (value === 0) return '';
                    const idx   = context.dataIndex;
                    const total = termMale[idx] + termFemale[idx];
                    const pct   = total > 0 ? Math.round((value / total) * 100) : 0;
                    return pct >= 10 ? pct + '%' : '';
                }
            }
        },
        scales: {
            x: {
                stacked: true,
                grid: { display: false },
                ticks: { font: { size: 11 } }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                grid: { color: '#e8f5e9' },
                ticks: { font: { size: 11 } }
            }
        }
    }
});

// ═══════════════════════════════════════════════
// 2. Gender Doughnut — with % labels
// ═══════════════════════════════════════════════
const genderData = <?php echo json_encode($gender_data); ?>;
const genderTotal = genderData.reduce((a, b) => a + b, 0);

new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($gender_labels); ?>,
        datasets: [{
            data: genderData,
            backgroundColor: ['#1e88e5', '#e91e8c'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14 } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const pct = genderTotal > 0 ? ((context.parsed / genderTotal) * 100).toFixed(1) : '0.0';
                        return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                    }
                }
            },
            datalabels: {
                color: '#fff',
                font: { size: 12, weight: 'bold' },
                formatter: function(value) {
                    const pct = genderTotal > 0 ? ((value / genderTotal) * 100).toFixed(1) : '0.0';
                    return pct + '%';
                }
            }
        }
    }
});

// ═══════════════════════════════════════════════
// 3. Honor Rank Doughnut — with % labels
// ═══════════════════════════════════════════════
const honorData = <?php echo json_encode($honor_data); ?>;
const honorTotal = honorData.reduce((a, b) => a + b, 0);

new Chart(document.getElementById('honorChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($honor_labels); ?>,
        datasets: [{
            data: honorData,
            backgroundColor: ['#ffd600', '#ff6f00'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12 } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const pct = honorTotal > 0 ? ((context.parsed / honorTotal) * 100).toFixed(1) : '0.0';
                        return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                    }
                }
            },
            datalabels: {
                color: '#fff',
                font: { size: 12, weight: 'bold' },
                formatter: function(value) {
                    const pct = honorTotal > 0 ? ((value / honorTotal) * 100).toFixed(1) : '0.0';
                    return pct + '%';
                }
            }
        }
    }
});

// ═══════════════════════════════════════════════
// 4. Major Bar — simple colored (total per major)
// ═══════════════════════════════════════════════
const majorMale   = <?php echo json_encode($major_male); ?>;
const majorFemale = <?php echo json_encode($major_female); ?>;
const majorTotals = <?php echo json_encode($major_data); ?>;
const majorTotal  = majorTotals.reduce((a,b) => a+b, 0);

new Chart(document.getElementById('majorBarChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($major_labels); ?>,
        datasets: [{
            label: 'Alumni',
            data: majorTotals,
            backgroundColor: <?php echo json_encode($major_colors); ?>,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 22 } },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const pct = majorTotal > 0 ? ((ctx.parsed.y / majorTotal) * 100).toFixed(1) : '0';
                        return 'Alumni: ' + ctx.parsed.y + ' (' + pct + '%)';
                    }
                }
            },
            datalabels: {
                anchor: 'end', align: 'end', offset: 2,
                color: function(ctx) { return <?php echo json_encode($major_colors); ?>[ctx.dataIndex] || '#555'; },
                font: { size: 11, weight: 'bold' },
                formatter: function(v) {
                    const pct = majorTotal > 0 ? ((v / majorTotal) * 100).toFixed(1) : '0';
                    return pct + '%';
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: '#e8f5e9' } }
        }
    }
});

// ═══════════════════════════════════════════════
// 4b. Major Distribution — by Gender (grouped)
// ═══════════════════════════════════════════════
new Chart(document.getElementById('majorGenderChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($major_labels); ?>,
        datasets: [
            { label: 'ذكر',  data: majorMale,   backgroundColor: '#1e88e5', borderRadius: 5, barThickness: 28 },
            { label: 'انثى', data: majorFemale, backgroundColor: '#e91e8c', borderRadius: 5, barThickness: 28 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 22 } },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 13 } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const idx   = ctx.dataIndex;
                        const total = majorMale[idx] + majorFemale[idx];
                        const pct   = total > 0 ? ((ctx.parsed.y / total) * 100).toFixed(1) : '0';
                        return ctx.dataset.label + ': ' + ctx.parsed.y + ' (' + pct + '%)';
                    }
                }
            },
            datalabels: {
                anchor: 'end', align: 'end', offset: 2,
                color: function(ctx) { return ctx.datasetIndex === 0 ? '#1565c0' : '#ad1457'; },
                font: { size: 10, weight: 'bold' },
                formatter: function(v) { return v > 0 ? v : ''; }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: '#e8f5e9' }, ticks: { precision: 0 } }
        }
    }
});
// ═══════════════════════════════════════════════
<?php foreach ($honor_by_major as $major => $ranks):
    $safe_id    = 'hmc_' . md5($major);
    $rank_labels = $all_honor_ranks;
    $male_data   = [];
    $female_data = [];
    $total_data  = [];
    foreach ($rank_labels as $rk) {
        $m = $ranks[$rk]['ذكر']  ?? 0;
        $f = $ranks[$rk]['انثى'] ?? 0;
        $male_data[]   = $m;
        $female_data[] = $f;
        $total_data[]  = $m + $f;
    }
?>
(function() {
    const maleD   = <?php echo json_encode($male_data); ?>;
    const femaleD = <?php echo json_encode($female_data); ?>;
    const totals  = <?php echo json_encode($total_data); ?>;
    new Chart(document.getElementById('<?php echo $safe_id; ?>'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($rank_labels); ?>,
            datasets: [
                { label: 'ذكر',  data: maleD,   backgroundColor: '#1e88e5', barThickness: 55 },
                { label: 'انثى', data: femaleD, backgroundColor: '#e91e8c', barThickness: 55 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 18 } },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 10, padding: 6 } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const tot = totals[ctx.dataIndex];
                            const pct = tot > 0 ? ((ctx.parsed.y / tot) * 100).toFixed(1) : '0';
                            return ctx.dataset.label + ': ' + ctx.parsed.y + ' (' + pct + '%)';
                        },
                        footer: function(items) {
                            return 'Total: ' + totals[items[0].dataIndex];
                        }
                    }
                },
                datalabels: {
                    anchor: 'end', align: 'end', offset: 1,
                    color: function(ctx) { return ctx.datasetIndex === 0 ? '#1565c0' : '#ad1457'; },
                    font: { size: 10, weight: 'bold' },
                    formatter: function(v) { return v > 0 ? v : ''; }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { beginAtZero: true, grid: { color: '#e8f5e9' }, ticks: { font: { size: 10 }, precision: 0 } }
            }
        }
    });
})();
<?php endforeach; ?>
</script>
</body>
</html>
