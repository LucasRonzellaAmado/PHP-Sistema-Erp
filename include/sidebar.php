<?php
require_once __DIR__ . '/session.php';
iniciar_sessao_segura();
require_once __DIR__ . '/csrf.php';

$paginaAtual = basename($_SERVER['PHP_SELF']);
$nivel = strtolower($_SESSION['nivel'] ?? '');
?>

<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assents/sidebar.css">

<div class="sidebar">
    <div class="logo">
        <img src="assents/Logo.png" alt="Logo" style="width: 100%; max-width: 150px; height: auto; display: block; margin: 0 auto;">
    </div>

    <nav class="menu">
        <?php if ($nivel === 'super_admin'): ?>
            <a class="<?= $paginaAtual == 'empresas.php' ? 'ativo' : '' ?>" href="empresas.php">
                <i class="bi bi-buildings"></i> <span>Empresas</span>
            </a>
        <?php else: ?>
        <a class="<?= $paginaAtual == 'home.php' ? 'ativo' : '' ?>" href="home.php">
            <i class="bi bi-speedometer2"></i> <span>Home</span>
        </a>

        <?php if (in_array($nivel, ['admin', 'gerente', 'vendedor'])): ?>
            <a class="<?= $paginaAtual == 'venda.php' ? 'ativo' : '' ?>" href="venda.php">
                <i class="bi bi-cart3"></i> <span>Venda</span>
            </a>
            <a class="<?= $paginaAtual == 'orcamento.php' ? 'ativo' : '' ?>" href="orcamento.php">
                <i class="bi bi-file-earmark-text"></i> <span>Orçamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['admin', 'gerente', 'vendedor', 'caixa'])): ?>
            <a class="<?= $paginaAtual == 'historico-orcamento.php' ? 'ativo' : '' ?>" href="historico-orcamento.php">
                <i class="bi bi-clock-history"></i> <span>Histórico Orçamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'caixa', 'vendedor', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'historico-venda.php' ? 'ativo' : '' ?>" href="historico-venda.php">
                <i class="bi bi-receipt"></i> <span>Histórico Vendas</span>
            </a>
            <a class="<?= $paginaAtual == 'entregas.php' ? 'ativo' : '' ?>" href="entregas.php">
                <i class="bi bi-truck"></i> <span>Entregas</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'caixa', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'caixa.php' ? 'ativo' : '' ?>" href="caixa.php">
                <i class="bi bi-cash-stack"></i> <span>Caixa</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'vendedor', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'cliente.php' ? 'ativo' : '' ?>" href="cliente.php">
                <i class="bi bi-people"></i> <span>Cliente</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'estoque', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'estoque.php' ? 'ativo' : '' ?>" href="estoque.php">
                <i class="bi bi-box-seam"></i> <span>Estoque</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'cadastrar_fornecedor.php' ? 'ativo' : '' ?>" href="cadastrar_fornecedor.php">
                <i class="bi bi-building"></i> <span>Fornecedor</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'estoque', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'pedido_compra.php' ? 'ativo' : '' ?>" href="pedido_compra.php">
                <i class="bi bi-clipboard-check"></i> <span>Pedido Compra</span>
            </a>
            <a class="<?= $paginaAtual == 'categorias.php' ? 'ativo' : '' ?>" href="categorias.php">
                <i class="bi bi-tags"></i> <span>Categorias</span>
            </a>
            <a class="<?= $paginaAtual == 'marcas.php' ? 'ativo' : '' ?>" href="marcas.php">
                <i class="bi bi-bookmark"></i> <span>Marcas</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'contas_pagar.php' ? 'ativo' : '' ?>" href="contas_pagar.php">
                <i class="bi bi-arrow-up-circle"></i> <span>Contas a Pagar</span>
            </a>
            <a class="<?= $paginaAtual == 'contas_receber.php' ? 'ativo' : '' ?>" href="contas_receber.php">
                <i class="bi bi-arrow-down-circle"></i> <span>Contas a Receber</span>
            </a>
            <a class="<?= $paginaAtual == 'relatorios.php' ? 'ativo' : '' ?>" href="relatorios.php">
                <i class="bi bi-graph-up"></i> <span>Relatórios</span>
            </a>
        <?php endif; ?>

        <?php if ($nivel === 'admin'): ?>
            <a class="<?= $paginaAtual == 'formas_pagamento.php' ? 'ativo' : '' ?>" href="formas_pagamento.php">
                <i class="bi bi-credit-card"></i> <span>Formas de Pagamento</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'fiscal.php' ? 'ativo' : '' ?>" href="fiscal.php">
                <i class="bi bi-file-earmark-ruled"></i> <span>Notas Fiscais</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($nivel, ['gerente', 'admin'])): ?>
            <a class="<?= $paginaAtual == 'usuarios.php' ? 'ativo' : '' ?>" href="usuarios.php">
                <i class="bi bi-person-badge"></i> <span>Usuários</span>
            </a>
        <?php endif; ?>

        <?php if ($nivel === 'admin'): ?>
            <a class="<?= $paginaAtual == 'auditoria.php' ? 'ativo' : '' ?>" href="auditoria.php">
                <i class="bi bi-shield-check"></i> <span>Auditoria</span>
            </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="rodape">

        <a href="action/logout.php" class="logout">
            <i class="bi bi-box-arrow-right"></i> <span>Sair</span>
        </a>
    </div>
</div>
