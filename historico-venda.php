<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'caixa', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$inicio_periodo = "$data_inicio 00:00:00";
$fim_periodo = "$data_fim 23:59:59";

$sql = "SELECT v.*, u.nome as nome_vendedor,
        (SELECT COUNT(*) FROM venda_itens vi WHERE vi.id_venda = v.id) as total_itens
        FROM vendas v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.data_venda BETWEEN ? AND ?
        ORDER BY v.id DESC";
$stmt = $mysql->prepare($sql);
$stmt->bind_param("ss", $inicio_periodo, $fim_periodo);
$stmt->execute();
$vendas = $stmt->get_result();

$sql_soma = "SELECT SUM(valor_total) as total_periodo FROM vendas WHERE data_venda BETWEEN ? AND ?";
$stmt_soma = $mysql->prepare($sql_soma);
$stmt_soma->bind_param("ss", $inicio_periodo, $fim_periodo);
$stmt_soma->execute();
$total_faturado = $stmt_soma->get_result()->fetch_assoc()['total_periodo'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Vendas - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/vendas_historico.css">
</head>
<body>
    <div class="container" style="display: flex;">
        <?php include 'include/sidebar.php'; ?>
        
        <div class="conteudo">
            <div class="header-vendas">
                <div>
                    <h2>📊 Histórico de Vendas</h2>
                    <p>Listagem de transações e faturamento do período.</p>
                </div>
                <div class="resumo-faturamento">
                    <small>TOTAL NO PERÍODO</small>
                    <strong>R$ <?= number_format($total_faturado, 2, ',', '.') ?></strong>
                </div>
            </div>

            <form method="GET" class="filtros-venda">
                <div class="filter-field">
                    <label>INÍCIO</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" class="input-erp">
                </div>
                <div class="filter-field">
                    <label>FIM</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="input-erp">
                </div>
                <button type="submit" class="btn-filtrar">FILTRAR</button>
            </form>

            <div class="table-container">
                <table class="table-erp">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>RESUMO</th>
                            <th>DATA/HORA</th>
                            <th>VENDEDOR</th>
                            <th>PAGAMENTO</th>
                            <th>TOTAL</th>
                            <th>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($vendas && $vendas->num_rows > 0): ?>
                            <?php while($v = $vendas->fetch_assoc()): ?>
                            <tr>
                                <td class="txt-id">#<?= $v['id'] ?></td>
                                <td>
                                    <strong>Venda de Produtos</strong><br>
                                    <small><?= $v['total_itens'] ?> item(ns)</small>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></td>
                                <td><?= htmlspecialchars($v['nome_vendedor'] ?? 'Sistema') ?></td>
                                <td><span class="badge"><?= htmlspecialchars($v['forma_pagamento']) ?></span></td>
                                <td class="txt-total">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
                                <td class="actions">
                                    <button class="btn-view" data-id="<?= $v['id'] ?>">👁️</button>
                                    <a href="imprimir_cupom.php?id=<?= $v['id'] ?>" target="_blank" class="btn-print">🖨️</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty">Nenhuma venda encontrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modalDetalhes" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes da Venda</h3>
                <span class="close">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script src="assents/vendas_historico.js"></script>
</body>
</html>