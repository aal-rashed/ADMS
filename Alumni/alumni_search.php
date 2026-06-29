<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include "config.php";

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

// ── Dropdown values ──
$degrees  = $conn->query("SELECT DISTINCT academic_degree FROM alumni WHERE academic_degree IS NOT NULL AND TRIM(academic_degree) != '' ORDER BY academic_degree");
$colleges = $conn->query("SELECT DISTINCT college         FROM alumni WHERE college         IS NOT NULL AND TRIM(college)         != '' ORDER BY college");
$majors   = $conn->query("SELECT DISTINCT major           FROM alumni WHERE major           IS NOT NULL AND TRIM(major)           != '' ORDER BY major");
$terms    = $conn->query("SELECT DISTINCT graduation_term FROM alumni WHERE graduation_term IS NOT NULL AND TRIM(graduation_term) != '' ORDER BY graduation_term DESC");
$honors   = $conn->query("SELECT DISTINCT honor_rank      FROM alumni WHERE honor_rank      IS NOT NULL AND TRIM(honor_rank)      != '' AND TRIM(honor_rank) != '0' ORDER BY honor_rank");

// ── Build WHERE ──
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if (!empty($_GET['search'])) {
    $s = "%" . $_GET['search'] . "%";
    $where .= " AND (name LIKE ? OR major LIKE ? OR college LIKE ?)";
    array_push($params, $s, $s, $s);
    $types .= "sss";
}

$dropdown_filters = ['academic_degree', 'college', 'major', 'graduation_term', 'honor_rank'];
foreach ($dropdown_filters as $col) {
    if (!empty($_GET[$col])) {
        $where   .= " AND $col = ?";
        $params[] = $_GET[$col];
        $types   .= "s";
    }
}

$filter_keys  = ['search', 'academic_degree', 'college', 'major', 'graduation_term', 'honor_rank'];
$active_count = 0;
foreach ($filter_keys as $k) { if (!empty($_GET[$k])) $active_count++; }

$rows  = [];
$total = 0;
$searched = ($active_count > 0);

if ($searched) {
    $stmt = $conn->prepare("SELECT name, major, college, academic_degree, graduation_term, honor_rank FROM alumni $where ORDER BY graduation_term DESC, name ASC LIMIT 300");
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total  = $result->num_rows;
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}

