<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$res_fornecedores = $mysql->query("SELECT id, razao_social FROM fornecedores WHERE status = 'Ativo' ORDER BY razao_social ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Pedido de Compra</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/pedido_compra.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <header class="compra-header">
            <div>
                <h2>📦 Pedido de Compra</h2>
                <p>Solicite reposição de estoque aos seus fornecedores.</p>
            </div>
        </header>

        <div class="pdv-grid">
            <div class="col-principal">
                <div class="card-erp">
                    <h3>1. Selecionar Fornecedor</h3>
                    <select id="select_fornecedor" onchange="carregarProdutosFornecedor(this.value)">
                        <option value="">Clique para selecionar um fornecedor cadastrado...</option>
                        <?php while($f = $res_fornecedores->fetch_assoc()): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['razao_social']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="card-erp">
                    <h3>2. Catálogo de Produtos</h3>
                    <div class="table-container">
                        <table class="table-erp">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="center">Estoque Atual</th>
                                    <th>Preço Custo</th>
                                    <th class="center">Qtd. Pedido</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="lista_produtos_fornecedor">
                                <tr><td colspan="5" class="empty-state">Aguardando seleção de fornecedor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lateral">
                <div class="card-erp summary-card">
                    <h3>3. Resumo do Pedido</h3>
                    
                    <div id="carrinho_compra">
                        <table class="cart-table">
                            <tbody id="itens_carrinho"></tbody>
                        </table>
                    </div>
                    
                    <div class="total-box">
                        <small>Estimativa de Gasto</small>
                        <span id="total_pedido">R$ 0,00</span>
                    </div>

                    <button class="btn-finish" onclick="finalizarPedido()">
                        ENVIAR ORDEM DE COMPRA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assents/pedido_compra.js"></script>
</body>
</html>