<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include "config.php";

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

$id   = (int) $_SESSION['alumni_id'];
$stmt = $conn->prepare("SELECT * FROM alumni WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { session_destroy(); header("Location: alumni_login.php"); exit(); }
$a = $result->fetch_assoc();
$stmt->close();

function e($v) { return htmlspecialchars($v ?? ''); }
function field($label, $val) {
    $display = (trim($val ?? '') !== '') ? htmlspecialchars($val) : '<span style="color:#b0bfb1;">—</span>';
    echo "<div class=\"info-item\">
            <div class=\"info-label\">$label</div>
            <div class=\"info-value\">$display</div>
          </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – Alumni Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hero-card {
            background:linear-gradient(135deg,var(--green-dark) 0%,#1b5e20 100%);
            border-radius:var(--radius-lg); padding:32px 36px;
            display:flex; align-items:center; gap:24px;
            margin-bottom:24px; position:relative; overflow:hidden;
            box-shadow:0 8px 30px rgba(46,125,50,0.28); max-width:900px;
        }
        .hero-card::before { content:''; position:absolute; top:-70px; right:-70px; width:260px; height:260px; background:rgba(255,255,255,0.05); border-radius:50%; }
        .hero-card::after  { content:''; position:absolute; bottom:-60px; left:160px; width:200px; height:200px; background:rgba(255,255,255,0.04); border-radius:50%; }
        .hero-avatar { width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.15); border:3px solid rgba(255,255,255,0.3); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:700; color:#fff; font-family:var(--font-display); flex-shrink:0; position:relative; z-index:1; }
        .hero-body  { position:relative; z-index:1; flex:1; }
        .hero-eyebrow { font-size:10px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,0.45); margin-bottom:5px; }
        .hero-name  { font-family:var(--font-display); font-size:clamp(18px,2.5vw,24px); font-weight:700; color:#fff; margin-bottom:8px; line-height:1.2; }
        .hero-meta  { display:flex; flex-wrap:wrap; gap:8px; }
        .hero-pill  { display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18); border-radius:100px; padding:4px 12px; font-size:12px; color:rgba(255,255,255,0.85); }
        .hero-honor { background:#f9a825; color:#5d4037; border:none; font-weight:700; margin-top:8px; }

        .gpa-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; max-width:900px; }
        .gpa-card  { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; text-align:center; box-shadow:0 2px 10px var(--shadow); transition:transform .2s,box-shadow .2s; }
        .gpa-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px var(--shadow); }
        .gpa-card-icon  { font-size:22px; margin-bottom:6px; }
        .gpa-card-val   { font-family:var(--font-display); font-size:24px; font-weight:700; color:var(--green-dark); line-height:1; margin-bottom:4px; }
        .gpa-card-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }

        .section-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:0 2px 10px var(--shadow); margin-bottom:20px; max-width:900px; }
        .section-head { padding:14px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; background:var(--off-white); }
        .sh-icon  { width:32px; height:32px; border-radius:8px; background:var(--green-light); display:flex; align-items:center; justify-content:center; font-size:16px; }
        .sh-title { font-size:13px; font-weight:600; color:var(--text); }
        .sh-sub   { font-size:11px; color:var(--muted); margin-top:1px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; }
        .info-item { padding:14px 22px; border-bottom:1px solid var(--border); border-right:1px solid var(--border); }
        .info-item:nth-child(even)  { border-right:none; }
        .info-item:nth-last-child(-n+2) { border-bottom:none; }
        .info-item:last-child:nth-child(odd) { border-right:none; grid-column:span 2; border-bottom:none; }
        .info-label { font-size:10.5px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:4px; }
        .info-value { font-size:14px; color:var(--text); font-weight:500; line-height:1.4; }

        @media(max-width:650px) {
            .hero-card { flex-direction:column; text-align:center; padding:26px 20px; }
            .hero-meta { justify-content:center; }
            .gpa-strip { grid-template-columns:1fr; }
            .info-grid { grid-template-columns:1fr; }
            .info-item { border-right:none !important; }
            .info-item:last-child { grid-column:span 1; }
            .com-prof-card { flex-direction:column; text-align:center; }
            .com-prof-photo { margin: 0 auto; }
            .com-prof-actions { justify-content:center; }
        }

        /* Community profile card — sits alongside main profile */
        .com-prof-card {
            background:var(--white);
            border:1px solid var(--border);
            border-radius:var(--radius-lg);
            padding:22px 26px;
            margin-bottom:24px;
            max-width:900px;
            display:flex;
            align-items:center;
            gap:22px;
            box-shadow:0 2px 10px var(--shadow);
            position:relative;
            overflow:hidden;
        }
        .com-prof-card::before {
            content:'';
            position:absolute;
            top:0; left:0; bottom:0; width:4px;
            background:linear-gradient(180deg, var(--green-dark), #66bb6a);
        }
        .com-prof-photo {
            width:90px; height:90px; border-radius:50%;
            object-fit:cover; flex-shrink:0;
            border:3px solid var(--green-light);
        }
        .com-prof-photo-init {
            width:90px; height:90px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-family:var(--font-display); font-size:32px; font-weight:700;
            color:#fff; background:var(--green-dark);
            flex-shrink:0; border:3px solid var(--green-light);
        }
        .com-prof-body { flex:1; min-width:0; }
        .com-prof-eyebrow {
            font-size:10px; font-weight:600; letter-spacing:2px;
            text-transform:uppercase; color:var(--green-dark);
            margin-bottom:6px;
        }
        .com-prof-name {
            font-family:var(--font-display); font-size:18px;
            font-weight:700; color:var(--text); margin-bottom:6px;
            line-height:1.2;
        }
        .com-prof-bio {
            font-size:13.5px; color:var(--text); line-height:1.55;
            white-space:pre-wrap; word-wrap:break-word;
        }
        .com-prof-bio-empty {
            font-size:13px; color:var(--muted); font-style:italic;
        }
        .com-prof-actions {
            display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;
        }
        .com-prof-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:7px 14px; border-radius:8px;
            font-size:12.5px; font-weight:600;
            text-decoration:none; transition:all .15s;
            border:1px solid transparent;
        }
        .com-prof-btn-primary {
            background:var(--green-dark); color:#fff;
        }
        .com-prof-btn-primary:hover { background:#1b5e20; }
        .com-prof-btn-secondary {
            background:var(--white); color:var(--green-dark);
            border-color:var(--green-light);
        }
        .com-prof-btn-secondary:hover { background:var(--off-white); }
    </style>
</head>
<body>
<div class="layout">

    <?php include "alumni_sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>My Profile</h1>
                <p class="page-sub">Your academic record and personal information</p>
            </div>
        </div>

        <!-- Hero -->
        <div class="hero-card">
            <div class="hero-avatar"><?php echo mb_substr($a['name'] ?? 'A', 0, 1); ?></div>
            <div class="hero-body">
                <div class="hero-eyebrow">Alumni Profile</div>
                <div class="hero-name"><?php echo e($a['name']); ?></div>
                <div class="hero-meta">
                    <?php if ($a['student_id']): ?><span class="hero-pill">🎓 <?php echo e($a['student_id']); ?></span><?php endif; ?>
                    <?php if ($a['major']): ?><span class="hero-pill">📚 <?php echo e($a['major']); ?></span><?php endif; ?>
                    <?php if ($a['graduation_term']): ?><span class="hero-pill">📅 Term <?php echo e($a['graduation_term']); ?></span><?php endif; ?>
                    <?php if ($a['academic_degree']): ?><span class="hero-pill">🏛️ <?php echo e($a['academic_degree']); ?></span><?php endif; ?>
                    <?php if (!empty($a['honor_rank'])): ?><span class="hero-pill hero-honor">🏅 <?php echo e($a['honor_rank']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Community Profile (photo + bio shown in the Community module) -->
        <?php
        $communityPhotoPath = $a['profile_photo'] ?? null;
        $communityBio       = trim((string) ($a['bio'] ?? ''));
        $hasCommunityPhoto  = $communityPhotoPath && file_exists(__DIR__ . '/' . $communityPhotoPath);
        ?>
        <div class="com-prof-card">
            <?php if ($hasCommunityPhoto): ?>
                <img src="<?php echo e($communityPhotoPath); ?>" alt="" class="com-prof-photo">
            <?php else:
                $parts = preg_split('/\s+/', trim((string) $a['name'])) ?: ['?'];
                $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
                if (count($parts) > 1) {
                    $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
                }
                ?>
                <div class="com-prof-photo-init"><?php echo e($initials); ?></div>
            <?php endif; ?>

            <div class="com-prof-body">
                <div class="com-prof-eyebrow">💬 Community Profile</div>
                <div class="com-prof-name"><?php echo e($a['name']); ?></div>
                <?php if ($communityBio !== ''): ?>
                    <div class="com-prof-bio"><?php echo e($communityBio); ?></div>
                <?php else: ?>
                    <div class="com-prof-bio-empty">No bio added yet — share something about yourself with the community.</div>
                <?php endif; ?>
                <div class="com-prof-actions">
                    <a href="alumni_community_profile.php?type=alumni&id=<?php echo (int) $a['id']; ?>#edit" class="com-prof-btn com-prof-btn-primary">
                        ✏️ Edit Community Profile
                    </a>
                    <a href="alumni_community_profile.php?type=alumni&id=<?php echo (int) $a['id']; ?>" class="com-prof-btn com-prof-btn-secondary">
                        👁️ View in Community
                    </a>
                </div>
            </div>
        </div>

        <!-- GPA strip -->
        <div class="gpa-strip">
            <div class="gpa-card">
                <div class="gpa-card-icon">📊</div>
                <div class="gpa-card-val"><?php echo ($a['gpa'] !== null) ? number_format($a['gpa'],2) : '—'; ?></div>
                <div class="gpa-card-label">GPA — المعدل</div>
            </div>
            <div class="gpa-card">
                <div class="gpa-card-icon">🏆</div>
                <div class="gpa-card-val" style="font-size:18px;padding-top:4px;"><?php echo e($a['academic_grade']) ?: '—'; ?></div>
                <div class="gpa-card-label">Grade — التقدير</div>
            </div>
            <div class="gpa-card">
                <div class="gpa-card-icon">📅</div>
                <div class="gpa-card-val" style="font-size:20px;padding-top:4px;"><?php echo e($a['graduation_term']) ?: '—'; ?></div>
                <div class="gpa-card-label">Term — الترم</div>
            </div>
        </div>

        <!-- Personal -->
        <div class="section-card">
            <div class="section-head">
                <div class="sh-icon">👤</div>
                <div><div class="sh-title">Personal Information</div><div class="sh-sub">المعلومات الشخصية</div></div>
            </div>
            <div class="info-grid">
                <?php
                field('Full Name — الاسم',           $a['name']);
                field('National ID — السجل المدني',  $a['national_id']);
                field('Gender — الجنس',              $a['gender']);
                field('Nationality — الجنسية',       $a['nationality']);
                field('Mobile — الجوال',             $a['mobile']);
                field('Email — البريد الإلكتروني',  $a['email']);
                ?>
            </div>
        </div>

        <!-- Academic -->
        <div class="section-card">
            <div class="section-head">
                <div class="sh-icon">🎓</div>
                <div><div class="sh-title">Academic Information</div><div class="sh-sub">المعلومات الأكاديمية</div></div>
            </div>
            <div class="info-grid">
                <?php
                field('Student ID — رقم الطالب',           $a['student_id']);
                field('Academic Degree — الدرجة العلمية',  $a['academic_degree']);
                field('College — الكلية',                  $a['college']);
                field('Major — التخصص',                    $a['major']);
                field('Study Type — نوع الدراسة',          $a['study_type']);
                field('Campus — المقر',                     $a['campus']);
                field('Graduation Term — الترم',            $a['graduation_term']);
                field('GPA — المعدل',                       $a['gpa'] !== null ? number_format($a['gpa'],2) : null);
                field('Academic Grade — التقدير',           $a['academic_grade']);
                field('Honor Rank — مرتبة الشرف',           !empty($a['honor_rank']) ? $a['honor_rank'] : 'لا يوجد');
                ?>
            </div>
        </div>

        <p style="font-size:12px;color:var(--muted);margin-top:8px;max-width:900px;text-align:center;">
            © 2026 College of Computer — Qassim University &nbsp;|&nbsp; This information is confidential and for personal use only.
        </p>

    </main>
</div>
</body>
</html>
