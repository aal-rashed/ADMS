<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ── Build WHERE ──
$where  = "WHERE 1=1";
$params = [];
$types  = "";

$filters = ['gender','academic_degree','college','campus','study_type','honor_rank','nationality'];
foreach ($filters as $col) {
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

if (!empty($_GET['search'])) {
    $s = "%" . $_GET['search'] . "%";
    $where .= " AND (name LIKE ? OR student_id LIKE ? OR national_id LIKE ?)";
    array_push($params, $s, $s, $s);
    $types .= "sss";
}
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

// ── CSV Download ──
if (isset($_GET['csv'])) {
    $stmt = $conn->prepare("SELECT name, student_id, national_id, major, academic_degree, gender, gpa, academic_grade, honor_rank, graduation_term, mobile, email, campus, nationality, study_type FROM alumni $where ORDER BY graduation_term ASC, name ASC");
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="alumni_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM عشان Excel يفتح العربي صح
    fputcsv($out, ['الاسم','رقم الطالب','السجل المدني','التخصص','الدرجة العلمية','الجنس','المعدل','التقدير','مرتبة الشرف','الترم','الجوال','البريد الإلكتروني','المقر','الجنسية','نوع الدراسة']);
    while ($r = $result->fetch_assoc()) {
        fputcsv($out, [
            $r['name']            ?? '',
            $r['student_id']      ?? '',
            $r['national_id']     ?? '',
            $r['major']           ?? '',
            $r['academic_degree'] ?? '',
            $r['gender']          ?? '',
            $r['gpa']             ?? '',
            $r['academic_grade']  ?? '',
            $r['honor_rank']      ?? '',
            $r['graduation_term'] ?? '',
            $r['mobile']          ?? '',
            $r['email']           ?? '',
            $r['campus']          ?? '',
            $r['nationality']     ?? '',
            $r['study_type']      ?? '',
        ]);
    }
    fclose($out);
    $stmt->close();
    exit();
}


// Dropdowns
$genders  = $conn->query("SELECT DISTINCT gender          FROM alumni WHERE gender          IS NOT NULL AND TRIM(gender)          != '' ORDER BY gender");
$degrees  = $conn->query("SELECT DISTINCT academic_degree FROM alumni WHERE academic_degree IS NOT NULL AND TRIM(academic_degree) != '' ORDER BY academic_degree");
$colleges = $conn->query("SELECT DISTINCT college         FROM alumni WHERE college         IS NOT NULL AND TRIM(college)         != '' ORDER BY college");
$majors   = $conn->query("SELECT DISTINCT major           FROM alumni WHERE major           IS NOT NULL AND TRIM(major)           != '' ORDER BY major");
$campuses = $conn->query("SELECT DISTINCT campus          FROM alumni WHERE campus          IS NOT NULL AND TRIM(campus)          != '' ORDER BY campus");
$terms    = $conn->query("SELECT DISTINCT graduation_term FROM alumni WHERE graduation_term IS NOT NULL AND TRIM(graduation_term) != '' ORDER BY graduation_term DESC");
$honors   = $conn->query("SELECT DISTINCT honor_rank      FROM alumni WHERE honor_rank      IS NOT NULL AND TRIM(honor_rank)      != '' ORDER BY honor_rank");
$nats     = $conn->query("SELECT DISTINCT nationality     FROM alumni WHERE nationality     IS NOT NULL AND TRIM(nationality)     != '' ORDER BY nationality");

// السنوات الهجرية — نستخرجها من الترمات (أول رقمين + "14")
$years_raw = $conn->query("SELECT DISTINCT graduation_term FROM alumni WHERE graduation_term IS NOT NULL AND graduation_term REGEXP '^[0-9]+$' ORDER BY graduation_term DESC");
$hijri_years = [];
while ($r = $years_raw->fetch_assoc()) {
    $t = $r['graduation_term'];
    if (strlen($t) >= 2) {
        $yr = '14' . substr($t, 0, 2);
        if (!in_array($yr, $hijri_years)) $hijri_years[] = $yr;
    }
}
rsort($hijri_years);

// Fetch results if preview requested
$rows        = [];
$total_found = 0;
$show_preview = isset($_GET['preview']);

if ($show_preview) {
    $stmt = $conn->prepare("SELECT campus, gender, academic_degree, college, major, study_type, graduation_term, student_id, name, national_id, nationality, gpa, academic_grade, mobile, email, honor_rank FROM alumni $where ORDER BY graduation_term ASC, name ASC");
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result      = $stmt->get_result();
    $total_found = $result->num_rows;
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}

$total_all = $conn->query("SELECT COUNT(*) as c FROM alumni")->fetch_assoc()['c'];

// Active filter count
$active = 0;
foreach (array_merge($filters, ['search']) as $k) { if (!empty($_GET[$k])) $active++; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Screen styles ── */
        .export-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:0 2px 10px var(--shadow); max-width:900px; margin-bottom:24px; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:20px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .field select, .field input { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px 11px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; }
        .field select:focus, .field input:focus { border-color:var(--green); }
        .export-info { background:var(--green-light); border:1px solid var(--green); border-radius:var(--radius-sm); padding:14px 18px; margin-bottom:20px; font-size:13px; color:var(--green-dark); }
        .preview-wrap { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:0 2px 10px var(--shadow); max-width:900px; }
        .preview-actions { display:flex; gap:12px; margin-bottom:20px; align-items:center; }
        .preview-count { font-size:13px; color:var(--muted); }

        /* ── PDF print area ── */
        #printArea { direction:rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; }
        #printArea .pdf-header { text-align:center; margin-bottom:20px; border-bottom:3px solid #2e7d32; padding-bottom:14px; }
        #printArea .pdf-header h2 { margin:0 0 4px; font-size:18px; color:#2e7d32; }
        #printArea .pdf-header p  { margin:0; font-size:12px; color:#666; }
        #printArea .pdf-meta { font-size:11px; color:#666; margin-bottom:12px; display:flex; justify-content:space-between; }
        #printArea table { width:100%; border-collapse:collapse; font-size:11px; }
        #printArea th { background:#2e7d32; color:#fff; padding:7px 8px; text-align:right; font-weight:600; border:1px solid #1b5e20; }
        #printArea td { padding:6px 8px; border:1px solid #ddd; text-align:right; vertical-align:top; }
        #printArea tr:nth-child(even) td { background:#f1f8e9; }

        /* ── Print media ── */
        @media print {
            /* إلغاء مسافة الـ sidebar */
            html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .layout, .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            body * { visibility: hidden !important; }
            #printArea, #printArea * { visibility: visible !important; }
            #printArea {
                position: absolute;
                top: 0; left: 0; right: 0;
                width: 100% !important;
                margin: 0 !important;
                direction: rtl;
            }
            #printArea table { font-size:9px; table-layout:fixed; width:100%; }
            #printArea th, #printArea td { padding:5px 4px; word-break:break-word; overflow-wrap:break-word; }

            /* عرض الأعمدة */
            #printArea table th:nth-child(1),
            #printArea table td:nth-child(1)  { width:3%;  }
            #printArea table th:nth-child(2),
            #printArea table td:nth-child(2)  { width:17%; }
            #printArea table th:nth-child(3),
            #printArea table td:nth-child(3)  { width:8%;  }
            #printArea table th:nth-child(4),
            #printArea table td:nth-child(4)  { width:10%; }
            #printArea table th:nth-child(5),
            #printArea table td:nth-child(5)  { width:7%;  }
            #printArea table th:nth-child(6),
            #printArea table td:nth-child(6)  { width:5%;  }
            #printArea table th:nth-child(7),
            #printArea table td:nth-child(7)  { width:6%;  }
            #printArea table th:nth-child(8),
            #printArea table td:nth-child(8)  { width:7%;  }
            #printArea table th:nth-child(9),
            #printArea table td:nth-child(9)  { width:10%; }
            #printArea table th:nth-child(10),
            #printArea table td:nth-child(10) { width:9%;  }
            #printArea table th:nth-child(11),
            #printArea table td:nth-child(11) { width:18%; }
            #printArea .col-term { display:none; }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Export to PDF</h1>
                <p class="page-sub">Filter alumni data and export as PDF</p>
            </div>
            <a class="btn btn-outline" href="dashboard.php">← Dashboard</a>
        </div>

        <!-- Filter Form -->
        <div class="export-card">
            <div class="export-info">
                📊 Total records: <strong><?php echo number_format($total_all); ?></strong> alumni.
                Use filters to export a specific subset, or leave empty to export all.
            </div>

            <form method="GET" action="export_alumni.php">
                <input type="hidden" name="preview" value="1">
                <div class="filters-grid">
                    <div class="field">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name / Student ID..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="field">
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
                    <div class="field">
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
                    <div class="field">
                        <label>Term — الترم</label>
                        <select name="graduation_term" onchange="this.form.year_from.value='';this.form.year_to.value='';">
                            <option value="">All Terms</option>
                            <?php while($r=$terms->fetch_assoc()): $v=htmlspecialchars($r['graduation_term']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['graduation_term']??'')==$r['graduation_term']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Gender — الجنس</label>
                        <select name="gender">
                            <option value="">All</option>
                            <?php while($r=$genders->fetch_assoc()): $v=htmlspecialchars($r['gender']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['gender']??'')==$r['gender']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Degree — الدرجة</label>
                        <select name="academic_degree">
                            <option value="">All</option>
                            <?php while($r=$degrees->fetch_assoc()): $v=htmlspecialchars($r['academic_degree']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['academic_degree']??'')==$r['academic_degree']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="field">
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
                    <div class="field">
                        <label>Campus — المقر</label>
                        <select name="campus">
                            <option value="">All</option>
                            <?php while($r=$campuses->fetch_assoc()): $v=htmlspecialchars($r['campus']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['campus']??'')==$r['campus']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Honor Rank</label>
                        <select name="honor_rank">
                            <option value="">All</option>
                            <?php while($r=$honors->fetch_assoc()): $v=htmlspecialchars($r['honor_rank']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['honor_rank']??'')==$r['honor_rank']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Nationality</label>
                        <select name="nationality">
                            <option value="">All</option>
                            <?php while($r=$nats->fetch_assoc()): $v=htmlspecialchars($r['nationality']); ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_GET['nationality']??'')==$r['nationality']?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field" style="grid-column:1/-1;">
    <label>GPA — المعدل</label>
    <div style="display:flex;align-items:center;gap:10px;">
        <input type="number" name="gpa_min" step="0.01" min="0" max="5"
               placeholder="Min (0.00)"
               value="<?php echo htmlspecialchars($_GET['gpa_min'] ?? ''); ?>"
               style="width:130px;">
        <span style="color:var(--muted);">—</span>
        <input type="number" name="gpa_max" step="0.01" min="0" max="5"
               placeholder="Max (5.00)"
               value="<?php echo htmlspecialchars($_GET['gpa_max'] ?? ''); ?>"
               style="width:130px;">
    </div>
</div>
                </div>
                <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid var(--border);">
                    <button type="submit" class="btn btn-primary" style="padding:11px 24px;">🔍 Preview Results</button>
                    <a href="export_alumni.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>

        <!-- Preview & Print -->
        <?php if ($show_preview): ?>
        <div class="preview-wrap">
            <div class="preview-actions">
                <button onclick="
    var style = document.createElement('style');
    style.innerHTML = '@page { size: A4 landscape; margin: 8mm; }';
    style.id = 'landscapeStyle';
    document.head.appendChild(style);
    window.print();
    setTimeout(function(){ var s = document.getElementById('landscapeStyle'); if(s) s.remove(); }, 1000);
" class="btn btn-primary" style="padding:10px 24px;">🖨️ Save as PDF</button>
                <a href="<?php
                    $csv_params = $_GET;
                    $csv_params['csv'] = '1';
                    unset($csv_params['preview']);
                    echo 'export_alumni.php?' . http_build_query($csv_params);
                ?>" class="btn btn-success" style="padding:10px 24px;">📊 Save as CSV</a>
                <span class="preview-count">Found <strong><?php echo number_format($total_found); ?></strong> records</span>
            </div>

            <div id="printArea">
                <div class="pdf-header">
                    <h2>كلية الحاسب — جامعة القصيم</h2>
                    <p>Graduate Data Report — تقرير بيانات الخريجين</p>
                </div>
                <div class="pdf-meta">
                    <span>تاريخ التصدير: <?php echo date('Y-m-d'); ?></span>
                    <span>عدد السجلات: <?php echo number_format($total_found); ?></span>
                    <?php if (!empty($_GET['graduation_term'])): ?>
                    <span>الترم: <?php echo htmlspecialchars($_GET['graduation_term']); ?></span>
                    <?php endif; ?>
                </div>
                <table>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>رقم الطالب</th>
                        <th>التخصص</th>
                        <th>الدرجة</th>
                        <th>الجنس</th>
                        <th>المعدل</th>
                        <th>التقدير</th>
                        <th>مرتبة الشرف</th>
                        <th>الجوال</th>
                        <th>البريد</th>
                        <th class="col-term">الترم</th>
                    </tr>
                    <?php foreach($rows as $i => $r): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($r['name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['student_id'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['major'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['academic_degree'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['gender'] ?? '—'); ?></td>
                        <td><?php echo $r['gpa'] ? number_format($r['gpa'],2) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($r['academic_grade'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['honor_rank'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['mobile'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['email'] ?? '—'); ?></td>
                        <td class="col-term"><?php echo htmlspecialchars($r['graduation_term'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
