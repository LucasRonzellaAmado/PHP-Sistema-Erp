<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_lancamento'])) {
    csrf_verify_form();

    $id_fornecedor = !empty($_POST['id_fornecedor']) ? intval($_POST['id_fornecedor']) : null;
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
    $vencimento = $_POST['vencimento'] ?? '';

    if ($descricao === '' || $valor <= 0 || $vencimento === '') {
        $erro = 'Preencha descrição, valor e vencimento corretamente.';
    } else {
        $stmt = $mysql->prepare("INSERT INTO contas_pagar (id_fornecedor, descricao, valor, data_vencimento, status, usuario_id) VALUES (?, ?, ?, ?, 'Pendente', ?)");
        $stmt->bind_param("isdsi", $id_fornecedor, $descricao, $valor, $vencimento, $_SESSION['id']);
        $stmt->execute();
        header("Location: contas_pagar.php?sucesso=1");
        exit;
    }
}

$filtro = $_GET['status'] ?? 'Pendente';
$where = '';
if ($filtro === 'Pendente') $where = "WHERE cp.status = 'Pendente'";
elseif ($filtro === 'Pago') $where = "WHERE cp.status = 'Pago'";
elseif ($filtro === 'Atrasado') $where = "WHERE cp.status = 'Pendente' AND cp.data_vencimento < CURDATE()";

$res = $mysql->query("SELECT cp.*, f.razao_social FROM contas_pagar cp
                       LEFT JOIN fornecedores f ON cp.id_fornecedor = f.id
                       $where
                       ORDER BY cp.data_vencimento ASC");

$res_resumo = $mysql->query("SELECT
    SUM(CASE WHEN status = 'Pendente' THEN valor ELSE 0 END) as total_pendente,
    SUM(CASE WHEN status = 'Pendente' AND data_vencimento < CURDATE() THEN valor ELSE 0 END) as total_atrasado,
    SUM(CASE WHEN status = 'Pago' AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE()) THEN valor ELSE 0 END) as total_pago_mes
    FROM contas_pagar");
$resumo = $res_resumo->fetch_assoc();

$res_fornecedores = $mysql->query("SELECT id, razao_social FROM fornecedores WHERE status = 'Ativo' ORDER BY razao_social ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contas a Pagar - NexusFlow</title>
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
                <h1>💸 Contas a Pagar</h1>
                <p>Obrigações com fornecedores, incluindo pedidos de compra</p>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <script>Swal.fire({icon:'success', title:'Salvo!', timer:1500, showConfirmButton:false});</script>
        <?php endif; ?>

        <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px; margin-bottom:20px;">
            <div class="card-erp"><span style="font-size:12px; color:#64748b;">TOTAL PENDENTE</span><h3 style="margin:5px 0; color:#1e293b;">R$ <?= number_format($resumo['total_pendente'] ?? 0, 2, ',', '.') ?></h3></div>
            <div class="card-erp"><span style="font-size:12px; color:#64748b;">TOTAL ATRASADO</span><h3 style="margin:5px 0; color:#ef4444;">R$ <?= number_format($resumo['total_atrasado'] ?? 0, 2, ',', '.') ?></h3></div>
            <div class="card-erp"><span style="font-size:12px; color:#64748b;">PAGO ESTE MÊS</span><h3 style="margin:5px 0; color:#10b981;">R$ <?= number_format($resumo['total_pago_mes'] ?? 0, 2, ',', '.') ?></h3></div>
        </div>

        <div class="card-erp" style="max-width:700px;">
            <h3 style="margin-bottom:15px;">Novo Lançamento</h3>
            <?php if ($erro): ?><p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <form method="post">
                <?php csrf_field(); ?>
                <div class="row" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div style="flex:2; min-width:180px;">
                        <label>Fornecedor</label>
                        <select name="id_fornecedor" class="input-erp" style="width:100%;">
                            <option value="">Sem fornecedor vinculado</option>
                            <?php while ($f = $res_fornecedores->fetch_assoc()): ?>
                                <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['razao_social']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div style="flex:2; min-width:180px;"><label>Descrição</label><input type="text" name="descricao" class="input-erp" required style="width:100%;"></div>
                    <div style="flex:1; min-width:120px;"><label>Valor (R$)</label><input type="text" name="valor" class="input-erp money-mask" value="0,00" required style="width:100%;"></div>
                    <div style="flex:1; min-width:150px;"><label>Vencimento</label><input type="date" name="vencimento" class="input-erp" required style="width:100%;"></div>
                    <div style="display:flex; align-items:flex-end;"><button type="submit" name="novo_lancamento" value="1" class="btn-primary">Lançar</button></div>
                </div>
            </form>
        </div>

        <div class="card-erp">
            <div class="filter-bar" style="display:flex; gap:10px; margin-bottom:15px;">
                <a href="?status=Pendente" class="btn-filtrar" style="<?= $filtro==='Pendente'?'font-weight:bold;':'' ?>">Pendentes</a>
                <a href="?status=Atrasado" class="btn-filtrar" style="<?= $filtro==='Atrasado'?'font-weight:bold;':'' ?>">Atrasadas</a>
                <a href="?status=Pago" class="btn-filtrar" style="<?= $filtro==='Pago'?'font-weight:bold;':'' ?>">Pagas</a>
                <a href="?status=Todas" class="btn-filtrar" style="<?= $filtro==='Todas'?'font-weight:bold;':'' ?>">Todas</a>
            </div>

            <div class="table-responsive">
                <table class="table-erp">
                    <thead><tr><th>Fornecedor</th><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()):
                                $atrasado = $row['status'] === 'Pendente' && strtotime($row['data_vencimento']) < strtotime(date('Y-m-d'));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['razao_social'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($row['descricao']) ?></td>
                                <td class="<?= $atrasado ? 'txt-danger txt-bold' : '' ?>"><?= date('d/m/Y', strtotime($row['data_vencimento'])) ?><?= $atrasado ? ' ⚠️' : '' ?></td>
                                <td class="txt-bold">R$ <?= number_format($row['valor'], 2, ',', '.') ?></td>
                                <td><span class="status-dot <?= $row['status']==='Pago'?'status-active':($atrasado?'status-inactive':'') ?>"><?= strtoupper($atrasado ? 'ATRASADO' : $row['status']) ?></span></td>
                                <td class="actions-cell">
                                    <?php if ($row['status'] === 'Pendente'): ?>
                                        <button class="btn-primary" style="padding:6px 12px; font-size:12px;" onclick="baixarConta(<?= (int)$row['id'] ?>)">Dar baixa</button>
                                    <?php else: ?>
                                        <small>Pago em <?= $row['data_pagamento'] ? date('d/m/Y', strtotime($row['data_pagamento'])) : '-' ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-state">Nenhuma conta encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function baixarConta(id) {
    Swal.fire({
        title: 'Confirmar pagamento?',
        text: 'Isso marcará a conta como paga.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, foi pago',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/baixar_conta_pagar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                body: JSON.stringify({ id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Erro', data.message || 'Não foi possível dar baixa.', 'error');
            })
            .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
        }
    });
}
</script>
</body>
</html>
