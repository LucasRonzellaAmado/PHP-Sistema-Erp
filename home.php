<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

// 1. Resumo Financeiro do Dia
$vendas_hoje = $mysql->query("SELECT SUM(valor_total) as total FROM vendas WHERE DATE(data_venda) = CURDATE()")->fetch_assoc();
$total_vendas = $vendas_hoje['total'] ?? 0;

// 2. Alertas de Estoque
$estoque_baixo = $mysql->query("SELECT COUNT(*) as total FROM estoque WHERE quantidade <= qtd_minima AND status = 'ATIVO'")->fetch_assoc();

// 3. Orçamentos Pendentes
$orc_res = $mysql->query("SELECT COUNT(*) as total FROM orcamentos WHERE status = 'Aberto'")->fetch_assoc();
$total_orc_pendentes = $orc_res['total'] ?? 0;

// 3b. Financeiro (só para gerente/admin)
$mostra_financeiro = in_array(strtolower($_SESSION['nivel'] ?? ''), ['gerente', 'admin']);
$total_a_pagar_vencido = 0;
$total_a_receber_vencido = 0;
if ($mostra_financeiro) {
    $r1 = $mysql->query("SELECT COALESCE(SUM(valor),0) as total FROM contas_pagar WHERE status = 'Pendente' AND data_vencimento < CURDATE()")->fetch_assoc();
    $total_a_pagar_vencido = (float)$r1['total'];
    $r2 = $mysql->query("SELECT COALESCE(SUM(valor),0) as total FROM contas_receber WHERE status = 'Pendente' AND data_vencimento < CURDATE()")->fetch_assoc();
    $total_a_receber_vencido = (float)$r2['total'];
}

// 4. Lógica do Gráfico de Linha (Faturamento 7 dias) — uma única query agregada em vez de 7 consultas em loop
$dias = [];
$valores = [];
$faturamento_por_dia = [];
$res_fat = $mysql->query("SELECT DATE(data_venda) as dia, SUM(valor_total) as total
                           FROM vendas
                           WHERE data_venda >= CURDATE() - INTERVAL 6 DAY
                           GROUP BY DATE(data_venda)");
while ($row = $res_fat->fetch_assoc()) {
    $faturamento_por_dia[$row['dia']] = (float)$row['total'];
}
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-$i days"));
    $dias[] = date('d/m', strtotime($data));
    $valores[] = $faturamento_por_dia[$data] ?? 0;
}

// 5. Lógica do Gráfico de Rosca (Status Orçamentos)
$stats_res = $mysql->query("SELECT status, COUNT(*) as qtd FROM orcamentos GROUP BY status");
$status_labels = [];
$status_qtds = [];
while($row = $stats_res->fetch_assoc()){
    $status_labels[] = $row['status'];
    $status_qtds[] = (int)$row['qtd'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Dashboard Comercial</title>
    <link rel="stylesheet" href="assents/layout.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 25px; }
        
        .stat-card { background: white; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .stat-card::after { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 5px; background: #2563eb; }
        .stat-card.alerta::after { background: #ef4444; }
        
        .stat-card span { font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card h3 { font-size: 28px; margin: 10px 0; color: #0f172a; font-weight: 800; }
        .stat-card a { font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .stat-card a:hover { text-decoration: underline; }

        .charts-container { display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 25px; margin-top: 30px; }
        .card-grafico { background: white; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-grafico h3 { font-size: 15px; color: #1e293b; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo" style="flex:1; padding: 30px; background: #f8fafc;">
        
        <header style="display:flex; justify-content:space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h2 style="font-size: 24px; color: #0f172a;">Olá, <?= htmlspecialchars(explode(' ', $_SESSION['nome'])[0]) ?> 👋</h2>
                <p style="color: #64748b;">Aqui está o que está acontecendo na sua empresa hoje.</p>
            </div>
            <div style="background: white; padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0; text-align: right;">
                <span style="font-weight: 800; color: #1e293b; display: block;"><?= date('d/m/Y') ?></span>
                <small id="relogio" style="color: #2563eb; font-family: monospace; font-weight: bold;"></small>
            </div>
        </header>

        <div class="dashboard-grid">
            <div class="stat-card">
                <span>💰 Faturamento Hoje</span>
                <h3>R$ <?= number_format($total_vendas, 2, ',', '.') ?></h3>
                <a href="historico-venda.php">Ver relatório →</a>
            </div>

            <div class="stat-card <?= ($estoque_baixo['total'] > 0) ? 'alerta' : '' ?>">
                <span>📦 Estoque Crítico</span>
                <h3><?= $estoque_baixo['total'] ?> <small style="font-size: 14px; color: #94a3b8;">itens</small></h3>
                <a href="estoque.php" style="<?= ($estoque_baixo['total'] > 0) ? 'color:#ef4444' : '' ?>">Repor agora →</a>
            </div>

            <div class="stat-card">
                <span>🧾 Propostas Abertas</span>
                <h3><?= $total_orc_pendentes ?></h3>
                <a href="historico-orcamento.php">Acompanhar vendas →</a>
            </div>

            <?php if ($mostra_financeiro): ?>
            <div class="stat-card <?= $total_a_pagar_vencido > 0 ? 'alerta' : '' ?>">
                <span>💸 Contas a Pagar Atrasadas</span>
                <h3>R$ <?= number_format($total_a_pagar_vencido, 2, ',', '.') ?></h3>
                <a href="contas_pagar.php?status=Atrasado" style="<?= $total_a_pagar_vencido > 0 ? 'color:#ef4444' : '' ?>">Ver contas →</a>
            </div>

            <div class="stat-card <?= $total_a_receber_vencido > 0 ? 'alerta' : '' ?>">
                <span>💵 Contas a Receber Atrasadas</span>
                <h3>R$ <?= number_format($total_a_receber_vencido, 2, ',', '.') ?></h3>
                <a href="contas_receber.php?status=Atrasado" style="<?= $total_a_receber_vencido > 0 ? 'color:#ef4444' : '' ?>">Ver contas →</a>
            </div>
            <?php endif; ?>
        </div>

        

        <div class="charts-container">
            <div class="card-grafico">
                <h3>📈 Desempenho de Vendas (7 dias)</h3>
                <div style="height: 300px;">
                    <canvas id="faturamentoChart"></canvas>
                </div>
            </div>

            <div class="card-grafico">
                <h3>📊 Funil de Orçamentos</h3>
                <div style="height: 300px; display: flex; justify-content: center;">
                    <canvas id="orcamentoChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de Faturamento
const ctxFat = document.getElementById('faturamentoChart').getContext('2d');
new Chart(ctxFat, {
    type: 'line',
    data: {
        labels: <?= json_encode($dias) ?>,
        datasets: [{
            label: 'Vendas Diárias',
            data: <?= json_encode($valores) ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.05)',
            fill: true,
            tension: 0.4,
            borderWidth: 4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#2563eb',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    },
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { borderDash: [5, 5] }, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-br') } },
            x: { grid: { display: false } }
        }
    }
});

// Gráfico de Orçamentos
const ctxOrc = document.getElementById('orcamentoChart').getContext('2d');
new Chart(ctxOrc, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($status_labels) ?>,
        datasets: [{
            data: <?= json_encode($status_qtds) ?>,
            backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#64748b'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } 
        },
        cutout: '70%'
    }
});

// Relógio em tempo real
setInterval(() => {
    document.getElementById('relogio').innerText = new Date().toLocaleTimeString('pt-BR');
}, 1000);
</script>

</body>
</html>