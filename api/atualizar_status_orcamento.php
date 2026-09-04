<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

$dados = json_decode(file_get_contents('php://input'), true);
$id = isset($dados['id']) ? intval($dados['id']) : 0;
$novo_status = $dados['status'] ?? '';

if ($id <= 0 || !in_array($novo_status, ['Aprovado', 'Cancelado'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$stmt = $mysql->prepare("UPDATE orcamentos SET status = ? WHERE id = ? AND status = 'Pendente'");
$stmt->bind_param("si", $novo_status, $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Orçamento não encontrado ou já finalizado.']);
    exit;
}

registrar_log($mysql, strtolower($novo_status) === 'aprovado' ? 'aprovar_orcamento' : 'cancelar_orcamento', 'orcamentos', $id);
echo json_encode(['success' => true]);
