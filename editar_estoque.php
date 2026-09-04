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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt_get = $mysql->prepare("SELECT * FROM estoque WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $dados = $stmt_get->get_result()->fetch_assoc();
}

if (!$dados) {
    header("Location: estoque.php?erro=produto_nao_encontrado");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar'])) {
    csrf_verify_form();

    // Campos de texto/data
    $campos_texto = [
        'nome', 'descricao', 'codigo_produto', 'unidade', 'fornecedor', 'localizacao',
        'codigo_barras', 'categoria', 'fabricante', 'codigo_produto_fornecedor',
        'ncm', 'cfop', 'subcategoria', 'marca', 'modelo', 'cst_csosn', 'data_validade', 'lote'
    ];
    // Campos numéricos decimais
    $campos_decimais = [
        'quantidade', 'preco_custo', 'ipi', 'substituicao_tributaria', 'margem_lucro',
        'preco_venda', 'peso', 'volume', 'frete', 'qtd_maxima', 'qtd_minima',
        'qtd_fornecedor', 'preco_venda_minimo', 'pis_aliquota', 'cofins_aliquota', 'ponto_reposicao'
    ];

    $d = [];
    foreach ($campos_texto as $campo) {
        $d[$campo] = $_POST[$campo] ?? '';
    }
    foreach ($campos_decimais as $campo) {
        $d[$campo] = isset($_POST[$campo]) && $_POST[$campo] !== '' ? (float)$_POST[$campo] : 0;
    }
    $status = ($_POST['status'] ?? 'ATIVO') === 'ATIVO' ? 'ATIVO' : 'INATIVO';
    $origem_produto = isset($_POST['origem_produto']) ? (int)$_POST['origem_produto'] : 0;

    $stmt = $mysql->prepare("UPDATE estoque SET
        nome=?, descricao=?, quantidade=?, codigo_produto=?, unidade=?, preco_custo=?,
        ipi=?, substituicao_tributaria=?, margem_lucro=?, preco_venda=?, fornecedor=?,
        localizacao=?, peso=?, volume=?, frete=?, qtd_maxima=?, qtd_minima=?,
        codigo_barras=?, categoria=?, fabricante=?, status=?, codigo_produto_fornecedor=?,
        qtd_fornecedor=?, ncm=?, cfop=?, subcategoria=?, marca=?, modelo=?,
        preco_venda_minimo=?, cst_csosn=?, origem_produto=?, pis_aliquota=?,
        cofins_aliquota=?, ponto_reposicao=?, data_validade=?, lote=?
        WHERE id = ?");

    $stmt->bind_param(
        "ssdssdddddssdddddsssssdsssssdsidddssi",
        $d['nome'], $d['descricao'], $d['quantidade'], $d['codigo_produto'], $d['unidade'], $d['preco_custo'],
        $d['ipi'], $d['substituicao_tributaria'], $d['margem_lucro'], $d['preco_venda'], $d['fornecedor'],
        $d['localizacao'], $d['peso'], $d['volume'], $d['frete'], $d['qtd_maxima'], $d['qtd_minima'],
        $d['codigo_barras'], $d['categoria'], $d['fabricante'], $status, $d['codigo_produto_fornecedor'],
        $d['qtd_fornecedor'], $d['ncm'], $d['cfop'], $d['subcategoria'], $d['marca'], $d['modelo'],
        $d['preco_venda_minimo'], $d['cst_csosn'], $origem_produto, $d['pis_aliquota'],
        $d['cofins_aliquota'], $d['ponto_reposicao'], $d['data_validade'], $d['lote'], $id
    );

    if ($stmt->execute()) {
        header("Location: estoque.php?sucesso_edit=1");
        exit;
    }
    error_log("editar_estoque.php: " . $mysql->error);
}

$res_categorias = $mysql->query("SELECT nome FROM categorias WHERE status = 1 ORDER BY nome ASC");
$res_marcas = $mysql->query("SELECT nome FROM marcas WHERE status = 1 ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Produto - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_edit.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo">
        <div class="header-edit">
            <h2>✏️ Editando: <?= htmlspecialchars($dados['nome'] ?? 'Produto') ?></h2>
            <a href="estoque.php" class="btn-voltar">⬅ Voltar</a>
        </div>

        <div class="card-erp">
            <form method="post">
                <?php csrf_field(); ?>
                <div class="grid-form">
                    <div class="section-title">1. Identificação</div>
                    <div><label>NOME</label><input type="text" name="nome" class="input-erp" value="<?= htmlspecialchars($dados['nome'] ?? '') ?>"></div>
                    <div><label>SKU/CÓDIGO</label><input type="text" name="codigo_produto" class="input-erp" value="<?= htmlspecialchars($dados['codigo_produto'] ?? '') ?>"></div>
                    <div><label>CÓDIGO DE BARRAS</label><input type="text" name="codigo_barras" class="input-erp" value="<?= htmlspecialchars($dados['codigo_barras'] ?? '') ?>"></div>
                    <div style="grid-column: span 3;"><label>DESCRIÇÃO</label><textarea name="descricao" class="input-erp" rows="2"><?= htmlspecialchars($dados['descricao'] ?? '') ?></textarea></div>

                    <div><label>CATEGORIA</label>
                        <select name="categoria" class="input-erp">
                            <option value="">Sem categoria</option>
                            <?php
                            $tem_categoria = false;
                            while ($c = $res_categorias->fetch_assoc()):
                                if ($c['nome'] === ($dados['categoria'] ?? '')) $tem_categoria = true;
                            ?>
                                <option value="<?= htmlspecialchars($c['nome']) ?>" <?= $c['nome'] === ($dados['categoria'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endwhile; ?>
                            <?php if (!$tem_categoria && !empty($dados['categoria'])): ?>
                                <option value="<?= htmlspecialchars($dados['categoria']) ?>" selected><?= htmlspecialchars($dados['categoria']) ?> (não cadastrada)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div><label>SUBCATEGORIA</label><input type="text" name="subcategoria" class="input-erp" value="<?= htmlspecialchars($dados['subcategoria'] ?? '') ?>"></div>
                    <div><label>MARCA</label>
                        <select name="marca" class="input-erp">
                            <option value="">Sem marca</option>
                            <?php
                            $tem_marca = false;
                            while ($m = $res_marcas->fetch_assoc()):
                                if ($m['nome'] === ($dados['marca'] ?? '')) $tem_marca = true;
                            ?>
                                <option value="<?= htmlspecialchars($m['nome']) ?>" <?= $m['nome'] === ($dados['marca'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                            <?php endwhile; ?>
                            <?php if (!$tem_marca && !empty($dados['marca'])): ?>
                                <option value="<?= htmlspecialchars($dados['marca']) ?>" selected><?= htmlspecialchars($dados['marca']) ?> (não cadastrada)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div><label>MODELO</label><input type="text" name="modelo" class="input-erp" value="<?= htmlspecialchars($dados['modelo'] ?? '') ?>"></div>
                    <div><label>FABRICANTE</label><input type="text" name="fabricante" class="input-erp" value="<?= htmlspecialchars($dados['fabricante'] ?? '') ?>"></div>

                    <div class="section-title">2. Valores e Estoque</div>
                    <div><label>PREÇO CUSTO</label><input type="number" step="0.01" name="preco_custo" class="input-erp" value="<?= (float)($dados['preco_custo'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>PREÇO VENDA</label><input type="number" step="0.01" name="preco_venda" class="input-erp" value="<?= (float)($dados['preco_venda'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>PREÇO VENDA MÍNIMO</label><input type="number" step="0.01" name="preco_venda_minimo" class="input-erp" value="<?= (float)($dados['preco_venda_minimo'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>MARGEM DE LUCRO (%)</label><input type="number" step="0.01" name="margem_lucro" class="input-erp" value="<?= (float)($dados['margem_lucro'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>QUANTIDADE ATUAL</label><input type="number" step="0.01" name="quantidade" class="input-erp" value="<?= (float)($dados['quantidade'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>QTD MÍNIMA</label><input type="number" step="0.01" name="qtd_minima" class="input-erp" value="<?= (float)($dados['qtd_minima'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>QTD MÁXIMA</label><input type="number" step="0.01" name="qtd_maxima" class="input-erp" value="<?= (float)($dados['qtd_maxima'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>PONTO DE REPOSIÇÃO</label><input type="number" step="0.01" name="ponto_reposicao" class="input-erp" value="<?= (float)($dados['ponto_reposicao'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>UNIDADE</label><select name="unidade" class="input-erp"><option value="KG" <?= ($dados["unidade"] ?? "") === "KG" ? "selected" : "" ?>>KG</option><option value="PEÇA" <?= ($dados["unidade"] ?? "") === "PEÇA" ? "selected" : "" ?>>PEÇA</option><option value="ROLO" <?= ($dados["unidade"] ?? "") === "ROLO" ? "selected" : "" ?>>ROLO</option></select></div>
                    <div><label>STATUS</label><select name="status" class="input-erp"><option value="ATIVO" <?= ($dados["status"] ?? "") === "ATIVO" ? "selected" : "" ?>>ATIVO</option><option value="INATIVO" <?= ($dados["status"] ?? "") === "INATIVO" ? "selected" : "" ?>>INATIVO</option></select></div>

                    <div class="section-title">3. Informações Fiscais (NCM/CFOP/IPI)</div>
                    <div><label>NCM</label><input type="text" name="ncm" class="input-erp" value="<?= htmlspecialchars($dados['ncm'] ?? '') ?>"></div>
                    <div><label>CFOP</label><input type="text" name="cfop" class="input-erp" value="<?= htmlspecialchars($dados['cfop'] ?? '') ?>"></div>
                    <div><label>CST/CSOSN</label><input type="text" name="cst_csosn" class="input-erp" value="<?= htmlspecialchars($dados['cst_csosn'] ?? '') ?>"></div>
                    <div><label>ORIGEM DO PRODUTO</label><input type="number" name="origem_produto" class="input-erp" value="<?= (int)($dados['origem_produto'] ?? 0) ?>"></div>
                    <div><label>IPI (%)</label><input type="number" step="0.01" name="ipi" class="input-erp" value="<?= (float)($dados['ipi'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>SUBST. TRIB. (R$)</label><input type="number" step="0.01" name="substituicao_tributaria" class="input-erp" value="<?= (float)($dados['substituicao_tributaria'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>PIS (%)</label><input type="number" step="0.01" name="pis_aliquota" class="input-erp" value="<?= (float)($dados['pis_aliquota'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>COFINS (%)</label><input type="number" step="0.01" name="cofins_aliquota" class="input-erp" value="<?= (float)($dados['cofins_aliquota'] ?? 0) ?>" onfocus="this.select()"></div>

                    <div class="section-title">4. Logística e Fornecedor</div>
                    <div><label>FORNECEDOR</label><input type="text" name="fornecedor" class="input-erp" value="<?= htmlspecialchars($dados['fornecedor'] ?? '') ?>"></div>
                    <div><label>CÓD. PRODUTO NO FORNECEDOR</label><input type="text" name="codigo_produto_fornecedor" class="input-erp" value="<?= htmlspecialchars($dados['codigo_produto_fornecedor'] ?? '') ?>"></div>
                    <div><label>QTD POR EMBALAGEM FORNECEDOR</label><input type="number" step="0.01" name="qtd_fornecedor" class="input-erp" value="<?= (float)($dados['qtd_fornecedor'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>LOCALIZAÇÃO</label><input type="text" name="localizacao" class="input-erp" value="<?= htmlspecialchars($dados['localizacao'] ?? '') ?>"></div>
                    <div><label>PESO (KG)</label><input type="number" step="0.01" name="peso" class="input-erp" value="<?= (float)($dados['peso'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>VOLUME</label><input type="number" step="0.01" name="volume" class="input-erp" value="<?= (float)($dados['volume'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>FRETE (R$)</label><input type="number" step="0.01" name="frete" class="input-erp" value="<?= (float)($dados['frete'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>LOTE</label><input type="text" name="lote" class="input-erp" value="<?= htmlspecialchars($dados['lote'] ?? '') ?>"></div>
                    <div><label>VALIDADE</label><input type="date" name="data_validade" class="input-erp" value="<?= $dados['data_validade'] ?? '' ?>"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="btn_atualizar" class="btn-save">ATUALIZAR DADOS DO PRODUTO</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
