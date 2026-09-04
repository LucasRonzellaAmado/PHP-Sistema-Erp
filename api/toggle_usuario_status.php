<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

$meu_nivel = strtolower($_SESSION['nivel'] ?? '');
if (!in_array($meu_nivel, ['admin', 'gerente'], true)) {
    header("Location: ../home.php?erro=sem_permissao");
    exit;
}
$sou_admin = $meu_nivel === 'admin';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrf_ok = isset($_GET['csrf']) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf']);

if ($id > 0 && $csrf_ok && $id != $_SESSION['id']) {
    $stmt = $mysql->prepare("SELECT nivel, status FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alvo = $stmt->get_result()->fetch_assoc();

    // Gerente não pode ativar/desativar conta de admin
    if ($alvo && !$sou_admin && strtolower($alvo['nivel']) === 'admin') {
        $alvo = null;
    }

    if ($alvo) {
        $vai_desativar = (string)$alvo['status'] === '1';
        $bloqueado = false;

        // Não deixa desativar o último administrador ativo do sistema
        if ($vai_desativar && strtolower($alvo['nivel']) === 'admin') {
            $stmt_count = $mysql->prepare("SELECT COUNT(*) as total FROM usuarios WHERE nivel = 'admin' AND status = 1");
            $stmt_count->execute();
            $total_admins = $stmt_count->get_result()->fetch_assoc()['total'];
            $bloqueado = $total_admins <= 1;
        }

        if (!$bloqueado) {
            $novo_status = $vai_desativar ? '0' : '1';
            $stmt_up = $mysql->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
            $stmt_up->bind_param("si", $novo_status, $id);
            $stmt_up->execute();
            registrar_log($mysql, $vai_desativar ? 'desativar_usuario' : 'reativar_usuario', 'usuarios', $id);
        }
    }
}

header("Location: ../usuarios.php?sucesso=1");
exit;
