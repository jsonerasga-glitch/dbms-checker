document.addEventListener('DOMContentLoaded', () => {
    // Navigation Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(target).classList.add('active');

            if (target === 'batch-tab') {
                loadBatchDatabases();
            } else if (target === 'logs-tab') {
                loadGeneralLogs();
            }
        });
    });

    // Check single student form submission
    const singleCheckForm = document.getElementById('single-check-form');
    if (singleCheckForm) {
        singleCheckForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const dbName = document.getElementById('single-db-name').value.trim();
            if (dbName) {
                runSingleCheck(dbName);
            }
        });
    }


    // Config form submission
    const configForm = document.getElementById('config-form');
    if (configForm) {
        configForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const data = {
                db_host: document.getElementById('cfg-db-host').value,
                db_port: document.getElementById('cfg-db-port').value,
                db_user: document.getElementById('cfg-db-user').value,
                db_pass: document.getElementById('cfg-db-pass').value,
                section_filter: document.getElementById('cfg-section-filter').value,
                log_date_enabled: document.getElementById('cfg-log-date-enabled')?.checked || false,
                log_date_start: document.getElementById('cfg-log-date-start')?.value || '',
                log_date_end: document.getElementById('cfg-log-date-end')?.value || '',
            };

            fetch('api.php?action=save_config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Configuration saved successfully!');
                    closeModal('config-modal');
                    testConnection();
                } else {
                    alert('Error saving config: ' + res.error);
                }
            })
            .catch(err => alert('Failed to save config: ' + err));
        });
    }

    // Auto test connection on launch
    testConnection();
});

// Cache variables for export
let cachedBatchResults = [];
let cachedGeneralLogs = [];
let currentSingleResult = null;

// Test Connection
function testConnection() {
    const statusBadge = document.getElementById('connection-status');
    if (!statusBadge) return;

    statusBadge.className = 'badge-warn';
    statusBadge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';

    fetch('api.php?action=test_connection')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let statusText = `<i class="fas fa-check-circle"></i> Connected (MySQL ${data.mysql_version})`;
                if (data.general_log === 'ON') {
                    statusText += ` | General Log: ACTIVE`;
                } else {
                    statusText += ` | General Log: DISABLED`;
                }
                statusBadge.className = 'badge-pass';
                statusBadge.innerHTML = statusText;
            } else {
                statusBadge.className = 'badge-fail';
                statusBadge.innerHTML = `<i class="fas fa-exclamation-triangle"></i> DB Error: ${data.error}`;
            }
        })
        .catch(err => {
            statusBadge.className = 'badge-fail';
            statusBadge.innerHTML = `<i class="fas fa-times-circle"></i> Connection Failed`;
        });
}

// Enable General Query Log
function enableGeneralLog() {
    if (!confirm("This will execute SET GLOBAL general_log = 'ON' and SET GLOBAL log_output = 'TABLE' on your MySQL server. Proceed?")) return;

    fetch('api.php?action=enable_general_log')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                testConnection();
                loadGeneralLogs();
            } else {
                alert('Error: ' + data.error);
            }
        });
}

