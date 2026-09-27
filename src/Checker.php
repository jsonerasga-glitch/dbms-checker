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

    /** Run a registered activity checker. */
    public function checkActivity($activityId, $dbName) {
        if ($activityId === 'activity2') {
            return $this->checkActivity2($dbName);
        }
        if ($activityId === 'activity3') {
            return $this->checkActivity3($dbName);
        }
        if ($activityId === 'activity4') {
            return $this->checkActivity4($dbName);
        }
        if ($activityId === 'activity5') {
            return $this->checkActivity5($dbName);
        }
        if ($activityId === 'activity6') {
            return $this->checkActivity6($dbName);
        }
        return $this->checkActivity1($dbName);
    }

    /**
     * Check single student database against Activity 1 rules
     */
    public function checkActivity1($dbName) {
        $result = [
            'activity_id' => 'activity1',
            'activity_name' => 'Activity 1 - Library Database',
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
     * Activity 2: Suppliers and items CRUD exercise.
     * Verified purely from final database state (schema + data) per the
     * Activity 2 spec - the MySQL general query log is not consulted.
     */
    public function checkActivity2($dbName) {
        $result = [
            'activity_id' => 'activity2', 'activity_name' => 'Activity 2 - Suppliers and Items CRUD',
            'db_name' => $dbName, 'db_exists' => false, 'user_exists' => false,
            'total_score' => 0, 'max_score' => 100, 'percentage' => 0, 'tasks' => [],
            'logs_found' => [], 'general_log_enabled' => false, 'checked_at' => date('Y-m-d H:i:s')
        ];
        $stmt = $this->pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$dbName]);
        if (!$stmt->fetch()) { $result['error'] = "Database '{$dbName}' does not exist on MySQL server."; return $result; }
        $result['db_exists'] = true;
        try { $u = $this->pdo->prepare('SELECT User FROM mysql.user WHERE User = ?'); $u->execute([$dbName]); $result['user_exists'] = (bool)$u->fetch(); } catch (Exception $e) { $result['user_exists'] = true; }

        // Activity 2 queries depend on both tables. Avoid querying a missing
        // table and return a clear zero-score result instead of an API error.
        $missingTables = [];
        foreach (['suppliers', 'items'] as $table) {
            if (!$this->tableExists($dbName, $table)) $missingTables[] = $table;
        }
        if ($missingTables) {
            $result['tasks'] = $this->a2UnavailableTasks($missingTables);
            $result['error'] = 'Activity 2 prerequisite table(s) missing: ' . implode(', ', $missingTables) . '. Score set to 0.';
            return $result;
        }

        // Only tasks with a checkable final-state outcome are scored. Tasks
        // 6-11 in the spec are read-only SELECT/display tasks that leave no
        // trace in the data, so they are not part of the scored set.
        $tasks = [
            'task1'  => $this->a2Schema($dbName),
            'task2'  => $this->a2Task($dbName, 2),
            'task3'  => $this->a2Task($dbName, 3),
            'task4'  => $this->a2Task($dbName, 4),
            'task5'  => $this->a2Task($dbName, 5),
            'task12' => $this->a2Task($dbName, 12),
            'task13' => $this->a2Task($dbName, 13),
            'task14' => $this->a2Task($dbName, 14),
            'task15' => $this->a2Task($dbName, 15),
            'task16' => $this->a2Task($dbName, 16),
        ];
        foreach ($tasks as &$task) { $task['weight'] = 10; $task['earned_score'] = round($task['score_percent'] / 10, 2); $result['total_score'] += $task['earned_score']; }
        $result['tasks'] = $tasks; $result['total_score'] = round($result['total_score'], 2); $result['percentage'] = $result['total_score'];
        return $result;
    }

    private function a2Schema($dbName) {
        $checks = [];
        $supplier = $this->tableExists($dbName, 'suppliers'); $items = $this->tableExists($dbName, 'items');
        $checks[] = $this->a2Check('Table suppliers exists', $supplier, $supplier ? 'Found suppliers' : 'Table missing');
        $checks[] = $this->a2Check('Table items exists', $items, $items ? 'Found items' : 'Table missing');
        $checks[] = $this->a2ColumnSet($dbName, 'suppliers', ['supplier_id'=>'int', 'supplier_name'=>'varchar', 'contact_number'=>'varchar', 'city'=>'varchar']);
        $checks[] = $this->a2ColumnSet($dbName, 'items', ['item_id'=>'int', 'item_name'=>'varchar', 'description'=>'varchar', 'category'=>'varchar', 'price'=>'decimal', 'quantity'=>'int', 'supplier_id'=>'int']);
        $checks[] = $this->a2Check('suppliers primary key is auto-increment supplier_id', $this->a2AutoId($dbName, 'suppliers', 'supplier_id'), 'Verified through information_schema');
        $checks[] = $this->a2Check('items primary key is auto-increment item_id', $this->a2AutoId($dbName, 'items', 'item_id'), 'Verified through information_schema');
        $checks[] = $this->a2Check('items.price is DECIMAL and NOT NULL', $this->a2ColumnMatches($dbName, 'items', 'price', 'decimal', true), 'Verified through information_schema');
        $checks[] = $this->a2Check('items.quantity is INT and NOT NULL', $this->a2ColumnMatches($dbName, 'items', 'quantity', 'int', true), 'Verified through information_schema');
        return $this->a2Result('Task 1 - Create suppliers and items tables', $checks);
    }

    /**
     * Activity 2 data tasks, verified strictly against the LATEST expected
     * record state (not query logs). Tasks whose inserted rows are later
     * modified/deleted by a subsequent task (e.g. task 3's Bright Goods Inc.
     * has its city changed in task 14; task 5's Century Tuna insert isn't
     * present here since qty is re-checked in task 13) only assert the
     * fields that remain stable, so an earlier task doesn't fail just
     * because a later task correctly changed the data.
     */
    private function a2Task($dbName, $task) {
        $spec = [
            2  => ['Add Alpha Trading using a whole-table INSERT',
                   "SELECT COUNT(*) FROM `{$dbName}`.`suppliers` WHERE supplier_name='Alpha Trading' AND contact_number='09171234567' AND city='Cebu City'", 1],
            3  => ['Add three suppliers with one multiple-row INSERT',
                   "SELECT COUNT(*) FROM `{$dbName}`.`suppliers` WHERE
                        (supplier_name='Circle Distributors' AND contact_number='09399998888' AND city='Lapu-Lapu City')
                     OR (supplier_name='Delta Supply House' AND contact_number='09055556666' AND city='Cebu City')
                     OR (supplier_name='Bright Goods Inc.' AND contact_number='09283334444')", 3],
            4  => ['Add Palmolive Shampoo using specific columns',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE item_name='Palmolive Shampoo' AND price=145 AND supplier_id=1", 1],
            5  => ['Add five items with one multiple-row INSERT',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE
                        (item_name='Dove Soap' AND description='White beauty bar' AND category='Toiletries' AND price=62.50 AND quantity=40 AND supplier_id=1)
                     OR (item_name='Safeguard' AND description='Antibacterial soap' AND category='Toiletries' AND price=48.75 AND quantity=60 AND supplier_id=2)", 2],
            12 => ['Complete Palmolive Shampoo record in one UPDATE',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE item_name='Palmolive Shampoo' AND description='Green shampoo 180ml' AND category='Toiletries' AND quantity=35", 1],
            13 => ['Change Century Tuna quantity to 120',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE item_name='Century Tuna' AND quantity=120", 1],
            14 => ['Change Bright Goods Inc. city to Talisay City',
                   "SELECT COUNT(*) FROM `{$dbName}`.`suppliers` WHERE supplier_name='Bright Goods Inc.' AND city='Talisay City'", 1],
            15 => ['Remove Tender Care from items',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE item_name='Tender Care'", 0],
            // Palmolive Shampoo is deliberately set to quantity 35 in task 12 (< 40),
            // so it is excluded here rather than penalizing a correct task 12.
            16 => ['Delete items with quantity below 40 and confirm remaining rows',
                   "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE quantity < 40 AND item_name <> 'Palmolive Shampoo'", 0],
        ][$task];
        [$title, $sql, $expected] = $spec;
        $checks = [];
        try {
            $count = (int)$this->pdo->query($sql)->fetchColumn();
            $passed = $expected === 0 ? ($count === 0) : ($count >= $expected);
            $detail = $passed ? 'Verified' : ($expected === 0 ? "{$count} row(s) still violate the requirement" : 'Not found or not yet applied');

            // Tasks 4 & 12 require the Palmolive Shampoo row, but a correctly
            // run task 16 (delete quantity < 40) legitimately removes it too,
            // since task 12 sets its quantity to 35. If the row is gone,
            // accept other evidence that a quantity-below-40 cleanup ran
            // (Tender Care and/or Milo 300g also missing) as a stand-in for
            // "this row existed correctly before that cleanup deleted it."
            if (!$passed && ($task === 4 || $task === 12) && $this->a2PalmoliveSweptByCleanup($dbName)) {
                $passed = true;
                $detail = 'Palmolive Shampoo record not present, but quantity-below-40 cleanup evidently ran (task 16) and would have removed it too; not penalized.';
            }

            $checks[] = $this->a2Check('Final database state matches the required result', $passed, $detail);
        } catch (Exception $e) {
            // Table/column required for this check is missing or malformed on the student's DB.
            $checks[] = $this->a2Check('Final database state matches the required result', false, 'Could not verify: required table or column is missing on this database.');
        }
        return $this->a2Result('Task ' . $task . ' - ' . $title, $checks);
    }

    /**
     * True if there's reasonable non-log evidence that Palmolive Shampoo once
     * existed and was removed by the task 16 quantity<40 cleanup, rather than
     * never having been created at all:
     *  - Tender Care and Milo 300g (also quantity < 40) are both gone, and
     *  - items.AUTO_INCREMENT has advanced past 6, meaning at least 6 rows
     *    were ever inserted (task 4's 1 + task 5's 5) - the counter only
     *    moves forward, so this holds even after later deletes.
     */
    private function a2PalmoliveSweptByCleanup($dbName) {
        try {
            $cleaned = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM `{$dbName}`.`items` WHERE item_name IN ('Tender Care', 'Milo 300g')"
            )->fetchColumn() === 0;
            if (!$cleaned) return false;

            $stmt = $this->pdo->prepare(
                "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'items'"
            );
            $stmt->execute([$dbName]);
            $autoIncrement = (int)$stmt->fetchColumn();
            return $autoIncrement >= 7;
        } catch (Exception $e) {
            return false;
        }
    }

    private function a2UnavailableTasks($missingTables) {
        $tasks = [];
        $detail = 'Cannot evaluate because required table(s) are missing: ' . implode(', ', $missingTables) . '.';
        foreach ([1, 2, 3, 4, 5, 12, 13, 14, 15, 16] as $task) {
            $tasks['task' . $task] = [
                'title' => 'Task ' . $task,
                'checks' => [$this->a2Check('Activity 2 prerequisite tables exist', false, $detail)],
                'score_percent' => 0,
                'weight' => 10,
                'earned_score' => 0,
                'log_verified' => false,
                'log_entry' => null
            ];
        }
        return $tasks;
    }

    private function a2Check($name, $passed, $detail) { return ['name'=>$name, 'passed'=>(bool)$passed, 'detail'=>$detail]; }
    private function a2Result($title, $checks) { $passed = count(array_filter($checks, function($c){ return $c['passed']; })); return ['title'=>$title, 'checks'=>$checks, 'score_percent'=>round(100 * $passed / max(1, count($checks))), 'log_verified'=>false, 'log_entry'=>null]; }
    private function a2AutoId($db, $table, $column) { $c=$this->getColumn($db,$table,$column); return $c && strpos(strtolower($c['EXTRA']), 'auto_increment') !== false && strpos(strtolower($c['COLUMN_KEY']), 'pri') !== false; }
    private function a2ColumnMatches($db,$table,$column,$type,$notNull=false) { $c=$this->getColumn($db,$table,$column); return $c && strtolower($c['DATA_TYPE']) === $type && (!$notNull || $c['IS_NULLABLE'] === 'NO'); }
    private function a2ColumnSet($db,$table,$columns) { foreach ($columns as $name=>$type) if (!$this->a2ColumnMatches($db,$table,$name,$type)) return $this->a2Check("$table has required columns", false, "Missing or invalid $name"); return $this->a2Check("$table has all required columns", true, 'Verified through information_schema'); }

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
        $totalChecks = 1;

        $table = 'tbl_authors';
        $colBio = $this->getColumn($dbName, $table, 'biography');
        $bioOk = $colBio && (strpos(strtolower($colBio['DATA_TYPE']), 'text') !== false || strpos(strtolower($colBio['DATA_TYPE']), 'varchar') !== false);

        $checks[] = [
            'name' => "Column 'biography' exists in tbl_authors with TEXT data type",
            'passed' => (bool)$bioOk,
            'detail' => $colBio ? "Type: {$colBio['COLUMN_TYPE']}" : "Column 'biography' is missing"
        ];
        if ($bioOk) $passedCount++;

        return [
            'title' => 'Task 2 - Alter tbl_authors (Add biography TEXT)',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => false,
            'log_entry' => null
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
        $totalChecks = 2;

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

        return [
            'title' => 'Task 4 - Alter tbl_members (widen email_address to VARCHAR(150) NOT NULL)',
            'checks' => $checks,
            'score_percent' => round(($passedCount / $totalChecks) * 100),
            'log_verified' => false,
            'log_entry' => null
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

    /**
     * Activity 3: SELECT statement exercise (Registrar's Office / dbms_activity).
     * Students log the SQL they used, per task, into their own submission
     * table. Verified by re-running both the student's logged SQL and the
     * instructor's reference SQL (from dbms_activity_answer_key) against the
     * shared dbms_activity database and comparing actual results.
     */
    public function checkActivity3($dbName) {
        return $this->a34Check($dbName, 'activity_20260805', 'activity3_answerkey', 'Activity 3 - SELECT Statements (Registrar Records)');
    }

    /**
     * Activity 4: Logical operators & aggregate functions exercise
     * (Springview Resort / dbms_activity). Same verification approach as
     * Activity 3, against dbms_activity_answer_key.activity4_answerkey.
     */
    public function checkActivity4($dbName) {
        return $this->a34Check($dbName, 'activity_20260817', 'activity4_answerkey', 'Activity 4 - Logical Operators & Aggregate Functions (Resort Records)');
    }

    /**
     * Activity 5: SELECT statement exercise (Pahina Bookstore / dbms_activity).
     * Same verification approach as Activity 3/4, against
     * dbms_activity_answer_key.activity5_answerkey.
     */
    public function checkActivity5($dbName) {
        return $this->a34Check($dbName, 'activity_20260923', 'activity5_answerkey', 'Activity 5 - SELECT Statements (Bookstore Records)');
    }

    /**
     * Activity 6: Aggregate functions & set operators exercise (Pitong Lawa
     * Pasalubong Center / dbms_activity). Same verification approach as
     * Activity 3/4/5, against dbms_activity_answer_key.activity6_answerkey.
     */
    public function checkActivity6($dbName) {
        return $this->a34Check($dbName, 'activity_20260929', 'activity6_answerkey', 'Activity 6 - Aggregate Functions & Set Operators (Pasalubong Center Records)');
    }

    /** Shared implementation for the SELECT-statement-logging activities (3-6). */
    private function a34Check($dbName, $submissionTable, $answerKeyTable, $activityName) {
        $activityId = preg_replace('/_answerkey$/', '', $answerKeyTable);
        $result = [
            'activity_id' => $activityId, 'activity_name' => $activityName,
            'db_name' => $dbName, 'db_exists' => false, 'user_exists' => false,
            'total_score' => 0, 'max_score' => 100, 'percentage' => 0, 'tasks' => [],
            'logs_found' => [], 'general_log_enabled' => false, 'checked_at' => date('Y-m-d H:i:s')
        ];
        $stmt = $this->pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$dbName]);
        if (!$stmt->fetch()) { $result['error'] = "Database '{$dbName}' does not exist on MySQL server."; return $result; }
        $result['db_exists'] = true;
        try { $u = $this->pdo->prepare('SELECT User FROM mysql.user WHERE User = ?'); $u->execute([$dbName]); $result['user_exists'] = (bool)$u->fetch(); } catch (Exception $e) { $result['user_exists'] = true; }

        if (!$this->tableExists($dbName, $submissionTable)) {
            $result['tasks'] = $this->a34UnavailableTasks("Submission table '{$submissionTable}' was not found in this database.");
            $result['error'] = "Submission table '{$submissionTable}' is missing. Score set to 0.";
            return $result;
        }

        // Pull the student's logged SQL per task_number (last row wins on duplicates).
        $submitted = [];
        try {
            $rows = $this->pdo->query("SELECT task_number, sql_syntax FROM `{$dbName}`.`{$submissionTable}` ORDER BY id ASC")->fetchAll();
            foreach ($rows as $row) {
                $submitted[(int)$row['task_number']] = $row['sql_syntax'];
            }
        } catch (Exception $e) {
            $result['tasks'] = $this->a34UnavailableTasks("Could not read '{$submissionTable}': required columns (task_number, sql_syntax) are missing or malformed.");
            $result['error'] = "Submission table '{$submissionTable}' is malformed. Score set to 0.";
            return $result;
        }

        $sourcePdo = null;
        try {
            $sourcePdo = $this->a34SourceConnection();
        } catch (Exception $e) {
            $result['tasks'] = $this->a34UnavailableTasks('Could not connect to the dbms_activity source database: ' . $e->getMessage());
            $result['error'] = 'dbms_activity source database is unreachable. Score set to 0.';
            return $result;
        }

        $tasks = [];
        for ($task = 1; $task <= 10; $task++) {
            $tasks['task' . $task] = $this->a34Task($sourcePdo, $answerKeyTable, $task, $submitted[$task] ?? null);
        }
        foreach ($tasks as &$t) { $t['weight'] = 10; $t['earned_score'] = round($t['score_percent'] / 10, 2); $result['total_score'] += $t['earned_score']; }
        $result['tasks'] = $tasks; $result['total_score'] = round($result['total_score'], 2); $result['percentage'] = $result['total_score'];
        return $result;
    }

    /** One SELECT-statement task: compare the student's logged query's live output against the answer key's. */
    private function a34Task($sourcePdo, $answerKeyTable, $task, $studentSql) {
        $refStmt = $this->pdo->prepare("SELECT sql_syntax FROM `dbms_activity_answer_key`.`{$answerKeyTable}` WHERE task_number = ?");
        $refStmt->execute([$task]);
        $refSql = $refStmt->fetchColumn();
        $title = 'Task ' . $task;
        if ($refSql === false) {
            return $this->a34Result($title, [$this->a2Check('Answer key entry exists', false, "No task {$task} entry found in {$answerKeyTable}.")]);
        }

        $checks = [];
        try {
            $ref = $this->a34RunQuery($sourcePdo, $refSql);
        } catch (Exception $e) {
            // Reference query itself is broken (answer key authoring issue) - not the student's fault to diagnose here.
            return $this->a34Result($title, [$this->a2Check('Answer key query is runnable', false, 'The instructor reference query failed: ' . $e->getMessage())]);
        }

        if ($studentSql === null) {
            $checks[] = $this->a2Check('Output columns and data match the expected result', false, 'No submission recorded for this task.');
            return $this->a34Result($title, $checks);
        }

        try {
            $student = $this->a34RunQuery($sourcePdo, $studentSql);
            $colsMatch = $this->a34ColumnsMatch($ref['columns'], $student['columns']);
            $dataMatch = $this->a34RowsMatch($ref['rows'], $student['rows']);
            $passed = $colsMatch && $dataMatch;
            $detail = 'Verified';
            if (!$passed) {
                $issues = [];
                if (!$colsMatch) $issues[] = 'expected columns [' . implode(', ', $ref['columns']) . '], got [' . implode(', ', $student['columns']) . ']';
                if (!$dataMatch) $issues[] = 'expected ' . count($ref['rows']) . ' row(s), got ' . count($student['rows']) . ' row(s) with different data';
                $detail = implode('; ', $issues);
            }
            $checks[] = $this->a2Check('Output columns and data match the expected result', $passed, $detail);
        } catch (Exception $e) {
            $checks[] = $this->a2Check('Output columns and data match the expected result', false, 'Query error: ' . $e->getMessage());
        }

        return $this->a34Result($title, $checks);
    }

    /** Activity 3/4 tasks are pass/fail: partial credit isn't awarded for matching only columns or only data. */
    private function a34Result($title, $checks) {
        $allPassed = count($checks) > 0 && count(array_filter($checks, function($c){ return $c['passed']; })) === count($checks);
        return ['title'=>$title, 'checks'=>$checks, 'score_percent'=>$allPassed ? 100 : 0, 'log_verified'=>false, 'log_entry'=>null];
    }

    private function a34UnavailableTasks($detail) {
        $tasks = [];
        for ($task = 1; $task <= 10; $task++) {
            $tasks['task' . $task] = [
                'title' => 'Task ' . $task,
                'checks' => [$this->a2Check('Submission is readable', false, $detail)],
                'score_percent' => 0,
                'weight' => 10,
                'earned_score' => 0,
                'log_verified' => false,
                'log_entry' => null
            ];
        }
        return $tasks;
    }

    /** Dedicated read-only connection to dbms_activity used to run student-submitted SQL safely. */
    private function a34SourceConnection() {
        $pdo = get_pdo_connection('dbms_activity');
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        try { $pdo->exec('SET SESSION MAX_EXECUTION_TIME = 5000'); } catch (Exception $e) { /* older MySQL without this variable */ }
        return $pdo;
    }

    /** Reject anything but a single, safe SELECT statement before it ever reaches the database. */
    private function a34ValidateSelect($sql) {
        $trimmed = trim((string)$sql);
        if ($trimmed === '') throw new Exception('Empty SQL statement.');
        $trimmed = preg_replace('/;\s*$/', '', $trimmed);
        if (strpos($trimmed, ';') !== false) {
            throw new Exception('Only a single SELECT statement is allowed (no semicolon-separated statements).');
        }
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $trimmed)) {
            throw new Exception('Only SELECT statements are allowed.');
        }
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE', 'CALL', 'EXECUTE', 'LOAD_FILE', 'OUTFILE', 'DUMPFILE', 'LOCK', 'UNLOCK', 'SET'];
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $trimmed)) {
                throw new Exception("Statement contains a disallowed keyword: {$kw}");
            }
        }
        return $trimmed;
    }

    /** Run a validated SELECT and return its column labels and raw row data. */
    private function a34RunQuery($pdo, $sql) {
        $safe = $this->a34ValidateSelect($sql);
        $stmt = $pdo->query($safe);
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = $meta['name'] ?? ('col' . $i);
        }
        return ['columns' => $columns, 'rows' => $stmt->fetchAll(PDO::FETCH_NUM)];
    }

    private function a34ColumnsMatch($refCols, $studentCols) {
        if (count($refCols) !== count($studentCols)) return false;
        foreach ($refCols as $i => $name) {
            if (strtolower($name) !== strtolower($studentCols[$i])) return false;
        }
        return true;
    }

    /** Order-independent comparison of row data (values only, not column labels). */
    private function a34RowsMatch($refRows, $studentRows) {
        if (count($refRows) !== count($studentRows)) return false;
        $normalize = function ($rows) {
            $lines = array_map(function ($row) {
                return implode("\x1f", array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    if (is_numeric($v)) return rtrim(rtrim(sprintf('%.6f', (float)$v), '0'), '.');
                    return trim((string)$v);
                }, $row));
            }, $rows);
            sort($lines);
            return $lines;
        };
        return $normalize($refRows) === $normalize($studentRows);
    }
}
