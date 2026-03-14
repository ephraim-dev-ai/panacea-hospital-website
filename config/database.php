<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'panacea_hospital');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',  'http://localhost/panacea');
define('ADMIN_URL', 'http://localhost/panacea/admin');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="color:red;font-family:monospace;padding:20px">DB Error: '.$e->getMessage().'</div>');
        }
    }
    return $pdo;
}
