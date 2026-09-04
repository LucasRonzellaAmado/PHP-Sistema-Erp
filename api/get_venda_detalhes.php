<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    exit("<div style='padding:20px; color:red; text-align:center;'>ID de venda inválido.</div>");
}

$venda_query = $mysql->query("SELECT v.*, u.nome as vendedor_nome 
                             FROM vendas v 
                             LEFT JOIN usuarios u ON v.usuario_id = u.id 
                             WHERE v.id = $id");
$venda = $venda_query->fetch_assoc();

if (!$venda) {
    exit("<div style='padding:20px; color:red; text-align:center;'>Venda não encontrada no banco de dados.</div>");
}

$sql_itens = "SELECT vi.*, e.nome as produto_nome 
              FROM venda_itens vi 
              LEFT JOIN estoque e ON vi.id_produto = e.id 
              WHERE vi.id_venda = $id";
$itens = $mysql->query($sql_itens);

$data_venda = date('d/m/Y H:i', strtotime($venda['data_venda']));
$forma_pgto = $venda['forma_pagamento'] ?? 'Não informada';
?>

<div style="font-family: 'Inter', sans-serif; color: #1e293b;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="margin:0; color: #0f172a; font-size: 1.5rem;">Venda #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></h2>
            <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">
                📅 <?= $data_venda ?> | 👤 Vendedor: <?= htmlspecialchars($venda['vendedor_nome'] ?? 'Sistema') ?>
            </p>
        </div>
        <div style="text-align: right;">
            <span style="background: #dcfce7; color: #166534; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                Concluída
            </span>
        </div>
    </div>

    <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="text-align: left; background: #f8fafc; color: #64748b;">
                    <th style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0;">PRODUTO</th>
                    <th style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: center;">QTD</th>
                    <th style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: right;">VALOR UNIT.</th>
                    <th style="padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: right;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php if($itens && $itens->num_rows > 0): ?>
                    <?php while($item = $itens->fetch_assoc()): ?>
                    <tr>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 500;">
                            <?= htmlspecialchars($item['produto_nome'] ?? "Produto ID: ".$item['id_produto']) ?>
                        </td>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                            <?= $item['quantidade'] ?>
                        </td>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #64748b;">
                            R$ <?= number_format($item['preco_unitario'] ?? 0, 2, ',', '.') ?>
                        </td>
                        <td style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: bold;">
                            R$ <?= number_format($item['valor_total_item'] ?? 0, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div style="padding: 15px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
            <small style="color: #64748b; font-weight: bold; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">Forma de Pagamento</small>
            <strong style="font-size: 16px; color: #1e293b;">💳 <?= htmlspecialchars($forma_pgto) ?></strong>
        </div>
        
        <div style="padding: 15px; background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe; text-align: right;">
            <small style="color: #3b82f6; font-weight: bold; text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px;">Valor Total Líquido</small>
            <strong style="font-size: 22px; color: #1d4ed8;">R$ <?= number_format($venda['valor_total'] ?? 0, 2, ',', '.') ?></strong>
        </div>
    </div>

    <div style="margin-top: 25px; display: flex; gap: 10px;">
        <button onclick="window.open('imprimir_cupom.php?id=<?= $id ?>', '_blank')" 
                style="flex: 1; padding: 14px; background: #1e293b; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;">
            🖨️ IMPRIMIR CUPOM
        </button>
    </div>
</div>