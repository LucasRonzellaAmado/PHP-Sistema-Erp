<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'caixa', 'admin'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || empty($dados['itens'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Carrinho vazio ou dados inválidos']);
    exit;
}

$TIPOS_VENDA_VALIDOS = ['Local', 'Entrega'];

$mysql->begin_transaction();

try {
    $usuario_id = $_SESSION['id'] ?? 0;
    $id_caixa   = intval($_SESSION['id_caixa_atual'] ?? 1);

    $id_cliente  = intval($dados['id_cliente'] ?? 1);
    $tipo_venda  = in_array($dados['tipo_venda'] ?? '', $TIPOS_VENDA_VALIDOS, true) ? $dados['tipo_venda'] : 'Local';

    // Forma de pagamento é sempre validada contra o cadastro (nunca aceita string livre do cliente)
    $forma_input = $dados['forma_pagamento'] ?? 'Dinheiro';
    $stmt_fp = $mysql->prepare("SELECT nome, permite_prazo FROM formas_pagamento WHERE nome = ? AND status = 1");
    $stmt_fp->bind_param("s", $forma_input);
    $stmt_fp->execute();
    $forma_row = $stmt_fp->get_result()->fetch_assoc();

    if (!$forma_row) {
        throw new Exception("Forma de pagamento inválida.");
    }
    $forma_pagto = $forma_row['nome'];
    $venda_a_prazo = (bool)$forma_row['permite_prazo'];

    // Nunca confiar em preço/quantidade vindos do cliente: recalcula tudo a partir do banco.
    $itens_calculados = [];
    $total = 0.0;

    foreach ($dados['itens'] as $item) {
        $id_p = intval($item['id'] ?? 0);
        $qtd  = intval($item['qtd'] ?? 0);

        if ($id_p <= 0 || $qtd <= 0) {
            throw new Exception("Item inválido no carrinho.");
        }

        $stmt_p = $mysql->prepare("SELECT preco_venda, quantidade FROM estoque WHERE id = ? AND status = 'ATIVO' FOR UPDATE");
        $stmt_p->bind_param("i", $id_p);
        $stmt_p->execute();
        $produto = $stmt_p->get_result()->fetch_assoc();

        if (!$produto) {
            throw new Exception("Produto #$id_p não encontrado ou inativo.");
        }
        if ($produto['quantidade'] < $qtd) {
            throw new Exception("Estoque insuficiente para o produto #$id_p.");
        }

        $preco_real = (float)$produto['preco_venda'];
        $tot_item = $qtd * $preco_real;
        $total += $tot_item;

        $itens_calculados[] = [
            'id' => $id_p,
            'qtd' => $qtd,
            'preco' => $preco_real,
            'total' => $tot_item,
        ];
    }

    $desconto = isset($dados['desconto']) ? max(0, floatval($dados['desconto'])) : 0;
    $frete = 0.0;
    if ($tipo_venda === 'Entrega' && isset($dados['entrega']['frete'])) {
        $frete = max(0, floatval($dados['entrega']['frete']));
    }
    $total = max(0, $total + $frete - $desconto);

    if ($venda_a_prazo) {
        if ($id_cliente <= 1) {
            throw new Exception("Venda a prazo exige um cliente cadastrado.");
        }

        $stmt_cli = $mysql->prepare("SELECT nome, limite_credito, validar_limite FROM clientes WHERE id = ?");
        $stmt_cli->bind_param("i", $id_cliente);
        $stmt_cli->execute();
        $cliente = $stmt_cli->get_result()->fetch_assoc();

        if (!$cliente) {
            throw new Exception("Cliente inválido.");
        }

        if ((int)$cliente['validar_limite'] === 1) {
            $stmt_deve = $mysql->prepare("SELECT COALESCE(SUM(valor), 0) as em_aberto FROM contas_receber WHERE id_cliente = ? AND status = 'Pendente'");
            $stmt_deve->bind_param("i", $id_cliente);
            $stmt_deve->execute();
            $em_aberto = (float)$stmt_deve->get_result()->fetch_assoc()['em_aberto'];

            if (($em_aberto + $total) > (float)$cliente['limite_credito']) {
                $disponivel = max(0, (float)$cliente['limite_credito'] - $em_aberto);
                throw new Exception("Limite de crédito insuficiente. Disponível: R$ " . number_format($disponivel, 2, ',', '.'));
            }
        }
    }

    // Inserir Venda Principal
    $stmt_venda = $mysql->prepare("INSERT INTO vendas (id_cliente, usuario_id, id_caixa, valor_total, forma_pagamento, tipo_venda, status_entrega, data_venda)
                  VALUES (?, ?, ?, ?, ?, ?, 'Pendente', NOW())");
    $stmt_venda->bind_param("iiidss", $id_cliente, $usuario_id, $id_caixa, $total, $forma_pagto, $tipo_venda);

    if (!$stmt_venda->execute()) {
        error_log("processa_venda.php - insert venda: " . $mysql->error);
        throw new Exception("Erro ao registrar a venda. Tente novamente.");
    }

    $venda_id = $mysql->insert_id;

    // Se for Entrega, insere os detalhes logísticos
    if ($tipo_venda === 'Entrega' && isset($dados['entrega'])) {
        $e      = $dados['entrega'];
        $rua    = $e['rua'] ?? '';
        $num    = $e['num'] ?? '';
        $bairro = $e['bairro'] ?? '';

        $stmt_entrega = $mysql->prepare("INSERT INTO venda_entregas (id_venda, logradouro, numero, bairro, valor_frete)
                        VALUES (?, ?, ?, ?, ?)");
        $stmt_entrega->bind_param("isssd", $venda_id, $rua, $num, $bairro, $frete);

        if (!$stmt_entrega->execute()) {
            throw new Exception("Erro ao salvar dados de entrega");
        }
    }

    // Inserir Itens e Baixar Estoque (preço e estoque já validados contra o banco acima)
    $stmt_item = $mysql->prepare("INSERT INTO venda_itens (id_venda, id_produto, quantidade, preco_unitario, valor_total_item)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt_baixa = $mysql->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ?");

    foreach ($itens_calculados as $item) {
        $stmt_item->bind_param("iiidd", $venda_id, $item['id'], $item['qtd'], $item['preco'], $item['total']);
        if (!$stmt_item->execute()) {
            error_log("processa_venda.php - insert item: " . $mysql->error);
            throw new Exception("Erro ao registrar item da venda. Tente novamente.");
        }

        $stmt_baixa->bind_param("iii", $item['qtd'], $item['id'], $item['qtd']);
        if (!$stmt_baixa->execute() || $stmt_baixa->affected_rows === 0) {
            throw new Exception("Erro ao baixar estoque do produto #{$item['id']}.");
        }
    }

    if ($venda_a_prazo) {
        // Venda a prazo não entra no caixa agora: vira conta a receber, baixada quando o cliente pagar
        $descricao_cr = "Venda #$venda_id" . (!empty($cliente['nome']) ? " - " . $cliente['nome'] : '');
        $vencimento_cr = date('Y-m-d', strtotime('+30 days'));
        if (!empty($dados['vencimento_fiado'])) {
            $data_informada = DateTime::createFromFormat('Y-m-d', $dados['vencimento_fiado']);
            if ($data_informada) {
                $vencimento_cr = $data_informada->format('Y-m-d');
            }
        }

        $stmt_cr = $mysql->prepare("INSERT INTO contas_receber (id_cliente, id_venda, descricao, valor, data_vencimento, status, forma_pagamento, usuario_id)
                                     VALUES (?, ?, ?, ?, ?, 'Pendente', ?, ?)");
        $stmt_cr->bind_param("iisdssi", $id_cliente, $venda_id, $descricao_cr, $total, $vencimento_cr, $forma_pagto, $usuario_id);
        if (!$stmt_cr->execute()) {
            error_log("processa_venda.php - insert contas_receber: " . $mysql->error);
            throw new Exception("Erro ao registrar a conta a receber. Tente novamente.");
        }
    } else {
        // Registrar entrada no caixa
        $obs = "Venda #$venda_id";
        $stmt_caixa = $mysql->prepare("INSERT INTO movimentacoes_caixa (caixa_id, tipo, origem, forma_pagamento, valor, observacao)
                                    VALUES (?, 'ENTRADA', 'Venda', ?, ?, ?)");
        $stmt_caixa->bind_param("isds", $id_caixa, $forma_pagto, $total, $obs);
        if (!$stmt_caixa->execute()) {
            error_log("processa_venda.php - insert caixa: " . $mysql->error);
            throw new Exception("Erro ao registrar a movimentação de caixa. Tente novamente.");
        }
    }

    $mysql->commit();
    registrar_log($mysql, 'venda_finalizada', 'vendas', $venda_id, "Total: R$ " . number_format($total, 2, ',', '.') . ", pagamento: $forma_pagto" . ($venda_a_prazo ? ' (fiado)' : ''));
    echo json_encode(['sucesso' => true, 'venda_id' => $venda_id]);

} catch (Exception $e) {
    $mysql->rollback();
    error_log("processa_venda.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
