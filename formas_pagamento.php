<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || strtolower($_SESSION['nivel']) !== 'admin') {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_forma'])) {
    csrf_verify_form();

    $nome = trim($_POST['nome'] ?? '');
    $permite_prazo = isset($_POST['permite_prazo']) ? 1 : 0;

    if ($nome === '') {
        $erro = 'Informe o nome da forma de pagamento.';
    } else {
        $stmt = $mysql->prepare("INSERT INTO formas_pagamento (nome, permite_prazo, status) VALUES (?, ?, 1)");
        $stmt->bind_param("si", $nome, $permite_prazo);
        if (!$stmt->execute()) {
            $erro = ($mysql->errno === 1062) ? 'Já existe uma forma de pagamento com esse nome.' : 'Erro ao salvar.';
        } else {
            header("Location: formas_pagamento.php?sucesso=1");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_id'])) {
    csrf_verify_form();

    $id = intval($_POST['editar_id']);
    $nome = trim($_POST['nome_edit'] ?? '');
    $status = ($_POST['status_edit'] ?? '1') === '1' ? 1 : 0;
    $permite_prazo = isset($_POST['permite_prazo_edit']) ? 1 : 0;

    if ($nome !== '') {
        $stmt = $mysql->prepare("UPDATE formas_pagamento SET nome = ?, permite_prazo = ?, status = ? WHERE id = ?");
        $stmt->bind_param("siii", $nome, $permite_prazo, $status, $id);
        $stmt->execute();
    }
    header("Location: formas_pagamento.php?sucesso=1");
    exit;
}

$res = $mysql->query("SELECT id, nome, permite_prazo, status FROM formas_pagamento ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Formas de Pagamento - NexusFlow</title>
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
                <h1>💳 Formas de Pagamento</h1>
                <p>Usadas no PDV, orçamentos e no financeiro. "Permite prazo" gera conta a receber automaticamente.</p>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <script>Swal.fire({icon:'success', title:'Salvo!', timer:1500, showConfirmButton:false});</script>
        <?php endif; ?>

        <div class="card-erp" style="max-width:500px;">
            <h3 style="margin-bottom:15px;">Nova Forma de Pagamento</h3>
            <?php if ($erro): ?><p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <form method="post">
                <?php csrf_field(); ?>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" name="nome" class="input-erp" placeholder="Ex: Vale Alimentação" required style="flex:1;">
                    <label style="display:flex; align-items:center; gap:5px; white-space:nowrap;"><input type="checkbox" name="permite_prazo"> Permite prazo</label>
                    <button type="submit" name="nova_forma" value="1" class="btn-primary">Adicionar</button>
                </div>
            </form>
        </div>

        <div class="card-erp">
            <div class="table-responsive">
                <table class="table-erp">
                    <thead><tr><th>Nome</th><th>Permite Prazo</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nome']) ?></td>
                                <td><?= $row['permite_prazo'] ? 'Sim' : 'Não' ?></td>
                                <td><span class="status-dot <?= $row['status'] ? 'status-active' : 'status-inactive' ?>"><?= $row['status'] ? 'ATIVO' : 'INATIVO' ?></span></td>
                                <td class="actions-cell">
                                    <button class="btn-edit" title="Editar" onclick='abrirEdicao(<?= json_encode(["id"=>$row["id"],"nome"=>$row["nome"],"status"=>$row["status"],"permite_prazo"=>$row["permite_prazo"]]) ?>)'>✏️</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="empty-state">Nenhuma forma de pagamento cadastrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalEdicao" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; padding:25px; border-radius:12px; width:100%; max-width:400px;">
        <h3 style="margin-bottom:15px;">Editar Forma de Pagamento</h3>
        <form method="post" id="formEdicao">
            <?php csrf_field(); ?>
            <input type="hidden" name="editar_id" id="edit_id">
            <div style="margin-bottom:10px;"><label>Nome</label><input type="text" name="nome_edit" id="edit_nome" class="input-erp" required style="width:100%;"></div>
            <div style="margin-bottom:10px;"><label><input type="checkbox" name="permite_prazo_edit" id="edit_prazo"> Permite prazo (gera conta a receber)</label></div>
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
    document.getElementById('edit_prazo').checked = !!item.permite_prazo;
    document.getElementById('modalEdicao').style.display = 'flex';
}
function fecharEdicao() {
    document.getElementById('modalEdicao').style.display = 'none';
}
</script>
</body>
</html>
