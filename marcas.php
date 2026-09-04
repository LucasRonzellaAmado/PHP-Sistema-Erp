<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_marca'])) {
    csrf_verify_form();

    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') {
        $erro = 'Informe o nome da marca.';
    } else {
        $stmt = $mysql->prepare("INSERT INTO marcas (nome, status) VALUES (?, 1)");
        $stmt->bind_param("s", $nome);
        if (!$stmt->execute()) {
            $erro = ($mysql->errno === 1062) ? 'Já existe uma marca com esse nome.' : 'Erro ao salvar marca.';
        } else {
            header("Location: marcas.php?sucesso=1");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_id'])) {
    csrf_verify_form();

    $id = intval($_POST['editar_id']);
    $nome = trim($_POST['nome_edit'] ?? '');
    $status = ($_POST['status_edit'] ?? '1') === '1' ? 1 : 0;

    if ($nome !== '') {
        $stmt = $mysql->prepare("UPDATE marcas SET nome = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sii", $nome, $status, $id);
        $stmt->execute();
    }
    header("Location: marcas.php?sucesso=1");
    exit;
}

$res = $mysql->query("SELECT id, nome, status FROM marcas ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Marcas - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1>🏭 Marcas de Produtos</h1>
                <p>Cadastro mestre usado no estoque</p>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <script>Swal.fire({icon:'success', title:'Salvo!', timer:1500, showConfirmButton:false});</script>
        <?php endif; ?>

        <div class="card-erp" style="max-width:500px;">
            <h3 style="margin-bottom:15px;">Nova Marca</h3>
            <?php if ($erro): ?><p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <form method="post" style="display:flex; gap:10px;">
                <?php csrf_field(); ?>
                <input type="text" name="nome" class="input-erp" placeholder="Nome da marca" required style="flex:1;">
                <button type="submit" name="nova_marca" value="1" class="btn-primary">Adicionar</button>
            </form>
        </div>

        <div class="card-erp">
            <div class="table-responsive">
                <table class="table-erp">
                    <thead><tr><th>Nome</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nome']) ?></td>
                                <td><span class="status-dot <?= $row['status'] ? 'status-active' : 'status-inactive' ?>"><?= $row['status'] ? 'ATIVO' : 'INATIVO' ?></span></td>
                                <td class="actions-cell">
                                    <button class="btn-edit" title="Editar" onclick='abrirEdicao(<?= json_encode(["id"=>$row["id"],"nome"=>$row["nome"],"status"=>$row["status"]]) ?>)'>✏️</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state">Nenhuma marca cadastrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalEdicao" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; padding:25px; border-radius:12px; width:100%; max-width:400px;">
        <h3 style="margin-bottom:15px;">Editar Marca</h3>
        <form method="post" id="formEdicao">
            <?php csrf_field(); ?>
            <input type="hidden" name="editar_id" id="edit_id">
            <div style="margin-bottom:10px;"><label>Nome</label><input type="text" name="nome_edit" id="edit_nome" class="input-erp" required style="width:100%;"></div>
            <div style="margin-bottom:15px;">
                <label>Status</label>
                <select name="status_edit" id="edit_status" class="input-erp" style="width:100%;">
                    <option value="1">ATIVO</option>
                    <option value="0">INATIVO</option>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="fecharEdicao()" class="btn-voltar" style="flex:1;">Cancelar</button>
                <button type="submit" class="btn-save" style="flex:1;">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirEdicao(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_nome').value = item.nome;
    document.getElementById('edit_status').value = item.status ? '1' : '0';
    document.getElementById('modalEdicao').style.display = 'flex';
}
function fecharEdicao() {
    document.getElementById('modalEdicao').style.display = 'none';
}
</script>
</body>
</html>
