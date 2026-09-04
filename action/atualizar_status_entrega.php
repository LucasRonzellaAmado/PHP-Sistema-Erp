<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin', 'caixa', 'vendedor'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

// Recebe os dados do fetch (JSON)
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || !isset($dados['id_venda'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
    exit;
}

$id_venda = intval($dados['id_venda']);
$novo_status = in_array($dados['status'] ?? '', ['Pendente', 'Em Rota', 'Entregue'], true) ? $dados['status'] : null;
$entregador = isset($dados['entregador']) ? trim($dados['entregador']) : null;

if (!$novo_status) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Status inválido']);
    exit;
}

try {
    $mysql->begin_transaction();

    $stmt_v = $mysql->prepare("UPDATE vendas SET status_entrega = ? WHERE id = ?");
    $stmt_v->bind_param("si", $novo_status, $id_venda);
    $stmt_v->execute();

    if ($entregador) {
        $stmt_e = $mysql->prepare("UPDATE venda_entregas SET entregador = ? WHERE id_venda = ?");
        $stmt_e->bind_param("si", $entregador, $id_venda);
        $stmt_e->execute();
    }

    $mysql->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    $mysql->rollback();
    error_log("atualizar_status_entrega.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar status.']);
}