// Create Demo Student DB
function createDemoStudent(mode, dbName) {
    dbName = dbName || '2_cs4_delacruz';
    const statusEl = document.getElementById('demo-status');
    if (statusEl) statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating demo database...';

    fetch(`api.php?action=create_demo_student&mode=${mode}&db_name=${encodeURIComponent(dbName)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (statusEl) statusEl.innerHTML = `<span class="badge-pass"><i class="fas fa-check"></i> Demo DB '${dbName}' created!</span>`;
                // Automatically set input and run check
                document.getElementById('single-db-name').value = dbName;
                runSingleCheck(dbName);
            } else {
                if (statusEl) statusEl.innerHTML = `<span class="badge-fail">Error: ${data.error}</span>`;
            }
        });
}

// Run Single Student Check
function runSingleCheck(dbName) {
    const activityId = document.getElementById('activity-id')?.value || 'activity1';
    const resultsContainer = document.getElementById('single-check-results');
    resultsContainer.innerHTML = `
        <div class="glass-card" style="text-align: center; padding: 3rem;">
            <i class="fas fa-cog fa-spin fa-3x" style="color: var(--accent-primary); margin-bottom: 1rem;"></i>
            <h3>Analyzing Database & General Logs for '${escapeHtml(dbName)}'...</h3>
            <p style="color: var(--text-muted);">Inspecting information_schema and verifying executed SQL statements...</p>
        </div>
    `;

    fetch(`api.php?action=check_student&activity_id=${encodeURIComponent(activityId)}&db_name=${encodeURIComponent(dbName)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                currentSingleResult = res.data;
                renderSingleResult(res.data);
            } else {
                resultsContainer.innerHTML = `
                    <div class="glass-card">
                        <div class="glass-card-header">
                            <span class="glass-card-title" style="color: var(--status-fail);">
                                <i class="fas fa-exclamation-circle"></i> Verification Failed
                            </span>
                        </div>
                        <p style="color: var(--text-muted);">${escapeHtml(res.error)}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            resultsContainer.innerHTML = `<div class="glass-card" style="color: var(--status-fail);">Request error: ${err}</div>`;
        });
}

// Render Single Student Result UI
function renderSingleResult(data, containerId = 'single-check-results') {
    const container = document.getElementById(containerId);
    
    if (!data.db_exists) {
        container.innerHTML = `
            <div class="glass-card" style="border-color: var(--status-fail);">
                <h3 style="color: var(--status-fail); margin-bottom: 0.5rem;"><i class="fas fa-database"></i> Database Not Found</h3>
                <p style="color: var(--text-muted);">${escapeHtml(data.error)}</p>
            </div>
        `;
        return;
    }

    const strokeDash = (data.percentage / 100) * 339.29;
    const remainingDash = 339.29 - strokeDash;

    let gradeClass = 'badge-pass';
    let gradeLabel = 'A+ (Excellent)';
    if (data.percentage < 60) { gradeClass = 'badge-fail'; gradeLabel = 'F (Needs Revision)'; }
    else if (data.percentage < 80) { gradeClass = 'badge-warn'; gradeLabel = 'C (Satisfactory)'; }
    else if (data.percentage < 90) { gradeClass = 'badge-pass'; gradeLabel = 'B (Good)'; }

    let html = `
        <div class="glass-card">
            <div class="score-hero">
                <div class="score-circle">
                    <svg>
                        <circle class="bg-ring" cx="60" cy="60" r="54"></circle>
                        <circle class="progress-ring" cx="60" cy="60" r="54" style="stroke-dashoffset: ${remainingDash};"></circle>
                    </svg>
                    <div class="score-number">
                        <span>${data.percentage}%</span>
                        <small>${data.total_score} / ${data.max_score}</small>
                    </div>
                </div>
                <div style="flex: 1; padding-left: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; margin-bottom: 0.25rem;">${escapeHtml(data.db_name)}</h2>
                            <p style="color: var(--accent-primary); font-size: 0.85rem; margin-bottom: 0.25rem;">${escapeHtml(data.activity_name || 'Activity Evaluation')}</p>
                            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.75rem;">Checked on ${data.checked_at}</p>
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn btn-success" style="padding: 6px 12px; font-size: 0.82rem;" onclick="exportSingleStudentExcel()" title="Export multi-sheet Excel file with Sheet 1: Evaluation Report and Sheet 2: Executed Query Logs">
                                <i class="fas fa-file-excel"></i> Export Excel (2 Sheets)
                            </button>
                            <button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.82rem;" onclick="exportSingleStudentReportCSV()" title="Export single CSV summary report">
                                <i class="fas fa-file-csv"></i> Export Summary CSV
                            </button>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <span class="task-score-badge ${gradeClass}">${gradeLabel}</span>
                        ${data.user_exists ? '<span class="task-score-badge badge-pass"><i class="fas fa-user-check"></i> DB User Present</span>' : '<span class="task-score-badge badge-warn"><i class="fas fa-user-slash"></i> User Not Found</span>'}
                        ${data.general_log_enabled ? '<span class="task-score-badge badge-pass"><i class="fas fa-terminal"></i> Log Tracking Active</span>' : '<span class="task-score-badge badge-warn"><i class="fas fa-exclamation-triangle"></i> Logs Disabled</span>'}
                        ${data.logs_found && data.logs_found.date_filter_active ? `<span class="task-score-badge badge-warn" title="Filtering logs between ${data.logs_found.date_start} and ${data.logs_found.date_end}"><i class="fas fa-calendar-day"></i> Date Filter (${data.logs_found.date_start} to ${data.logs_found.date_end})</span>` : ''}
                    </div>
                </div>
            </div>

            <h3 style="margin-bottom: 1rem; font-size: 1.1rem; display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fas fa-tasks"></i> Task Breakdown & Log Evidence</span>
            </h3>
    `;

    // Iterate through tasks
    for (const key in data.tasks) {
        const task = data.tasks[key];
        const isPass = task.score_percent >= 100;
        const badgeClass = isPass ? 'badge-pass' : (task.score_percent > 0 ? 'badge-warn' : 'badge-fail');

        html += `
            <div class="task-card">
                <div class="task-header">
                    <span class="task-title">${escapeHtml(task.title)}</span>
                    <span class="task-score-badge ${badgeClass}">${task.earned_score} / ${task.weight} pts (${task.score_percent}%)</span>
                </div>
                <div class="check-list">
        `;

        task.checks.forEach(chk => {
            const iconClass = chk.passed ? 'icon-pass' : 'icon-fail';
            const iconSymbol = chk.passed ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
            html += `
                <div class="check-item">
                    <div class="check-icon ${iconClass}">${iconSymbol}</div>
                    <div class="check-content">
                        <strong>${escapeHtml(chk.name)}</strong>
                        <small>${escapeHtml(chk.detail)}</small>
                    </div>
                </div>
            `;
        });

        if (task.log_verified) {
            html += `
                <div class="log-banner">
                    <i class="fas fa-terminal"></i> <strong>MySQL Query Log Proof:</strong><br>
                    <code>${escapeHtml(task.log_entry)}</code>
                </div>
            `;
        } else if (data.general_log_enabled) {
            html += `
                <div class="log-banner" style="background: rgba(245,158,11,0.1); border-color: var(--status-warn); color: var(--status-warn);">
                    <i class="fas fa-info-circle"></i> No corresponding DDL execution entry found in general_log.
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;
    }

    html += `</div>`;
    container.innerHTML = html;
}

// Batch Database Discovery & Auto Check
function loadBatchDatabases() {
    const tableBody = document.getElementById('batch-table-body');
    const summaryStat = document.getElementById('batch-stat-summary');
    
    tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Discovering student databases...</td></tr>`;

    fetch('api.php?action=discover_databases')
        .then(res => res.json())
        .then(res => {
            if (res.success && res.databases.length > 0) {
                runBatchCheck(res.databases.map(d => d.db_name));
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 2rem;">No student databases found matching filter. Use Demo Generator or enter databases manually.</td></tr>`;
                summaryStat.innerHTML = '0 Student Databases Discovered';
            }
        });
}

