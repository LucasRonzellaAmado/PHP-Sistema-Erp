<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    echo json_encode(['saldo' => 0, 'limite' => 0]);
    exit;
}

$id_cliente = isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;

if ($id_cliente <= 0) {
    echo json_encode(['saldo' => 0, 'limite' => 0]);
    exit;
}

$stmt = $mysql->prepare("SELECT COALESCE(SUM(valor), 0) as saldo FROM contas_receber WHERE id_cliente = ? AND status = 'Pendente' AND empresa_id = ?");
$stmt->bind_param("ii", $id_cliente, $_SESSION['empresa_id']);
$stmt->execute();
$saldo = (float)$stmt->get_result()->fetch_assoc()['saldo'];

$stmt_c = $mysql->prepare("SELECT limite_credito, validar_limite FROM clientes WHERE id = ? AND empresa_id = ?");
$stmt_c->bind_param("ii", $id_cliente, $_SESSION['empresa_id']);
$stmt_c->execute();
$cliente = $stmt_c->get_result()->fetch_assoc();

echo json_encode([
    'saldo' => $saldo,
    'limite' => $cliente ? (float)$cliente['limite_credito'] : 0,
    'validar_limite' => $cliente ? (int)$cliente['validar_limite'] : 0,
]);
