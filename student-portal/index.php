<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DBMS Student Activity Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .portal-score-header { display: flex; align-items: center; gap: 1.25rem; padding: 1.5rem; background: rgba(15, 23, 42, .5); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .portal-score-value { flex: 0 0 118px; text-align: center; padding: 1rem .5rem; border-radius: 12px; background: rgba(99, 102, 241, .12); border: 1px solid var(--border-color-glow); }
        .portal-score-value strong { display: block; color: var(--text-highlight); font-size: 2rem; line-height: 1.1; }
        .portal-score-value small { color: var(--text-muted); font-size: .8rem; }
        .portal-score-details h2 { margin: 0 0 .3rem; font-size: 1.35rem; word-break: break-word; }
        .portal-score-details p { margin: .2rem 0; }
        @media (max-width: 560px) { .portal-score-header { align-items: flex-start; flex-direction: column; } .portal-score-value { width: 100%; box-sizing: border-box; } }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="brand"><div class="brand-icon"><i class="fas fa-user-graduate"></i></div><div class="brand-title"><h1>DBMS Student Portal</h1><p>View your own activity progress and SQL evidence</p></div></div>
    </header>
    <main class="app-container">
        <div class="checker-grid">
            <section class="glass-card">
                <div class="glass-card-header"><span class="glass-card-title"><i class="fas fa-right-to-bracket"></i> Student Login</span></div>
                <p style="color:var(--text-muted); margin-bottom:1rem;">Sign in with the MySQL account assigned to your activity database.</p>
                <form id="student-login-form">
                    <div class="form-group"><label for="student-username">MySQL Username</label><input id="student-username" class="form-control" required autocomplete="username" placeholder="e.g. 2_cs4_delacruz"></div>
                    <div class="form-group"><label for="student-password">MySQL Password</label><input id="student-password" type="password" class="form-control" required autocomplete="current-password"></div>
                    <button class="btn btn-primary" style="width:100%;justify-content:center;"><i class="fas fa-right-to-bracket"></i> Log In</button>
                </form>
                <div id="student-session" style="margin-top:1rem;"></div>
                <div id="student-controls" style="display:none; margin-top:1rem;">
                    <div class="form-group"><label for="student-activity-id">Activity</label><select id="student-activity-id" class="form-control"><option value="activity1">Activity 1 - Library Database</option><option value="activity2">Activity 2 - Suppliers & Items CRUD</option></select></div>
                    <div style="display:flex; gap:.5rem;"><button class="btn btn-success" type="button" onclick="loadStatus()"><i class="fas fa-clipboard-check"></i> View My Status</button><button class="btn" type="button" onclick="logout()">Log Out</button></div>
                </div>
            </section>
            <section id="student-results"><div class="glass-card" style="text-align:center;padding:4rem 1.5rem;"><i class="fas fa-lock fa-3x" style="color:var(--border-color-glow);margin-bottom:1rem;"></i><h3>Your Activity Record</h3><p style="color:var(--text-muted);">Log in to see only your own evaluation results.</p></div></section>
        </div>
    </main>
    <script>
    const api = '../api.php';
    const escapeHtml = value => String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    document.getElementById('student-login-form').addEventListener('submit', event => {
        event.preventDefault();
        const session = document.getElementById('student-session');
        session.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying MySQL account...';
        fetch(`${api}?action=student_login`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ username: document.getElementById('student-username').value.trim(), password: document.getElementById('student-password').value }) })
            .then(response => response.json()).then(result => {
                if (!result.success) throw new Error(result.error || 'Login failed.');
                document.getElementById('student-password').value = '';
                document.getElementById('student-controls').style.display = 'block';
                session.innerHTML = `<span class="badge-pass"><i class="fas fa-user-check"></i> Logged in as ${escapeHtml(result.username)}</span><small style="display:block;color:var(--text-muted);margin-top:.5rem;">Database: ${escapeHtml(result.database)}</small>`;
                loadStatus();
            }).catch(error => session.innerHTML = `<span class="badge-fail">${escapeHtml(error.message)}</span>`);
    });

    function loadStatus() {
        const container = document.getElementById('student-results');
        container.innerHTML = '<div class="glass-card" style="text-align:center;padding:3rem;"><i class="fas fa-cog fa-spin fa-2x"></i><p style="margin-top:1rem;">Loading your activity record...</p></div>';
        fetch(`${api}?action=student_status&activity_id=${encodeURIComponent(document.getElementById('student-activity-id').value)}`)
            .then(response => response.json()).then(result => { if (!result.success) throw new Error(result.error || 'Unable to load your record.'); renderResult(result.data); })
            .catch(error => container.innerHTML = `<div class="glass-card" style="color:var(--status-fail);">${escapeHtml(error.message)}</div>`);
    }

    function renderResult(data) {
        const tasks = Object.values(data.tasks || {}).map(task => `<div class="task-card"><div class="task-header"><span class="task-title">${escapeHtml(task.title)}</span><span class="task-score-badge ${task.score_percent === 100 ? 'badge-pass' : task.score_percent ? 'badge-warn' : 'badge-fail'}">${task.earned_score} / ${task.weight} pts (${task.score_percent}%)</span></div><div class="check-list">${task.checks.map(check => `<div class="check-item"><div class="check-icon ${check.passed ? 'icon-pass' : 'icon-fail'}"><i class="fas fa-${check.passed ? 'check' : 'times'}"></i></div><div class="check-content"><strong>${escapeHtml(check.name)}</strong><small>${escapeHtml(check.detail)}</small></div></div>`).join('')}${task.log_verified ? `<div class="log-banner"><i class="fas fa-terminal"></i> <strong>Recorded SQL:</strong><br><code>${escapeHtml(task.log_entry)}</code></div>` : ''}</div></div>`).join('');
        document.getElementById('student-results').innerHTML = `<div class="glass-card"><div class="portal-score-header"><div class="portal-score-value"><strong>${data.percentage}%</strong><small>${data.total_score} / ${data.max_score} points</small></div><div class="portal-score-details"><h2>${escapeHtml(data.db_name)}</h2><p style="color:var(--accent-primary);">${escapeHtml(data.activity_name)}</p><p style="color:var(--text-muted);font-size:.85rem;">Checked on ${escapeHtml(data.checked_at)}</p></div></div><h3 style="margin:1rem 0;">Task Breakdown &amp; SQL Evidence</h3>${tasks}</div>`;
    }

    function logout() {
        fetch(`${api}?action=student_logout`, {method:'POST'}).finally(() => {
            document.getElementById('student-controls').style.display = 'none'; document.getElementById('student-login-form').reset(); document.getElementById('student-session').innerHTML = '';
            document.getElementById('student-results').innerHTML = '<div class="glass-card" style="text-align:center;padding:4rem 1.5rem;"><i class="fas fa-lock fa-3x" style="color:var(--border-color-glow);margin-bottom:1rem;"></i><h3>Your Activity Record</h3><p style="color:var(--text-muted);">Log in to see only your own evaluation results.</p></div>';
        });
    }
    </script>
</body>
</html>
