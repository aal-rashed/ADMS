<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ── Dropdown values from DB (all dynamic) ──
$genders        = $conn->query("SELECT DISTINCT gender          FROM alumni WHERE gender          IS NOT NULL AND TRIM(gender)          != '' ORDER BY gender");
$degrees        = $conn->query("SELECT DISTINCT academic_degree FROM alumni WHERE academic_degree IS NOT NULL AND TRIM(academic_degree) != '' ORDER BY academic_degree");
$colleges       = $conn->query("SELECT DISTINCT college         FROM alumni WHERE college         IS NOT NULL AND TRIM(college)         != '' ORDER BY college");
$majors         = $conn->query("SELECT DISTINCT major           FROM alumni WHERE major           IS NOT NULL AND TRIM(major)           != '' ORDER BY major");
$campuses       = $conn->query("SELECT DISTINCT campus          FROM alumni WHERE campus          IS NOT NULL AND TRIM(campus)          != '' ORDER BY campus");
$study_types    = $conn->query("SELECT DISTINCT study_type      FROM alumni WHERE study_type      IS NOT NULL AND TRIM(study_type)      != '' ORDER BY study_type");
$terms          = $conn->query("SELECT DISTINCT graduation_term FROM alumni WHERE graduation_term IS NOT NULL AND TRIM(graduation_term) != '' ORDER BY graduation_term DESC");
$grades         = $conn->query("SELECT DISTINCT academic_grade  FROM alumni WHERE academic_grade  IS NOT NULL AND TRIM(academic_grade)  != '' ORDER BY academic_grade");
$honors         = $conn->query("SELECT DISTINCT honor_rank      FROM alumni WHERE honor_rank      IS NOT NULL AND TRIM(honor_rank)      != '' ORDER BY honor_rank");
$nationalities  = $conn->query("SELECT DISTINCT nationality     FROM alumni WHERE nationality     IS NOT NULL AND TRIM(nationality)     != '' ORDER BY nationality");

// ── Build WHERE clause ──
$where  = "WHERE 1=1";
$params = [];
$types  = "";

// ── Arabic character normalization for search ──
function normalizeArabicSearch($text) {
    $text = str_replace(['أ','إ','آ','ٱ','ٲ'], 'ا', $text); // alef variants → alef
    $text = str_replace('ى', 'ي', $text);                    // alef maqsoura → ya
    $text = str_replace('ة', 'ه', $text);                    // ta marbuta → ha
    $text = str_replace('ئ', 'ي', $text);                    // ya with hamza → ya
    return $text;
}
// Apply same normalization inside MySQL via REPLACE chains
function normSQL($col) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,'أ','ا'),'إ','ا'),'آ','ا'),'ى','ي'),'ة','ه'),'ئ','ي')";
}

if (!empty($_GET['search'])) {
    $raw        = trim($_GET['search']);
    $normalized = normalizeArabicSearch($raw);

    // First name  → "هدى ..."
    $s_first = $normalized . ' %';
    // Last name   → "... هدى"
    $s_last  = '% ' . $normalized;
    // Single-word name → "هدى"
    $s_exact = $normalized;

    // IDs / email / mobile: partial LIKE كافي
    $s_id = '%' . $raw . '%';

    $norm_name = normSQL('name');
    $where .= " AND ($norm_name LIKE ? OR $norm_name LIKE ? OR $norm_name = ? OR student_id LIKE ? OR national_id LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    array_push($params, $s_first, $s_last, $s_exact, $s_id, $s_id, $s_id, $s_id);
    $types .= "sssssss";
}

$dropdown_filters = ['gender','academic_degree','college','campus','study_type','honor_rank','nationality'];
foreach ($dropdown_filters as $col) {
    if (!empty($_GET[$col])) {
        $where   .= " AND $col = ?";
        $params[] = $_GET[$col];
        $types   .= "s";
    }
}

// ── فلتر التخصص — exact match ──
if (!empty($_GET['major'])) {
    $where   .= " AND major = ?";
    $params[] = $_GET['major'];
    $types   .= "s";
}

// ── فلتر التقدير — يتجاهل التنوين ──
if (!empty($_GET['academic_grade'])) {
    // حذف التنوين من القيمة المرسلة ومن عمود DB
    $grade_norm = str_replace(['ً','ٌ','ٍ'], '', $_GET['academic_grade']);
    $where .= " AND REPLACE(REPLACE(REPLACE(academic_grade,'ً',''),'ٌ',''),'ٍ','') = ?";
    $params[] = $grade_norm;
    $types   .= "s";
}

