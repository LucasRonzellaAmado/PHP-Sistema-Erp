<?php
require_once 'include/session.php';
iniciar_sessao_segura();
if (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
    header("Location: home.php");
} else {
    header("Location: login.php");
}

exit;