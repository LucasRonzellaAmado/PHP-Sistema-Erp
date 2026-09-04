<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

$id_cliente = isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;

if ($id_cliente <= 0) {
    echo json_encode(['saldo' => 0, 'limite' => 0]);
    exit;
}

$stmt = $mysql->prepare("SELECT COALESCE(SUM(valor), 0) as saldo FROM contas_receber WHERE id_cliente = ? AND status = 'Pendente'");
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$saldo = (float)$stmt->get_result()->fetch_assoc()['saldo'];

$stmt_c = $mysql->prepare("SELECT limite_credito, validar_limite FROM clientes WHERE id = ?");
$stmt_c->bind_param("i", $id_cliente);
$stmt_c->execute();
$cliente = $stmt_c->get_result()->fetch_assoc();

echo json_encode([
    'saldo' => $saldo,
    'limite' => $cliente ? (float)$cliente['limite_credito'] : 0,
    'validar_limite' => $cliente ? (int)$cliente['validar_limite'] : 0,
]);
