<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$path = file_exists('include/auth.php') ? 'include/' : '';
require_once $path . 'auth.php';
require_once $path . 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $res = $mysql->query("SELECT * FROM estoque WHERE id = $id");
    $dados = $res->fetch_assoc();
}

if (!$dados) {
    header("Location: estoque.php?erro=produto_nao_encontrado");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_atualizar'])) {
    $d = [];
    $campos = [
        'nome', 'descricao', 'quantidade', 'codigo_produto', 'unidade', 'preco_custo',
        'ipi', 'substituicao_tributaria', 'margem_lucro', 'preco_venda', 'fornecedor',
        'localizacao', 'peso', 'volume', 'frete', 'qtd_maxima', 'qtd_minima',
        'codigo_barras', 'categoria', 'fabricante', 'status', 'codigo_produto_fornecedor',
        'qtd_fornecedor', 'ncm', 'cfop', 'subcategoria', 'marca', 'modelo',
        'preco_venda_minimo', 'cst_csosn', 'origem_produto', 'pis_aliquota',
        'cofins_aliquota', 'ponto_reposicao', 'data_validade', 'lote'
    ];

    foreach ($campos as $campo) {
        $valor = isset($_POST[$campo]) ? $_POST[$campo] : '';
        $d[$campo] = $mysql->real_escape_string($valor);
    }

    $sql = "UPDATE estoque SET 
        nome='{$d['nome']}', descricao='{$d['descricao']}', quantidade='".(float)($d['quantidade'] ?: 0)."', 
        codigo_produto='{$d['codigo_produto']}', unidade='{$d['unidade']}', preco_custo='".(float)($d['preco_custo'] ?: 0)."',
        ipi='".(float)($d['ipi'] ?: 0)."', substituicao_tributaria='".(float)($d['substituicao_tributaria'] ?: 0)."', 
        margem_lucro='".(float)($d['margem_lucro'] ?: 0)."', preco_venda='".(float)($d['preco_venda'] ?: 0)."', 
        fornecedor='{$d['fornecedor']}', localizacao='{$d['localizacao']}', peso='".(float)($d['peso'] ?: 0)."', 
        volume='".(float)($d['volume'] ?: 0)."', frete='".(float)($d['frete'] ?: 0)."', qtd_maxima='".(float)($d['qtd_maxima'] ?: 0)."', 
        qtd_minima='".(float)($d['qtd_minima'] ?: 0)."', codigo_barras='{$d['codigo_barras']}', categoria='{$d['categoria']}', 
        fabricante='{$d['fabricante']}', status='{$d['status']}', codigo_produto_fornecedor='{$d['codigo_produto_fornecedor']}',
        qtd_fornecedor='".(float)($d['qtd_fornecedor'] ?: 0)."', ncm='{$d['ncm']}', cfop='{$d['cfop']}', 
        subcategoria='{$d['subcategoria']}', marca='{$d['marca']}', modelo='{$d['modelo']}',
        preco_venda_minimo='".(float)($d['preco_venda_minimo'] ?: 0)."', cst_csosn='{$d['cst_csosn']}', 
        origem_produto='".(int)($d['origem_produto'] ?: 0)."', pis_aliquota='".(float)($d['pis_aliquota'] ?: 0)."',
        cofins_aliquota='".(float)($d['cofins_aliquota'] ?: 0)."', ponto_reposicao='".(float)($d['ponto_reposicao'] ?: 0)."', 
        data_validade='{$d['data_validade']}', lote='{$d['lote']}'
        WHERE id = $id";

    if ($mysql->query($sql)) {
        header("Location: estoque.php?sucesso_edit=1");
        exit;
    }
}
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
                <div class="grid-form">
                    <div class="section-title">1. Identificação</div>
                    <div><label>NOME</label><input type="text" name="nome" class="input-erp" value="<?= htmlspecialchars($dados['nome'] ?? '') ?>"></div>
                    <div><label>SKU/CÓDIGO</label><input type="text" name="codigo_produto" class="input-erp" value="<?= htmlspecialchars($dados['codigo_produto'] ?? '') ?>"></div>
                    <div><label>CÓDIGO DE BARRAS</label><input type="text" name="codigo_barras" class="input-erp" value="<?= htmlspecialchars($dados['codigo_barras'] ?? '') ?>"></div>
                    
                    <div class="section-title">2. Valores e Estoque</div>
                    <div><label>PREÇO CUSTO</label><input type="number" step="0.01" name="preco_custo" class="input-erp" value="<?= (float)($dados['preco_custo'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>PREÇO VENDA</label><input type="number" step="0.01" name="preco_venda" class="input-erp" value="<?= (float)($dados['preco_venda'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>QUANTIDADE ATUAL</label><input type="number" step="0.01" name="quantidade" class="input-erp" value="<?= (float)($dados['quantidade'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>UNIDADE</label><select name="unidade" class="input-erp"><option value="KG" <?= ($dados["unidade"] ?? "") === "KG" ? "selected" : "" ?>>KG</option><option value="PEÇA" <?= ($dados["unidade"] ?? "") === "PEÇA" ? "selected" : "" ?>>PEÇA</option><option value="ROLO" <?= ($dados["unidade"] ?? "") === "ROLO" ? "selected" : "" ?>>ROLO</option></select></div>
                    <div><label>STATUS</label><select name="status" class="input-erp"><option value="ATIVO" <?= ($dados["status"] ?? "") === "ATIVO" ? "selected" : "" ?>>ATIVO</option><option value="INATIVO" <?= ($dados["status"] ?? "") === "INATIVO" ? "selected" : "" ?>>INATIVO</option></select></div>
                    
                    <div class="section-title">3. Informações Fiscais (NCM/CFOP/IPI)</div>
                    <div><label>NCM</label><input type="text" name="ncm" class="input-erp" value="<?= htmlspecialchars($dados['ncm'] ?? '') ?>"></div>
                    <div><label>CFOP</label><input type="text" name="cfop" class="input-erp" value="<?= htmlspecialchars($dados['cfop'] ?? '') ?>"></div>
                    <div><label>IPI (%)</label><input type="number" step="0.01" name="ipi" class="input-erp" value="<?= (float)($dados['ipi'] ?? 0) ?>" onfocus="this.select()"></div>
                    <div><label>SUBST. TRIB. (R$)</label><input type="number" step="0.01" name="substituicao_tributaria" class="input-erp" value="<?= (float)($dados['substituicao_tributaria'] ?? 0) ?>" onfocus="this.select()"></div>

                    <div class="section-title">4. Logística e Outros</div>
                    <div><label>FORNECEDOR</label><input type="text" name="fornecedor" class="input-erp" value="<?= htmlspecialchars($dados['fornecedor'] ?? '') ?>"></div>
                    <div><label>LOCALIZAÇÃO</label><input type="text" name="localizacao" class="input-erp" value="<?= htmlspecialchars($dados['localizacao'] ?? '') ?>"></div>
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