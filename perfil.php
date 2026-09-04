<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$erro = '';
$sucesso = false;
$pode_alterar_senha = in_array(strtolower($_SESSION['nivel'] ?? ''), ['admin', 'gerente'], true);

$stmt = $mysql->prepare("SELECT id, usuario, nome, senha, nivel FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$meu_usuario = $stmt->get_result()->fetch_assoc();

if ($pode_alterar_senha && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_form();

    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $nova_senha_confirma = $_POST['nova_senha_confirma'] ?? '';

    if (!password_verify($senha_atual, $meu_usuario['senha']) && !hash_equals($meu_usuario['senha'], $senha_atual)) {
        $erro = 'Senha atual incorreta.';
    } elseif (strlen($nova_senha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($nova_senha !== $nova_senha_confirma) {
        $erro = 'As senhas não conferem.';
    } else {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt_up = $mysql->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt_up->bind_param("si", $hash, $_SESSION['id']);
        $stmt_up->execute();
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meu Perfil - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo">
        <div class="header-edit">
            <h2>👤 Meu Perfil</h2>
        </div>

        <?php if ($sucesso): ?>
            <script>Swal.fire({icon:'success', title:'Senha alterada!', text:'Sua senha foi atualizada com sucesso.', timer:2500, showConfirmButton:false});</script>
        <?php endif; ?>

        <div class="card-erp" style="max-width:500px;">
            <p><strong>Login:</strong> <?= htmlspecialchars($meu_usuario['usuario']) ?></p>
            <p><strong>Nome:</strong> <?= htmlspecialchars($meu_usuario['nome']) ?></p>
            <p><strong>Nível:</strong> <?= htmlspecialchars(ucfirst($meu_usuario['nivel'])) ?></p>
        </div>

        <?php if ($pode_alterar_senha): ?>
        <div class="card-erp" style="max-width:500px;">
            <h3 style="margin-bottom:15px;">Alterar Senha</h3>

            <?php if ($erro): ?>
                <p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div><label>SENHA ATUAL</label><input type="password" name="senha_atual" class="input-erp" required autocomplete="current-password"></div>
                    <div><label>NOVA SENHA (mín. 6 caracteres)</label><input type="password" name="nova_senha" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                    <div><label>CONFIRMAR NOVA SENHA</label><input type="password" name="nova_senha_confirma" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save">ALTERAR SENHA</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="card-erp" style="max-width:500px;">
            <p style="color:#64748b;">Sua senha só pode ser alterada por um gerente ou administrador. Fale com um deles se precisar trocá-la.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
