<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_form();

    $nome = trim($_POST['nome'] ?? '');
    $codigo_produto = trim($_POST['codigo_produto'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $unidade = in_array($_POST['unidade'] ?? '', ['KG', 'PEÇA', 'ROLO'], true) ? $_POST['unidade'] : 'PEÇA';
    $preco_custo = (float)($_POST['preco_custo'] ?? 0);
    $preco_venda = (float)($_POST['preco_venda'] ?? 0);
    $quantidade = (float)($_POST['quantidade'] ?? 0);
    $qtd_minima = (float)($_POST['qtd_minima'] ?? 0);
    $id_fornecedor = !empty($_POST['id_fornecedor']) ? intval($_POST['id_fornecedor']) : null;

    if ($nome === '') {
        $erro = 'Informe o nome do produto.';
    } else {
        $stmt = $mysql->prepare("INSERT INTO estoque (nome, codigo_produto, codigo_barras, categoria, marca, unidade, preco_custo, preco_venda, quantidade, qtd_minima, id_fornecedor, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ATIVO')");
        $stmt->bind_param("ssssssddddi", $nome, $codigo_produto, $codigo_barras, $categoria, $marca, $unidade, $preco_custo, $preco_venda, $quantidade, $qtd_minima, $id_fornecedor);

        if ($stmt->execute()) {
            header("Location: estoque.php?sucesso_edit=1");
            exit;
        }
        $erro = 'Erro ao salvar produto. Verifique os dados informados.';
        error_log("cad_estoque.php: " . $mysql->error);
    }
}

$res_fornecedores = $mysql->query("SELECT id, razao_social FROM fornecedores WHERE status = 'Ativo' ORDER BY razao_social ASC");
$res_categorias = $mysql->query("SELECT nome FROM categorias WHERE status = 1 ORDER BY nome ASC");
$res_marcas = $mysql->query("SELECT nome FROM marcas WHERE status = 1 ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Produto - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo">
        <div class="header-edit">
            <h2>➕ Novo Produto</h2>
            <a href="estoque.php" class="btn-voltar">⬅ Voltar</a>
        </div>

        <div class="card-erp">
            <?php if ($erro): ?>
                <p style="color:#dc2626;"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>

            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form">
                    <div class="section-title">1. Identificação</div>
                    <div><label>NOME *</label><input type="text" name="nome" class="input-erp" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"></div>
                    <div><label>SKU/CÓDIGO</label><input type="text" name="codigo_produto" class="input-erp" value="<?= htmlspecialchars($_POST['codigo_produto'] ?? '') ?>"></div>
                    <div><label>CÓDIGO DE BARRAS</label><input type="text" name="codigo_barras" class="input-erp" value="<?= htmlspecialchars($_POST['codigo_barras'] ?? '') ?>"></div>
                    <div><label>CATEGORIA</label>
                        <select name="categoria" class="input-erp">
                            <option value="">Sem categoria</option>
                            <?php while ($c = $res_categorias->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($c['nome']) ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small><a href="categorias.php">+ gerenciar categorias</a></small>
                    </div>
                    <div><label>MARCA</label>
                        <select name="marca" class="input-erp">
                            <option value="">Sem marca</option>
                            <?php while ($m = $res_marcas->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($m['nome']) ?>"><?= htmlspecialchars($m['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small><a href="marcas.php">+ gerenciar marcas</a></small>
                    </div>

                    <div class="section-title">2. Valores e Estoque</div>
                    <div><label>PREÇO CUSTO</label><input type="number" step="0.01" name="preco_custo" class="input-erp" value="0"></div>
                    <div><label>PREÇO VENDA</label><input type="number" step="0.01" name="preco_venda" class="input-erp" value="0"></div>
                    <div><label>QUANTIDADE INICIAL</label><input type="number" step="0.01" name="quantidade" class="input-erp" value="0"></div>
                    <div><label>QTD MÍNIMA</label><input type="number" step="0.01" name="qtd_minima" class="input-erp" value="0"></div>
                    <div><label>UNIDADE</label>
                        <select name="unidade" class="input-erp">
                            <option value="PEÇA">PEÇA</option>
                            <option value="KG">KG</option>
                            <option value="ROLO">ROLO</option>
                        </select>
                    </div>

                    <div class="section-title">3. Fornecedor</div>
                    <div>
                        <label>FORNECEDOR</label>
                        <select name="id_fornecedor" class="input-erp">
                            <option value="">Nenhum</option>
                            <?php while ($f = $res_fornecedores->fetch_assoc()): ?>
                                <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['razao_social']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">CADASTRAR PRODUTO</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
