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
    echo json_encode(['success' => false, 'message' => 'Nota fiscal inválida.']);
    exit;
}

$stmt = $mysql->prepare("UPDATE notas_fiscais SET status = 'Cancelada' WHERE id = ? AND status != 'Cancelada'");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Nota fiscal não encontrada ou já cancelada.']);
    exit;
}

$motivo = isset($dados['motivo']) ? trim($dados['motivo']) : null;
registrar_log($mysql, 'cancelar_nf', 'notas_fiscais', $id, $motivo);
echo json_encode(['success' => true]);
