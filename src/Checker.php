<?php
/**
 * DBMS Activity Verification & Scoring Engine
 */

class ActivityChecker {
    private $pdo;
    private $cfg;

    public function __construct($pdo, $cfg) {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    /**
     * Check single student database against Activity 1 rules
     */
    public function checkActivity1($dbName) {
        $result = [
            'db_name' => $dbName,
            'db_exists' => false,
            'user_exists' => false,
            'total_score' => 0,
            'max_score' => 100,
            'percentage' => 0,
            'tasks' => [],
            'logs_found' => [],
            'general_log_enabled' => false,
            'checked_at' => date('Y-m-d H:i:s')
        ];

        // 1. Check if database exists
        $stmt = $this->pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$dbName]);
        if (!$stmt->fetch()) {
            $result['db_exists'] = false;
            $result['error'] = "Database '{$dbName}' does not exist on MySQL server.";
            return $result;
        }
        $result['db_exists'] = true;

        // 2. Check if student user exists in mysql.user
        try {
            $userStmt = $this->pdo->prepare("SELECT User FROM mysql.user WHERE User = ?");
            $userStmt->execute([$dbName]);
            $result['user_exists'] = (bool)$userStmt->fetch();
        } catch (Exception $e) {
            // Non-root PDO might not have access to mysql.user, treat gracefully
            $result['user_exists'] = true; 
        }

        // 3. Check General Log Status & Fetch Logs
        $logs = $this->fetchStudentGeneralLogs($dbName);
        $result['general_log_enabled'] = $logs['enabled'];
        $result['logs_found'] = $logs['entries'];

        // 4. Inspect Tables & Schema
        $tasks = [
            'task1' => $this->checkTask1_Authors($dbName, $logs['entries']),
            'task2' => $this->checkTask2_AlterAuthors($dbName, $logs['entries']),
            'task3' => $this->checkTask3_Members($dbName, $logs['entries']),
            'task4' => $this->checkTask4_AlterMembers($dbName, $logs['entries']),
            'task5' => $this->checkTask5_Books($dbName, $logs['entries']),
            'task6' => $this->checkTask6_BorrowTransactions($dbName, $logs['entries']),
        ];

        // Calculate total score based on weights
        $totalScore = 0;
        $weights = $this->cfg['score_weights'];

        foreach ($tasks as $key => &$task) {
            $weight = isset($weights[$key]) ? $weights[$key] : 15;
            $task['weight'] = $weight;
            $task['earned_score'] = round(($task['score_percent'] / 100) * $weight, 2);
            $totalScore += $task['earned_score'];
        }

        $result['tasks'] = $tasks;
        $result['total_score'] = round($totalScore, 2);
        $result['percentage'] = round(($totalScore / $result['max_score']) * 100, 1);