// فلتر السنة الهجرية — range من/إلى
if (!empty($_GET['year_from']) || !empty($_GET['year_to'])) {
    $yr_from = !empty($_GET['year_from']) ? substr(preg_replace('/\D/','',$_GET['year_from']), 2, 2) : '00';
    $yr_to   = !empty($_GET['year_to'])   ? substr(preg_replace('/\D/','',$_GET['year_to']),   2, 2) : '99';
    $where   .= " AND LEFT(graduation_term, 2) BETWEEN ? AND ?";
    $params[] = $yr_from;
    $params[] = $yr_to;
    $types   .= "ss";
} elseif (!empty($_GET['graduation_term'])) {
    $where   .= " AND graduation_term = ?";
    $params[] = $_GET['graduation_term'];
    $types   .= "s";
}

// السنوات الهجرية
$years_raw   = $conn->query("SELECT DISTINCT graduation_term FROM alumni WHERE graduation_term IS NOT NULL AND graduation_term REGEXP '^[0-9]+$' ORDER BY graduation_term DESC");
$hijri_years = [];
while ($r = $years_raw->fetch_assoc()) {
    $t = $r['graduation_term'];
    if (strlen($t) >= 2) {
        $yr = '14' . substr($t, 0, 2);
        if (!in_array($yr, $hijri_years)) $hijri_years[] = $yr;
    }
}
rsort($hijri_years);

if (!empty($_GET['gpa_min'])) {
    $where   .= " AND gpa >= ?";
    $params[] = floatval($_GET['gpa_min']);
    $types   .= "d";
}
if (!empty($_GET['gpa_max'])) {
    $where   .= " AND gpa <= ?";
    $params[] = floatval($_GET['gpa_max']);
    $types   .= "d";
}

