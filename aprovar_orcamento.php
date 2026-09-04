<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf_ok = isset($_GET['csrf']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf']);
$sucesso = false;

if ($id > 0 && $csrf_ok) {
    $stmt = $mysql->prepare("UPDATE orcamentos SET status = 'Aprovado', data_aprovacao = NOW() WHERE id = ? AND status = 'Pendente'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $sucesso = $stmt->affected_rows > 0;
    if ($sucesso) {
        registrar_log($mysql, 'aprovar_orcamento', 'orcamentos', $id);
    }
}

header("Location: historico-orcamento.php?aprovado=" . ($sucesso ? '1' : '0'));
exit;
