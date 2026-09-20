<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin'])) {
    exit("<p>Sem permissão para esta ação.</p>");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt_nf = $mysql->prepare("SELECT * FROM notas_fiscais WHERE id = ?");
$stmt_nf->bind_param("i", $id);
$stmt_nf->execute();
$nf = $stmt_nf->get_result()->fetch_assoc();

if (!$nf) {
    exit("<p>Nota Fiscal não encontrada.</p>");
}

$stmt_itens = $mysql->prepare("SELECT * FROM nota_fiscal_itens WHERE nota_id = ?");
$stmt_itens->bind_param("i", $id);
$stmt_itens->execute();
$res_itens = $stmt_itens->get_result();
?>

<div class="nf-detalhes">
    <div class="nf-row">
        <div class="nf-col">
            <h3>1. Identificação da Nota</h3>
            <p><strong>Tipo:</strong> <?= htmlspecialchars($nf['tipo_nota'] ?? '') ?></p>
            <p><strong>Número:</strong> <?= htmlspecialchars($nf['numero_nota'] ?? '') ?> | <strong>Série:</strong> <?= htmlspecialchars($nf['serie'] ?? '') ?></p>
            <p><strong>Chave de Acesso:</strong> <?= htmlspecialchars($nf['chave_acesso'] ?? '') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($nf['status'] ?? '') ?></p>
            <p><strong>Emissão:</strong> <?= $nf['data_emissao'] ? date('d/m/Y H:i', strtotime($nf['data_emissao'])) : '---' ?></p>
        </div>
        <div class="nf-col">
            <h3>2. Cliente / Destinatário</h3>
            <p><strong>Nome:</strong> <?= htmlspecialchars($nf['cliente_nome'] ?? '') ?></p>
            <p><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($nf['cliente_documento'] ?? '') ?></p>
        </div>
    </div>

    <hr>

    <h3>3. Itens da Nota</h3>
    <table class="table-itens">
        <thead>
            <tr>
                <th>Cód</th>
                <th>Descrição</th>
                <th>NCM</th>
                <th>CFOP</th>
                <th>Qtd</th>
                <th>Unit</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while($item = $res_itens->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($item['codigo_prod'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['descricao'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['ncm'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['cfop'] ?? '') ?></td>
                <td><?= number_format($item['quantidade'] ?? 0, 2) ?></td>
                <td>R$ <?= number_format($item['valor_unitario'] ?? 0, 2, ',', '.') ?></td>
                <td>R$ <?= number_format($item['subtotal'] ?? 0, 2, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <hr>

    <div class="nf-row">
        <div class="nf-col">
            <h3>4. Impostos (Resumo)</h3>
            <table class="table-resumo">
                <tr><td>Base ICMS:</td><td>R$ <?= number_format($nf['valor_produtos'] ?? 0, 2, ',', '.') ?></td></tr>
                <tr><td>Valor ICMS:</td><td>R$ <?= number_format($nf['valor_icms'] ?? 0, 2, ',', '.') ?></td></tr>
                <tr><td>IPI:</td><td>R$ <?= number_format($nf['valor_ipi'] ?? 0, 2, ',', '.') ?></td></tr>
                <tr><td>PIS:</td><td>R$ <?= number_format($nf['valor_pis'] ?? 0, 2, ',', '.') ?></td></tr>
                <tr><td>COFINS:</td><td>R$ <?= number_format($nf['valor_cofins'] ?? 0, 2, ',', '.') ?></td></tr>
            </table>
        </div>
        <div class="nf-col total-nf-box">
            <h3>5. Totais</h3>
            <p>Produtos: R$ <?= number_format($nf['valor_produtos'] ?? 0, 2, ',', '.') ?></p>
            <p>Descontos: R$ <?= number_format(0, 2, ',', '.') ?></p>
            <h2 style="color: #2563eb;">Total NF: R$ <?= number_format($nf['valor_total_nota'] ?? 0, 2, ',', '.') ?></h2>
        </div>
    </div>
</div>