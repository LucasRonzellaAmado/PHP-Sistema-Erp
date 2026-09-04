<?php
require_once 'include/session.php';
iniciar_sessao_segura();
include('include/conexao.php');
require_once 'include/auditoria.php';

$erro_login = false;
$erro_bloqueio = false;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$max_tentativas = 5;
$bloqueio_segundos = 60;
$tentativas = $_SESSION['login_tentativas'] ?? 0;
$ultima_tentativa = $_SESSION['login_ultima_tentativa'] ?? 0;

if ($tentativas >= $max_tentativas && (time() - $ultima_tentativa) < $bloqueio_segundos) {
    $erro_bloqueio = true;
} elseif ($tentativas >= $max_tentativas) {
    // Janela de bloqueio expirou, libera novas tentativas
    $_SESSION['login_tentativas'] = 0;
}

if (!$erro_bloqueio && isset($_POST['usuario']) && isset($_POST['senha'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        exit('Sessão expirada, recarregue a página e tente novamente.');
    }

    $stmt = $mysql->prepare("SELECT * FROM usuarios WHERE usuario = ? LIMIT 1");
    $stmt->bind_param("s", $_POST['usuario']);
    $stmt->execute();
    $usuario_db = $stmt->get_result()->fetch_assoc();

    $senha_ok = false;
    if ($usuario_db) {
        $hash_atual = $usuario_db['senha'];
        // Suporta hashes bcrypt (password_hash) e migra senhas antigas em texto puro
        // no primeiro login correto, sem precisar de uma migração em lote separada.
        if (password_verify($_POST['senha'], $hash_atual)) {
            $senha_ok = true;
        } elseif (hash_equals($hash_atual, $_POST['senha'])) {
            $senha_ok = true;
            $novo_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            $upd = $mysql->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $upd->bind_param("si", $novo_hash, $usuario_db['id']);
            $upd->execute();
        }
    }

    $usuario_ativo = $usuario_db && (
        !array_key_exists('status', $usuario_db) ||
        in_array(strtolower((string)$usuario_db['status']), ['1', 'ativo', 'active'], true)
    );

    if ($senha_ok && $usuario_ativo) {
        session_regenerate_id(true);

        $_SESSION['id']         = $usuario_db['id'];
        $_SESSION['usuario_id'] = $usuario_db['id'];
        $_SESSION['nome']       = $usuario_db['nome'];
        $_SESSION['nivel']      = $usuario_db['nivel'];
        $_SESSION['status']     = $usuario_db['status'];
        unset($_SESSION['login_tentativas'], $_SESSION['login_ultima_tentativa']);
        registrar_log($mysql, 'login_sucesso', 'usuarios', $usuario_db['id']);

        header("Location: home.php");
        exit;

    } else {
        $erro_login = true;
        $_SESSION['login_tentativas'] = $tentativas + 1;
        $_SESSION['login_ultima_tentativa'] = time();
        registrar_log($mysql, 'login_falha', 'usuarios', $usuario_db['id'] ?? null, "Tentativa com login: " . ($_POST['usuario'] ?? ''));
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - NexusFlow</title>
    <link rel="stylesheet" href="assents/login.css?v=<?= time() ?>">
</head>
<body>

    <div class="login">
        <h1>Entrar</h1>

        <?php if ($erro_bloqueio): ?>
            <p style="color:#dc2626;">Muitas tentativas incorretas. Aguarde um minuto e tente novamente.</p>
        <?php elseif ($erro_login): ?>
            <p style="color:#dc2626;">Usuário ou senha incorretos!</p>
        <?php endif; ?>

        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <p>
                <label>Usuário</label><br>
                <input class="nome" type="text" name="usuario" required>
            </p>

            <p>
                <label>Senha</label><br>
                <input class="senha" type="password" name="senha" required>
            </p>

            <p>
                <button class="enviar" type="submit" <?= $erro_bloqueio ? 'disabled' : '' ?>>Entrar</button>
            </p>
        </form>
    </div>

</body>
</html>
