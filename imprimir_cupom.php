<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$id) exit("Venda não encontrada");

$venda = $mysql->query("SELECT v.*, u.nome as vendedor FROM vendas v LEFT JOIN usuarios u ON v.usuario_id = u.id WHERE v.id = $id")->fetch_assoc();

if (!$venda) exit("Venda inexistente");

$query_itens = "SELECT vi.quantidade, vi.preco_unitario, vi.valor_total_item, e.nome AS produto_nome FROM venda_itens vi LEFT JOIN estoque e ON vi.id_produto = e.id WHERE vi.id_venda = $id";
$itens = $mysql->query($query_itens);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cupom Venda #<?= $id ?></title>
    <link rel="stylesheet" href="assents/cupom_termico.css">
</head>
<body>
    <div class="no-print">
        <button class="btn-print" id="btn-imprimir">IMPRIMIR CUPOM</button>
        <button class="btn-print btn-secondary" id="btn-fechar">FECHAR JANELA</button>
        <div class="linha-tracejada"></div>
    </div>
    <div class="cupom-wrapper">
        <div class="text-center">
            <strong class="font-lg">NEXUS FLOW ERP</strong><br>
            SOLUÇÕES EM GESTÃO<br>
            Rua Exemplo, 123 - Americana/SP<br>
            CNPJ: 00.000.000/0001-00<br>
            IE: 123.456.789.110
        </div>
        <div class="linha-tracejada"></div>
        <div class="text-center bold">CUPOM NÃO FISCAL</div>
        <div class="linha-tracejada"></div>
        <div class="font-sm">
            ORDEM: #<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?><br>
            DATA:  <?= date('d/m/Y H:i:s', strtotime($venda['data_venda'])) ?><br>
            VEND:  <?= htmlspecialchars(strtoupper($venda['vendedor'] ?? 'SISTEMA')) ?>
        </div>
        <div class="linha-tracejada"></div>
        <table>
            <thead>
                <tr>
                    <th>DESCRIÇÃO</th>
                    <th class="text-center">QTD</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $itens->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($item['produto_nome'] ?? 'PRODUTO NÃO ENCONTRADO') ?></td>
                    <td class="text-center"><?= number_format($item['quantidade'], 2, ',', '.') ?></td>
                    <td class="text-right">R$ <?= number_format($item['valor_total_item'], 2, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="linha-tracejada"></div>
        <div class="total-container">
            <div class="flex-between">
                <span>TOTAL:</span>
                <span>R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="linha-tracejada"></div>
        <div class="font-sm">
            <strong>FORMA DE PAGAMENTO:</strong><br>
            <?= htmlspecialchars($venda['forma_pagamento'] ?? 'NÃO INFORMADA') ?>
        </div>
        <div class="linha-tracejada"></div>
        <div class="text-center footer-space">
            OBRIGADO PELA PREFERÊNCIA!<br>
            WWW.NEXUSFLOW.COM.BR
        </div>
    </div>
    <script src="assents/cupom_termico.js"></script>
</body>
</html>