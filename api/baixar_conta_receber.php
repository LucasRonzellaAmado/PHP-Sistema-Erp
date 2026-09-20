<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

$dados = json_decode(file_get_contents('php://input'), true);
$id = isset($dados['id']) ? intval($dados['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Conta inválida.']);
    exit;
}

$stmt = $mysql->prepare("UPDATE contas_receber SET status = 'Recebido', data_recebimento = NOW() WHERE id = ? AND status = 'Pendente'");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Conta não encontrada ou já baixada.']);
    exit;
}

registrar_log($mysql, 'baixar_conta_receber', 'contas_receber', $id);
echo json_encode(['success' => true]);
