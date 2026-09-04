<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $mysql->prepare("SELECT id, nome, preco_custo, quantidade FROM estoque WHERE id_fornecedor = ? AND status = 'ATIVO'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $produtos = [];
    while($p = $res->fetch_assoc()) {
        $produtos[] = [
            'id' => $p['id'],
            'nome' => $p['nome'],
            'preco_custo' => $p['preco_custo'],
            'quantidade' => $p['quantidade']
        ];
    }
    
    echo json_encode($produtos);
} else {
    echo json_encode([]);
}