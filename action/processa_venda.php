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
    $empresa_id = (int)$_SESSION['empresa_id'];
    $cliente_avulso = (int)$_SESSION['cliente_avulso_id'];

    if (empty($_SESSION['caixa_aberto']) || empty($_SESSION['id_caixa_atual'])) {
        throw new Exception("Abra o caixa antes de lançar uma venda.");
    }
    $id_caixa   = intval($_SESSION['id_caixa_atual']);

    $id_cliente  = intval($dados['id_cliente'] ?? $cliente_avulso);
    $stmt_dono = $mysql->prepare("SELECT id FROM clientes WHERE id = ? AND empresa_id = ?");
    $stmt_dono->bind_param("ii", $id_cliente, $empresa_id);
    $stmt_dono->execute();
    if (!$stmt_dono->get_result()->fetch_assoc()) {
        throw new Exception("Cliente inválido.");
    }
    $tipo_venda  = in_array($dados['tipo_venda'] ?? '', $TIPOS_VENDA_VALIDOS, true) ? $dados['tipo_venda'] : 'Local';

    // Forma de pagamento é sempre validada contra o cadastro (nunca aceita string livre do cliente)
    $forma_input = $dados['forma_pagamento'] ?? 'Dinheiro';
    $stmt_fp = $mysql->prepare("SELECT nome, permite_prazo FROM formas_pagamento WHERE nome = ? AND status = 1 AND empresa_id = ?");
    $stmt_fp->bind_param("si", $forma_input, $empresa_id);
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

        $stmt_p = $mysql->prepare("SELECT CASE WHEN preco_venda > 0 THEN preco_venda WHEN preco > 0 THEN preco ELSE 0 END as preco_venda, quantidade FROM estoque WHERE id = ? AND status = 'ATIVO' AND empresa_id = ? FOR UPDATE");
        $stmt_p->bind_param("ii", $id_p, $empresa_id);
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
        if ($id_cliente === $cliente_avulso) {
            throw new Exception("Venda a prazo exige um cliente cadastrado.");
        }

        $stmt_cli = $mysql->prepare("SELECT nome, limite_credito, validar_limite FROM clientes WHERE id = ? AND empresa_id = ?");
        $stmt_cli->bind_param("ii", $id_cliente, $empresa_id);
        $stmt_cli->execute();
        $cliente = $stmt_cli->get_result()->fetch_assoc();

        if (!$cliente) {
            throw new Exception("Cliente inválido.");
        }

        if ((int)$cliente['validar_limite'] === 1) {
            $stmt_deve = $mysql->prepare("SELECT COALESCE(SUM(valor), 0) as em_aberto FROM contas_receber WHERE id_cliente = ? AND status = 'Pendente' AND empresa_id = ?");
            $stmt_deve->bind_param("ii", $id_cliente, $empresa_id);
            $stmt_deve->execute();
            $em_aberto = (float)$stmt_deve->get_result()->fetch_assoc()['em_aberto'];

            if (($em_aberto + $total) > (float)$cliente['limite_credito']) {
                $disponivel = max(0, (float)$cliente['limite_credito'] - $em_aberto);
                throw new Exception("Limite de crédito insuficiente. Disponível: R$ " . number_format($disponivel, 2, ',', '.'));
            }
        }
    }

    // Inserir Venda Principal
    $stmt_venda = $mysql->prepare("INSERT INTO vendas (id_cliente, usuario_id, id_caixa, valor_total, forma_pagamento, tipo_venda, status_entrega, data_venda, empresa_id)
                  VALUES (?, ?, ?, ?, ?, ?, 'Pendente', NOW(), ?)");
    $stmt_venda->bind_param("iiidssi", $id_cliente, $usuario_id, $id_caixa, $total, $forma_pagto, $tipo_venda, $empresa_id);

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

        $stmt_entrega = $mysql->prepare("INSERT INTO venda_entregas (id_venda, logradouro, numero, bairro, valor_frete, empresa_id)
                        VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_entrega->bind_param("isssdi", $venda_id, $rua, $num, $bairro, $frete, $empresa_id);

        if (!$stmt_entrega->execute()) {
            throw new Exception("Erro ao salvar dados de entrega");
        }
    }

    // Inserir Itens e Baixar Estoque (preço e estoque já validados contra o banco acima)
    $stmt_item = $mysql->prepare("INSERT INTO venda_itens (id_venda, id_produto, quantidade, preco_unitario, valor_total_item, empresa_id)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_baixa = $mysql->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE id = ? AND quantidade >= ? AND empresa_id = ?");

    foreach ($itens_calculados as $item) {
        $stmt_item->bind_param("iiiddi", $venda_id, $item['id'], $item['qtd'], $item['preco'], $item['total'], $empresa_id);
        if (!$stmt_item->execute()) {
            error_log("processa_venda.php - insert item: " . $mysql->error);
            throw new Exception("Erro ao registrar item da venda. Tente novamente.");
        }

        $stmt_baixa->bind_param("iiii", $item['qtd'], $item['id'], $item['qtd'], $empresa_id);
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

        $stmt_cr = $mysql->prepare("INSERT INTO contas_receber (id_cliente, id_venda, descricao, valor, data_vencimento, status, forma_pagamento, usuario_id, empresa_id)
                                     VALUES (?, ?, ?, ?, ?, 'Pendente', ?, ?, ?)");
        $stmt_cr->bind_param("iisdssii", $id_cliente, $venda_id, $descricao_cr, $total, $vencimento_cr, $forma_pagto, $usuario_id, $empresa_id);
        if (!$stmt_cr->execute()) {
            error_log("processa_venda.php - insert contas_receber: " . $mysql->error);
            throw new Exception("Erro ao registrar a conta a receber. Tente novamente.");
        }
    } else {
        // Registrar entrada no caixa
        $obs = "Venda #$venda_id";
        $stmt_caixa = $mysql->prepare("INSERT INTO movimentacoes_caixa (caixa_id, tipo, origem, forma_pagamento, valor, observacao, empresa_id)
                                    VALUES (?, 'ENTRADA', 'Venda', ?, ?, ?, ?)");
        $stmt_caixa->bind_param("isdsi", $id_caixa, $forma_pagto, $total, $obs, $empresa_id);
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
