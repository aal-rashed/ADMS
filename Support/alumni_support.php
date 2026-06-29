<?php
/**
 * Alumni Support — direct message channel with the admin team.
 * One thread per alumni. Looks like a typical messenger chat.
 */
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
include __DIR__ . '/config.php';
require __DIR__ . '/support_lib.php';

if (!isset($_SESSION['alumni_id'])) {
    header("Location: alumni_login.php");
    exit();
}

$alumniId   = (int) $_SESSION['alumni_id'];
$alumniName = $_SESSION['alumni_name'] ?? 'Alumni';
$csrf       = support_csrf_token();

$ready = $pdo && support_table_ready($pdo);

// Pre-load thread so the page renders with messages even before JS runs
$thread = [];
if ($ready) {
    $thread = support_fetch_thread($pdo, $alumniId);
    // Mark inbound (admin → alumni) messages as read on page load
    support_mark_read($pdo, $alumniId, 'alumni');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support – Alumni Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ============== Support chat (alumni & admin share these) ============== */
        .sup-wrap {
            display:flex; flex-direction:column;
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-lg); box-shadow:0 2px 10px var(--shadow);
            height:calc(100vh - 170px); min-height:520px; max-width:920px;
            overflow:hidden;
        }
        .sup-header {
            display:flex; align-items:center; gap:14px;
            padding:14px 20px; background:linear-gradient(135deg, var(--green-dark), var(--green));
            color:var(--white); flex-shrink:0;
        }
        .sup-avatar {
            width:44px; height:44px; border-radius:50%;
            background:rgba(255,255,255,0.22);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
            border:2px solid rgba(255,255,255,0.35);
        }
        .sup-header-info  { flex:1; min-width:0; }
        .sup-header-title { font-family:var(--font-display); font-size:16px; font-weight:700; }
        .sup-header-sub   { font-size:12px; opacity:.85; margin-top:2px; }
        .sup-presence-dot {
            display:inline-block; width:8px; height:8px; border-radius:50%;
            background:#4caf50; margin-right:6px; vertical-align:middle;
            box-shadow:0 0 0 2px rgba(255,255,255,0.35);
        }

        /* Message feed */
        .sup-feed {
            flex:1; overflow-y:auto; padding:22px 22px 16px;
            background:
                linear-gradient(180deg, var(--off-white) 0%, #f1f8f2 100%);
        }
        .sup-empty {
            text-align:center; color:var(--muted); padding:50px 20px;
        }
        .sup-empty .se-icon { font-size:42px; margin-bottom:12px; }
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

        /* Intro pinned card (only when no messages yet) */
        .sup-intro {
            background:var(--white); border:1px solid var(--border);
            border-radius:var(--radius-md); padding:16px 18px;
            display:flex; gap:14px; align-items:flex-start;
            box-shadow:0 1px 4px var(--shadow); margin-bottom:18px;
        }
        .sup-intro .si-icon {
            width:42px; height:42px; border-radius:10px; background:var(--green-light);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
        }
        .sup-intro h4 { font-size:13.5px; color:var(--text); margin-bottom:4px; }
        .sup-intro p  { font-size:12.5px; color:var(--muted); line-height:1.55; }
    </style>
</head>
<body>
<div class="layout">

    <?php include "alumni_sidebar.php"; ?>

    <main class="main-content animate-fadeIn">

        <div class="page-header">
            <div>
                <h1>Support</h1>
                <p class="page-sub">Need to update your info or have a question? Message the admin team directly.</p>
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

        <div class="sup-wrap" id="supWrap">
            <div class="sup-header">
                <div class="sup-avatar">🛟</div>
                <div class="sup-header-info">
                    <div class="sup-header-title">Admin Support</div>
                    <div class="sup-header-sub">
                        <span class="sup-presence-dot"></span>
                        College of Computer — Alumni Affairs
                    </div>
                </div>
            </div>

            <div class="sup-feed" id="supFeed">

                <?php if (empty($thread)): ?>
                    <div class="sup-intro">
                        <div class="si-icon">✉️</div>
                        <div>
                            <h4>Welcome, <?php echo support_h($alumniName); ?> 👋</h4>
                            <p>Use this channel to request profile updates (name spelling, major, contact info, graduation term, etc.) or to ask the admin team anything about your alumni record. A response is usually within one business day.</p>
                        </div>
                    </div>
                    <div class="sup-empty">
                        <div class="se-icon">💬</div>
                        <h3>No messages yet</h3>
                        <p>Send your first message below — share what you'd like updated or ask any question and the admin team will get back to you here.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $lastDate = '';
                    foreach ($thread as $m):
                        $isMe   = ($m['sender_type'] === 'alumni');
                        $stamp  = support_msg_stamp($m['created_at']);
                        $d      = date('Y-m-d', strtotime($m['created_at']));
                        if ($d !== $lastDate):
                            $lastDate = $d;
                            $today    = date('Y-m-d');
                            $label    = ($d === $today) ? 'Today' : date('l, M j, Y', strtotime($d));
                    ?>
                        <div class="sup-day-sep"><span><?php echo support_h($label); ?></span></div>
                    <?php endif; ?>

                    <div class="sup-msg-row <?php echo $isMe ? 'me' : ''; ?>" data-id="<?php echo (int) $m['id']; ?>">
                        <div class="sup-bubble <?php echo $isMe ? 'me' : 'them'; ?>">
                            <?php if (!$isMe): ?>
                                <span class="sup-author">🛟 Admin Support</span>
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
                    placeholder="Type your message…  (Enter to send, Shift+Enter for new line)"
                    rows="1" maxlength="4000" required></textarea>
                <button type="submit" class="sup-send-btn" id="supSendBtn" title="Send">➤</button>
            </form>
        </div>

        <script>
        (function () {
            const CSRF   = <?php echo json_encode($csrf); ?>;
            const PORTAL = 'alumni';
            const ALUMNI_ID = <?php echo (int) $alumniId; ?>;

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
                document.querySelectorAll('.sup-intro, .sup-empty').forEach(el => el.remove());
            }

            function renderMessage(msg) {
                const label = todayLabel(msg.created_at);
                if (lastDaySepInFeed() !== label) {
                    const sep = document.createElement('div');
                    sep.className = 'sup-day-sep';
                    sep.innerHTML = '<span>' + escapeHtml(label) + '</span>';
                    feed.appendChild(sep);
                }

                const isMe = (msg.sender_type === 'alumni');
                const row  = document.createElement('div');
                row.className = 'sup-msg-row' + (isMe ? ' me' : '');
                row.dataset.id = msg.id;

                const authorHtml = isMe ? '' : '<span class="sup-author">🛟 Admin Support</span>';
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
                const body = Object.assign({ action, csrf: CSRF, portal: PORTAL }, payload || {});
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

            // Poll every 6 seconds for new admin replies
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

            // Scroll to bottom on first paint
            scrollToBottom();
            input.focus();
        })();
        </script>

        <?php endif; ?>

    </main>
</div>
</body>
</html>