function sel_a($key, $val) { return (isset($_GET[$key]) && $_GET[$key] == $val) ? 'selected' : ''; }
function buildOpts($result, $key) {
    $html = '';
    while ($r = $result->fetch_assoc()) {
        $v = htmlspecialchars(array_values($r)[0]);
        $s = sel_a($key, array_values($r)[0]);
        $html .= "<option value=\"$v\" $s>$v</option>";
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Search – Alumni Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reuse exact admin filter panel styles */
        .filters-panel { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:24px; overflow:hidden; box-shadow:0 2px 10px var(--shadow); }
        .filters-header { display:flex; align-items:center; justify-content:space-between; padding:13px 18px; cursor:pointer; user-select:none; border-bottom:1px solid transparent; transition:border-color .2s; background:var(--off-white); }
        .filters-header.open { border-bottom-color:var(--border); }
        .filters-header-left { display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; color:var(--text); }
        .active-count { background:var(--green); color:var(--white); border-radius:20px; padding:1px 8px; font-size:11px; font-weight:700; }
        .toggle-icon { color:var(--muted); font-size:12px; transition:transform .3s; }
        .toggle-icon.open { transform:rotate(180deg); }
        .filters-body { display:none; padding:20px; }
        .filters-body.open { display:block; }
        .search-row { margin-bottom:16px; }
        .search-wrap { position:relative; }
        .search-wrap input { width:100%; background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px 14px 10px 38px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .2s; }
        .search-wrap input:focus { border-color:var(--green); background:var(--white); }
        .search-wrap input::placeholder { color:#b0bfb1; }
        .search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(178px,1fr)); gap:12px; margin-bottom:16px; }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }
        .filter-group select { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px 11px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .2s; }
        .filter-group select:focus { border-color:var(--green); background:var(--white); }
        .filter-actions { display:flex; gap:10px; border-top:1px solid var(--border); padding-top:16px; }

        .privacy-notice { background:var(--green-light); border:1px solid #a5d6a7; border-radius:var(--radius-sm); padding:11px 16px; font-size:12px; color:var(--green-dark); margin-bottom:20px; display:flex; align-items:center; gap:8px; }

        .table-wrap { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:0 2px 10px var(--shadow); }
        .table-meta { padding:12px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); background:var(--off-white); }
        .count-badge { background:var(--green-light); color:var(--green-dark); border-radius:20px; padding:2px 10px; font-size:12px; font-weight:600; border:1px solid #a5d6a7; }

        .honor-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; background:#fff8e1; color:#e65100; border:1px solid #ffe082; }

        .prompt-state { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:60px 20px; text-align:center; box-shadow:0 2px 10px var(--shadow); }
        .prompt-state .ps-icon { font-size:48px; margin-bottom:14px; }
        .prompt-state p { color:var(--muted); font-size:14px; }
    </style>
</head>
<body>
<div class="layout">

    <?php include "alumni_sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Alumni Search</h1>
                <p class="page-sub">Browse the College of Computer graduate directory</p>
            </div>
        </div>

        <!-- Privacy notice -->
        <div class="privacy-notice">
            🔒 This directory shows limited public information only. Contact details and personal IDs are not displayed.
        </div>

        <!-- Filters — exact same pattern as admin -->
        <form method="GET" action="alumni_search.php">
        <div class="filters-panel">
            <div class="filters-header <?php echo $active_count > 0 ? 'open':''; ?>" id="filtersToggle">
                <div class="filters-header-left">
                    🔍 Search & Filter
                    <?php if ($active_count > 0): ?>
                        <span class="active-count"><?php echo $active_count; ?> active</span>
                    <?php endif; ?>
                </div>
                <span class="toggle-icon <?php echo $active_count > 0 ? 'open':''; ?>" id="toggleIcon">▼</span>
            </div>

            <div class="filters-body <?php echo $active_count > 0 ? 'open':''; ?>" id="filtersBody">

                <div class="search-row">
                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search"
                               placeholder="Search by name, major, or college..."
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                </div>

                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Degree — الدرجة العلمية</label>
                        <select name="academic_degree">
                            <option value="">All Degrees</option>
                            <?php echo buildOpts($degrees, 'academic_degree'); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>College — الكلية</label>
                        <select name="college">
                            <option value="">All Colleges</option>
                            <?php echo buildOpts($colleges, 'college'); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Major — التخصص</label>
                        <select name="major">
                            <option value="">All Majors</option>
                            <?php echo buildOpts($majors, 'major'); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Term — الترم</label>
                        <select name="graduation_term">
                            <option value="">All Terms</option>
                            <?php echo buildOpts($terms, 'graduation_term'); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Honor Rank — مرتبة الشرف</label>
                        <select name="honor_rank">
                            <option value="">All</option>
                            <?php echo buildOpts($honors, 'honor_rank'); ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="alumni_search.php" class="btn btn-outline">✕ Reset All</a>
                </div>

            </div>
        </div>
        </form>

        <!-- Results -->
        <?php if (!$searched): ?>
            <div class="prompt-state">
                <div class="ps-icon">🎓</div>
                <p>Use the search bar or filters above to browse the alumni directory.</p>
            </div>

        <?php elseif ($total === 0): ?>
            <div class="table-wrap">
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <p>No alumni found matching your criteria.</p>
                </div>
            </div>

        <?php else: ?>
            <div class="table-wrap">
                <div class="table-meta">
                    Showing <span class="count-badge"><?php echo number_format($total); ?></span> result(s)
                    <?php if ($total === 300): ?>
                        <span style="font-size:12px;color:var(--muted);">— Top 300 shown. Refine your filters for more specific results.</span>
                    <?php endif; ?>
                </div>
                <div style="overflow-x:auto;">
                <table>
                    <tr>
                        <th>#</th>
                        <th>Name — الاسم</th>
                        <th>College — الكلية</th>
                        <th>Major — التخصص</th>
                        <th>Degree — الدرجة</th>
                        <th>Term — الترم</th>
                        <th>Honor — مرتبة الشرف</th>
                    </tr>
                    <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?php echo $i + 1; ?></td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($row['name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['college'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['major'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['academic_degree'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['graduation_term'] ?? '—'); ?></td>
                        <td>
                            <?php if (!empty($row['honor_rank'])): ?>
                                <span class="honor-badge">🏅 <?php echo htmlspecialchars($row['honor_rank']); ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
const toggle = document.getElementById('filtersToggle');
const body   = document.getElementById('filtersBody');
const icon   = document.getElementById('toggleIcon');
toggle.addEventListener('click', () => {
    const open = body.classList.contains('open');
    body.classList.toggle('open', !open);
    icon.classList.toggle('open', !open);
    toggle.classList.toggle('open', !open);
});
</script>
</body>
</html>
