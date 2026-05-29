<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_queue');
define('DB_USER', 'root');
define('DB_PASS', '');          // <-- your MySQL password

define('GEMINI_API_KEY', 'AIzaSyDGs68jN2_1T9r7l1SvAEQ2rS96R5V5Fz0');   // <-- replace with your key
define('GEMINI_ENDPOINT',
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY
);

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            json_response(['error' => 'Database connection failed', 'detail' => $e->getMessage()], 500);
        }
    }
    return $pdo;
}

function json_response(array $data, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data);
    exit;
}