function runBatchCheck(dbList) {
    const tableBody = document.getElementById('batch-table-body');
    const summaryStat = document.getElementById('batch-stat-summary');

    tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Running batch check for ${dbList.length} student databases...</td></tr>`;

    fetch('api.php?action=batch_check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'activity_id=' + encodeURIComponent(document.getElementById('batch-activity-id')?.value || 'activity1') + '&databases=' + encodeURIComponent(JSON.stringify(dbList))
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            cachedBatchResults = res.results;
            renderBatchTable(cachedBatchResults);
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--status-fail);">Batch check failed: ${res.error}</td></tr>`;
        }
    });
}

function renderBatchTable(results) {
    const tableBody = document.getElementById('batch-table-body');
    const summaryStat = document.getElementById('batch-stat-summary');

    if (results.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 2rem;">No results found.</td></tr>`;
        return;
    }

    let totalScoreSum = 0;
    let passCount = 0;
    let html = '';

    results.forEach((item, index) => {
        totalScoreSum += item.percentage || 0;
        if (item.percentage >= 75) passCount++;

        let badgeClass = item.percentage >= 80 ? 'badge-pass' : (item.percentage >= 60 ? 'badge-warn' : 'badge-fail');

        html += `
            <tr>
                <td><strong>#${index + 1}</strong></td>
                <td>
                    <span style="font-weight: 600; color: var(--text-main);">${escapeHtml(item.db_name)}</span>
                </td>
                <td>
                    <span class="task-score-badge ${badgeClass}">${item.percentage}% (${item.total_score} pts)</span>
                </td>
                <td>
                    ${item.general_log_enabled ? '<span style="color: var(--status-pass);"><i class="fas fa-check"></i> Logs Tracked (' + (item.logs_found ? item.logs_found.length : 0) + ' queries)</span>' : '<span style="color: var(--text-muted);"><i class="fas fa-minus"></i> No Logs</span>'}
                </td>
                <td>${item.checked_at}</td>
                <td>
                    <button class="btn" style="padding: 4px 10px; font-size: 0.8rem;" onclick="viewStudentDetail('${escapeHtml(item.db_name)}')">
                        <i class="fas fa-eye"></i> Details
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;

    const avgScore = (totalScoreSum / results.length).toFixed(1);
    summaryStat.innerHTML = `Total Students: <strong>${results.length}</strong> | Class Avg: <strong>${avgScore}%</strong> | Passing: <strong>${passCount}</strong>`;
}

function viewStudentDetail(dbName) {
    // Switch to single tab and run check
    document.querySelector('[data-tab="single-tab"]').click();
    document.getElementById('single-db-name').value = dbName;
    runSingleCheck(dbName);
}

function updateActivityInfo() {
    const activityId = document.getElementById('activity-id')?.value || 'activity1';
    const title = document.getElementById('activity-summary-title');
    const description = document.getElementById('activity-summary-description');
    const list = document.getElementById('activity-summary-list');
    if (!title || !description || !list) return;
    if (activityId === 'activity2') {
        title.innerHTML = '<i class="fas fa-boxes-stacked"></i> Activity 2 Summary';
        description.textContent = 'Verifies the suppliers and items CRUD exercise by checking the final table schema and data state (no query log required).';
        list.innerHTML = '<li><strong>Task 1:</strong> Create <code>suppliers</code> and <code>items</code></li><li><strong>Tasks 2–5:</strong> Required single and multiple-row INSERT statements</li><li><strong>Tasks 12–16:</strong> Required UPDATE and DELETE operations</li>';
    } else if (activityId === 'activity3') {
        title.innerHTML = '<i class="fas fa-list-check"></i> Activity 3 Summary';
        description.textContent = 'Re-runs each of the 10 SELECT statements the student logged in their activity_20260805 table against dbms_activity, and compares the live output to the instructor answer key (dbms_activity_answer_key.activity3_answerkey).';
        list.innerHTML = '<li>Submission table <code>activity_20260805</code> must contain 10 rows (task_number 1–10) with the SQL statement used.</li><li>Each task is scored on matching output columns and matching result data, not query text.</li>';
    } else if (activityId === 'activity4') {
        title.innerHTML = '<i class="fas fa-hot-tub-person"></i> Activity 4 Summary';
        description.textContent = 'Re-runs each of the 10 SELECT statements the student logged in their activity_20260817 table against dbms_activity, and compares the live output to the instructor answer key (dbms_activity_answer_key.activity4_answerkey).';
        list.innerHTML = '<li>Submission table <code>activity_20260817</code> must contain 10 rows (task_number 1–10) with the SQL statement used.</li><li>Each task is scored on matching output columns and matching result data, not query text.</li>';
    } else if (activityId === 'activity5') {
        title.innerHTML = '<i class="fas fa-book-open"></i> Activity 5 Summary';
        description.textContent = 'Re-runs each of the 10 SELECT statements the student logged in their activity_20260923 table against dbms_activity, and compares the live output to the instructor answer key (dbms_activity_answer_key.activity5_answerkey).';
        list.innerHTML = '<li>Submission table <code>activity_20260923</code> must contain 10 rows (task_number 1–10) with the SQL statement used.</li><li>Each task is scored on matching output columns and matching result data, not query text.</li>';
    } else if (activityId === 'activity6') {
        title.innerHTML = '<i class="fas fa-gift"></i> Activity 6 Summary';
        description.textContent = 'Re-runs each of the 10 SELECT statements the student logged in their activity_20260929 table against dbms_activity, and compares the live output to the instructor answer key (dbms_activity_answer_key.activity6_answerkey).';
        list.innerHTML = '<li>Submission table <code>activity_20260929</code> must contain 10 rows (task_number 1–10) with the SQL statement used.</li><li>Each task is scored on matching output columns and matching result data, not query text.</li>';
    } else {
        title.innerHTML = '<i class="fas fa-book"></i> Activity 1 Summary';
        description.textContent = 'Verifies student implementation for Library Database:';
        list.innerHTML = '<li><strong>Task 1:</strong> <code>tbl_authors</code> (PK auto-inc, columns)</li><li><strong>Task 2:</strong> <code>ALTER tbl_authors ADD biography TEXT</code></li><li><strong>Task 3:</strong> <code>tbl_members</code> (PK auto-inc, columns)</li><li><strong>Task 4:</strong> <code>ALTER tbl_members MODIFY email_address VARCHAR(150) NOT NULL</code></li><li><strong>Task 5:</strong> <code>tbl_books</code> (FK to authors, ENUM format)</li><li><strong>Task 6:</strong> <code>tbl_borrow_transactions</code> (FKs to books & members)</li>';
    }
}

/**
 * EXPORT 1: Batch Export (With Logs or Without Logs)
 */
function exportBatchCSV(includeLogs = false) {
    if (cachedBatchResults.length === 0) {
        alert('No batch evaluation data available to export.');
        return;
    }

    let csvRows = [];
    const taskKeys = Object.keys(cachedBatchResults[0]?.tasks || {});
    
    // Define Headers based on includeLogs option
    let headers = [];
    if (includeLogs) {
        headers = [
            "Database Name",
            "Score Percentage (%)",
            "Total Points Earned",
            "Grade Label",
            "Tasks Passed Count",
            ...taskKeys.map(key => `${cachedBatchResults[0].tasks[key].title} - Log Proof`),
            "Total Executed SQL Logs Count",
            "All Executed Query Logs",
            "Checked At Timestamp"
        ];
    } else {
        headers = [
            "Database Name",
            "Score Percentage (%)",
            "Total Points Earned",
            "Grade Label",
            "Tasks Passed Count",
            "Checked At Timestamp"
        ];
    }

    csvRows.push(headers.map(h => `"${h.replace(/"/g, '""')}"`).join(","));

    cachedBatchResults.forEach(r => {
        let passedTasks = 0;
        for (let t in r.tasks) {
            if (r.tasks[t].score_percent >= 100) passedTasks++;
        }

        let gradeLabel = 'A+';
        if (r.percentage < 60) gradeLabel = 'F';
        else if (r.percentage < 80) gradeLabel = 'C';
        else if (r.percentage < 90) gradeLabel = 'B';

        let row = [];
        if (includeLogs) {
            const taskLogs = taskKeys.map(key => r.tasks?.[key]?.log_entry || (r.tasks?.[key]?.log_verified ? 'Verified' : 'None'));

            let allQueries = '';
            if (r.logs_found && r.logs_found.length > 0) {
                allQueries = r.logs_found.map(l => `[${l.event_time}] ${l.argument}`).join(" | ");
            } else {
                allQueries = 'No general log entries recorded for this user/database.';
            }

            row = [
                r.db_name || '',
                r.percentage + '%',
                r.total_score + ' / ' + r.max_score,
                gradeLabel,
                passedTasks + ' / ' + Object.keys(r.tasks || {}).length,
                ...taskLogs,
                r.logs_found ? r.logs_found.length : 0,
                allQueries,
                r.checked_at || ''
            ];
        } else {
            row = [
                r.db_name || '',
                r.percentage + '%',
                r.total_score + ' / ' + r.max_score,
                gradeLabel,
                passedTasks + ' / ' + Object.keys(r.tasks || {}).length,
                r.checked_at || ''
            ];
        }

        csvRows.push(row.map(val => `"${String(val).replace(/"/g, '""')}"`).join(","));
    });

    const fileSuffix = includeLogs ? "With_Logs" : "Summary_No_Logs";
    downloadCSV(csvRows.join("\n"), `DBMS_Class_Scores_${fileSuffix}_${new Date().toISOString().slice(0,10)}.csv`);
}

