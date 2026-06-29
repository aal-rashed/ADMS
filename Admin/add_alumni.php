<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$message  = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fields = ['campus','gender','academic_degree','college','major','study_type','graduation_term',
               'student_id','name','national_id','nationality','academic_grade','mobile','email','honor_rank'];

    $clean = [];
    foreach ($fields as $f) {
        $clean[$f] = trim($_POST[$f] ?? '');
        if ($clean[$f] === '') $clean[$f] = null;
    }

    // GPA
    $gpa = null;
    if (!empty($_POST['gpa'])) {
        $gpa_str = str_replace(',', '.', trim($_POST['gpa']));
        if (is_numeric($gpa_str)) $gpa = floatval($gpa_str);
    }

    // Validate required
    if (empty($clean['name'])) {
        $message  = "Name is required.";
        $msg_type = "error";
    } else {
        // Check duplicate student_id
        if (!empty($clean['student_id'])) {
            $check = $conn->prepare("SELECT id FROM alumni WHERE student_id = ?");
            $check->bind_param("s", $clean['student_id']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message  = "A record with this Student ID already exists.";
                $msg_type = "error";
            }
            $check->close();
        }

        if (!$message) {
            $stmt = $conn->prepare("
                INSERT INTO alumni (campus, gender, academic_degree, college, major, study_type, graduation_term,
                    student_id, name, national_id, nationality, gpa, academic_grade, mobile, email, honor_rank)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param("sssssssssssdssss",
                $clean['campus'], $clean['gender'], $clean['academic_degree'], $clean['college'],
                $clean['major'], $clean['study_type'], $clean['graduation_term'],
                $clean['student_id'], $clean['name'], $clean['national_id'], $clean['nationality'],
                $gpa, $clean['academic_grade'], $clean['mobile'], $clean['email'], $clean['honor_rank']
            );

            if ($stmt->execute()) {
                $message  = "✅ Alumni added successfully!";
                $msg_type = "success";
                // Clear form
                $_POST = [];
            } else {
                $message  = "Error: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

function val($key) {
    return htmlspecialchars($_POST[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Alumni – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; box-shadow:0 2px 10px var(--shadow); max-width:900px; }
        .form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--green); margin:24px 0 14px; padding-bottom:6px; border-bottom:2px solid var(--green-light); }
        .form-section-title:first-child { margin-top:0; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
        @media(max-width:650px) { .form-grid,.form-grid-3 { grid-template-columns:1fr; } }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
        .field input, .field select { background:var(--off-white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:9px 12px; color:var(--text); font-family:var(--font-body); font-size:13px; outline:none; transition:border-color .2s; }
        .field input:focus, .field select:focus { border-color:var(--green); background:var(--white); }
        .field input::placeholder { color:#b0bfb1; }
        .form-actions { display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid var(--border); }
    </style>
</head>
<body>
<div class="layout">
    <?php include "sidebar.php"; ?>
    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Add Alumni</h1>
                <p class="page-sub">Manually add a new graduate record</p>
            </div>
            <a class="btn btn-outline" href="alumni.php">← Back to Records</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>" style="max-width:900px;"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">

                <!-- Personal Info -->
                <div class="form-section-title">👤 Personal Information</div>
                <div class="form-grid">
                    <div class="field">
                        <label>Full Name — اسم الطالب *</label>
                        <input type="text" name="name" placeholder="e.g. محمد عبدالله " value="<?php echo val('name'); ?>" required>
                    </div>
                    <div class="field">
                        <label>National ID — السجل المدني</label>
                        <input type="text" name="national_id" placeholder="e.g. 1234567890" value="<?php echo val('national_id'); ?>">
                    </div>
                    <div class="field">
                        <label>Gender — الجنس</label>
                        <select name="gender">
                            <option value="">— Select —</option>
                            <option value="ذكر"  <?php echo val('gender')=='ذكر'  ? 'selected':''; ?>>ذكر — Male</option>
                            <option value="انثى" <?php echo val('gender')=='انثى' ? 'selected':''; ?>>انثى — Female</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Nationality — الجنسية</label>
                        <input type="text" name="nationality" placeholder="e.g. سعودي" value="<?php echo val('nationality'); ?>">
                    </div>
                    <div class="field">
                        <label>Mobile — الجوال</label>
                        <input type="text" name="mobile" placeholder="e.g. 0512345678" value="<?php echo val('mobile'); ?>">
                    </div>
                    <div class="field">
                        <label>Email — البريد الإلكتروني</label>
                        <input type="email" name="email" placeholder="e.g. student@qu.edu.sa" value="<?php echo val('email'); ?>">
                    </div>
                </div>

                <!-- Academic Info -->
                <div class="form-section-title">🎓 Academic Information</div>
                <div class="form-grid">
                    <div class="field">
                        <label>Student ID — رقم الطالب</label>
                        <input type="text" name="student_id" placeholder="e.g. 441100123" value="<?php echo val('student_id'); ?>">
                    </div>
                    <div class="field">
                        <label>Graduation Term — الترم</label>
                        <input type="text" name="graduation_term" placeholder="e.g. 452" value="<?php echo val('graduation_term'); ?>">
                    </div>
                    <div class="field">
                        <label>Academic Degree — الدرجة العلمية</label>
                        <select name="academic_degree">
                            <option value="">— Select —</option>
                            <option value="بكالوريوس" <?php echo val('academic_degree')=='بكالوريوس' ? 'selected':''; ?>>بكالوريوس — Bachelor</option>
                            <option value="ماجستير"   <?php echo val('academic_degree')=='ماجستير'   ? 'selected':''; ?>>ماجستير — Master</option>
                            <option value="دكتوراه"   <?php echo val('academic_degree')=='دكتوراه'   ? 'selected':''; ?>>دكتوراه — PhD</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Study Type — نوع الدراسة</label>
                        <input type="text" name="study_type" placeholder="e.g. انتظام" value="<?php echo val('study_type'); ?>">
                    </div>
                    <div class="field">
                        <label>College — الكلية</label>
                        <input type="text" name="college" placeholder="e.g. الحاسب" value="<?php echo val('college'); ?>">
                    </div>
                    <div class="field">
                        <label>Major — التخصص</label>
                        <input type="text" name="major" placeholder="e.g. تقنية المعلومات" value="<?php echo val('major'); ?>">
                    </div>
                    <div class="field">
                        <label>Campus — المقر</label>
                        <input type="text" name="campus" placeholder="e.g. مقر الجامعة الرئيس طلاب / طالبات" value="<?php echo val('campus'); ?>">
                    </div>
                    <div class="field">
                        <label>GPA — المعدل</label>
                        <input type="number" name="gpa" step="0.01" min="0" max="5" placeholder="e.g. 4.75" value="<?php echo val('gpa'); ?>">
                    </div>
                    <div class="field">
                        <label>Academic Grade — التقدير</label>
                        <select name="academic_grade">
                            <option value="">— Select —</option>
                            <?php foreach(['ممتاز','جيد جداً','جيد','مقبول'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo val('academic_grade')==$g ? 'selected':''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Honor Rank — مرتبة الشرف</label>
                        <select name="honor_rank">
                            <option value="">— None —</option>
                            <option value="مرتبة الشرف الأولى"  <?php echo val('honor_rank')=='مرتبة الشرف الأولى'  ? 'selected':''; ?>>مرتبة الشرف الأولى</option>
                            <option value="مرتبة الشرف الثانية" <?php echo val('honor_rank')=='مرتبة الشرف الثانية' ? 'selected':''; ?>>مرتبة الشرف الثانية</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="padding:11px 28px;">➕ Add Alumni</button>
                    <a href="alumni.php" class="btn btn-outline">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>
</body>
</html>
