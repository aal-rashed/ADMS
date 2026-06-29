<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: alumni.php");
    exit();
}

$id   = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM alumni WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: alumni.php");
    exit();
}
$alumni = $result->fetch_assoc();
$stmt->close();

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fields = ['name','national_id','student_id','gender','nationality','college','major',
               'academic_degree','study_type','graduation_term','academic_grade','mobile','email',
               'honor_rank','campus'];

    $clean = [];
    foreach ($fields as $f) {
        $clean[$f] = trim($_POST[$f] ?? '');
        if ($clean[$f] === '') $clean[$f] = null;
    }

    $gpa = null;
    if (!empty($_POST['gpa'])) {
        $gpa_str = str_replace(',', '.', trim($_POST['gpa']));
        if (is_numeric($gpa_str)) $gpa = floatval($gpa_str);
    }

    $stmt = $conn->prepare("UPDATE alumni SET
        name=?, national_id=?, student_id=?, gender=?, nationality=?,
        college=?, major=?, academic_degree=?, study_type=?, graduation_term=?,
        gpa=?, academic_grade=?, mobile=?, email=?, honor_rank=?, campus=?
        WHERE id=?");
    $stmt->bind_param("ssssssssssdsssssi",
        $clean['name'], $clean['national_id'], $clean['student_id'], $clean['gender'], $clean['nationality'],
        $clean['college'], $clean['major'], $clean['academic_degree'], $clean['study_type'], $clean['graduation_term'],
        $gpa, $clean['academic_grade'], $clean['mobile'], $clean['email'], $clean['honor_rank'], $clean['campus'],
        $id
    );

    if ($stmt->execute()) {
        $success = "Record updated successfully!";
        $s2 = $conn->prepare("SELECT * FROM alumni WHERE id = ?");
        $s2->bind_param("i", $id);
        $s2->execute();
        $alumni = $s2->get_result()->fetch_assoc();
        $s2->close();
    } else {
        $error = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

function v($alumni, $key) { return htmlspecialchars($alumni[$key] ?? ''); }
function sel($alumni, $key, $val) { return ($alumni[$key] ?? '') === $val ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Alumni – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:0 2px 10px var(--shadow); max-width:900px; }
        .form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--green); margin:24px 0 14px; padding-bottom:6px; border-bottom:2px solid var(--green-light); }
        .form-section-title:first-child { margin-top:0; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media(max-width:650px) { .form-grid { grid-template-columns:1fr; } }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .field input, .field select { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:9px 12px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .2s; }
        .field input:focus, .field select:focus { border-color:var(--green); background:var(--white); }
        .form-actions { display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--border); align-items:center; }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Edit Alumni</h1>
                <p class="page-sub">
                    <a href="alumni.php" style="color:var(--green);">Alumni Records</a> /
                    <?php echo v($alumni,'name'); ?>
                </p>
            </div>
            <a class="btn btn-outline" href="alumni.php">← Back</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="max-width:900px;">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="max-width:900px;">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-card">
        <form method="POST">

            <div class="form-section-title">👤 Personal Information</div>
            <div class="form-grid">
                <div class="field">
                    <label>Full Name — اسم الطالب *</label>
                    <input type="text" name="name" value="<?php echo v($alumni,'name'); ?>" required>
                </div>
                <div class="field">
                    <label>National ID — السجل المدني</label>
                    <input type="text" name="national_id" value="<?php echo v($alumni,'national_id'); ?>">
                </div>
                <div class="field">
                    <label>Gender — الجنس</label>
                    <select name="gender">
                        <option value="">— Select —</option>
                        <option value="ذكر"  <?php echo sel($alumni,'gender','ذكر'); ?>>ذكر — Male</option>
                        <option value="انثى" <?php echo sel($alumni,'gender','انثى'); ?>>انثى — Female</option>
                    </select>
                </div>
                <div class="field">
                    <label>Nationality — الجنسية</label>
                    <input type="text" name="nationality" value="<?php echo v($alumni,'nationality'); ?>">
                </div>
                <div class="field">
                    <label>Mobile — الجوال</label>
                    <input type="text" name="mobile" value="<?php echo v($alumni,'mobile'); ?>">
                </div>
                <div class="field">
                    <label>Email — البريد الإلكتروني</label>
                    <input type="email" name="email" value="<?php echo v($alumni,'email'); ?>">
                </div>
            </div>

            <div class="form-section-title">🎓 Academic Information</div>
            <div class="form-grid">
                <div class="field">
                    <label>Student ID — رقم الطالب</label>
                    <input type="text" name="student_id" value="<?php echo v($alumni,'student_id'); ?>">
                </div>
                <div class="field">
                    <label>Graduation Term — الترم</label>
                    <input type="text" name="graduation_term" value="<?php echo v($alumni,'graduation_term'); ?>">
                </div>
                <div class="field">
                    <label>Academic Degree — الدرجة العلمية</label>
                    <select name="academic_degree">
                        <option value="">— Select —</option>
                        <option value="بكالوريوس" <?php echo sel($alumni,'academic_degree','بكالوريوس'); ?>>بكالوريوس — Bachelor</option>
                        <option value="ماجستير"   <?php echo sel($alumni,'academic_degree','ماجستير'); ?>>ماجستير — Master</option>
                        <option value="دكتوراه"   <?php echo sel($alumni,'academic_degree','دكتوراه'); ?>>دكتوراه — PhD</option>
                    </select>
                </div>
                <div class="field">
                    <label>Study Type — نوع الدراسة</label>
                    <input type="text" name="study_type" value="<?php echo v($alumni,'study_type'); ?>">
                </div>
                <div class="field">
                    <label>College — الكلية</label>
                    <input type="text" name="college" value="<?php echo v($alumni,'college'); ?>">
                </div>
                <div class="field">
                    <label>Major — التخصص</label>
                    <input type="text" name="major" value="<?php echo v($alumni,'major'); ?>">
                </div>
                <div class="field">
                    <label>Campus — المقر</label>
                    <input type="text" name="campus" value="<?php echo v($alumni,'campus'); ?>">
                </div>
                <div class="field">
                    <label>GPA — المعدل</label>
                    <input type="number" step="0.01" min="0" max="5" name="gpa" value="<?php echo v($alumni,'gpa'); ?>">
                </div>
                <div class="field">
                    <label>Academic Grade — التقدير</label>
                    <select name="academic_grade">
                        <option value="">— Select —</option>
                        <?php foreach(['ممتاز','جيد جداً','جيد','مقبول'] as $g): ?>
                        <option value="<?php echo $g; ?>" <?php echo sel($alumni,'academic_grade',$g); ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Honor Rank — مرتبة الشرف</label>
                    <select name="honor_rank">
                        <option value="">— None —</option>
                        <option value="مرتبة الشرف الأولى"  <?php echo sel($alumni,'honor_rank','مرتبة الشرف الأولى'); ?>>مرتبة الشرف الأولى</option>
                        <option value="مرتبة الشرف الثانية" <?php echo sel($alumni,'honor_rank','مرتبة الشرف الثانية'); ?>>مرتبة الشرف الثانية</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="padding:11px 28px;">💾 Save Changes</button>
                <a href="alumni.php" class="btn btn-outline">Cancel</a>
                <a href="delete_alumni.php?id=<?php echo $id; ?>"
                   class="btn btn-danger" style="margin-right:auto;"
                   onclick="return confirm('Delete this alumni record permanently?')">
                   🗑️ Delete Record
                </a>
            </div>

        </form>
        </div>
    </main>
</div>
</body>
</html>
