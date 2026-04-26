<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'MySQL-8.4');
define('DB_NAME', 'cofeshop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_NAME', 'Информационная система для управления кафе');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');
define('ROOT_PATH', dirname(__FILE__));
date_default_timezone_set('Europe/Moscow');
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    return $db;
}
$db = getDB();
?>