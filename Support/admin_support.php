<?php
/**
 * Admin Support — inbox of alumni conversations.
 *  - Without ?alumni_id : shows the inbox list.
 *  - With ?alumni_id=N  : opens the chat thread with that alumni.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/support_lib.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId   = (int) $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$csrf      = support_csrf_token();

$ready = $pdo && support_table_ready($pdo);

$openAlumniId = isset($_GET['alumni_id']) ? (int) $_GET['alumni_id'] : 0;
$search       = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$inbox      = [];
$thread     = [];
$alumniRow  = null;

if ($ready) {
    $inbox = support_admin_inbox($pdo, $search);

    if ($openAlumniId > 0) {
        $alumniRow = support_load_alumni($pdo, $openAlumniId);
        if ($alumniRow) {
            $thread = support_fetch_thread($pdo, $openAlumniId);
            // Mark incoming alumni messages as read for this admin view
            support_mark_read($pdo, $openAlumniId, 'admin');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Inbox – ADMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ============== Support inbox + chat (admin) ============== */
        .sup-app {
            display:grid;
            grid-template-columns:340px 1fr;
            gap:18px;
            height:calc(100vh - 170px); min-height:540px;
        }

        /* ---------- Inbox (left rail) ---------- */
        .sup-inbox {
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); box-shadow:0 2px 10px var(--shadow);
            display:flex; flex-direction:column; overflow:hidden;
        }
        .sup-inbox-head {
            padding:14px 16px; background:var(--green-dark); color:var(--white);
            display:flex; align-items:center; gap:8px; flex-shrink:0;
        }
        .sup-inbox-head h3 {
            font-family:var(--font-display); font-size:15px; font-weight:700; flex:1;
        }
        .sup-inbox-count {
            background:rgba(255,255,255,0.18); padding:2px 10px;
            border-radius:20px; font-size:11px; font-weight:600;
        }
        .sup-inbox-search {
            padding:10px 12px; border-bottom:1px solid var(--border);
            background:var(--off-white); flex-shrink:0;
        }
        .sup-inbox-search input {
            width:100%; padding:8px 12px; font-size:12.5px;
            border:1px solid var(--border); border-radius:20px;
            background:var(--white); font-family:var(--font-body);
        }
        .sup-inbox-search input:focus {
            outline:none; border-color:var(--green);
            box-shadow:0 0 0 3px var(--green-light);
        }

        .sup-inbox-list { flex:1; overflow-y:auto; }
        .sup-empty-list {
            padding:36px 20px; text-align:center; color:var(--muted); font-size:13px;
        }
        .sup-empty-list .se-icon { font-size:38px; margin-bottom:10px; }

        .sup-conv {
            display:flex; gap:10px; padding:12px 14px;
            border-bottom:1px solid var(--border);
            cursor:pointer; text-decoration:none; color:inherit;
            transition:background .12s;
        }
        .sup-conv:hover    { background:var(--green-light); }
        .sup-conv.active   { background:var(--green-light); border-left:3px solid var(--green); padding-left:11px; }
        .sup-conv.unread .sup-conv-name { font-weight:700; color:var(--text); }
        .sup-conv.unread .sup-conv-preview { color:var(--text); font-weight:500; }

        .sup-conv-avatar {
            width:38px; height:38px; border-radius:50%;
            background:var(--green); color:var(--white);
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:14px; flex-shrink:0;
        }
        .sup-conv-body { flex:1; min-width:0; }
        .sup-conv-top  { display:flex; align-items:baseline; gap:8px; }
        .sup-conv-name {
            font-size:13px; color:var(--text); flex:1;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .sup-conv-time { font-size:10.5px; color:var(--muted); flex-shrink:0; }
        .sup-conv-meta { font-size:11px; color:var(--muted); margin-top:1px; }
        .sup-conv-preview {
            font-size:12px; color:var(--muted); margin-top:3px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .sup-conv-preview .you-prefix { color:var(--green-dark); font-weight:600; }
        .sup-conv-badge {
            background:var(--green); color:var(--white);
            border-radius:20px; padding:1px 8px;
            font-size:10.5px; font-weight:700; margin-left:6px;
        }

        /* ---------- Chat panel (right) ---------- */
        .sup-wrap {
            display:flex; flex-direction:column;
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); box-shadow:0 2px 10px var(--shadow);
            overflow:hidden;
        }
        .sup-header {
            display:flex; align-items:center; gap:14px;
            padding:14px 20px; background:linear-gradient(135deg, var(--green-dark), var(--green));
            color:var(--white); flex-shrink:0;
        }
        .sup-avatar {
            width:46px; height:46px; border-radius:50%;
            background:rgba(255,255,255,0.22);
            display:flex; align-items:center; justify-content:center;
            font-size:17px; font-weight:700; flex-shrink:0;
            border:2px solid rgba(255,255,255,0.35);
        }
        .sup-header-info  { flex:1; min-width:0; }
        .sup-header-title { font-family:var(--font-display); font-size:16px; font-weight:700; }
        .sup-header-sub   { font-size:12px; opacity:.85; margin-top:2px; }
        .sup-header-link  {
            background:rgba(255,255,255,0.18); color:var(--white);
            font-size:11.5px; padding:5px 12px; border-radius:20px;
            border:1px solid rgba(255,255,255,0.3);
            transition:background .15s;
        }
        .sup-header-link:hover { background:rgba(255,255,255,0.28); }

        /* Message feed */
        .sup-feed {
            flex:1; overflow-y:auto; padding:22px 22px 16px;
            background:linear-gradient(180deg, var(--off-white) 0%, #f1f8f2 100%);
        }
        .sup-empty {
            text-align:center; color:var(--muted); padding:60px 20px;
        }
        .sup-empty .se-icon { font-size:48px; margin-bottom:14px; }
        .sup-empty h3 { font-family:var(--font-display); color:var(--text); margin-bottom:6px; }
        .sup-empty p  { font-size:13px; max-width:380px; margin:0 auto; }

        .sup-day-sep {
            text-align:center; margin:14px 0 18px;
            font-size:11px; color:var(--muted);
            text-transform:uppercase; letter-spacing:1px; font-weight:600;
        }
        .sup-day-sep span {
            background:var(--white); padding:4px 12px; border-radius:20px;
            border:1px solid var(--border);
        }

        .sup-msg-row    { display:flex; margin-bottom:10px; }
        .sup-msg-row.me { justify-content:flex-end; }
        .sup-bubble {
            max-width:70%; padding:9px 14px;
            border-radius:18px; font-size:13.5px; line-height:1.5;
            box-shadow:0 1px 2px rgba(0,0,0,0.05);
            word-wrap:break-word; white-space:pre-wrap; position:relative;
        }
        .sup-bubble.me {
            background:var(--green); color:var(--white);
            border-bottom-right-radius:5px;
        }
        .sup-bubble.them {
            background:var(--white); color:var(--text);
            border:1px solid var(--border); border-bottom-left-radius:5px;
        }
        .sup-bubble .sup-author {
            display:block; font-size:10.5px; font-weight:700;
            text-transform:uppercase; letter-spacing:.5px;
            opacity:.75; margin-bottom:2px;
        }
        .sup-bubble.me   .sup-author { color:rgba(255,255,255,0.85); }
        .sup-bubble.them .sup-author { color:var(--green-dark); }
        .sup-stamp {
            display:block; font-size:10.5px; margin-top:4px; opacity:.7;
        }
        .sup-bubble.me   .sup-stamp { color:rgba(255,255,255,0.9); text-align:right; }
        .sup-bubble.them .sup-stamp { color:var(--muted); }

        /* Composer */
        .sup-composer {
            display:flex; gap:10px; align-items:flex-end;
            padding:14px 18px; background:var(--white);
            border-top:1px solid var(--border); flex-shrink:0;
        }
        .sup-composer textarea {
            flex:1; resize:none; border:1px solid var(--border);
            border-radius:22px; padding:10px 16px; font-family:var(--font-body);
            font-size:13.5px; line-height:1.5; max-height:130px; min-height:42px;
            background:var(--off-white); transition:border-color .15s;
        }
        .sup-composer textarea:focus {
            outline:none; border-color:var(--green); background:var(--white);
            box-shadow:0 0 0 3px var(--green-light);
        }
        .sup-send-btn {
            background:var(--green); color:var(--white);
            border:none; border-radius:50%; width:44px; height:44px;
            font-size:18px; cursor:pointer; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            transition:background .15s, transform .1s;
            box-shadow:0 2px 6px var(--shadow);
        }
        .sup-send-btn:hover    { background:var(--green-dark); }
        .sup-send-btn:disabled { background:#a5d6a7; cursor:not-allowed; }
        .sup-send-btn:active   { transform:scale(0.95); }
        .sup-msg-status {
            padding:0 18px 6px; min-height:18px;
            font-size:11.5px; color:var(--muted); background:var(--white);
        }
        .sup-msg-status.err { color:var(--red); }

        /* Placeholder when no conversation selected */
        .sup-no-thread {
            display:flex; align-items:center; justify-content:center;
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); box-shadow:0 2px 10px var(--shadow);
            text-align:center; padding:40px;
        }
        .sup-no-thread .nt-icon { font-size:64px; margin-bottom:18px; }
        .sup-no-thread h3 { font-family:var(--font-display); font-size:18px; color:var(--text); margin-bottom:8px; }
        .sup-no-thread p  { font-size:13px; color:var(--muted); max-width:340px; margin:0 auto; }

        @media(max-width:900px) {
            .sup-app { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="layout">

    <?php include "sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Support Inbox</h1>
                <p class="page-sub">Direct messages from alumni — info-update requests and questions.</p>
            </div>
        </div>

        <?php if (!$pdo): ?>
            <div class="alert alert-error">
                Database could not be initialized. Check MySQL credentials in <code>config.php</code>.
            </div>
        <?php elseif (!$ready): ?>
            <div class="alert alert-warning">
                The Support messaging table is not installed yet.
                Please import <strong>support_messages_schema.sql</strong> into the <code>adms</code> database, then refresh this page.
            </div>
        <?php else: ?>

        <div class="sup-app">

            <!-- INBOX -->
            <aside class="sup-inbox">
                <div class="sup-inbox-head">
                    <span style="font-size:18px;">📨</span>
                    <h3>Inbox</h3>
                    <span class="sup-inbox-count"><?php echo count($inbox); ?></span>
                </div>

                <form class="sup-inbox-search" method="GET" action="admin_support.php">
                    <?php if ($openAlumniId): ?>
                        <input type="hidden" name="alumni_id" value="<?php echo (int) $openAlumniId; ?>">
                    <?php endif; ?>
                    <input type="text" name="q"
                           placeholder="🔍 Search by name or student ID…"
                           value="<?php echo support_h($search); ?>">
                </form>

                <div class="sup-inbox-list">
                    <?php if (empty($inbox)): ?>
                        <div class="sup-empty-list">
                            <div class="se-icon">📭</div>
                            <p>No conversations yet.<br>Alumni messages will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inbox as $c):
                            $isActive   = ((int) $c['alumni_id'] === $openAlumniId);
                            $unread     = (int) $c['unread_count'];
                            $isUnread   = ($unread > 0);
                            $initial    = mb_strtoupper(mb_substr($c['alumni_name'] ?: '?', 0, 1));
                            $preview    = trim((string) $c['last_body']);
                            $youPrefix  = ($c['last_sender'] === 'admin');
                            // shorten preview
                            if (mb_strlen($preview) > 60) {
                                $preview = mb_substr($preview, 0, 60) . '…';
                            }
                            $linkQuery  = ['alumni_id' => (int) $c['alumni_id']];
                            if ($search !== '') $linkQuery['q'] = $search;
                            $href = 'admin_support.php?' . http_build_query($linkQuery);
                        ?>
                            <a href="<?php echo support_h($href); ?>"
                               class="sup-conv<?php echo $isActive ? ' active' : ''; ?><?php echo $isUnread ? ' unread' : ''; ?>">
                                <div class="sup-conv-avatar"><?php echo support_h($initial); ?></div>
                                <div class="sup-conv-body">
                                    <div class="sup-conv-top">
                                        <span class="sup-conv-name">
                                            <?php echo support_h($c['alumni_name']); ?>
                                        </span>
                                        <span class="sup-conv-time">
                                            <?php echo support_h(support_time_ago($c['last_time'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($c['alumni_sid'])): ?>
                                        <div class="sup-conv-meta">
                                            🎓 <?php echo support_h($c['alumni_sid']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="sup-conv-preview">
                                        <?php if ($youPrefix): ?>
                                            <span class="you-prefix">You:</span>
                                        <?php endif; ?>
                                        <?php echo support_h($preview); ?>
                                        <?php if ($isUnread): ?>
                                            <span class="sup-conv-badge"><?php echo (int) $unread; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- CHAT THREAD -->
            <?php if (!$openAlumniId || !$alumniRow): ?>

                <section class="sup-no-thread">
                    <div>
                        <div class="nt-icon">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Pick an alumni from the inbox on the left to read their message and reply. Threads with new messages appear at the top with a green badge.</p>
                    </div>
                </section>

            <?php else: ?>

                <section class="sup-wrap" id="supWrap"
                         data-alumni-id="<?php echo (int) $openAlumniId; ?>">

                    <div class="sup-header">
                        <div class="sup-avatar">
                            <?php echo support_h(mb_strtoupper(mb_substr($alumniRow['name'] ?: 'A', 0, 1))); ?>
                        </div>
                        <div class="sup-header-info">
                            <div class="sup-header-title"><?php echo support_h($alumniRow['name']); ?></div>
                            <div class="sup-header-sub">
                                <?php if (!empty($alumniRow['student_id'])): ?>
                                    🎓 <?php echo support_h($alumniRow['student_id']); ?>
                                <?php endif; ?>
                                <?php if (!empty($alumniRow['major'])): ?>
                                    &nbsp;·&nbsp; <?php echo support_h($alumniRow['major']); ?>
                                <?php endif; ?>
                                <?php if (!empty($alumniRow['graduation_term'])): ?>
                                    &nbsp;·&nbsp; Term <?php echo support_h($alumniRow['graduation_term']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a class="sup-header-link"
                           href="edit_alumni.php?id=<?php echo (int) $alumniRow['id']; ?>"
                           title="Open this alumni's record to make edits">
                            ✏️ Edit Record
                        </a>
                    </div>

                    <div class="sup-feed" id="supFeed">
                        <?php if (empty($thread)): ?>
                            <div class="sup-empty">
                                <div class="se-icon">💬</div>
                                <h3>No messages yet</h3>
                                <p>This thread is open — send the first message below.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            $lastDate = '';
                            foreach ($thread as $m):
                                $isMe = ($m['sender_type'] === 'admin');
                                $stamp = support_msg_stamp($m['created_at']);
                                $d = date('Y-m-d', strtotime($m['created_at']));
                                if ($d !== $lastDate):
                                    $lastDate = $d;
                                    $today = date('Y-m-d');
                                    $label = ($d === $today) ? 'Today' : date('l, M j, Y', strtotime($d));
                            ?>
                                <div class="sup-day-sep"><span><?php echo support_h($label); ?></span></div>
                            <?php endif; ?>

                            <div class="sup-msg-row <?php echo $isMe ? 'me' : ''; ?>" data-id="<?php echo (int) $m['id']; ?>">
                                <div class="sup-bubble <?php echo $isMe ? 'me' : 'them'; ?>">
                                    <?php if (!$isMe): ?>
                                        <span class="sup-author">🎓 <?php echo support_h($alumniRow['name']); ?></span>
                                    <?php endif; ?>
                                    <?php echo nl2br(support_h($m['body'])); ?>
                                    <span class="sup-stamp"><?php echo support_h($stamp); ?></span>
                                </div>
                            </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="sup-msg-status" id="supStatus"></div>

                    <form class="sup-composer" id="supForm" autocomplete="off">
                        <textarea
                            id="supInput"
                            placeholder="Reply to <?php echo support_h($alumniRow['name']); ?>…  (Enter to send, Shift+Enter for new line)"
                            rows="1" maxlength="4000" required></textarea>
                        <button type="submit" class="sup-send-btn" id="supSendBtn" title="Send">➤</button>
                    </form>
                </section>

                <script>
                (function () {
                    const CSRF      = <?php echo json_encode($csrf); ?>;
                    const PORTAL    = 'admin';
                    const ALUMNI_ID = <?php echo (int) $openAlumniId; ?>;
                    const ALUMNI_NAME = <?php echo json_encode($alumniRow['name']); ?>;

                    const feed   = document.getElementById('supFeed');
                    const form   = document.getElementById('supForm');
                    const input  = document.getElementById('supInput');
                    const sendBt = document.getElementById('supSendBtn');
                    const status = document.getElementById('supStatus');

                    let lastSeenId = 0;
                    document.querySelectorAll('.sup-msg-row').forEach(el => {
                        const id = parseInt(el.dataset.id, 10) || 0;
                        if (id > lastSeenId) lastSeenId = id;
                    });

                    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, m => ({
                        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;',
                    }[m]));
                    const nl2br = (s) => escapeHtml(s).replace(/\r?\n/g, '<br>');

                    function scrollToBottom() { feed.scrollTop = feed.scrollHeight; }

                    function todayLabel(dateStr) {
                        const d   = new Date(dateStr);
                        const now = new Date();
                        const sameDay = d.toDateString() === now.toDateString();
                        if (sameDay) return 'Today';
                        return d.toLocaleDateString(undefined, { weekday:'long', month:'short', day:'numeric', year:'numeric' });
                    }

                    function lastDaySepInFeed() {
                        const seps = feed.querySelectorAll('.sup-day-sep');
                        return seps.length ? seps[seps.length - 1].textContent.trim() : '';
                    }

                    function removeEmptyState() {
                        document.querySelectorAll('.sup-empty').forEach(el => el.remove());
                    }

                    function renderMessage(msg) {
                        const label = todayLabel(msg.created_at);
                        if (lastDaySepInFeed() !== label) {
                            const sep = document.createElement('div');
                            sep.className = 'sup-day-sep';
                            sep.innerHTML = '<span>' + escapeHtml(label) + '</span>';
                            feed.appendChild(sep);
                        }
                        const isMe = (msg.sender_type === 'admin');
                        const row = document.createElement('div');
                        row.className = 'sup-msg-row' + (isMe ? ' me' : '');
                        row.dataset.id = msg.id;

                        const authorHtml = isMe ? '' :
                            '<span class="sup-author">🎓 ' + escapeHtml(ALUMNI_NAME) + '</span>';
                        row.innerHTML =
                            '<div class="sup-bubble ' + (isMe ? 'me' : 'them') + '">' +
                                authorHtml +
                                nl2br(msg.body) +
                                '<span class="sup-stamp">' + escapeHtml(msg.stamp) + '</span>' +
                            '</div>';
                        feed.appendChild(row);

                        if (msg.id > lastSeenId) lastSeenId = msg.id;
                    }

                    async function api(action, payload) {
                        const body = Object.assign({ action, csrf: CSRF, portal: PORTAL, alumni_id: ALUMNI_ID }, payload || {});
                        const r = await fetch('support_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(body),
                        });
                        let data = null;
                        try { data = await r.json(); } catch (e) {}
                        if (!r.ok || !data || data.ok === false) {
                            throw new Error((data && data.error) || ('HTTP ' + r.status));
                        }
                        return data;
                    }

                    // Auto-grow textarea
                    input.addEventListener('input', () => {
                        input.style.height = 'auto';
                        input.style.height = Math.min(input.scrollHeight, 130) + 'px';
                    });

                    // Enter to send, Shift+Enter for newline
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            form.requestSubmit();
                        }
                    });

                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const body = input.value.trim();
                        if (!body) return;

                        sendBt.disabled = true;
                        status.classList.remove('err');
                        status.textContent = 'Sending…';

                        try {
                            const data = await api('send', { body });
                            removeEmptyState();
                            renderMessage(data.message);
                            input.value = '';
                            input.style.height = 'auto';
                            status.textContent = '';
                            scrollToBottom();
                        } catch (err) {
                            status.textContent = err.message || 'Could not send message.';
                            status.classList.add('err');
                        } finally {
                            sendBt.disabled = false;
                            input.focus();
                        }
                    });

                    // Poll every 6 seconds for new messages from the alumni
                    async function poll() {
                        try {
                            const data = await api('fetch_thread', { after_id: lastSeenId });
                            if (data.messages && data.messages.length) {
                                removeEmptyState();
                                data.messages.forEach(renderMessage);
                                scrollToBottom();
                            }
                        } catch (e) { /* swallow polling errors */ }
                    }
                    setInterval(poll, 6000);

                    scrollToBottom();
                    input.focus();
                })();
                </script>

            <?php endif; ?>

        </div>

        <?php endif; ?>

    </main>
</div>
</body>
</html>
