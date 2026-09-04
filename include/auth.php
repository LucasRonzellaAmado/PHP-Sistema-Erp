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

$_SESSION['caixa_aberto'] = false;
unset($_SESSION['id_caixa_atual']);

$sql_caixa = "SELECT id FROM controle_caixas WHERE status = 'Aberto' LIMIT 1";
$res_caixa = $mysql->query($sql_caixa);

if ($res_caixa && $res_caixa->num_rows > 0) {
    $dados_caixa = $res_caixa->fetch_assoc();
    $_SESSION['caixa_aberto'] = true;
    $_SESSION['id_caixa_atual'] = $dados_caixa['id'];
}
?>