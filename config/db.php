<?php
// dash/tours/config/db.php

// Caminho absoluto para a config do Skymetrics
$caminhoConfig = '/var/www/kafly_user/data/www/config_db.php';

if (!file_exists($caminhoConfig)) {
    die("ERRO CRÍTICO: Não encontrei config_db.php em: $caminhoConfig");
}

require_once $caminhoConfig;

// === MUDANÇA IMPORTANTE AQUI ===
// Agora usamos DB_VOOS_NAME (kafly_tracker) em vez de DB_PILOTOS_NAME
$dbName = defined('DB_VOOS_NAME') ? DB_VOOS_NAME : 'kafly_tracker';

$host   = defined('DB_SERVERNAME') ? DB_SERVERNAME : 'localhost';
// O usuário e senha geralmente são os mesmos para ambos os bancos no seu config
$user   = defined('DB_VOOS_USER') ? DB_VOOS_USER : DB_PILOTOS_USER;
$pass   = defined('DB_VOOS_PASS') ? DB_VOOS_PASS : DB_PILOTOS_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro ao conectar no banco de dados de VOOS: " . $e->getMessage());
}
?>