<?php
require_once __DIR__ . '/session.php';
iniciar_sessao_segura();
require_once __DIR__ . '/csrf.php';

$paginaAtual = basename($_SERVER['PHP_SELF']);
$nivel = strtolower($_SESSION['nivel'] ?? '');
?>

<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<link rel="stylesheet" href="assents/sidebar.css">

<div class="sidebar">
    <div class="logo">
        <img src="assents/Logo.png" alt="Logo" style="width: 100%; max-width: 150px; height: auto; display: block; margin: 0 auto;">
    </div>

    <nav class="menu">
        <a class="<?= $paginaAtual == 'home.php' ? 'ativo' : '' ?>" href="home.php">
            🏠 <span>Home</span>
        </a>

        <?php if (in_array($nivel, ['admin', 'gerente', 'vendedor'])): ?>
            <a class="<?= $paginaAtual == 'venda.php' ? 'ativo' : '' ?>" href="venda.php">
                🛒 <span>Venda</span>
            </a>
            <a class="<?= $paginaAtual == 'orcamento.php' ? 'ativo' : '' ?>" href="orcamento.php">
                🧾 <span>Orçamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['admin', 'gerente', 'vendedor', 'caixa'])): ?>
            <a class="<?= $paginaAtual == 'historico-orcamento.php' ? 'ativo' : '' ?>" href="historico-orcamento.php">
                📚 <span>Histórico Orçamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'caixa', 'vendedor', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'historico-venda.php' ? 'ativo' : '' ?>" href="historico-venda.php">
                📑 <span>Histórico Vendas</span>
            </a>
            <a class="<?= $paginaAtual == 'entregas.php' ? 'ativo' : '' ?>" href="entregas.php">
                🛵 <span>Entregas</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'caixa', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'caixa.php' ? 'ativo' : '' ?>" href="caixa.php">
                💰 <span>Caixa</span>
            </a>
        <?php endif; ?>
        
        <?php if (in_array($nivel, ['gerente', 'vendedor', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'cliente.php' ? 'ativo' : '' ?>" href="cliente.php">
                🧑 <span>Cliente</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'estoque', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'estoque.php' ? 'ativo' : '' ?>" href="estoque.php">
                📦 <span>Estoque</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'cadastrar_fornecedor.php' ? 'ativo' : '' ?>" href="cadastrar_fornecedor.php">
                🚚 <span>Fornecedor</span>
            </a>
        <?php endif; ?>
        
        <?php if (in_array($nivel, ['gerente', 'estoque', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'pedido_compra.php' ? 'ativo' : '' ?>" href="pedido_compra.php">
                📝 <span>Pedido Compra</span>
            </a>
            <a class="<?= $paginaAtual == 'categorias.php' ? 'ativo' : '' ?>" href="categorias.php">
                🏷️ <span>Categorias</span>
            </a>
            <a class="<?= $paginaAtual == 'marcas.php' ? 'ativo' : '' ?>" href="marcas.php">
                🏭 <span>Marcas</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'contas_pagar.php' ? 'ativo' : '' ?>" href="contas_pagar.php">
                💸 <span>Contas a Pagar</span>
            </a>
            <a class="<?= $paginaAtual == 'contas_receber.php' ? 'ativo' : '' ?>" href="contas_receber.php">
                💵 <span>Contas a Receber</span>
            </a>
            <a class="<?= $paginaAtual == 'relatorios.php' ? 'ativo' : '' ?>" href="relatorios.php">
                📈 <span>Relatórios</span>
            </a>
        <?php endif; ?>

        <?php if ($nivel === 'admin'): ?>
            <a class="<?= $paginaAtual == 'formas_pagamento.php' ? 'ativo' : '' ?>" href="formas_pagamento.php">
                💳 <span>Formas de Pagamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'fiscal.php' ? 'ativo' : '' ?>" href="fiscal.php">
                🏛️ <span>Notas Fiscais</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'usuarios.php' ? 'ativo' : '' ?>" href="usuarios.php">
                🔑 <span>Usuários</span>
            </a>
        <?php endif; ?>

        <?php if ($nivel === 'admin'): ?>
            <a class="<?= $paginaAtual == 'auditoria.php' ? 'ativo' : '' ?>" href="auditoria.php">
                🕵️ <span>Auditoria</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="rodape">

        <a class="<?= $paginaAtual == 'perfil.php' ? 'ativo' : '' ?>" href="perfil.php">
            👤 <span>Meu Perfil</span>
        </a>

                <a href="action/logout.php" class="logout">

                    🚪 <span>Sair</span>

        </a>
    </div>
</div>