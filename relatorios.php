<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$relatorio = $_GET['r'] ?? 'abc';
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-d');
$inicio_periodo = "$data_inicio 00:00:00";
$fim_periodo = "$data_fim 23:59:59";

$RELATORIOS = [
    'abc' => 'Curva ABC de Produtos',
    'comissoes' => 'Comissão de Vendedores',
    'estoque' => 'Estoque Valorizado',
    'clientes' => 'Ranking de Clientes',
    'dre' => 'DRE Simplificado',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1>📈 Relatórios Gerenciais</h1>
                <p>Indicadores de vendas, estoque e financeiro</p>
            </div>
        </div>

        <div class="card-erp">
            <div class="filter-bar" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:15px;">
                <?php foreach ($RELATORIOS as $key => $label): ?>
                    <a href="?r=<?= $key ?>&inicio=<?= htmlspecialchars($data_inicio) ?>&fim=<?= htmlspecialchars($data_fim) ?>"
                       class="btn-filtrar" style="<?= $relatorio === $key ? 'background:#2563eb; color:#fff;' : '' ?> padding:8px 14px; border-radius:6px; text-decoration:none; font-size:13px;">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($relatorio !== 'estoque'): ?>
            <form method="GET" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:20px;">
                <input type="hidden" name="r" value="<?= htmlspecialchars($relatorio) ?>">
                <div><label>Início</label><br><input type="date" name="inicio" class="input-erp" value="<?= htmlspecialchars($data_inicio) ?>"></div>
                <div><label>Fim</label><br><input type="date" name="fim" class="input-erp" value="<?= htmlspecialchars($data_fim) ?>"></div>
                <button type="submit" class="btn-filtrar">Aplicar Período</button>
            </form>
            <?php endif; ?>

            <?php if ($relatorio === 'abc'): ?>
                <?php
                $stmt = $mysql->prepare("SELECT e.id, e.nome, SUM(vi.quantidade) as qtd_vendida, SUM(vi.valor_total_item) as valor_vendido
                    FROM venda_itens vi
                    JOIN vendas v ON vi.id_venda = v.id
                    LEFT JOIN estoque e ON vi.id_produto = e.id
                    WHERE v.data_venda BETWEEN ? AND ?
                    GROUP BY e.id, e.nome
                    ORDER BY valor_vendido DESC");
                $stmt->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt->execute();
                $produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $total_geral = array_sum(array_column($produtos, 'valor_vendido'));
                $acumulado = 0;
                ?>
                <table class="table-erp">
                    <thead><tr><th>#</th><th>Produto</th><th>Qtd Vendida</th><th>Valor Vendido</th><th>% do Total</th><th>% Acumulado</th><th>Classe</th></tr></thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr><td colspan="7" class="empty-state">Nenhuma venda no período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($produtos as $i => $p):
                            $percent = $total_geral > 0 ? ($p['valor_vendido'] / $total_geral) * 100 : 0;
                            $acumulado += $percent;
                            $classe = $acumulado <= 80 ? 'A' : ($acumulado <= 95 ? 'B' : 'C');
                            $cor = $classe === 'A' ? '#10b981' : ($classe === 'B' ? '#f59e0b' : '#ef4444');
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($p['nome'] ?? 'Produto removido') ?></td>
                            <td><?= number_format($p['qtd_vendida'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($p['valor_vendido'], 2, ',', '.') ?></td>
                            <td><?= number_format($percent, 1, ',', '.') ?>%</td>
                            <td><?= number_format($acumulado, 1, ',', '.') ?>%</td>
                            <td><span style="background:<?= $cor ?>; color:#fff; padding:2px 10px; border-radius:10px; font-weight:bold;"><?= $classe ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($relatorio === 'comissoes'): ?>
                <?php
                $stmt = $mysql->prepare("SELECT u.id, u.nome, COUNT(DISTINCT v.id) as qtd_vendas, SUM(v.valor_total) as total_vendido
                    FROM vendas v
                    JOIN usuarios u ON v.usuario_id = u.id
                    WHERE v.data_venda BETWEEN ? AND ?
                    GROUP BY u.id, u.nome
                    ORDER BY total_vendido DESC");
                $stmt->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt->execute();
                $vendedores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <div style="margin-bottom:15px;">
                    <label>% de comissão: </label>
                    <input type="number" id="percentComissao" value="3" step="0.1" style="width:80px;" onchange="recalcComissoes()"> %
                </div>
                <table class="table-erp">
                    <thead><tr><th>Vendedor</th><th>Qtd Vendas</th><th>Total Vendido</th><th>Comissão</th></tr></thead>
                    <tbody>
                        <?php if (empty($vendedores)): ?>
                            <tr><td colspan="4" class="empty-state">Nenhuma venda no período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($vendedores as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['nome']) ?></td>
                            <td><?= (int)$v['qtd_vendas'] ?></td>
                            <td class="valor-vendido" data-valor="<?= (float)$v['total_vendido'] ?>">R$ <?= number_format($v['total_vendido'], 2, ',', '.') ?></td>
                            <td class="valor-comissao txt-bold" style="color:#10b981;">R$ 0,00</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                function recalcComissoes() {
                    const percent = parseFloat(document.getElementById('percentComissao').value) || 0;
                    document.querySelectorAll('.valor-vendido').forEach(td => {
                        const valor = parseFloat(td.dataset.valor);
                        const comissao = valor * (percent / 100);
                        td.parentElement.querySelector('.valor-comissao').innerText = 'R$ ' + comissao.toLocaleString('pt-br', {minimumFractionDigits: 2});
                    });
                }
                document.addEventListener('DOMContentLoaded', recalcComissoes);
                </script>

            <?php elseif ($relatorio === 'estoque'): ?>
                <?php
                $res_valor = $mysql->query("SELECT COUNT(*) as total_produtos, SUM(quantidade * preco_custo) as valor_custo, SUM(quantidade * preco_venda) as valor_venda
                    FROM estoque WHERE status = 1");
                $resumo = $res_valor->fetch_assoc();

                $res_categorias = $mysql->query("SELECT COALESCE(NULLIF(categoria,''), 'Sem categoria') as categoria,
                    SUM(quantidade * preco_custo) as valor_custo, SUM(quantidade) as qtd_total
                    FROM estoque WHERE status = 1 GROUP BY categoria ORDER BY valor_custo DESC");
                ?>
                <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px; margin-bottom:20px;">
                    <div class="card-erp"><span style="font-size:12px; color:#64748b;">PRODUTOS ATIVOS</span><h3><?= (int)$resumo['total_produtos'] ?></h3></div>
                    <div class="card-erp"><span style="font-size:12px; color:#64748b;">VALOR EM CUSTO</span><h3>R$ <?= number_format($resumo['valor_custo'] ?? 0, 2, ',', '.') ?></h3></div>
                    <div class="card-erp"><span style="font-size:12px; color:#64748b;">VALOR EM VENDA (POTENCIAL)</span><h3>R$ <?= number_format($resumo['valor_venda'] ?? 0, 2, ',', '.') ?></h3></div>
                </div>
                <table class="table-erp">
                    <thead><tr><th>Categoria</th><th>Qtd em Estoque</th><th>Valor em Custo</th></tr></thead>
                    <tbody>
                        <?php while ($c = $res_categorias->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['categoria']) ?></td>
                            <td><?= number_format($c['qtd_total'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($c['valor_custo'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

            <?php elseif ($relatorio === 'clientes'): ?>
                <?php
                $stmt = $mysql->prepare("SELECT c.id, c.nome, COUNT(v.id) as qtd_compras, SUM(v.valor_total) as total_comprado
                    FROM vendas v
                    JOIN clientes c ON v.id_cliente = c.id
                    WHERE v.data_venda BETWEEN ? AND ?
                    GROUP BY c.id, c.nome
                    ORDER BY total_comprado DESC
                    LIMIT 30");
                $stmt->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt->execute();
                $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <table class="table-erp">
                    <thead><tr><th>#</th><th>Cliente</th><th>Qtd Compras</th><th>Total Comprado</th><th>Ticket Médio</th></tr></thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr><td colspan="5" class="empty-state">Nenhuma venda no período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($clientes as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($c['nome']) ?></td>
                            <td><?= (int)$c['qtd_compras'] ?></td>
                            <td class="txt-bold">R$ <?= number_format($c['total_comprado'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($c['total_comprado'] / max(1, $c['qtd_compras']), 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($relatorio === 'dre'): ?>
                <?php
                $stmt_receita = $mysql->prepare("SELECT COALESCE(SUM(valor_total),0) as total FROM vendas WHERE data_venda BETWEEN ? AND ?");
                $stmt_receita->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt_receita->execute();
                $receita = (float)$stmt_receita->get_result()->fetch_assoc()['total'];

                $stmt_cmv = $mysql->prepare("SELECT COALESCE(SUM(vi.quantidade * e.preco_custo),0) as total
                    FROM venda_itens vi
                    JOIN vendas v ON vi.id_venda = v.id
                    LEFT JOIN estoque e ON vi.id_produto = e.id
                    WHERE v.data_venda BETWEEN ? AND ?");
                $stmt_cmv->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt_cmv->execute();
                $cmv = (float)$stmt_cmv->get_result()->fetch_assoc()['total'];

                $stmt_desp = $mysql->prepare("SELECT COALESCE(SUM(valor),0) as total FROM contas_pagar WHERE status = 'Pago' AND data_pagamento BETWEEN ? AND ?");
                $stmt_desp->bind_param("ss", $inicio_periodo, $fim_periodo);
                $stmt_desp->execute();
                $despesas = (float)$stmt_desp->get_result()->fetch_assoc()['total'];

                $lucro_bruto = $receita - $cmv;
                $resultado = $lucro_bruto - $despesas;
                ?>
                <p style="color:#64748b; font-size:13px; margin-bottom:15px;">
                    ⚠️ O CMV usa o preço de custo <strong>atual</strong> dos produtos (o sistema não guarda o custo histórico por venda) — é uma aproximação, não um valor contábil exato.
                </p>
                <table class="table-erp">
                    <tbody>
                        <tr><td>Receita Bruta de Vendas</td><td class="txt-bold" style="text-align:right;">R$ <?= number_format($receita, 2, ',', '.') ?></td></tr>
                        <tr><td>(-) CMV (Custo da Mercadoria Vendida)</td><td style="text-align:right; color:#ef4444;">R$ <?= number_format($cmv, 2, ',', '.') ?></td></tr>
                        <tr style="border-top:2px solid #e2e8f0;"><td class="txt-bold">= Lucro Bruto</td><td class="txt-bold" style="text-align:right;">R$ <?= number_format($lucro_bruto, 2, ',', '.') ?></td></tr>
                        <tr><td>(-) Despesas Pagas no Período (Contas a Pagar)</td><td style="text-align:right; color:#ef4444;">R$ <?= number_format($despesas, 2, ',', '.') ?></td></tr>
                        <tr style="border-top:2px solid #1e293b;"><td class="txt-bold" style="font-size:16px;">= Resultado do Período</td><td class="txt-bold" style="text-align:right; font-size:16px; color:<?= $resultado >= 0 ? '#10b981' : '#ef4444' ?>;">R$ <?= number_format($resultado, 2, ',', '.') ?></td></tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
