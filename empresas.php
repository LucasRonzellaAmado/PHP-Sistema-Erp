<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (strtolower($_SESSION['nivel'] ?? '') !== 'super_admin') {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_empresa'])) {
    csrf_verify_form();

    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    $admin_nome = trim($_POST['admin_nome'] ?? '');
    $admin_usuario = trim($_POST['admin_usuario'] ?? '');
    $admin_senha = $_POST['admin_senha'] ?? '';
    $admin_senha_confirma = $_POST['admin_senha_confirma'] ?? '';

    if ($nome_fantasia === '' || $admin_nome === '' || $admin_usuario === '') {
        $erro = 'Preencha ao menos o nome da empresa e o nome/login do administrador.';
    } elseif (strlen($admin_senha) < 6) {
        $erro = 'A senha do administrador deve ter pelo menos 6 caracteres.';
    } elseif ($admin_senha !== $admin_senha_confirma) {
        $erro = 'As senhas não conferem.';
    } else {
        $stmt_check = $mysql->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt_check->bind_param("s", $admin_usuario);
        $stmt_check->execute();

        if ($stmt_check->get_result()->fetch_assoc()) {
            $erro = 'Já existe um usuário com esse login em alguma empresa. Escolha outro.';
        } else {
            $mysql->begin_transaction();

            try {
                $cnpj_ou_null = $cnpj !== '' ? $cnpj : null;
                $stmt_emp = $mysql->prepare("INSERT INTO empresas (nome_fantasia, razao_social, cnpj, email, telefone, endereco, cidade, estado, status)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')");
                $stmt_emp->bind_param("ssssssss", $nome_fantasia, $razao_social, $cnpj_ou_null, $email, $telefone, $endereco, $cidade, $estado);
                if (!$stmt_emp->execute()) {
                    throw new Exception($mysql->errno === 1062 ? 'Já existe uma empresa com esse CNPJ.' : 'Erro ao criar empresa.');
                }
                $nova_empresa_id = $mysql->insert_id;

                $hash = password_hash($admin_senha, PASSWORD_DEFAULT);
                $stmt_user = $mysql->prepare("INSERT INTO usuarios (usuario, senha, nome, nivel, status, empresa_id) VALUES (?, ?, ?, 'admin', 1, ?)");
                $stmt_user->bind_param("sssi", $admin_usuario, $hash, $admin_nome, $nova_empresa_id);
                if (!$stmt_user->execute()) {
                    throw new Exception('Erro ao criar o usuário administrador.');
                }

                $stmt_fp = $mysql->prepare("INSERT INTO formas_pagamento (nome, permite_prazo, empresa_id) VALUES (?, ?, ?)");
                foreach ([['Dinheiro', 0], ['Pix', 0], ['Cartão Débito', 0], ['Cartão Crédito', 0], ['Fiado', 1]] as $fp) {
                    $stmt_fp->bind_param("sii", $fp[0], $fp[1], $nova_empresa_id);
                    if (!$stmt_fp->execute()) {
                        throw new Exception('Erro ao criar formas de pagamento padrão.');
                    }
                }

                $stmt_cliente = $mysql->prepare("INSERT INTO clientes (nome, tipo_pessoa, status, empresa_id) VALUES ('Consumidor Final', 'PF', 1, ?)");
                $stmt_cliente->bind_param("i", $nova_empresa_id);
                if (!$stmt_cliente->execute()) {
                    throw new Exception('Erro ao criar cliente padrão.');
                }
                $cliente_avulso_id = $mysql->insert_id;

                $stmt_upd = $mysql->prepare("UPDATE empresas SET cliente_avulso_id = ? WHERE id = ?");
                $stmt_upd->bind_param("ii", $cliente_avulso_id, $nova_empresa_id);
                $stmt_upd->execute();

                $mysql->commit();
                registrar_log($mysql, 'criar_empresa', 'empresas', $nova_empresa_id, "Empresa: $nome_fantasia, admin: $admin_usuario");
                header("Location: empresas.php?sucesso=1");
                exit;
            } catch (Exception $e) {
                $mysql->rollback();
                $erro = $e->getMessage();
            }
        }
    }
}

$res_empresas = $mysql->query("SELECT id, nome_fantasia, razao_social, cnpj, email, status, data_criacao FROM empresas ORDER BY nome_fantasia ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Empresas - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1><i class="bi bi-buildings"></i> Empresas</h1>
                <p>Cada empresa cadastrada aqui enxerga só os próprios dados — nenhuma vê a outra</p>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <script>Swal.fire({icon:'success', title:'Salvo!', timer:1500, showConfirmButton:false});</script>
        <?php endif; ?>

        <div class="card-erp" style="max-width:700px;">
            <h3 style="margin-bottom:15px;">Nova Empresa</h3>
            <?php if ($erro): ?><p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form">
                    <div class="section-title">Dados da Empresa</div>
                    <div><label>NOME FANTASIA *</label><input type="text" name="nome_fantasia" class="input-erp" required></div>
                    <div><label>RAZÃO SOCIAL</label><input type="text" name="razao_social" class="input-erp"></div>
                    <div><label>CNPJ</label><input type="text" name="cnpj" class="input-erp"></div>
                    <div><label>EMAIL</label><input type="email" name="email" class="input-erp"></div>
                    <div><label>TELEFONE</label><input type="text" name="telefone" class="input-erp"></div>
                    <div><label>ENDEREÇO</label><input type="text" name="endereco" class="input-erp"></div>
                    <div><label>CIDADE</label><input type="text" name="cidade" class="input-erp"></div>
                    <div><label>UF</label><input type="text" name="estado" class="input-erp" maxlength="2"></div>

                    <div class="section-title">Administrador da Empresa</div>
                    <div><label>NOME *</label><input type="text" name="admin_nome" class="input-erp" required></div>
                    <div><label>LOGIN *</label><input type="text" name="admin_usuario" class="input-erp" required autocomplete="off"></div>
                    <div><label>SENHA (mín. 6 caracteres) *</label><input type="password" name="admin_senha" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                    <div><label>CONFIRMAR SENHA *</label><input type="password" name="admin_senha_confirma" class="input-erp" required minlength="6" autocomplete="new-password"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="nova_empresa" value="1" class="btn-save">CADASTRAR EMPRESA</button>
                </div>
            </form>
        </div>

        <div class="card-erp">
            <div class="table-responsive">
                <table class="table-erp">
                    <thead><tr><th>Nome Fantasia</th><th>CNPJ</th><th>Email</th><th>Status</th><th>Criada em</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if ($res_empresas->num_rows > 0): ?>
                            <?php while ($row = $res_empresas->fetch_assoc()): ?>
                            <tr>
                                <td class="txt-bold"><?= htmlspecialchars($row['nome_fantasia']) ?></td>
                                <td><?= htmlspecialchars($row['cnpj'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                <td><span class="status-dot <?= strtolower($row['status']) === 'ativo' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($row['data_criacao'])) ?></td>
                                <td class="actions-cell">
                                    <a href="api/toggle_empresa_status.php?id=<?= (int)$row['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>"
                                       class="btn-edit" title="<?= strtolower($row['status']) === 'ativo' ? 'Desativar' : 'Reativar' ?>"
                                       onclick="return confirm('<?= strtolower($row['status']) === 'ativo' ? 'Desativar' : 'Reativar' ?> esta empresa?')">
                                        <i class="bi bi-<?= strtolower($row['status']) === 'ativo' ? 'x-circle' : 'check-circle' ?>"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-state">Nenhuma empresa cadastrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
