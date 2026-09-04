<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$meu_nivel = strtolower($_SESSION['nivel'] ?? '');
if (!in_array($meu_nivel, ['admin', 'gerente'], true)) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}
$sou_admin = $meu_nivel === 'admin';

// Gerente pode promover até gerente, nunca até admin (evita escalonamento de privilégio)
$NIVEIS_VALIDOS = $sou_admin ? ['admin', 'gerente', 'vendedor', 'caixa', 'estoque'] : ['gerente', 'vendedor', 'caixa', 'estoque'];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: usuarios.php");
    exit;
}

$stmt = $mysql->prepare("SELECT id, usuario, nome, nivel, status FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$dados = $stmt->get_result()->fetch_assoc();

if (!$dados) {
    header("Location: usuarios.php?erro=nao_encontrado");
    exit;
}

// Gerente não pode mexer em conta de admin (nem a própria senha de outro admin), só admin mexe em admin
if (!$sou_admin && strtolower($dados['nivel']) === 'admin') {
    header("Location: usuarios.php?erro=sem_permissao");
    exit;
}

$erro = '';
$e_proprio_usuario = ($id == $_SESSION['id']);
$nivel_original = strtolower($dados['nivel']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_form();

    $nome = trim($_POST['nome'] ?? '');
    $nivel_novo = in_array($_POST['nivel'] ?? '', $NIVEIS_VALIDOS, true) ? $_POST['nivel'] : $dados['nivel'];
    $status_novo = ($_POST['status'] ?? '1') === '1' ? '1' : '0';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $nova_senha_confirma = $_POST['nova_senha_confirma'] ?? '';

    // Ninguém pode se rebaixar de nível nem se desativar (evita ficar trancado fora do próprio sistema)
    if ($e_proprio_usuario && (strtolower($nivel_novo) !== $nivel_original || $status_novo !== '1')) {
        $erro = 'Você não pode alterar seu próprio nível de acesso ou se desativar.';
    } elseif ($nome === '') {
        $erro = 'Informe o nome.';
    } elseif ($nova_senha !== '' && strlen($nova_senha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($nova_senha !== $nova_senha_confirma) {
        $erro = 'As senhas não conferem.';
    } else {
        if ($nova_senha !== '') {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt_u = $mysql->prepare("UPDATE usuarios SET nome = ?, nivel = ?, status = ?, senha = ? WHERE id = ?");
            $stmt_u->bind_param("ssssi", $nome, $nivel_novo, $status_novo, $hash, $id);
        } else {
            $stmt_u = $mysql->prepare("UPDATE usuarios SET nome = ?, nivel = ?, status = ? WHERE id = ?");
            $stmt_u->bind_param("sssi", $nome, $nivel_novo, $status_novo, $id);
        }

        if ($stmt_u->execute()) {
            $detalhe = "nome: $nome, nível: $nivel_novo, status: $status_novo" . ($nova_senha !== '' ? ' (senha redefinida)' : '');
            registrar_log($mysql, 'editar_usuario', 'usuarios', $id, $detalhe);
            header("Location: usuarios.php?sucesso=1");
            exit;
        }
        $erro = 'Erro ao salvar alterações.';
        error_log("editar_usuario.php: " . $mysql->error);
    }

    $dados['nome'] = $nome;
    $dados['nivel'] = $nivel_novo;
    $dados['status'] = $status_novo;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuário - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo">
        <div class="header-edit">
            <h2>✏️ Editando: <?= htmlspecialchars($dados['nome']) ?></h2>
            <a href="usuarios.php" class="btn-voltar">⬅ Voltar</a>
        </div>

        <div class="card-erp" style="max-width:600px;">
            <?php if ($erro): ?>
                <p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>
            <?php if ($e_proprio_usuario): ?>
                <p style="color:#64748b; font-size:13px;">Você está editando a sua própria conta — não é possível alterar seu nível de acesso nem se desativar por aqui.</p>
            <?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form">
                    <div><label>LOGIN</label><input type="text" class="input-erp" value="<?= htmlspecialchars($dados['usuario']) ?>" disabled></div>
                    <div><label>NOME COMPLETO *</label><input type="text" name="nome" class="input-erp" required value="<?= htmlspecialchars($dados['nome']) ?>"></div>
                    <div>
                        <label>NÍVEL DE ACESSO</label>
                        <select name="nivel" class="input-erp" <?= $e_proprio_usuario ? 'disabled' : '' ?>>
                            <?php
                            $opcoes_nivel = ['vendedor' => 'Vendedor', 'caixa' => 'Caixa', 'estoque' => 'Estoque', 'gerente' => 'Gerente'];
                            if ($sou_admin) $opcoes_nivel['admin'] = 'Administrador';
                            foreach ($opcoes_nivel as $val => $label): ?>
                                <option value="<?= $val ?>" <?= strtolower($dados['nivel']) === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($e_proprio_usuario): ?><input type="hidden" name="nivel" value="<?= htmlspecialchars($nivel_original) ?>"><?php endif; ?>
                    </div>
                    <div>
                        <label>STATUS</label>
                        <select name="status" class="input-erp" <?= $e_proprio_usuario ? 'disabled' : '' ?>>
                            <option value="1" <?= (string)$dados['status'] === '1' ? 'selected' : '' ?>>ATIVO</option>
                            <option value="0" <?= (string)$dados['status'] === '0' ? 'selected' : '' ?>>INATIVO</option>
                        </select>
                        <?php if ($e_proprio_usuario): ?><input type="hidden" name="status" value="1"><?php endif; ?>
                    </div>
                    <div><label>NOVA SENHA (deixe em branco para não alterar)</label><input type="password" name="nova_senha" class="input-erp" minlength="6" autocomplete="new-password"></div>
                    <div><label>CONFIRMAR NOVA SENHA</label><input type="password" name="nova_senha_confirma" class="input-erp" minlength="6" autocomplete="new-password"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">SALVAR ALTERAÇÕES</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
