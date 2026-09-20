<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'caixa', 'admin'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão para esta ação']);
    exit;
}

$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

if ($codigo === '') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Código não informado']);
    exit;
}

$stmt = $mysql->prepare("SELECT id, nome, CASE WHEN preco_venda > 0 THEN preco_venda WHEN preco > 0 THEN preco ELSE 0 END as preco_venda, quantidade FROM estoque WHERE codigo_barras = ? AND status = 'ATIVO' LIMIT 1");
$stmt->bind_param("s", $codigo);
$stmt->execute();
$res = $stmt->get_result();
$produto = $res->fetch_assoc();

if ($produto) {
    echo json_encode([
        'sucesso' => true,
        'produto' => [
            'id' => $produto['id'],
            'nome' => $produto['nome'],
            'preco' => $produto['preco_venda'],
            'quantidade_estoque' => $produto['quantidade']
        ]
    ]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado para este código de barras']);
}
