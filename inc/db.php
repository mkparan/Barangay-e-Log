<?php
// inc/db.php
require_once __DIR__ . '/config.php';

function db_connect() {
    static $mysqli;
    if (!isset($mysqli)) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            die('DB Connect Error: ' . $mysqli->connect_error);
        }
        $mysqli->set_charset('utf8mb4');
    }
    return $mysqli;
}
