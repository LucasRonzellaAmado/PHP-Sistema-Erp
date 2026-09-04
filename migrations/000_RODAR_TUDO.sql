-- ============================================================
-- SCRIPT ÚNICO: roda as migrações 001 a 005 de uma vez só.
-- No DBeaver: abra este arquivo, clique em "Execute SQL Script"
-- (não "Execute SQL Statement") ou use Alt+X para rodar tudo.
-- Pode rodar quantas vezes quiser: todo CREATE TABLE usa
-- "IF NOT EXISTS" e os INSERTs usam "IGNORE", então não duplica nada.
-- ============================================================

-- ===== 001: Cadastros mestres (categorias, marcas, formas de pagamento) =====

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_marca_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formas_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    permite_prazo TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_forma_pagamento_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO formas_pagamento (nome, permite_prazo) VALUES
    ('Dinheiro', 0),
    ('Pix', 0),
    ('Cartão Débito', 0),
    ('Cartão Crédito', 0),
    ('Fiado', 1);

INSERT IGNORE INTO categorias (nome)
    SELECT DISTINCT categoria FROM estoque WHERE categoria IS NOT NULL AND categoria != '';

INSERT IGNORE INTO marcas (nome)
    SELECT DISTINCT marca FROM estoque WHERE marca IS NOT NULL AND marca != '';

-- ===== 002: Financeiro (contas a pagar e a receber) =====

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

-- ===== 003: Status de fornecedores (já confirmado OK no seu banco, só conferência) =====

-- fornecedores.status é ENUM('Ativo','Inativo') e já está correto — nada a corrigir.

-- ===== 004: Log de auditoria =====

CREATE TABLE IF NOT EXISTS log_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario_nome VARCHAR(150) NULL,
    acao VARCHAR(60) NOT NULL,
    entidade VARCHAR(60) NULL,
    entidade_id INT NULL,
    detalhes VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_usuario (usuario_id),
    KEY idx_log_acao (acao),
    KEY idx_log_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 005: Pedido de compra (tabelas que nunca existiram) =====

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

-- ===== Conferência final: liste as tabelas para ver se as 8 novas apareceram =====
SHOW TABLES;
