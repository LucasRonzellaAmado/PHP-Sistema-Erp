<?php
require_once (file_exists('include/session.php') ? 'include/' : '') . 'session.php';
iniciar_sessao_segura();

$path = file_exists('include/auth.php') ? 'include/' : '';
require_once $path . 'auth.php';
require_once $path . 'conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$busca = isset($_GET['busca']) ? $mysql->real_escape_string($_GET['busca']) : '';
$where = !empty($busca) ? "WHERE nome LIKE '%$busca%' OR codigo_produto LIKE '%$busca%' OR codigo_barras LIKE '%$busca%'" : "";

$sql = "SELECT id, nome, codigo_produto, quantidade, qtd_minima, preco_venda, categoria, status FROM estoque $where ORDER BY nome ASC";
$res = $mysql->query($sql);

$sucesso = isset($_GET['sucesso_edit']) ? "Produto atualizado com sucesso!" : "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Estoque - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    
    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1>📦 Controle de Estoque</h1>
                <p>Gerencie seus produtos e níveis de inventário</p>
            </div>
            <div class="actions-group">
                <a href="cad_estoque.php" class="btn-novo">+ Novo Produto</a>
            </div>
        </div>

        <?php if ($sucesso): ?>
            <script>
                Swal.fire({ icon: 'success', title: 'Sucesso!', text: '<?= $sucesso ?>', timer: 2000, showConfirmButton: false });
            </script>
        <?php endif; ?>

        <div class="card-erp">
            <div class="filter-bar">
                <form method="GET" action="estoque.php" class="search-form">
                    <input type="text" name="busca" placeholder="Buscar por nome, SKU ou código de barras..." value="<?= htmlspecialchars($busca ?? '') ?>">
                    <button type="submit">🔍 Filtrar</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table-erp">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Qtd Atual</th>
                            <th>Qtd Mín.</th>
                            <th>Preço Venda</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): 
                                $critico = ($row['quantidade'] <= $row['qtd_minima']);
                                $produto_ativo = $row['status'] === 'ATIVO';
                                $status_class = $produto_ativo ? 'status-active' : 'status-inactive';
                            ?>
                                <tr>
                                    <td class="txt-bold">#<?= htmlspecialchars($row['codigo_produto']) ?></td>
                                    <td><?= htmlspecialchars($row['nome'] ?? '') ?></td>
                                    <td><span class="badge-categoria"><?= htmlspecialchars($row['categoria'] ?? 'Sem Categoria') ?></span></td>
                                    <td class="<?= $critico ? 'txt-danger txt-bold' : '' ?>">
                                        <?= number_format($row['quantidade'] ?? 0, 2, ',', '.') ?>
                                        <?= $critico ? ' ⚠️' : '' ?>
                                    </td>
                                    <td><?= number_format($row['qtd_minima'] ?? 0, 2, ',', '.') ?></td>
                                    <td class="txt-primary txt-bold">R$ <?= number_format($row['preco_venda'] ?? 0, 2, ',', '.') ?></td>
                                    <td><span class="status-dot <?= $status_class ?>"><?= $produto_ativo ? 'ATIVO' : 'INATIVO' ?></span></td>
                                    <td class="actions-cell">
                                        <a href="editar_estoque.php?id=<?= (int)$row['id'] ?>" class="btn-edit" title="Editar">✏️</a>
                                        <button onclick="confirmarExclusao(<?= (int)$row['id'] ?>)" class="btn-delete" title="Desativar">🗑️</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">Nenhum produto encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id) {
    Swal.fire({
        title: 'Desativar produto?',
        text: "O produto deixará de aparecer nas vendas e orçamentos. Você pode reativá-lo depois em Editar > Status.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, desativar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'api/deletar_estoque.php?id=' + id + '&csrf=' + encodeURIComponent(window.CSRF_TOKEN);
        }
    })
}
</script>
</body>
</html>