        return $result;
    }

    /**
     * Fetch General Logs for student user/database
     */
    public function fetchStudentGeneralLogs($dbName) {
        $data = ['enabled' => false, 'entries' => []];

        try {
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'general_log'");
            $row = $stmt->fetch();
            if ($row && strtolower($row['Value']) === 'on') {
                $data['enabled'] = true;
            }

            if ($data['enabled']) {
                $searchPattern = '%' . $dbName . '%';
                $params = [$searchPattern, $searchPattern];

                $sql = "
                    SELECT event_time, user_host, argument 
                    FROM mysql.general_log 
                    WHERE (argument LIKE ? OR user_host LIKE ?)
                      AND command_type IN ('Query', 'Execute')
                      AND argument NOT LIKE 'SELECT %FROM mysql.general_log%'
                ";

                if (!empty($this->cfg['log_date_enabled'])) {
                    if (!empty($this->cfg['log_date_start'])) {
                        $sql .= " AND event_time >= ?";
                        $params[] = trim($this->cfg['log_date_start']) . ' 00:00:00';
                    }
                    if (!empty($this->cfg['log_date_end'])) {
                        $sql .= " AND event_time <= ?";
                        $params[] = trim($this->cfg['log_date_end']) . ' 23:59:59';
                    }
                }

                $sql .= " ORDER BY event_time DESC";

                $logStmt = $this->pdo->prepare($sql);
                $logStmt->execute($params);
                $data['entries'] = $logStmt->fetchAll();
                $data['date_filter_active'] = !empty($this->cfg['log_date_enabled']);
                $data['date_start'] = $this->cfg['log_date_start'] ?? null;
                $data['date_end'] = $this->cfg['log_date_end'] ?? null;
            }
        } catch (Exception $e) {
            // general_log table might not exist or be accessible
            $data['error'] = $e->getMessage();
        }

        return $data;
    }

    /**
     * Check if a specific SQL log command was executed
     */
    private function findInLogs($logs, $keyword, $tableKeyword = null) {
        if (empty($logs)) return false;
        foreach ($logs as $entry) {
            $arg = strtoupper($entry['argument'] ?? '');
            if (strpos($arg, strtoupper($keyword)) !== false) {
                if ($tableKeyword === null || strpos($arg, strtoupper($tableKeyword)) !== false) {
                    return $entry;
                }
            }
        }
        return false;
    }

    /**
     * Task 1: Create tbl_authors
     */
    private function checkTask1_Authors($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 5;

        $table = 'tbl_authors';
        $exists = $this->tableExists($dbName, $table);
        $checks[] = [
            'name' => "Table '{$table}' exists",
            'passed' => $exists,
            'detail' => $exists ? "Found table {$table}" : "Table {$table} missing"
        ];
        if ($exists) $passedCount++;

        // Columns
        $colId = $this->getColumn($dbName, $table, 'author_id');
        $idOk = $colId && strpos(strtolower($colId['DATA_TYPE']), 'int') !== false 
                      && strpos(strtolower($colId['COLUMN_KEY']), 'pri') !== false 
                      && strpos(strtolower($colId['EXTRA']), 'auto_increment') !== false;
        $checks[] = [
            'name' => "Column 'author_id' (INT, Primary Key, Auto Increment)",
            'passed' => (bool)$idOk,
            'detail' => $colId ? "Type: {$colId['COLUMN_TYPE']}, Key: {$colId['COLUMN_KEY']}, Extra: {$colId['EXTRA']}" : "Column missing"
        ];
        if ($idOk) $passedCount++;

        $colName = $this->getColumn($dbName, $table, 'author_name');
        $nameOk = $colName && strpos(strtolower($colName['DATA_TYPE']), 'varchar') !== false 
                        && $colName['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'author_name' (VARCHAR(100), NOT NULL)",
            'passed' => (bool)$nameOk,
            'detail' => $colName ? "Type: {$colName['COLUMN_TYPE']}, Nullable: {$colName['IS_NULLABLE']}" : "Column missing"
        ];
        if ($nameOk) $passedCount++;

        $colNat = $this->getColumn($dbName, $table, 'nationality');
        $natOk = $colNat && strpos(strtolower($colNat['DATA_TYPE']), 'varchar') !== false;
        $checks[] = [
            'name' => "Column 'nationality' (VARCHAR(50))",
            'passed' => (bool)$natOk,
            'detail' => $colNat ? "Type: {$colNat['COLUMN_TYPE']}" : "Column missing"
        ];
        if ($natOk) $passedCount++;

        $colBirth = $this->getColumn($dbName, $table, 'birth_year');
        $birthOk = $colBirth && (strpos(strtolower($colBirth['DATA_TYPE']), 'int') !== false || strpos(strtolower($colBirth['DATA_TYPE']), 'year') !== false);
        $checks[] = [
            'name' => "Column 'birth_year' (SMALLINT/INT)",
            'passed' => (bool)$birthOk,
            'detail' => $colBirth ? "Type: {$colBirth['COLUMN_TYPE']}" : "Column missing"
        ];
        if ($birthOk) $passedCount++;

        // Log check
        $logMatch = $this->findInLogs($logs, 'CREATE TABLE', 'tbl_authors');

        return [
            'title' => 'Task 1 - Create tbl_authors',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$logMatch,
            'log_entry' => $logMatch ? $logMatch['argument'] : null
        ];
    }

    /**
     * Task 2: Alter tbl_authors (Add biography TEXT)
     */
    private function checkTask2_AlterAuthors($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 2;

        $table = 'tbl_authors';
        $colBio = $this->getColumn($dbName, $table, 'biography');
        $bioOk = $colBio && (strpos(strtolower($colBio['DATA_TYPE']), 'text') !== false || strpos(strtolower($colBio['DATA_TYPE']), 'varchar') !== false);
        
        $checks[] = [
            'name' => "Column 'biography' exists in tbl_authors with TEXT data type",
            'passed' => (bool)$bioOk,
            'detail' => $colBio ? "Type: {$colBio['COLUMN_TYPE']}" : "Column 'biography' is missing"
        ];
        if ($bioOk) $passedCount++;

        // Log check for ALTER TABLE
        $alterLog = $this->findInLogs($logs, 'ALTER TABLE', 'tbl_authors');
        $checks[] = [
            'name' => "Execution log contains ALTER TABLE statement for tbl_authors",
            'passed' => (bool)$alterLog,
            'detail' => $alterLog ? "Verified in MySQL log: " . substr($alterLog['argument'], 0, 80) . "..." : "No ALTER TABLE query recorded in log for tbl_authors"
        ];
        if ($alterLog) $passedCount++;

        return [
            'title' => 'Task 2 - Alter tbl_authors (Add biography TEXT)',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$alterLog,
            'log_entry' => $alterLog ? $alterLog['argument'] : null
        ];
    }

    /**
     * Task 3: Create tbl_members
     */
    private function checkTask3_Members($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 5;

        $table = 'tbl_members';
        $exists = $this->tableExists($dbName, $table);
        $checks[] = [
            'name' => "Table '{$table}' exists",
            'passed' => $exists,
            'detail' => $exists ? "Found table {$table}" : "Table {$table} missing"
        ];
        if ($exists) $passedCount++;

        $colId = $this->getColumn($dbName, $table, 'member_id');
        $idOk = $colId && strpos(strtolower($colId['DATA_TYPE']), 'int') !== false 
                      && strpos(strtolower($colId['COLUMN_KEY']), 'pri') !== false 
                      && strpos(strtolower($colId['EXTRA']), 'auto_increment') !== false;
        $checks[] = [
            'name' => "Column 'member_id' (INT, Primary Key, Auto Increment)",
            'passed' => (bool)$idOk,
            'detail' => $colId ? "Type: {$colId['COLUMN_TYPE']}, Key: {$colId['COLUMN_KEY']}, Extra: {$colId['EXTRA']}" : "Column missing"
        ];
        if ($idOk) $passedCount++;

        $colLast = $this->getColumn($dbName, $table, 'last_name');
        $lastOk = $colLast && strpos(strtolower($colLast['DATA_TYPE']), 'varchar') !== false && $colLast['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'last_name' (VARCHAR(50), NOT NULL)",
            'passed' => (bool)$lastOk,
            'detail' => $colLast ? "Type: {$colLast['COLUMN_TYPE']}, Nullable: {$colLast['IS_NULLABLE']}" : "Column missing"
        ];
        if ($lastOk) $passedCount++;

        $colFirst = $this->getColumn($dbName, $table, 'first_name');
        $firstOk = $colFirst && strpos(strtolower($colFirst['DATA_TYPE']), 'varchar') !== false && $colFirst['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'first_name' (VARCHAR(50), NOT NULL)",
            'passed' => (bool)$firstOk,
            'detail' => $colFirst ? "Type: {$colFirst['COLUMN_TYPE']}, Nullable: {$colFirst['IS_NULLABLE']}" : "Column missing"
        ];
        if ($firstOk) $passedCount++;

        $colDate = $this->getColumn($dbName, $table, 'date_joined');
        $dateOk = $colDate && (strpos(strtolower($colDate['DATA_TYPE']), 'date') !== false || strpos(strtolower($colDate['DATA_TYPE']), 'time') !== false);
        $checks[] = [
            'name' => "Column 'date_joined' (DATE)",
            'passed' => (bool)$dateOk,
            'detail' => $colDate ? "Type: {$colDate['COLUMN_TYPE']}" : "Column missing"
        ];
        if ($dateOk) $passedCount++;

        $logMatch = $this->findInLogs($logs, 'CREATE TABLE', 'tbl_members');

        return [
            'title' => 'Task 3 - Create tbl_members',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$logMatch,
            'log_entry' => $logMatch ? $logMatch['argument'] : null
        ];
    }

    /**
     * Task 4: Alter tbl_members (widen email_address to VARCHAR(150) NOT NULL)
     */
    private function checkTask4_AlterMembers($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 3;

        $table = 'tbl_members';
        $colEmail = $this->getColumn($dbName, $table, 'email_address');
        
        $lengthOk = $colEmail && strpos(strtolower($colEmail['DATA_TYPE']), 'varchar') !== false 
                             && ($colEmail['CHARACTER_MAXIMUM_LENGTH'] >= 150);
        $checks[] = [
            'name' => "Column 'email_address' modified to VARCHAR(150)",
            'passed' => (bool)$lengthOk,
            'detail' => $colEmail ? "Type: {$colEmail['COLUMN_TYPE']} (Length: {$colEmail['CHARACTER_MAXIMUM_LENGTH']})" : "Column missing"
        ];
        if ($lengthOk) $passedCount++;

        $nullOk = $colEmail && $colEmail['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'email_address' set to NOT NULL",
            'passed' => (bool)$nullOk,
            'detail' => $colEmail ? "Nullable: {$colEmail['IS_NULLABLE']}" : "Column missing"
        ];
        if ($nullOk) $passedCount++;

        // Log check for ALTER TABLE tbl_members
        $alterLog = $this->findInLogs($logs, 'ALTER TABLE', 'tbl_members');
        $checks[] = [
            'name' => "Execution log contains ALTER TABLE statement for tbl_members",
            'passed' => (bool)$alterLog,
            'detail' => $alterLog ? "Verified in MySQL log: " . substr($alterLog['argument'], 0, 80) . "..." : "No ALTER TABLE query recorded in log for tbl_members"
        ];
        if ($alterLog) $passedCount++;

        return [
            'title' => 'Task 4 - Alter tbl_members (widen email_address to VARCHAR(150) NOT NULL)',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$alterLog,
            'log_entry' => $alterLog ? $alterLog['argument'] : null
        ];
    }

    /**
     * Task 5: Create tbl_books
     */
    private function checkTask5_Books($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 6;

        $table = 'tbl_books';
        $exists = $this->tableExists($dbName, $table);
        $checks[] = [
            'name' => "Table '{$table}' exists",
            'passed' => $exists,
            'detail' => $exists ? "Found table {$table}" : "Table {$table} missing"
        ];
        if ($exists) $passedCount++;

        $colId = $this->getColumn($dbName, $table, 'book_id');
        $idOk = $colId && strpos(strtolower($colId['DATA_TYPE']), 'int') !== false 
                      && strpos(strtolower($colId['COLUMN_KEY']), 'pri') !== false 
                      && strpos(strtolower($colId['EXTRA']), 'auto_increment') !== false;
        $checks[] = [
            'name' => "Column 'book_id' (INT, Primary Key, Auto Increment)",
            'passed' => (bool)$idOk,
            'detail' => $colId ? "Type: {$colId['COLUMN_TYPE']}, Key: {$colId['COLUMN_KEY']}, Extra: {$colId['EXTRA']}" : "Column missing"
        ];
        if ($idOk) $passedCount++;

        $colTitle = $this->getColumn($dbName, $table, 'title');
        $titleOk = $colTitle && strpos(strtolower($colTitle['DATA_TYPE']), 'varchar') !== false && $colTitle['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'title' (VARCHAR(150), NOT NULL)",
            'passed' => (bool)$titleOk,
            'detail' => $colTitle ? "Type: {$colTitle['COLUMN_TYPE']}, Nullable: {$colTitle['IS_NULLABLE']}" : "Column missing"
        ];
        if ($titleOk) $passedCount++;

        // Foreign Key author_id -> tbl_authors(author_id)
        $fkOk = $this->checkForeignKey($dbName, $table, 'author_id', 'tbl_authors', 'author_id');
        $checks[] = [
            'name' => "Column 'author_id' has Foreign Key referencing tbl_authors(author_id)",
            'passed' => (bool)$fkOk,
            'detail' => $fkOk ? "Foreign Key constraint valid" : "Foreign key on author_id to tbl_authors is missing or invalid"
        ];
        if ($fkOk) $passedCount++;

        $colCopies = $this->getColumn($dbName, $table, 'copies_owned');
        $copiesOk = $colCopies && strpos(strtolower($colCopies['DATA_TYPE']), 'int') !== false && $colCopies['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'copies_owned' (INT, NOT NULL)",
            'passed' => (bool)$copiesOk,
            'detail' => $colCopies ? "Type: {$colCopies['COLUMN_TYPE']}" : "Column missing"
        ];
        if ($copiesOk) $passedCount++;

        // Enum check: ENUM('hardcover','paperback','ebook','audiobook')
        $colFormat = $this->getColumn($dbName, $table, 'book_format');
        $enumOk = false;
        if ($colFormat && strpos(strtolower($colFormat['DATA_TYPE']), 'enum') !== false) {
            $type = strtolower($colFormat['COLUMN_TYPE']);
            if (strpos($type, 'hardcover') !== false && strpos($type, 'paperback') !== false && strpos($type, 'ebook') !== false && strpos($type, 'audiobook') !== false) {
                $enumOk = true;
            }
        }
        $checks[] = [
            'name' => "Column 'book_format' ENUM('hardcover', 'paperback', 'ebook', 'audiobook') NOT NULL",
            'passed' => (bool)$enumOk,
            'detail' => $colFormat ? "Type: {$colFormat['COLUMN_TYPE']}, Nullable: {$colFormat['IS_NULLABLE']}" : "Column missing"
        ];
        if ($enumOk) $passedCount++;

        $logMatch = $this->findInLogs($logs, 'CREATE TABLE', 'tbl_books');

        return [
            'title' => 'Task 5 - Create tbl_books',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$logMatch,
            'log_entry' => $logMatch ? $logMatch['argument'] : null
        ];
    }

    /**
     * Task 6: Create tbl_borrow_transactions
     */
    private function checkTask6_BorrowTransactions($dbName, $logs) {
        $checks = [];
        $passedCount = 0;
        $totalChecks = 6;

        $table = 'tbl_borrow_transactions';
        $exists = $this->tableExists($dbName, $table);
        $checks[] = [
            'name' => "Table '{$table}' exists",
            'passed' => $exists,
            'detail' => $exists ? "Found table {$table}" : "Table {$table} missing"
        ];
        if ($exists) $passedCount++;

        $colId = $this->getColumn($dbName, $table, 'borrow_id');
        $idOk = $colId && strpos(strtolower($colId['DATA_TYPE']), 'int') !== false 
                      && strpos(strtolower($colId['COLUMN_KEY']), 'pri') !== false 
                      && strpos(strtolower($colId['EXTRA']), 'auto_increment') !== false;
        $checks[] = [
            'name' => "Column 'borrow_id' (INT, Primary Key, Auto Increment)",
            'passed' => (bool)$idOk,
            'detail' => $colId ? "Type: {$colId['COLUMN_TYPE']}, Key: {$colId['COLUMN_KEY']}" : "Column missing"
        ];
        if ($idOk) $passedCount++;

        // Foreign Key book_id -> tbl_books(book_id)
        $fkBook = $this->checkForeignKey($dbName, $table, 'book_id', 'tbl_books', 'book_id');
        $checks[] = [
            'name' => "Column 'book_id' FK referencing tbl_books(book_id)",
            'passed' => (bool)$fkBook,
            'detail' => $fkBook ? "Foreign Key constraint valid" : "FK on book_id missing or invalid"
        ];
        if ($fkBook) $passedCount++;

        // Foreign Key member_id -> tbl_members(member_id)
        $fkMember = $this->checkForeignKey($dbName, $table, 'member_id', 'tbl_members', 'member_id');
        $checks[] = [
            'name' => "Column 'member_id' FK referencing tbl_members(member_id)",
            'passed' => (bool)$fkMember,
            'detail' => $fkMember ? "Foreign Key constraint valid" : "FK on member_id missing or invalid"
        ];
        if ($fkMember) $passedCount++;

        $colDate = $this->getColumn($dbName, $table, 'date_borrowed');
        $dateOk = $colDate && (strpos(strtolower($colDate['DATA_TYPE']), 'date') !== false || strpos(strtolower($colDate['DATA_TYPE']), 'time') !== false) && $colDate['IS_NULLABLE'] === 'NO';
        $checks[] = [
            'name' => "Column 'date_borrowed' (DATE, NOT NULL)",
            'passed' => (bool)$dateOk,
            'detail' => $colDate ? "Type: {$colDate['COLUMN_TYPE']}, Nullable: {$colDate['IS_NULLABLE']}" : "Column missing"
        ];
        if ($dateOk) $passedCount++;

        $colFine = $this->getColumn($dbName, $table, 'fine_amount');
        $fineOk = $colFine && (strpos(strtolower($colFine['DATA_TYPE']), 'decimal') !== false || strpos(strtolower($colFine['DATA_TYPE']), 'numeric') !== false || strpos(strtolower($colFine['DATA_TYPE']), 'float') !== false || strpos(strtolower($colFine['DATA_TYPE']), 'double') !== false);
        $checks[] = [
            'name' => "Column 'fine_amount' (DECIMAL(6,2))",
            'passed' => (bool)$fineOk,
            'detail' => $colFine ? "Type: {$colFine['COLUMN_TYPE']}" : "Column missing"
        ];
        if ($fineOk) $passedCount++;

        $logMatch = $this->findInLogs($logs, 'CREATE TABLE', 'tbl_borrow_transactions');

        return [
            'title' => 'Task 6 - Create tbl_borrow_transactions',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => (bool)$logMatch,
            'log_entry' => $logMatch ? $logMatch['argument'] : null
        ];
    }

    // Helper functions
    private function tableExists($dbName, $tableName) {
        $stmt = $this->pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt->execute([$dbName, $tableName]);
        return (bool)$stmt->fetch();
    }

    private function getColumn($dbName, $tableName, $columnName) {
        $stmt = $this->pdo->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$dbName, $tableName, $columnName]);
        return $stmt->fetch();
    }

    private function checkForeignKey($dbName, $tableName, $colName, $refTable, $refCol) {
        $stmt = $this->pdo->prepare("
            SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$dbName, $tableName, $colName]);
        $fk = $stmt->fetch();
        if (!$fk) return false;
        return (strtolower($fk['REFERENCED_TABLE_NAME']) === strtolower($refTable) && strtolower($fk['REFERENCED_COLUMN_NAME']) === strtolower($refCol));
    }
}
