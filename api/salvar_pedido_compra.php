<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'estoque', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta ação']);
    exit;
}

csrf_verify_json();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$usuario_id = $_SESSION['id'];
$id_fornecedor = !empty($input['id_fornecedor']) ? intval($input['id_fornecedor']) : null;
$itens = $input['itens'] ?? [];

if (!$id_fornecedor) {
    echo json_encode(['success' => false, 'message' => 'Selecione um fornecedor.']);
    exit;
}

if (empty($itens)) {
    echo json_encode(['success' => false, 'message' => 'O pedido está vazio.']);
    exit;
}

$mysql->begin_transaction();

try {
    // Confirma que o fornecedor existe e está ativo
    $stmt_f = $mysql->prepare("SELECT id FROM fornecedores WHERE id = ? AND status = 'Ativo'");
    $stmt_f->bind_param("i", $id_fornecedor);
    $stmt_f->execute();
    if (!$stmt_f->get_result()->fetch_assoc()) {
        throw new Exception("Fornecedor inválido.");
    }

    // Preço vem sempre do banco, nunca do cliente, para evitar pedidos com custo adulterado
    $itens_calculados = [];
    $total_pedido = 0;

    foreach ($itens as $i) {
        $prod_id = intval($i['id'] ?? 0);
        $qtd = intval($i['qtd'] ?? 0);
        if ($prod_id <= 0 || $qtd <= 0) {
            throw new Exception("Item inválido no pedido.");
        }

        $stmt_p = $mysql->prepare("SELECT preco_custo FROM estoque WHERE id = ? AND id_fornecedor = ? AND status = 'ATIVO'");
        $stmt_p->bind_param("ii", $prod_id, $id_fornecedor);
        $stmt_p->execute();
        $produto = $stmt_p->get_result()->fetch_assoc();
        if (!$produto) {
            throw new Exception("Produto #$prod_id não pertence a este fornecedor.");
        }

        $preco = (float)$produto['preco_custo'];
        $sub = $preco * $qtd;
        $total_pedido += $sub;

        $itens_calculados[] = ['id' => $prod_id, 'qtd' => $qtd, 'preco' => $preco, 'sub' => $sub];
    }

    $sql = "INSERT INTO pedidos_compra (id_fornecedor, usuario_id, valor_total, status, data_pedido)
            VALUES (?, ?, ?, 'Pendente', NOW())";

    $stmt = $mysql->prepare($sql);
    $stmt->bind_param("iid", $id_fornecedor, $usuario_id, $total_pedido);

    if (!$stmt->execute()) throw new Exception("Falha ao gravar pedido.");

    $id_pedido = $mysql->insert_id;

    $stmt_i = $mysql->prepare("INSERT INTO pedido_compra_itens (id_pedido, id_produto, quantidade, preco_custo, subtotal) VALUES (?, ?, ?, ?, ?)");

    foreach ($itens_calculados as $item) {
        $stmt_i->bind_param("iiidd", $id_pedido, $item['id'], $item['qtd'], $item['preco'], $item['sub']);
        if (!$stmt_i->execute()) throw new Exception("Erro ao registrar item do pedido.");
    }

    // Gera automaticamente a conta a pagar correspondente (vencimento padrão de 30 dias)
    $descricao_cp = "Pedido de compra #$id_pedido";
    $vencimento_cp = date('Y-m-d', strtotime('+30 days'));
    $stmt_cp = $mysql->prepare("INSERT INTO contas_pagar (id_fornecedor, id_pedido_compra, descricao, valor, data_vencimento, status, usuario_id)
                                 VALUES (?, ?, ?, ?, ?, 'Pendente', ?)");
    $stmt_cp->bind_param("iisdsi", $id_fornecedor, $id_pedido, $descricao_cp, $total_pedido, $vencimento_cp, $usuario_id);
    if (!$stmt_cp->execute()) throw new Exception("Erro ao gerar conta a pagar do pedido.");

    $mysql->commit();

    echo json_encode([
        'success' => true,
        'id' => $id_pedido
    ]);

} catch (Exception $e) {
    $mysql->rollback();
    error_log("salvar_pedido_compra.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
