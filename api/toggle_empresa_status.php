<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

if (strtolower($_SESSION['nivel'] ?? '') !== 'super_admin') {
    header("Location: ../home.php?erro=sem_permissao");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf_ok = isset($_GET['csrf']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf']);

if ($id > 0 && $csrf_ok) {
    $stmt = $mysql->prepare("SELECT status FROM empresas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $empresa = $stmt->get_result()->fetch_assoc();

    if ($empresa) {
        $novo_status = strtolower($empresa['status']) === 'ativo' ? 'Inativo' : 'Ativo';
        $stmt_up = $mysql->prepare("UPDATE empresas SET status = ? WHERE id = ?");
        $stmt_up->bind_param("si", $novo_status, $id);
        $stmt_up->execute();
        registrar_log($mysql, $novo_status === 'Inativo' ? 'desativar_empresa' : 'reativar_empresa', 'empresas', $id);
    }
}

header("Location: ../empresas.php?sucesso=1");
exit;
