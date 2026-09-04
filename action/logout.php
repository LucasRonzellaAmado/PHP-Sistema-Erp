<?php
require_once '../include/session.php';
iniciar_sessao_segura();
require_once '../include/conexao.php';
require_once '../include/auditoria.php';

if (isset($_SESSION['id'])) {
    registrar_log($mysql, 'logout', 'usuarios', $_SESSION['id']);
}

session_unset();
session_destroy();

header("Location: ../login.php");
exit;