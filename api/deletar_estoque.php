<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    header("Location: ../home.php?erro=sem_permissao");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf_ok = isset($_GET['csrf']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf']);

if ($id > 0 && $csrf_ok) {
    // Desativa em vez de apagar: um DELETE definitivo quebraria o histórico de vendas
    // e orçamentos que já referenciam este produto (venda_itens.id_produto, orcamento_itens.id_produto).
    $stmt = $mysql->prepare("UPDATE estoque SET status = 'INATIVO' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: ../estoque.php?produto_desativado=1");
exit;
