<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação']);
    exit;
}

if (!isset($_SESSION['caixa_aberto']) || $_SESSION['caixa_aberto'] === false) {
    echo json_encode(['success' => false, 'message' => 'Abra o caixa antes de converter um orçamento em venda.']);
    exit;
}

csrf_verify_json();

$dados = json_decode(file_get_contents('php://input'), true);
$id_orcamento = isset($dados['id']) ? intval($dados['id']) : 0;

if ($id_orcamento <= 0) {
    echo json_encode(['success' => false, 'message' => 'Orçamento inválido.']);
    exit;
}

$mysql->begin_transaction();

try {
    $stmt_orc = $mysql->prepare("SELECT id, id_cliente FROM orcamentos WHERE id = ? AND status = 'Pendente' FOR UPDATE");
    $stmt_orc->bind_param("i", $id_orcamento);
    $stmt_orc->execute();
    $orcamento = $stmt_orc->get_result()->fetch_assoc();

    if (!$orcamento) {
        throw new Exception("Orçamento não encontrado ou já finalizado.");
    }

    $stmt_itens = $mysql->prepare("SELECT id_produto, quantidade FROM orcamento_itens WHERE id_orcamento = ?");
    $stmt_itens->bind_param("i", $id_orcamento);
    $stmt_itens->execute();
    $itens_orcamento = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($itens_orcamento)) {
        throw new Exception("Orçamento não possui itens.");
    }

    $usuario_id = $_SESSION['id'];
    $id_caixa = intval($_SESSION['id_caixa_atual'] ?? 1);
    $id_cliente = intval($orcamento['id_cliente'] ?? 1);

    // Preço e estoque revalidados no momento da conversão (podem ter mudado desde a criação do orçamento)
    $itens_calculados = [];
    $total = 0.0;

    foreach ($itens_orcamento as $item) {
        $id_p = intval($item['id_produto']);
        $qtd = intval($item['quantidade']);

        $stmt_p = $mysql->prepare("SELECT preco_venda, quantidade FROM estoque WHERE id = ? AND status = 'ATIVO' FOR UPDATE");
        $stmt_p->bind_param("i", $id_p);
        $stmt_p->execute();
        $produto = $stmt_p->get_result()->fetch_assoc();

        if (!$produto) {
            throw new Exception("Produto #$id_p não está mais disponível.");
        }
        if ($produto['quantidade'] < $qtd) {
            throw new Exception("Estoque insuficiente para o produto #$id_p.");
        }

        $preco_real = (float)$produto['preco_venda'];
        $tot_item = $qtd * $preco_real;
        $total += $tot_item;

        $itens_calculados[] = ['id' => $id_p, 'qtd' => $qtd, 'preco' => $preco_real, 'total' => $tot_item];
    }

    $stmt_venda = $mysql->prepare("INSERT INTO vendas (id_cliente, usuario_id, id_caixa, valor_total, forma_pagamento, tipo_venda, status_entrega, data_venda)
                  VALUES (?, ?, ?, ?, 'Dinheiro', 'Local', 'Pendente', NOW())");
    $stmt_venda->bind_param("iiid", $id_cliente, $usuario_id, $id_caixa, $total);
    if (!$stmt_venda->execute()) {
        throw new Exception("Erro ao registrar a venda.");
    }
    $venda_id = $mysql->insert_id;

    $stmt_item = $mysql->prepare("INSERT INTO venda_itens (id_venda, id_produto, quantidade, preco_unitario, valor_total_item)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt_baixa = $mysql->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ?");

    foreach ($itens_calculados as $item) {
        $stmt_item->bind_param("iiidd", $venda_id, $item['id'], $item['qtd'], $item['preco'], $item['total']);
        if (!$stmt_item->execute()) {
            throw new Exception("Erro ao registrar item da venda.");
        }
        $stmt_baixa->bind_param("iii", $item['qtd'], $item['id'], $item['qtd']);
        if (!$stmt_baixa->execute() || $stmt_baixa->affected_rows === 0) {
            throw new Exception("Erro ao baixar estoque do produto #{$item['id']}.");
        }
    }

    $obs = "Venda #$venda_id (via orçamento #$id_orcamento)";
    $stmt_caixa = $mysql->prepare("INSERT INTO movimentacoes_caixa (caixa_id, tipo, origem, forma_pagamento, valor, observacao)
                                VALUES (?, 'ENTRADA', 'Venda', 'Dinheiro', ?, ?)");
    $stmt_caixa->bind_param("ids", $id_caixa, $total, $obs);
    if (!$stmt_caixa->execute()) {
        throw new Exception("Erro ao registrar a movimentação de caixa.");
    }

    $stmt_status = $mysql->prepare("UPDATE orcamentos SET status = 'Aprovado' WHERE id = ?");
    $stmt_status->bind_param("i", $id_orcamento);
    $stmt_status->execute();

    $mysql->commit();
    registrar_log($mysql, 'converter_orcamento_venda', 'orcamentos', $id_orcamento, "Gerou venda #$venda_id");
    echo json_encode(['success' => true, 'venda_id' => $venda_id]);

} catch (Exception $e) {
    $mysql->rollback();
    error_log("converter_orcamento_venda.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