/**
 * EXPORT 2: Export MySQL General Query Logs (Filtered by search tab)
 */
function exportGeneralLogsCSV() {
    if (cachedGeneralLogs.length === 0) {
        alert('No general log entries loaded to export.');
        return;
    }

    let csvRows = [];
    csvRows.push(['"Event Time"', '"User / Host"', '"Command Type / SQL Query"'].join(","));

    cachedGeneralLogs.forEach(log => {
        let time = log.event_time || '';
        let user = log.user_host || '';
        let query = log.argument || '';
        csvRows.push([
            `"${time.replace(/"/g, '""')}"`,
            `"${user.replace(/"/g, '""')}"`,
            `"${query.replace(/"/g, '""')}"`
        ].join(","));
    });

    const filter = document.getElementById('log-search-db')?.value.trim() || 'all';
    downloadCSV(csvRows.join("\n"), `MySQL_Query_Logs_${filter}_${new Date().toISOString().slice(0,10)}.csv`);
}

/**
 * EXPORT 3: Multi-Sheet Excel Export (.xls SpreadsheetML)
 * Sheet 1: Evaluation Report
 * Sheet 2: Executed Query Logs (Moved to dedicated second sheet in Excel!)
 */
function exportSingleStudentExcel() {
    if (!currentSingleResult) {
        alert('No student evaluation active to export.');
        return;
    }

    const r = currentSingleResult;

    // Sheet 1: Evaluation Report Data
    let sheet1Rows = [
        ["STUDENT DBMS EVALUATION REPORT"],
        ["Database Name / Username", r.db_name],
        ["Score Percentage", r.percentage + '%'],
        ["Points Earned", r.total_score + ' / ' + r.max_score],
        ["Evaluation Time", r.checked_at],
        [""],
        ["TASK BREAKDOWN & DDL LOG PROOF"],
        ["Task Key", "Task Title", "Score (%)", "Points Earned", "Log Verified", "SQL Query Log Proof"]
    ];

    for (let tKey in r.tasks) {
        const task = r.tasks[tKey];
        sheet1Rows.push([
            tKey,
            task.title,
            task.score_percent + '%',
            task.earned_score + ' / ' + task.weight,
            task.log_verified ? 'YES' : 'NO',
            task.log_entry || 'N/A'
        ]);
    }

    // Sheet 2: Executed Query Logs Data (Dedicated Sheet)
    let sheet2Rows = [
        ["EXECUTED MYSQL QUERY LOGS FOR STUDENT: " + r.db_name],
        ["Event Time", "User / Host", "Executed SQL Statement"]
    ];

    if (r.logs_found && r.logs_found.length > 0) {
        r.logs_found.forEach(l => {
            sheet2Rows.push([
                l.event_time || '',
                l.user_host || '',
                l.argument || ''
            ]);
        });
    } else {
        sheet2Rows.push(["N/A", "N/A", "No general log entries recorded for database " + r.db_name]);
    }

    // Build Excel XML with 2 Worksheets
    let xml = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Header">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#4F46E5" ss:Pattern="Solid"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Evaluation Report">
  <Table>`;

    sheet1Rows.forEach((row, rIndex) => {
        xml += `<Row>`;
        row.forEach(cell => {
            const isHeader = (rIndex === 0 || rIndex === 6 || rIndex === 7);
            const style = isHeader ? ' ss:StyleID="Header"' : '';
            xml += `<Cell${style}><Data ss:Type="String">${escapeXml(String(cell))}</Data></Cell>`;
        });
        xml += `</Row>`;
    });

    xml += `  </Table>
 </Worksheet>
 <Worksheet ss:Name="Executed Query Logs">
  <Table>`;

    sheet2Rows.forEach((row, rIndex) => {
        xml += `<Row>`;
        row.forEach(cell => {
            const isHeader = (rIndex === 0 || rIndex === 1);
            const style = isHeader ? ' ss:StyleID="Header"' : '';
            xml += `<Cell${style}><Data ss:Type="String">${escapeXml(String(cell))}</Data></Cell>`;
        });
        xml += `</Row>`;
    });

    xml += `  </Table>
 </Worksheet>
</Workbook>`;

    downloadFile(xml, `Student_Report_${r.db_name}_${new Date().toISOString().slice(0,10)}.xls`, 'application/vnd.ms-excel');
}

/**
 * EXPORT 4: Export Single Student Report Summary CSV
 */
function exportSingleStudentReportCSV() {
    if (!currentSingleResult) {
        alert('No student evaluation active to export.');
        return;
    }

    const r = currentSingleResult;
    let csvRows = [];

    csvRows.push(`"STUDENT DBMS EVALUATION REPORT"`);
    csvRows.push(`"Database Name / Username","${r.db_name}"`);
    csvRows.push(`"Score Percentage","${r.percentage}%"`);
    csvRows.push(`"Points Earned","${r.total_score} / ${r.max_score}"`);
    csvRows.push(`"Evaluation Time","${r.checked_at}"`);
    csvRows.push("");

    csvRows.push(`"TASK BREAKDOWN & DDL LOG PROOF"`);
    csvRows.push(`"Task Key","Task Title","Score (%)","Points Earned","Log Verified","SQL Query Log Proof"`);

    for (let tKey in r.tasks) {
        const task = r.tasks[tKey];
        csvRows.push([
            `"${tKey}"`,
            `"${task.title.replace(/"/g, '""')}"`,
            `"${task.score_percent}%"`,
            `"${task.earned_score} / ${task.weight}"`,
            `"${task.log_verified ? 'YES' : 'NO'}"`,
            `"${(task.log_entry || 'N/A').replace(/"/g, '""')}"`
        ].join(","));
    }

    downloadCSV(csvRows.join("\n"), `Student_Report_${r.db_name}_${new Date().toISOString().slice(0,10)}.csv`);
}

