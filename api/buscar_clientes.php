<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'caixa', 'admin'])) {
    echo json_encode(['results' => []]);
    exit;
}

$termo = isset($_GET['q']) ? trim($_GET['q']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $mysql->prepare("SELECT id, nome FROM clientes WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param("ii", $id, $_SESSION['empresa_id']);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();

    if ($c) {
        echo json_encode(['results' => [['id' => (int)$c['id'], 'text' => $c['nome']]]]);
    } else {
        echo json_encode(['results' => []]);
    }
    exit;
}

if ($termo === '') {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $termo . '%';
$prefixo = $termo . '%';

$stmt = $mysql->prepare(
    "SELECT id, nome FROM clientes
     WHERE empresa_id = ? AND (nome LIKE ? OR CAST(id AS CHAR) LIKE ? OR cpf_cnpj LIKE ?)
     ORDER BY nome ASC
     LIMIT 20"
);
$stmt->bind_param("isss", $_SESSION['empresa_id'], $like, $prefixo, $prefixo);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($c = $res->fetch_assoc()) {
    $results[] = ['id' => (int)$c['id'], 'text' => $c['nome']];
}

echo json_encode(['results' => $results]);
