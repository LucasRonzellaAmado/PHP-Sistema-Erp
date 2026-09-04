<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$meu_nivel = strtolower($_SESSION['nivel'] ?? '');
if (!in_array($meu_nivel, ['admin', 'gerente'], true)) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}
$sou_admin = $meu_nivel === 'admin';

// Gerente pode cadastrar qualquer colaborador, menos outro admin (evita escalonamento de privilégio)
$NIVEIS_VALIDOS = $sou_admin ? ['admin', 'gerente', 'vendedor', 'caixa', 'estoque'] : ['gerente', 'vendedor', 'caixa', 'estoque'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_form();

    $usuario_login = trim($_POST['usuario'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirma = $_POST['senha_confirma'] ?? '';
    $nivel_novo = in_array($_POST['nivel'] ?? '', $NIVEIS_VALIDOS, true) ? $_POST['nivel'] : 'vendedor';

    if ($usuario_login === '' || $nome === '') {
        $erro = 'Preencha o login e o nome.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $senha_confirma) {
        $erro = 'As senhas não conferem.';
    } else {
        $stmt_check = $mysql->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt_check->bind_param("s", $usuario_login);
        $stmt_check->execute();

        if ($stmt_check->get_result()->fetch_assoc()) {
            header("Location: usuarios.php?erro=usuario_existe");
            exit;
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $mysql->prepare("INSERT INTO usuarios (usuario, senha, nome, nivel, status) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $usuario_login, $hash, $nome, $nivel_novo);

        if ($stmt->execute()) {
            registrar_log($mysql, 'criar_usuario', 'usuarios', $mysql->insert_id, "Login: $usuario_login, nível: $nivel_novo");
            header("Location: usuarios.php?sucesso=1");
            exit;
        }
        $erro = 'Erro ao salvar usuário.';
        error_log("cad_usuario.php: " . $mysql->error);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Usuário - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo">
        <div class="header-edit">
            <h2>➕ Novo Usuário</h2>
            <a href="usuarios.php" class="btn-voltar">⬅ Voltar</a>
        </div>

        <div class="card-erp" style="max-width:600px;">
            <?php if ($erro): ?>
                <p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form">
                    <div><label>LOGIN *</label><input type="text" name="usuario" class="input-erp" required autocomplete="off" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"></div>
                    <div><label>NOME COMPLETO *</label><input type="text" name="nome" class="input-erp" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"></div>
                    <div>
                        <label>NÍVEL DE ACESSO *</label>
                        <select name="nivel" class="input-erp">
                            <option value="vendedor">Vendedor</option>
                            <option value="caixa">Caixa</option>
                            <option value="estoque">Estoque</option>
                            <option value="gerente">Gerente</option>
                            <?php if ($sou_admin): ?>
                                <option value="admin">Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div><label>SENHA * (mín. 6 caracteres)</label><input type="password" name="senha" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                    <div><label>CONFIRMAR SENHA *</label><input type="password" name="senha_confirma" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">CADASTRAR USUÁRIO</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
