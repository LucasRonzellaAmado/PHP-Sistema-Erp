<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

header('Content-Type: application/json');

$id_cliente = isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0;

if ($id_cliente > 0) {
    $sql = "SELECT
                v.id,
                v.data_venda,
                v.forma_pagamento AS metodo_pagamento,
                vi.quantidade,
                vi.preco_unitario AS valor_venda,
                COALESCE(e.nome, CONCAT('Produto #', vi.id_produto)) AS nome_produto
            FROM vendas v
            JOIN venda_itens vi ON vi.id_venda = v.id
            LEFT JOIN estoque e ON vi.id_produto = e.id
            WHERE v.id_cliente = ?
            ORDER BY v.data_venda DESC";

    $stmt = $mysql->prepare($sql);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $res = $stmt->get_result();

    $vendas = [];
    while ($row = $res->fetch_assoc()) {
        $row['data_venda'] = date('d/m/Y H:i', strtotime($row['data_venda']));
        $vendas[] = $row;
    }

    echo json_encode($vendas);
} else {
    echo json_encode([]);
}
