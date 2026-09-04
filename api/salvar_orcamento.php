<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || empty($dados['itens'])) {
    echo json_encode(['success' => false, 'message' => 'Orçamento vazio ou dados inválidos']);
    exit;
}

$usuario_id = $_SESSION['id'];
$id_cliente = !empty($dados['id_cliente']) ? intval($dados['id_cliente']) : null;
$validade = $dados['validade'] ?? date('Y-m-d', strtotime('+7 days'));
$condicoes = $dados['condicoes'] ?? '';
$observacoes = $dados['observacoes'] ?? '';

$mysql->begin_transaction();

try {
    // Preço de cada item vem sempre do banco, nunca do que o navegador enviou
    $itens_calculados = [];
    $total_bruto = 0;

    foreach ($dados['itens'] as $item) {
        $id_p = intval($item['id'] ?? 0);
        $qtd  = intval($item['qtd'] ?? 0);

        if ($id_p <= 0 || $qtd <= 0) {
            throw new Exception("Item inválido no orçamento.");
        }

        $stmt_p = $mysql->prepare("SELECT CASE WHEN preco_venda > 0 THEN preco_venda WHEN preco > 0 THEN preco ELSE 0 END as preco
                                    FROM estoque WHERE id = ? AND status = 'ATIVO'");
        $stmt_p->bind_param("i", $id_p);
        $stmt_p->execute();
        $produto = $stmt_p->get_result()->fetch_assoc();

        if (!$produto) {
            throw new Exception("Produto #$id_p não encontrado ou inativo.");
        }

        $preco_real = (float)$produto['preco'];
        $subtotal = $qtd * $preco_real;
        $total_bruto += $subtotal;

        $itens_calculados[] = ['id' => $id_p, 'qtd' => $qtd, 'preco' => $preco_real, 'subtotal' => $subtotal];
    }

    $desconto_percent = isset($dados['desconto']) ? max(0, min(100, floatval($dados['desconto']))) : 0;
    $total_final = $total_bruto - ($total_bruto * ($desconto_percent / 100));

    $stmt_orc = $mysql->prepare("INSERT INTO orcamentos (id_cliente, usuario_id, status, validade, valor_total, condicoes_comerciais, observacoes, data_emissao)
                                  VALUES (?, ?, 'Pendente', ?, ?, ?, ?, NOW())");
    $stmt_orc->bind_param("iisdss", $id_cliente, $usuario_id, $validade, $total_final, $condicoes, $observacoes);

    if (!$stmt_orc->execute()) {
        throw new Exception("Erro ao registrar orçamento.");
    }

    $id_orcamento = $mysql->insert_id;

    $stmt_item = $mysql->prepare("INSERT INTO orcamento_itens (id_orcamento, id_produto, quantidade, preco_unitario, valor_total_item)
                                   VALUES (?, ?, ?, ?, ?)");

    foreach ($itens_calculados as $item) {
        $stmt_item->bind_param("iiidd", $id_orcamento, $item['id'], $item['qtd'], $item['preco'], $item['subtotal']);
        if (!$stmt_item->execute()) {
            throw new Exception("Erro ao registrar item do orçamento.");
        }
    }

    $mysql->commit();
    echo json_encode(['success' => true, 'id' => $id_orcamento]);

} catch (Exception $e) {
    $mysql->rollback();
    error_log("salvar_orcamento.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
