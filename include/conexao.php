<?php
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    die('Arquivo de configuração .env não encontrado. Copie .env.example para .env e preencha os dados de conexão.');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

$host = $_ENV['DB_HOST'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$db   = $_ENV['DB_NAME'];
$port = isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 3306;

$mysql = new mysqli($host, $user, $pass, $db, $port);

if ($mysql->connect_error) {
    die("Falha na conexão: " . $mysql->connect_error);
}

$mysql->set_charset('utf8mb4');
?>