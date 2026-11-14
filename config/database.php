<?php
declare(strict_types=1);

/**
 * Konfigurasi Koneksi Database (PDO) & Kunci Rahasia Admin
 * Mendukung environment lokal (XAMPP/Laragon) maupun cloud hosting (InfinityFree/Railway).
 */

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = ($httpHost === 'localhost' || $httpHost === '127.0.0.1' || str_starts_with($httpHost, 'localhost:') || str_starts_with($httpHost, '127.0.0.1:'));

if ($isLocalhost) {
    // Konfigurasi Localhost (XAMPP / Laragon)
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: '24424020');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
} else {
    // Konfigurasi Cloud Hosting (InfinityFree)
    define('DB_HOST', getenv('DB_HOST') ?: 'sql111.infinityfree.com');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_43434224_vocab');
    define('DB_USER', getenv('DB_USER') ?: 'if0_43434224');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '1yrApxObC0vDfK');
}
define('DB_CHARSET', 'utf8mb4');

define('ADMIN_SECRET_KEY', 'cukurukuk666');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$dbConnectionError = null;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    $dbConnectionError = 'Gagal terhubung ke database MySQL (' . DB_NAME . ' pada host ' . DB_HOST . '). '
        . 'Pastikan layanan MySQL di XAMPP/Laragon sudah aktif dan file database.sql telah diimpor.';
}

