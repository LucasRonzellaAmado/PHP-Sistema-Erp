<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || empty($dados['itens'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Carrinho vazio ou dados inválidos']);
    exit;
}

$mysql->begin_transaction();

try {
    $usuario_id = $_SESSION['id'] ?? 0;
    $id_caixa   = intval($_SESSION['id_caixa_atual'] ?? 1);

    $id_cliente  = intval($dados['id_cliente'] ?? 1);
    $total       = floatval($dados['total']);
    $tipo_venda  = $mysql->real_escape_string($dados['tipo_venda'] ?? 'Local');
    $forma_pagto = $mysql->real_escape_string($dados['forma_pagamento'] ?? 'Dinheiro');

    // Inserir Venda Principal
    $sql_venda = "INSERT INTO vendas (id_cliente, usuario_id, id_caixa, valor_total, forma_pagamento, tipo_venda, status_entrega, data_venda) 
                  VALUES ($id_cliente, $usuario_id, $id_caixa, $total, '$forma_pagto', '$tipo_venda', 'Pendente', NOW())";

    if (!$mysql->query($sql_venda)) {
        throw new Exception("Erro ao inserir venda: " . $mysql->error);
    }

    $venda_id = $mysql->insert_id;

    // Se for Entrega, insere os detalhes logísticos
    if ($tipo_venda === 'Entrega' && isset($dados['entrega'])) {
        $e      = $dados['entrega'];
        $rua    = $mysql->real_escape_string($e['rua']);
        $num    = $mysql->real_escape_string($e['num']);
        $bairro = $mysql->real_escape_string($e['bairro']);
        $frete  = floatval($e['frete']);

        $sql_entrega = "INSERT INTO venda_entregas (id_venda, logradouro, numero, bairro, valor_frete) 
                        VALUES ($venda_id, '$rua', '$num', '$bairro', $frete)";

        if (!$mysql->query($sql_entrega)) {
            throw new Exception("Erro ao salvar dados de entrega");
        }
    }

    // Inserir Itens e Baixar Estoque
    foreach ($dados['itens'] as $item) {
        $id_p     = intval($item['id']);
        $qtd      = intval($item['qtd']);
        $pre      = floatval($item['preco']);
        $tot_item = $qtd * $pre;

        $res = $mysql->query("INSERT INTO venda_itens (id_venda, id_produto, quantidade, preco_unitario, valor_total_item) 
                              VALUES ($venda_id, $id_p, $qtd, $pre, $tot_item)");
        if (!$res) throw new Exception("Erro ao inserir item: " . $mysql->error);

        $mysql->query("UPDATE estoque SET quantidade = quantidade - $qtd WHERE id = $id_p");
    }

    // Registrar entrada no caixa
    $obs = $mysql->real_escape_string("Venda #$venda_id");
    $res_caixa = $mysql->query("INSERT INTO movimentacoes_caixa (caixa_id, tipo, origem, forma_pagamento, valor, observacao) 
                                VALUES ($id_caixa, 'ENTRADA', 'Venda', '$forma_pagto', $total, '$obs')");
    if (!$res_caixa) throw new Exception("Erro ao registrar no caixa: " . $mysql->error);

    $mysql->commit();
    echo json_encode(['sucesso' => true, 'venda_id' => $venda_id]);

} catch (Exception $e) {
    $mysql->rollback();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}