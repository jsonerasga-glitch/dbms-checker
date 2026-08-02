<?php
/**
 * DBMS Activity Checker API Router
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Checker.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_config':
            echo json_encode(['success' => true, 'config' => get_config()]);
            break;

        case 'save_config':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $updated = save_config($input);
            echo json_encode(['success' => true, 'config' => $updated, 'message' => 'Configuration saved successfully.']);
            break;

        case 'test_connection':
            $pdo = get_pdo_connection();
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            
            // Check general_log status
            $genLog = $pdo->query("SHOW VARIABLES LIKE 'general_log'")->fetch();
            $logOutput = $pdo->query("SHOW VARIABLES LIKE 'log_output'")->fetch();

            echo json_encode([
                'success' => true,
                'mysql_version' => $version,
                'general_log' => $genLog['Value'] ?? 'OFF',
                'log_output' => $logOutput['Value'] ?? 'NONE',
                'message' => 'Connected to MySQL Server successfully!'
            ]);
            break;

        case 'enable_general_log':
            $pdo = get_pdo_connection();
            $pdo->exec("SET GLOBAL log_output = 'TABLE'");
            $pdo->exec("SET GLOBAL general_log = 'ON'");
            
            echo json_encode([
                'success' => true,
                'message' => 'MySQL General Log has been enabled and set to TABLE output.'
            ]);
            break;

        case 'discover_databases':
            $pdo = get_pdo_connection();
            $stmt = $pdo->query("SHOW DATABASES");
            $allDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $cfg = get_config();
            $filter = strtolower(trim($cfg['section_filter'] ?? ''));

            // System databases to exclude
            $sysDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];

            $studentDbs = [];
            foreach ($allDbs as $db) {
                if (in_array(strtolower($db), $sysDbs)) continue;
                
                // If filter specified, match against it or match pattern section_lastname (e.g. 2_cs4_delacruz)
                if ($filter && strpos(strtolower($db), $filter) !== 0 && !preg_match('/^[0-9]+_[a-zA-Z0-9]+_[a-zA-Z0-9_]+$/', $db)) {
                    // Still include if matches standard section_lastname format
                    if (!preg_match('/^[0-9A-Za-z]+_[0-9A-Za-z]+_[0-9A-Za-z_]+$/', $db)) continue;
                }

                // Extract section and student name if pattern matches
                $parts = explode('_', $db, 3);
                $section = count($parts) >= 2 ? $parts[0] . '_' . $parts[1] : 'Default';
                $lastname = count($parts) >= 3 ? $parts[2] : $db;

                $studentDbs[] = [
                    'db_name' => $db,
                    'section' => $section,
                    'lastname' => ucfirst($lastname),
                ];
            }

            echo json_encode([
                'success' => true,
                'databases' => $studentDbs,
                'total' => count($studentDbs)
            ]);
            break;

        case 'check_student':
            $dbName = trim($_GET['db_name'] ?? $_POST['db_name'] ?? '');
            $activityId = trim($_GET['activity_id'] ?? $_POST['activity_id'] ?? 'activity1');
            if (!in_array($activityId, ['activity1', 'activity2'], true)) {
                throw new Exception("Unknown activity '{$activityId}'.");
            }
            if (!$dbName) {
                throw new Exception("Database name parameter 'db_name' is required.");
            }

            $pdo = get_pdo_connection();
            $cfg = get_config();
            $checker = new ActivityChecker($pdo, $cfg);
            $result = $checker->checkActivity($activityId, $dbName);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        case 'batch_check':
            $pdo = get_pdo_connection();
            $cfg = get_config();
            $checker = new ActivityChecker($pdo, $cfg);
            $activityId = trim($_POST['activity_id'] ?? $_GET['activity_id'] ?? 'activity1');
            if (!in_array($activityId, ['activity1', 'activity2'], true)) {
                throw new Exception("Unknown activity '{$activityId}'.");
            }

            $dbsParam = $_POST['databases'] ?? $_GET['databases'] ?? [];
            if (is_string($dbsParam)) {
                $dbsParam = json_decode($dbsParam, true) ?? explode(',', $dbsParam);
            }

            if (empty($dbsParam)) {
                // Auto-discover if not provided
                $stmt = $pdo->query("SHOW DATABASES");
                $allDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $sysDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
                foreach ($allDbs as $db) {
                    if (!in_array(strtolower($db), $sysDbs)) {
                        $dbsParam[] = $db;
                    }
                }
            }

            $results = [];
            foreach ($dbsParam as $db) {
                $db = trim($db);
                if ($db) {
                    $results[] = $checker->checkActivity($activityId, $db);
                }
            }

            echo json_encode([
                'success' => true,
                'count' => count($results),
                'results' => $results
            ]);
            break;

        case 'student_login':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $username = trim($input['username'] ?? '');
            $password = (string)($input['password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $username) || $password === '') {
                throw new Exception('Enter a valid MySQL username and password.');
            }
            try {
                $studentPdo = get_student_pdo_connection($username, $password);
                $databases = $studentPdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                throw new Exception('Invalid MySQL username or password.');
            }
            $studentDb = null;
            foreach ($databases as $database) {
                if (strcasecmp($database, $username) === 0) { $studentDb = $database; break; }
            }
            if (!$studentDb) {
                throw new Exception('Your MySQL user must have access to a database with the same name as your username.');
            }
            session_regenerate_id(true);
            $_SESSION['student_username'] = $username;
            $_SESSION['student_db'] = $studentDb;
            echo json_encode(['success' => true, 'username' => $username, 'database' => $studentDb]);
            break;

        case 'student_status':
            if (empty($_SESSION['student_db'])) {
                http_response_code(401);
                throw new Exception('Please log in as a student first.');
            }
            $activityId = trim($_GET['activity_id'] ?? $_POST['activity_id'] ?? 'activity1');
            if (!in_array($activityId, ['activity1', 'activity2'], true)) throw new Exception("Unknown activity '{$activityId}'.");
            $checker = new ActivityChecker(get_pdo_connection(), get_config());
            echo json_encode(['success' => true, 'data' => $checker->checkActivity($activityId, $_SESSION['student_db'])]);
            break;

        case 'student_logout':
            $_SESSION = [];
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        case 'view_logs':
            $pdo = get_pdo_connection();
            $cfg = get_config();
            $dbName = trim($_GET['db_name'] ?? $_POST['db_name'] ?? '');
            $startDate = trim($_GET['start_date'] ?? $_POST['start_date'] ?? '');
            $endDate = trim($_GET['end_date'] ?? $_POST['end_date'] ?? '');
            
            $sql = "SELECT event_time, user_host, argument FROM mysql.general_log WHERE command_type IN ('Query', 'Execute')";
            $params = [];

            if ($dbName) {
                $sql .= " AND (argument LIKE ? OR user_host LIKE ?)";
                $params[] = "%{$dbName}%";
                $params[] = "%{$dbName}%";
            }

            if ($startDate) {
                $sql .= " AND event_time >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if ($endDate) {
                $sql .= " AND event_time <= ?";
                $params[] = $endDate . ' 23:59:59';
            }

            $sql .= " ORDER BY event_time DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;

        case 'create_demo_student':
            // Helper tool to populate demo student DBs for testing
            $pdo = get_pdo_connection();
            $mode = $_GET['mode'] ?? 'full'; // 'full', 'no_alter', 'no_fk'
            $demoDb = $_GET['db_name'] ?? '2_cs4_delacruz';

            $pdo->exec("DROP DATABASE IF EXISTS `{$demoDb}`");
            $pdo->exec("CREATE DATABASE `{$demoDb}`");
            $pdo->exec("USE `{$demoDb}`");

            // Also enable general log if creating demo
            try {
                $pdo->exec("SET GLOBAL log_output = 'TABLE'");
                $pdo->exec("SET GLOBAL general_log = 'ON'");
            } catch (Exception $e) {}

            if ($mode === 'full') {
                // Task 1: Create tbl_authors
                $pdo->exec("CREATE TABLE tbl_authors (
                    author_id INT PRIMARY KEY AUTO_INCREMENT,
                    author_name VARCHAR(100) NOT NULL,
                    nationality VARCHAR(50),
                    birth_year SMALLINT
                )");
                // Task 2: Alter tbl_authors
                $pdo->exec("ALTER TABLE tbl_authors ADD COLUMN biography TEXT");

                // Task 3: Create tbl_members
                $pdo->exec("CREATE TABLE tbl_members (
                    member_id INT PRIMARY KEY AUTO_INCREMENT,
                    last_name VARCHAR(50) NOT NULL,
                    first_name VARCHAR(50) NOT NULL,
                    email_address VARCHAR(100),
                    date_joined DATE
                )");
                // Task 4: Alter tbl_members
                $pdo->exec("ALTER TABLE tbl_members MODIFY COLUMN email_address VARCHAR(150) NOT NULL");

                // Task 5: Create tbl_books
                $pdo->exec("CREATE TABLE tbl_books (
                    book_id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(150) NOT NULL,
                    author_id INT NOT NULL,
                    isbn VARCHAR(20),
                    copies_owned INT NOT NULL,
                    book_format ENUM('hardcover', 'paperback', 'ebook', 'audiobook') NOT NULL,
                    FOREIGN KEY (author_id) REFERENCES tbl_authors(author_id)
                )");

                // Task 6: Create tbl_borrow_transactions
                $pdo->exec("CREATE TABLE tbl_borrow_transactions (
                    borrow_id INT PRIMARY KEY AUTO_INCREMENT,
                    book_id INT NOT NULL,
                    member_id INT NOT NULL,
                    date_borrowed DATE NOT NULL,
                    date_returned DATE,
                    fine_amount DECIMAL(6,2),
                    FOREIGN KEY (book_id) REFERENCES tbl_books(book_id),
                    FOREIGN KEY (member_id) REFERENCES tbl_members(member_id)
                )");
            } else if ($mode === 'no_alter') {
                // Student imported directly without alter commands
                $pdo->exec("CREATE TABLE tbl_authors (
                    author_id INT PRIMARY KEY AUTO_INCREMENT,
                    author_name VARCHAR(100) NOT NULL,
                    nationality VARCHAR(50),
                    birth_year SMALLINT
                )");
                $pdo->exec("CREATE TABLE tbl_members (
                    member_id INT PRIMARY KEY AUTO_INCREMENT,
                    last_name VARCHAR(50) NOT NULL,
                    first_name VARCHAR(50) NOT NULL,
                    email_address VARCHAR(100),
                    date_joined DATE
                )");
            }

            // Create student user if possible
            try {
                $pdo->exec("CREATE USER IF NOT EXISTS '{$demoDb}'@'localhost' IDENTIFIED BY 'password123'");
                $pdo->exec("GRANT ALL PRIVILEGES ON `{$demoDb}`.* TO '{$demoDb}'@'localhost'");
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'message' => "Demo student database '{$demoDb}' created in '{$mode}' mode."
            ]);
            break;

        default:
            throw new Exception("Unknown action '{$action}'");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
