<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

if ($codigo === '') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Código não informado']);
    exit;
}

$stmt = $mysql->prepare("SELECT id, nome, preco_venda, quantidade FROM estoque WHERE codigo_barras = ? AND status = 'ATIVO' LIMIT 1");
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
