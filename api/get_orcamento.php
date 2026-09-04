<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

$id = intval($_GET['id']);

$stmt_orc = $mysql->prepare("SELECT o.*, c.nome as cliente_nome, c.telefone, c.email FROM orcamentos o LEFT JOIN clientes c ON o.id_cliente = c.id WHERE o.id = ?");
$stmt_orc->bind_param("i", $id);
$stmt_orc->execute();
$orc = $stmt_orc->get_result()->fetch_assoc();

$stmt_itens = $mysql->prepare("SELECT oi.*, e.nome FROM orcamento_itens oi JOIN estoque e ON oi.id_produto = e.id WHERE oi.id_orcamento = ?");
$stmt_itens->bind_param("i", $id);
$stmt_itens->execute();
$itens = $stmt_itens->get_result();

echo "<h3>Detalhes do Orçamento #" . str_pad($id, 5, '0', STR_PAD_LEFT) . "</h3>";
?>

<div class="row" style="display: flex; gap: 20px;">
    <div class="col" style="flex: 1;">
        <label style="font-weight: bold; display: block; color: #64748b;">Cliente</label>
        <p><strong><?= htmlspecialchars($orc['cliente_nome'] ?? 'Avulso') ?></strong><br><?= htmlspecialchars($orc['email'] ?? '') ?> | <?= htmlspecialchars($orc['telefone'] ?? '') ?></p>
    </div>
    <div class="col" style="flex: 1;">
        <label style="font-weight: bold; display: block; color: #64748b;">Situação</label>
        <p>Status: <strong><?= htmlspecialchars($orc['status']) ?></strong><br>Validade: <?= date('d/m/Y', strtotime($orc['validade'])) ?></p>
    </div>
</div>

<div class="tabela-container" style="margin:15px 0;">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid #e2e8f0;">
                <th style="padding: 10px;">Produto</th>
                <th>Qtd</th>
                <th>Unitário</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while($i = $itens->fetch_assoc()): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 10px;"><?= htmlspecialchars($i['nome']) ?></td>
                <td><?= $i['quantidade'] ?></td>
                <td>R$ <?= number_format($i['preco_unitario'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($i['valor_total_item'], 2, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div class="card-erp" style="background:#f8fafc; border:1px dashed #cbd5e1; padding: 15px; border-radius: 8px;">
    <label style="font-weight: bold; color: #64748b;">Observações e Condições:</label>
    <p style="font-size:13px; color:#475569; margin-top: 5px;"><?= nl2br(htmlspecialchars($orc['condicoes_comerciais'] . "\n" . $orc['observacoes'])) ?></p>
</div>

<div style="display:flex; gap:10px; margin-top:20px;">
    <?php if($orc['status'] == 'Pendente'): ?>
        <button class="btn-finalizar" style="background: #10b981; color: #fff; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;" onclick="converterEmVenda(<?= $id ?>)">✅ CONVERTER EM VENDA</button>
        <button class="btn-remover" style="background:#ef4444; color:#fff; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;" onclick="cancelarOrcamento(<?= $id ?>)">🚫 CANCELAR</button>
    <?php endif; ?>
    <button class="btn-primary" style="background:#2563eb; color: #fff; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;" onclick="window.open('imprimir_orcamento.php?id=<?= $id ?>')">🖨️ IMPRIMIR PDF</button>
</div>