$stmt = $conn->prepare("SELECT * FROM alumni $where ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total  = $result->num_rows;

// Count active filters
$filter_keys  = ['search','gender','academic_degree','college','major','campus','study_type','graduation_term','academic_grade','honor_rank','nationality','gpa_min','gpa_max'];
$active_count = 0;
foreach ($filter_keys as $k) { if (!empty($_GET[$k])) $active_count++; }

function sel($key, $val) {
    return (isset($_GET[$key]) && $_GET[$key] == $val) ? 'selected' : '';
}

function buildOptions($result, $key) {
    $html = '';
    while ($r = $result->fetch_assoc()) {
        $v    = htmlspecialchars(array_values($r)[0]);
        $sel  = sel($key, array_values($r)[0]);
        $html .= "<option value=\"$v\" $sel>$v</option>";
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Records – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
        .gpa-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .gpa-row label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); white-space:nowrap; }
        .gpa-row input { width:110px; background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px 12px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; }
        .gpa-row input:focus { border-color:var(--green); }
        .gpa-row span { color:var(--muted); }
        .filter-actions { display:flex; gap:10px; border-top:1px solid var(--border); padding-top:16px; }
    </style>
</head>
<body>
<div class="layout">

    <?php include "sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Alumni Records</h1>
                <p class="page-sub">Manage and search all College of Computer graduates</p>
            </div>
            <a class="btn btn-primary" href="add_alumni.php">➕ Add Alumni</a>
        </div>

        <!-- Filters -->
        <form method="GET" action="alumni.php">
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
                               placeholder="Search by name, student ID, national ID, email, or mobile..."
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                </div>

                <div class="filters-grid">

                    <div class="filter-group">
                        <label>Gender — الجنس</label>
                        <select name="gender">
                            <option value="">All</option>
                            <?php echo buildOptions($genders, 'gender'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Degree — الدرجة العلمية</label>
                        <select name="academic_degree">
                            <option value="">All</option>
                            <?php echo buildOptions($degrees, 'academic_degree'); ?>
                        </select>
                    </div>

                    

                    <div class="filter-group">
                        <label>Major — التخصص</label>
                        <select name="major">
                            <option value="">All</option>
                            <optgroup label="── بكالوريوس ──">
                                <option value="تقنية المعلومات" <?php echo ($_GET['major']??'')=='تقنية المعلومات'?'selected':''; ?>>تقنية المعلومات</option>
                                <option value="علوم الحاسب"     <?php echo ($_GET['major']??'')=='علوم الحاسب'    ?'selected':''; ?>>علوم الحاسب</option>
                                <option value="هندسة الحاسب"    <?php echo ($_GET['major']??'')=='هندسة الحاسب'   ?'selected':''; ?>>هندسة الحاسب</option>
                            </optgroup>
                            <optgroup label="── ماجستير ──">
                                <option value="العلوم في علوم الحاسب"               <?php echo ($_GET['major']??'')=='العلوم في علوم الحاسب'               ?'selected':''; ?>>العلوم في علوم الحاسب</option>
                                <option value="العلوم في المعلوماتية"               <?php echo ($_GET['major']??'')=='العلوم في المعلوماتية'               ?'selected':''; ?>>العلوم في المعلوماتية</option>
                                <option value="العلوم في الأمن السيبراني"           <?php echo ($_GET['major']??'')=='العلوم في الأمن السيبراني'           ?'selected':''; ?>>العلوم في الأمن السيبراني</option>
                                <option value="العلوم في أمن المعلومات والشبكات"   <?php echo ($_GET['major']??'')=='العلوم في أمن المعلومات والشبكات'   ?'selected':''; ?>>العلوم في أمن المعلومات والشبكات</option>
                                <option value="العلوم في تقنية المعلومات"           <?php echo ($_GET['major']??'')=='العلوم في تقنية المعلومات'           ?'selected':''; ?>>العلوم في تقنية المعلومات</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Campus — المقر</label>
                        <select name="campus">
                            <option value="">All</option>
                            <?php echo buildOptions($campuses, 'campus'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Study Type — نوع الدراسة</label>
                        <select name="study_type">
                            <option value="">All</option>
                            <?php echo buildOptions($study_types, 'study_type'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>من سنة — Year From</label>
                        <select name="year_from" onchange="this.form.graduation_term.value='';">
                            <option value="">— من</option>
                            <?php foreach ($hijri_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo ($_GET['year_from'] ?? '') == $yr ? 'selected' : ''; ?>>
                                <?php echo $yr; ?> هـ
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>إلى سنة — Year To</label>
                        <select name="year_to" onchange="this.form.graduation_term.value='';">
                            <option value="">— إلى</option>
                            <?php foreach ($hijri_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo ($_GET['year_to'] ?? '') == $yr ? 'selected' : ''; ?>>
                                <?php echo $yr; ?> هـ
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Term — الترم</label>
                        <select name="graduation_term" onchange="this.form.year_from.value='';this.form.year_to.value='';">
                            <option value="">All Terms</option>
                            <?php echo buildOptions($terms, 'graduation_term'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Grade — التقدير</label>
                        <select name="academic_grade">
                            <option value="">All</option>
                            <?php echo buildOptions($grades, 'academic_grade'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Honor Rank — مرتبة الشرف</label>
                        <select name="honor_rank">
                            <option value="">All</option>
                            <?php echo buildOptions($honors, 'honor_rank'); ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Nationality — الجنسية</label>
                        <select name="nationality">
                            <option value="">All</option>
                            <?php echo buildOptions($nationalities, 'nationality'); ?>
                        </select>
                    </div>

                </div>

                <div class="gpa-row">
                    <label>GPA — المعدل:</label>
                    <input type="number" name="gpa_min" step="0.01" min="0" max="5"
                           placeholder="Min (0.00)"
                           value="<?php echo htmlspecialchars($_GET['gpa_min'] ?? ''); ?>">
                    <span>—</span>
                    <input type="number" name="gpa_max" step="0.01" min="0" max="5"
                           placeholder="Max (5.00)"
                           value="<?php echo htmlspecialchars($_GET['gpa_max'] ?? ''); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="alumni.php" class="btn btn-outline">✕ Reset All</a>
                </div>

            </div>
        </div>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <div class="table-meta">
                Showing <span class="count-badge"><?php echo number_format($total); ?></span> result(s)
            </div>

            <?php if ($total == 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <p>No alumni found matching your criteria.</p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <tr>
                    <th>#</th>
                    <th>Term — الترم</th>
                    <th>Name — الاسم</th>
                    <th>Student ID — رقم الطالب</th>
                    <th>College — الكلية</th>
                    <th>Major — التخصص</th>
                    <th>Degree — الدرجة</th>
                    <th>Gender — الجنس</th>
                    <th>GPA — المعدل</th>
                    <th>Grade — التقدير</th>
                    <th>Honor — مرتبة الشرف</th>
                    <th>Campus — المقر</th>
                    <th>Nationality — الجنسية</th>
                    <th>Email — البريد</th>
                    <th>Mobile — الجوال</th>
                    <th>Actions</th>
                </tr>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['graduation_term'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['student_id'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['college'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['major'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['academic_degree'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['gender'] ?? '—'); ?></td>
                    <td><?php echo $row['gpa'] !== null ? number_format($row['gpa'],2) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($row['academic_grade'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['honor_rank'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['campus'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['nationality'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['mobile'] ?? '—'); ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-success" href="edit_alumni.php?id=<?php echo $row['id']; ?>">Edit</a>
                            <a class="btn btn-danger"  href="delete_alumni.php?id=<?php echo $row['id']; ?>"
                               onclick="return confirm('Delete this alumni record?')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            </div>
            <?php endif; ?>
        </div>

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
