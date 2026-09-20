<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'caixa', 'admin'])) {
    echo json_encode(['results' => []]);
    exit;
}

$termo = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($termo === '') {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $termo . '%';
$prefixo = $termo . '%';

$stmt = $mysql->prepare(
    "SELECT id, nome,
        CASE WHEN preco_venda > 0 THEN preco_venda WHEN preco > 0 THEN preco ELSE 0 END as preco_venda,
        quantidade
     FROM estoque
     WHERE status = 'ATIVO' AND empresa_id = ?
       AND (nome LIKE ? OR CAST(id AS CHAR) LIKE ? OR codigo_barras LIKE ? OR codigo_produto LIKE ?)
     ORDER BY nome ASC
     LIMIT 20"
);
$stmt->bind_param("issss", $_SESSION['empresa_id'], $like, $prefixo, $prefixo, $prefixo);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($p = $res->fetch_assoc()) {
    $preco = (float)$p['preco_venda'];
    $results[] = [
        'id' => (int)$p['id'],
        'text' => $p['id'] . ' - ' . $p['nome'] . ' (R$ ' . number_format($preco, 2, ',', '.') . ')',
        'nome' => $p['nome'],
        'preco' => $preco,
        'quantidade' => (int)$p['quantidade'],
    ];
}

echo json_encode(['results' => $results]);
