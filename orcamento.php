<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$res_clientes = $mysql->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
$res_produtos = $mysql->query("SELECT id, nome,
    CASE WHEN preco_venda > 0 THEN preco_venda WHEN preco > 0 THEN preco ELSE 0 END as preco
    FROM estoque WHERE status = 'ATIVO' ORDER BY nome ASC");
$data_validade = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Novo Orçamento</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/orcamento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="container" style="display: flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <header class="orcamento-header">
            <div>
                <h2>📝 Gestão de Orçamentos</h2>
                <p>Crie propostas comerciais personalizadas.</p>
            </div>
            <div class="vendedor-info">
                <small>Vendedor: <strong><?= htmlspecialchars($_SESSION['nome'] ?? 'Administrador') ?></strong></small><br>
                <span>NEXUS FLOW ERP</span>
            </div>
        </header>

        <div class="pdv-grid">
            <div class="col-principal">
                <div class="card-erp">
                    <h3>1. Dados do Cliente e Validade</h3>
                    <div class="form-row">
                        <div class="form-col col-3">
                            <label>CLIENTE</label>
                            <select id="id_cliente">
                                <option value="">Cliente Avulso (Não identificado)</option>
                                <?php while($c = $res_clientes->fetch_assoc()): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>VALIDADE</label>
                            <input type="date" id="validade" value="<?= $data_validade ?>">
                        </div>
                    </div>
                </div>

                <div class="card-erp">
                    <h3>2. Itens do Orçamento</h3>
                    <div class="form-row align-end">
                        <div class="form-col col-4">
                            <label>PRODUTO</label>
                            <select id="select_produto">
                                <option value="">Pesquisar produto...</option>
                                <?php 
                                $res_produtos->data_seek(0);
                                while($p = $res_produtos->fetch_assoc()): 
                                ?>
                                    <option value="<?= (int)$p['id'] ?>" data-preco="<?= (float)$p['preco'] ?>" data-nome="<?= htmlspecialchars($p['nome']) ?>">
                                        <?= htmlspecialchars($p['nome']) ?> - R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>QTD</label>
                            <input type="number" id="qtd_item" value="1" min="1">
                        </div>
                        <button type="button" class="btn-primary" onclick="adicionarItemOrcamento()">ADICIONAR</button>
                    </div>

                    <div class="tabela-container">
                        <table class="table-erp" id="tabela_itens_orcamento">
                            <thead>
                                <tr>
                                    <th>PRODUTO</th>
                                    <th class="center">QTD</th>
                                    <th>UNITÁRIO</th>
                                    <th>SUBTOTAL</th>
                                    <th class="center">AÇÃO</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lateral">
                <div class="card-erp sticky">
                    <h3>3. Resumo e Condições</h3>
                    
                    <div class="form-group">
                        <label>Desconto (%)</label>
                        <input type="number" id="desconto_orcamento" value="0" step="0.01" oninput="renderizarTabelaOrcamento()">
                    </div>

                    <div class="form-group">
                        <label>Condições de Pagamento</label>
                        <textarea id="condicoes" rows="2" placeholder="Ex: Entrada + 2x boleto..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Observações</label>
                        <textarea id="obs_orcamento" rows="2" placeholder="Ex: Entrega inclusa..."></textarea>
                    </div>

                    <div class="total-container">
                        <small>TOTAL DA PROPOSTA</small>
                        <span class="valor-total" id="total_orcamento">R$ 0,00</span>
                    </div>

                    <button class="btn-save" onclick="salvarOrcamento()">💾 SALVAR ORÇAMENTO</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assents/orcamento.js"></script>
</body>
</html>