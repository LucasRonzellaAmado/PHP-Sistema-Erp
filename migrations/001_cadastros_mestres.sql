-- Cadastros mestres: categorias, marcas e formas de pagamento
-- Rode este script uma vez no banco "erp" antes de usar categorias.php, marcas.php e formas_pagamento.php

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

-- Formas de pagamento que o sistema já usava no código (fica tudo configurável a partir de agora)
INSERT IGNORE INTO formas_pagamento (nome, permite_prazo) VALUES
    ('Dinheiro', 0),
    ('Pix', 0),
    ('Cartão Débito', 0),
    ('Cartão Crédito', 0),
    ('Fiado', 1);

-- Opcional: aproveita os valores de texto já usados em estoque.categoria/estoque.marca
-- para popular os cadastros (evita começar do zero). Rode depois de criar as tabelas acima.
INSERT IGNORE INTO categorias (nome)
    SELECT DISTINCT categoria FROM estoque WHERE categoria IS NOT NULL AND categoria != '';

INSERT IGNORE INTO marcas (nome)
    SELECT DISTINCT marca FROM estoque WHERE marca IS NOT NULL AND marca != '';
