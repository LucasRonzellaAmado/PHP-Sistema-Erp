-- Módulo financeiro: contas a pagar (fornecedores) e contas a receber (clientes/vendas a prazo)
-- Rode depois de 001_cadastros_mestres.sql

CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_fornecedor INT NULL,
    id_pedido_compra INT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_emissao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_vencimento DATE NOT NULL,
    data_pagamento DATETIME NULL,
    status ENUM('Pendente','Pago','Cancelado') NOT NULL DEFAULT 'Pendente',
    forma_pagamento VARCHAR(50) NULL,
    usuario_id INT NULL,
    KEY idx_cp_fornecedor (id_fornecedor),
    KEY idx_cp_status (status),
    KEY idx_cp_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contas_receber (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NULL,
    id_venda INT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_emissao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_vencimento DATE NOT NULL,
    data_recebimento DATETIME NULL,
    status ENUM('Pendente','Recebido','Cancelado') NOT NULL DEFAULT 'Pendente',
    forma_pagamento VARCHAR(50) NULL,
    usuario_id INT NULL,
    KEY idx_cr_cliente (id_cliente),
    KEY idx_cr_status (status),
    KEY idx_cr_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
