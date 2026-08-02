<?php
require_once __DIR__ . '/config.php';
$cfg = get_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DBMS Student Activity Checker & Log Verifier</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header -->
    <header class="app-header">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-database"></i>
            </div>
            <div class="brand-title">
                <h1>DBMS Student Evaluator</h1>
                <p>Activity 1 Checker & MySQL Query Log Verifier</p>
            </div>
        </div>

        <nav class="nav-tabs">
            <button class="tab-btn active" data-tab="single-tab">
                <i class="fas fa-user-graduate"></i> Single Check
            </button>
            <button class="tab-btn" data-tab="batch-tab">
                <i class="fas fa-list-check"></i> Class Batch Checker
            </button>
            <button class="tab-btn" data-tab="logs-tab">
                <i class="fas fa-terminal"></i> MySQL Log Explorer
            </button>
            <button class="tab-btn" data-tab="demo-tab">
                <i class="fas fa-vial"></i> Demo Generator
            </button>
        </nav>

        <div class="header-controls">
            <span id="connection-status" class="badge-warn" style="font-size: 0.8rem; padding: 6px 12px; border-radius: 20px; cursor: pointer;" onclick="openModal('config-modal')">
                <i class="fas fa-spinner fa-spin"></i> Checking DB...
            </span>
            <button class="btn" onclick="openModal('config-modal')" title="Settings">
                <i class="fas fa-cog"></i> Settings
            </button>
        </div>
    </header>

    <!-- Main Container -->
    <div class="app-container">

        <!-- TAB 1: SINGLE CHECK -->
        <div id="single-tab" class="tab-content active">
            <div class="checker-grid">
                <!-- Left Column: Input Form -->
                <div>
                    <div class="glass-card">
                        <div class="glass-card-header">
                            <span class="glass-card-title"><i class="fas fa-search"></i> Check Student Database</span>
                        </div>
                        <form id="single-check-form">
                            <div class="form-group">
                                <label for="single-db-name">Student Database Name / Username</label>
                                <input type="text" id="single-db-name" class="form-control" placeholder="e.g. 2_cs4_delacruz" value="2_cs4_delacruz" required>
                                <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                    Format: <code>section_lastname</code> (e.g. <code>2_cs4_delacruz</code>)
                                </small>
                            </div>

                            <div style="margin-bottom: 1rem;">
                                <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Quick Demo Databases:</label>
                                <span class="chip" onclick="document.getElementById('single-db-name').value='2_cs4_delacruz'; runSingleCheck('2_cs4_delacruz');">2_cs4_delacruz</span>
                                <span class="chip" onclick="document.getElementById('single-db-name').value='2_cs4_santos'; runSingleCheck('2_cs4_santos');">2_cs4_santos</span>
                                <span class="chip" onclick="document.getElementById('single-db-name').value='2_cs4_reyes'; runSingleCheck('2_cs4_reyes');">2_cs4_reyes</span>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem;">
                                <i class="fas fa-shield-halved"></i> Run Verification & Score
                            </button>
                        </form>
                    </div>

                    <div class="glass-card" style="font-size: 0.85rem;">
                        <h4 style="margin-bottom: 0.5rem; color: var(--text-highlight);"><i class="fas fa-book"></i> Activity 1 Summary</h4>
                        <p style="color: var(--text-muted); line-height: 1.5; margin-bottom: 0.5rem;">
                            Verifies student implementation for Library Database:
                        </p>
                        <ul style="color: var(--text-muted); padding-left: 1.2rem; line-height: 1.6;">
                            <li><strong>Task 1:</strong> <code>tbl_authors</code> (PK auto-inc, columns)</li>
                            <li><strong>Task 2:</strong> <code>ALTER tbl_authors ADD biography TEXT</code></li>
                            <li><strong>Task 3:</strong> <code>tbl_members</code> (PK auto-inc, columns)</li>
                            <li><strong>Task 4:</strong> <code>ALTER tbl_members MODIFY email_address VARCHAR(150) NOT NULL</code></li>
                            <li><strong>Task 5:</strong> <code>tbl_books</code> (FK to authors, ENUM format)</li>
                            <li><strong>Task 6:</strong> <code>tbl_borrow_transactions</code> (FKs to books & members)</li>
                        </ul>
                    </div>
                </div>

                <!-- Right Column: Verification Results -->
                <div id="single-check-results">
                    <div class="glass-card" style="text-align: center; padding: 4rem 2rem;">
                        <i class="fas fa-clipboard-check fa-4x" style="color: var(--border-color-glow); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--text-main);">Ready to Check</h3>
                        <p style="color: var(--text-muted); max-width: 500px; margin: 0.5rem auto 1.5rem auto;">
                            Enter a student database name or select a quick sample database to verify table structures, column definitions, constraints, foreign keys, and MySQL execution logs.
                        </p>
                        <button class="btn btn-primary" onclick="createDemoStudent('full', '2_cs4_delacruz')">
                            <i class="fas fa-magic"></i> Generate & Check Demo Database
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: BATCH CHECKER -->
        <div id="batch-tab" class="tab-content">
            <div class="glass-card">
                <div class="glass-card-header">
                    <div>
                        <span class="glass-card-title"><i class="fas fa-users-viewfinder"></i> Class Batch Checker</span>
                        <p id="batch-stat-summary" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                            Discovering student databases...
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn" onclick="loadBatchDatabases()"><i class="fas fa-rotate"></i> Refresh</button>
                        <button class="btn btn-success" onclick="exportBatchCSV(false)" title="Export scores and summary statistics without query logs"><i class="fas fa-file-excel"></i> Export CSV (Without Logs)</button>
                        <button class="btn btn-primary" onclick="exportBatchCSV(true)" title="Export scores along with per-task SQL log proofs and full query history"><i class="fas fa-file-invoice"></i> Export CSV (With Logs)</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Database Name</th>
                                <th>Score & Grade</th>
                                <th>Execution Log Status</th>
                                <th>Checked At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batch-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: MYSQL LOG EXPLORER -->
        <div id="logs-tab" class="tab-content">
            <div class="glass-card">
                <div class="glass-card-header">
                    <div>
                        <span class="glass-card-title"><i class="fas fa-terminal"></i> MySQL General Query Log Explorer</span>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                            Inspect exact DDL and SQL statements executed by student user accounts on the server.
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="enableGeneralLog()">
                            <i class="fas fa-power-off"></i> Enable General Query Log
                        </button>
                        <button class="btn btn-success" onclick="exportGeneralLogsCSV()">
                            <i class="fas fa-download"></i> Export Filtered Logs (CSV)
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; margin-bottom: 1rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.78rem; color:var(--text-muted);">Database / Student Search</label>
                        <input type="text" id="log-search-db" class="form-control" placeholder="Filter by Student DB / Username (e.g. delacruz)" onkeyup="loadGeneralLogs()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.78rem; color:var(--text-muted);">From Date</label>
                        <input type="date" id="log-search-start-date" class="form-control" value="<?= htmlspecialchars($cfg['log_date_start'] ?? date('Y-m-d')) ?>" onchange="loadGeneralLogs()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.78rem; color:var(--text-muted);">To Date</label>
                        <input type="date" id="log-search-end-date" class="form-control" value="<?= htmlspecialchars($cfg['log_date_end'] ?? date('Y-m-d')) ?>" onchange="loadGeneralLogs()">
                    </div>
                    <button class="btn" onclick="loadGeneralLogs()"><i class="fas fa-search"></i> Filter</button>
                </div>

                <div class="log-box" id="log-box-content">
                    <div style="color: var(--text-muted);">Loading logs...</div>
                </div>
            </div>
        </div>

        <!-- TAB 4: DEMO GENERATOR -->
        <div id="demo-tab" class="tab-content">
            <div class="glass-card">
                <div class="glass-card-header">
                    <span class="glass-card-title"><i class="fas fa-flask"></i> Quick Demo & Testing Suite</span>
                </div>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    Generate sample student databases directly on your MySQL server to test grading, error detection, and log tracking.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="task-card" style="padding: 1.25rem;">
                        <h4 style="color: var(--status-pass); margin-bottom: 0.5rem;"><i class="fas fa-star"></i> Perfect Student (100%)</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Database <code>2_cs4_delacruz</code>: All 6 tasks completed with ALTER TABLE statements and foreign keys.
                        </p>
                        <button class="btn btn-primary" onclick="createDemoStudent('full', '2_cs4_delacruz')">
                            Generate 2_cs4_delacruz
                        </button>
                    </div>

                    <div class="task-card" style="padding: 1.25rem;">
                        <h4 style="color: var(--status-warn); margin-bottom: 0.5rem;"><i class="fas fa-exclamation-triangle"></i> Partial Student (No ALTER logs)</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Database <code>2_cs4_santos</code>: Created initial tables but skipped <code>ALTER TABLE</code> steps.
                        </p>
                        <button class="btn" onclick="createDemoStudent('no_alter', '2_cs4_santos')">
                            Generate 2_cs4_santos
                        </button>
                    </div>
                </div>

                <div id="demo-status" style="margin-top: 1.5rem;"></div>
            </div>
        </div>

    </div>

    <!-- Modal: Settings & Config -->
    <div id="config-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3 style="font-size: 1.15rem;"><i class="fas fa-sliders"></i> MySQL Connection & Config</h3>
                <button class="modal-close" onclick="closeModal('config-modal')">&times;</button>
            </div>
            <form id="config-form">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>MySQL Host</label>
                        <input type="text" id="cfg-db-host" class="form-control" value="<?= htmlspecialchars($cfg['db_host']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="text" id="cfg-db-port" class="form-control" value="<?= htmlspecialchars($cfg['db_port']) ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Admin User</label>
                        <input type="text" id="cfg-db-user" class="form-control" value="<?= htmlspecialchars($cfg['db_user']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="cfg-db-pass" class="form-control" value="<?= htmlspecialchars($cfg['db_pass']) ?>" placeholder="Leave blank if none">
                    </div>
                </div>

                <div class="form-group">
                    <label>Section Prefix Filter (Optional)</label>
                    <input type="text" id="cfg-section-filter" class="form-control" value="<?= htmlspecialchars($cfg['section_filter']) ?>" placeholder="e.g. 2_cs4">
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem;">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--text-highlight);"><i class="fas fa-calendar-alt"></i> Log Verification Date Filter</h4>
                    
                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <input type="checkbox" id="cfg-log-date-enabled" <?= !empty($cfg['log_date_enabled']) ? 'checked' : '' ?>>
                        <label for="cfg-log-date-enabled" style="margin-bottom: 0; cursor: pointer; color: var(--text-main);">
                            Restrict log verification & proofs to specific date range
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Log Check Start Date</label>
                            <input type="date" id="cfg-log-date-start" class="form-control" value="<?= htmlspecialchars($cfg['log_date_start'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Log Check End Date</label>
                            <input type="date" id="cfg-log-date-end" class="form-control" value="<?= htmlspecialchars($cfg['log_date_end'] ?? date('Y-m-d')) ?>">
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeModal('config-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Application Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
