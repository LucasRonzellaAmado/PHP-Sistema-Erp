<?php
require_once __DIR__ . '/session.php';
iniciar_sessao_segura();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auditoria.php';

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/conexao.php';

$stmt_sessao = $mysql->prepare("SELECT u.nivel, u.status, u.empresa_id, e.status as empresa_status, e.cliente_avulso_id
                                 FROM usuarios u LEFT JOIN empresas e ON u.empresa_id = e.id
                                 WHERE u.id = ?");
$stmt_sessao->bind_param("i", $_SESSION['id']);
$stmt_sessao->execute();
$usuario_sessao = $stmt_sessao->get_result()->fetch_assoc();

$sessao_ativa = $usuario_sessao && (
    !array_key_exists('status', $usuario_sessao) ||
    in_array(strtolower((string)$usuario_sessao['status']), ['1', 'ativo', 'active'], true)
);

$empresa_ativa = $usuario_sessao && (
    strtolower((string)$usuario_sessao['nivel']) === 'super_admin' ||
    strtolower((string)($usuario_sessao['empresa_status'] ?? '')) === 'ativo'
);

if (!$sessao_ativa || !$empresa_ativa) {
    session_unset();
    session_destroy();
    header("Location: login.php?erro=sessao_encerrada");
    exit;
}

$_SESSION['nivel'] = $usuario_sessao['nivel'];
$_SESSION['empresa_id'] = $usuario_sessao['empresa_id'];
$_SESSION['cliente_avulso_id'] = $usuario_sessao['cliente_avulso_id'];

$PAGINAS_SUPER_ADMIN = ['empresas.php', 'toggle_empresa_status.php'];
if (strtolower((string)$_SESSION['nivel']) === 'super_admin' && !in_array(basename($_SERVER['PHP_SELF']), $PAGINAS_SUPER_ADMIN, true)) {
    header("Location: /empresas.php");
    exit;
}

$_SESSION['caixa_aberto'] = false;
unset($_SESSION['id_caixa_atual']);

if (strtolower((string)$_SESSION['nivel']) !== 'super_admin') {
    $stmt_caixa = $mysql->prepare("SELECT id FROM controle_caixas WHERE status = 'Aberto' AND empresa_id = ? LIMIT 1");
    $stmt_caixa->bind_param("i", $_SESSION['empresa_id']);
    $stmt_caixa->execute();
    $res_caixa = $stmt_caixa->get_result();

    if ($res_caixa && $res_caixa->num_rows > 0) {
        $dados_caixa = $res_caixa->fetch_assoc();
        $_SESSION['caixa_aberto'] = true;
        $_SESSION['id_caixa_atual'] = $dados_caixa['id'];
    }
}
?>