// Helper function for XML escaping
function escapeXml(unsafe) {
    if (!unsafe) return '';
    return unsafe.toString().replace(/[<>&'"]/g, function (c) {
        switch (c) {
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '&': return '&amp;';
            case '\'': return '&apos;';
            case '"': return '&quot;';
        }
    });
}

// File Download Helper
function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Download Helper
function downloadCSV(csvString, filename) {
    const blob = new Blob(["\ufeff" + csvString], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Load MySQL General Logs Viewer
function loadGeneralLogs() {
    const logContainer = document.getElementById('log-box-content');
    logContainer.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching MySQL general query logs...';

    const searchDb = document.getElementById('log-search-db')?.value.trim() || '';
    const startDate = document.getElementById('log-search-start-date')?.value.trim() || '';
    const endDate = document.getElementById('log-search-end-date')?.value.trim() || '';

    let url = `api.php?action=view_logs&db_name=${encodeURIComponent(searchDb)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.logs.length > 0) {
                cachedGeneralLogs = res.logs;
                let html = '';
                res.logs.forEach(log => {
                    const argUpper = (log.argument || '').toUpperCase();
                    const isDDL = argUpper.includes('CREATE TABLE') || argUpper.includes('ALTER TABLE') || argUpper.includes('DROP TABLE');
                    const ddlClass = isDDL ? 'ddl' : '';
                    html += `
                        <div class="log-entry-row">
                            <span class="log-time">[${escapeHtml(log.event_time)}] [${escapeHtml(log.user_host)}]</span><br>
                            <span class="log-query ${ddlClass}">${escapeHtml(log.argument)}</span>
                        </div>
                    `;
                });
                logContainer.innerHTML = html;
            } else {
                cachedGeneralLogs = [];
                logContainer.innerHTML = '<div style="color: var(--text-muted); padding: 1rem;">No general log entries found. Ensure general_log is enabled.</div>';
            }
        });
}

// Modal helper
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
