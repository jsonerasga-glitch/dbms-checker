<?php
/**
 * DBMS Student Activity Checker Configuration
 */

define('CONFIG_FILE', __DIR__ . '/config.json');

function get_config() {
    $defaults = [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_user' => 'root',
        'db_pass' => '',
        'log_check_enabled' => true,
        'log_date_enabled' => false,
        'log_date_start' => date('Y-m-d'),
        'log_date_end' => date('Y-m-d'),
        'section_filter' => '2_cs4', // Default prefix filter if any, or empty for all
        'score_weights' => [
            'task1' => 15, // tbl_authors
            'task2' => 15, // alter tbl_authors
            'task3' => 15, // tbl_members
            'task4' => 15, // alter tbl_members
            'task5' => 20, // tbl_books
            'task6' => 20, // tbl_borrow_transactions
        ]
    ];

    if (file_exists(CONFIG_FILE)) {
        $saved = json_decode(file_get_contents(CONFIG_FILE), true);
        if (is_array($saved)) {
            return array_merge($defaults, $saved);
        }
    }
    return $defaults;
}

function save_config($data) {
    $current = get_config();
    $updated = array_merge($current, $data);
    file_put_contents(CONFIG_FILE, json_encode($updated, JSON_PRETTY_PRINT));
    return $updated;
}

function get_pdo_connection($db_name = null) {
    $cfg = get_config();
    $dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};charset=utf8mb4";
    if ($db_name) {
        $dsn .= ";dbname={$db_name}";
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ];

    try {
        return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);
    } catch (PDOException $e) {
        throw new Exception("MySQL Connection Error: " . $e->getMessage());
    }
}
