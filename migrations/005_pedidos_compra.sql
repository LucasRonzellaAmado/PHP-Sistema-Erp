-- Tabelas que o módulo de Pedido de Compra sempre esperou que existissem, mas nunca existiram
-- no banco real (confirmado via SHOW TABLES) — por isso "Enviar Ordem de Compra" nunca funcionou.

CREATE TABLE IF NOT EXISTS pedidos_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_fornecedor INT NOT NULL,
    usuario_id INT NULL,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pendente','Recebido','Cancelado') NOT NULL DEFAULT 'Pendente',
    data_pedido TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pc_fornecedor (id_fornecedor),
    KEY idx_pc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedido_compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    KEY idx_pci_pedido (id_pedido),
    KEY idx_pci_produto (id_produto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
