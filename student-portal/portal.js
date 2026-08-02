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
    document.getElementById('student-results').innerHTML = `<div class="glass-card"><div class="score-hero"><div class="score-number"><span>${data.percentage}%</span><small>${data.total_score} / ${data.max_score}</small></div><div style="padding-left:1.5rem;"><h2>${escapeHtml(data.db_name)}</h2><p style="color:var(--accent-primary);">${escapeHtml(data.activity_name)}</p><p style="color:var(--text-muted);font-size:.85rem;">Checked on ${escapeHtml(data.checked_at)}</p></div></div><h3 style="margin:1rem 0;">Task Breakdown &amp; SQL Evidence</h3>${tasks}</div>`;
}

function logout() {
    fetch(`${api}?action=student_logout`, {method:'POST'}).finally(() => {
        document.getElementById('student-controls').style.display = 'none'; document.getElementById('student-login-form').reset(); document.getElementById('student-session').innerHTML = '';
        document.getElementById('student-results').innerHTML = '<div class="glass-card" style="text-align:center;padding:4rem 1.5rem;"><i class="fas fa-lock fa-3x" style="color:var(--border-color-glow);margin-bottom:1rem;"></i><h3>Your Activity Record</h3><p style="color:var(--text-muted);">Log in to see only your own evaluation results.</p></div>';
    });
}
