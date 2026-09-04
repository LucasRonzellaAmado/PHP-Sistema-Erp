<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Orçamento inválido");

$stmt_orc = $mysql->prepare("SELECT o.*, c.nome as cliente_nome, c.telefone, c.email, u.nome as vendedor_nome
    FROM orcamentos o
    LEFT JOIN clientes c ON o.id_cliente = c.id
    LEFT JOIN usuarios u ON o.usuario_id = u.id
    WHERE o.id = ?");
$stmt_orc->bind_param("i", $id);
$stmt_orc->execute();
$orc = $stmt_orc->get_result()->fetch_assoc();

if (!$orc) exit("Orçamento não encontrado");

$stmt_itens = $mysql->prepare("SELECT oi.*, e.nome FROM orcamento_itens oi LEFT JOIN estoque e ON oi.id_produto = e.id WHERE oi.id_orcamento = ?");
$stmt_itens->bind_param("i", $id);
$stmt_itens->execute();
$itens = $stmt_itens->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Orçamento #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 30px auto; padding: 0 20px; }
        h1 { font-size: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 13px; }
        .text-right { text-align: right; }
        .total { font-size: 18px; font-weight: bold; text-align: right; margin-top: 15px; }
        .no-print { margin-bottom: 15px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <h1>Orçamento #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?> — NEXUS FLOW ERP</h1>
    <p>
        <strong>Cliente:</strong> <?= htmlspecialchars($orc['cliente_nome'] ?? 'Consumidor Avulso') ?><br>
        <strong>Contato:</strong> <?= htmlspecialchars($orc['email'] ?? '') ?> <?= htmlspecialchars($orc['telefone'] ?? '') ?><br>
        <strong>Vendedor:</strong> <?= htmlspecialchars($orc['vendedor_nome'] ?? 'Sistema') ?><br>
        <strong>Emissão:</strong> <?= date('d/m/Y', strtotime($orc['data_emissao'])) ?> &nbsp;
        <strong>Validade:</strong> <?= date('d/m/Y', strtotime($orc['validade'])) ?><br>
        <strong>Status:</strong> <?= htmlspecialchars($orc['status']) ?>
    </p>

    <table>
        <thead>
            <tr><th>Produto</th><th>Qtd</th><th class="text-right">Unitário</th><th class="text-right">Subtotal</th></tr>
        </thead>
        <tbody>
            <?php while ($i = $itens->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($i['nome'] ?? ('Produto #' . $i['id_produto'])) ?></td>
                <td><?= (int)$i['quantidade'] ?></td>
                <td class="text-right">R$ <?= number_format($i['preco_unitario'], 2, ',', '.') ?></td>
                <td class="text-right">R$ <?= number_format($i['valor_total_item'], 2, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="total">TOTAL: R$ <?= number_format($orc['valor_total'], 2, ',', '.') ?></div>

    <?php if (!empty($orc['condicoes_comerciais']) || !empty($orc['observacoes'])): ?>
    <div style="margin-top:20px; font-size:13px;">
        <strong>Condições e observações:</strong>
        <p><?= nl2br(htmlspecialchars(trim($orc['condicoes_comerciais'] . "\n" . $orc['observacoes']))) ?></p>
    </div>
    <?php endif; ?>
</body>
</html>
