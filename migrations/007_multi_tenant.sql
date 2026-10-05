CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_fantasia VARCHAR(150) NOT NULL,
    razao_social VARCHAR(150) NULL,
    cnpj VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    telefone VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    cidade VARCHAR(100) NULL,
    estado CHAR(2) NULL,
    status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
    cliente_avulso_id INT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_empresa_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO empresas (id, nome_fantasia, razao_social, status)
SELECT 1, 'NexusFlow', 'NexusFlow', 'Ativo'
WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE id = 1);

DELIMITER $$

DROP PROCEDURE IF EXISTS _add_empresa_id_if_missing$$
CREATE PROCEDURE _add_empresa_id_if_missing(IN p_table VARCHAR(64))
BEGIN
    DECLARE v_existe INT DEFAULT 0;

    SELECT COUNT(*) INTO v_existe
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = 'empresa_id';

    IF v_existe = 0 THEN
        SET @sqlstmt = CONCAT(
            'ALTER TABLE `', p_table, '` ',
            'ADD COLUMN empresa_id INT NOT NULL DEFAULT 1, ',
            'ADD INDEX idx_', p_table, '_empresa_id (empresa_id), ',
            'ENGINE=InnoDB'
        );
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL _add_empresa_id_if_missing('clientes');
CALL _add_empresa_id_if_missing('fornecedores');
CALL _add_empresa_id_if_missing('fornecedor_contas');
CALL _add_empresa_id_if_missing('estoque');
CALL _add_empresa_id_if_missing('estoque_movimentacao');
CALL _add_empresa_id_if_missing('categorias');
CALL _add_empresa_id_if_missing('marcas');
CALL _add_empresa_id_if_missing('formas_pagamento');
CALL _add_empresa_id_if_missing('vendas');
CALL _add_empresa_id_if_missing('venda_itens');
CALL _add_empresa_id_if_missing('venda_entregas');
CALL _add_empresa_id_if_missing('orcamentos');
CALL _add_empresa_id_if_missing('orcamento_itens');
CALL _add_empresa_id_if_missing('controle_caixas');
CALL _add_empresa_id_if_missing('movimentacoes_caixa');
CALL _add_empresa_id_if_missing('notas_fiscais');
CALL _add_empresa_id_if_missing('nota_fiscal_itens');
CALL _add_empresa_id_if_missing('pedidos_compra');
CALL _add_empresa_id_if_missing('pedido_compra_itens');
CALL _add_empresa_id_if_missing('contas_pagar');
CALL _add_empresa_id_if_missing('contas_receber');
CALL _add_empresa_id_if_missing('log_auditoria');

DROP PROCEDURE IF EXISTS _add_empresa_id_if_missing;

ALTER TABLE venda_entregas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

SET @exist_usu_empresa := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'empresa_id');
SET @sql_usu_empresa := IF(@exist_usu_empresa = 0, 'ALTER TABLE usuarios ADD COLUMN empresa_id INT NULL, ADD INDEX idx_usuarios_empresa_id (empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql_usu_empresa;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE usuarios MODIFY nivel ENUM('admin','vendedor','gerente','estoque','caixa','super_admin') NULL;
ALTER TABLE usuarios ENGINE=InnoDB;

UPDATE usuarios SET empresa_id = 1 WHERE id != 1;
UPDATE usuarios SET nivel = 'super_admin', empresa_id = NULL WHERE id = 1;

UPDATE empresas SET cliente_avulso_id = 1 WHERE id = 1;

SET @exist_cat_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias' AND INDEX_NAME = 'uq_categoria_nome');
SET @sql_cat_uq := IF(@exist_cat_uq > 0, 'ALTER TABLE categorias DROP INDEX uq_categoria_nome, ADD UNIQUE KEY uq_categoria_nome (empresa_id, nome)', 'SELECT 1');
PREPARE stmt FROM @sql_cat_uq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_marca_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marcas' AND INDEX_NAME = 'uq_marca_nome');
SET @sql_marca_uq := IF(@exist_marca_uq > 0, 'ALTER TABLE marcas DROP INDEX uq_marca_nome, ADD UNIQUE KEY uq_marca_nome (empresa_id, nome)', 'SELECT 1');
PREPARE stmt FROM @sql_marca_uq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_fp_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'formas_pagamento' AND INDEX_NAME = 'uq_forma_pagamento_nome');
SET @sql_fp_uq := IF(@exist_fp_uq > 0, 'ALTER TABLE formas_pagamento DROP INDEX uq_forma_pagamento_nome, ADD UNIQUE KEY uq_forma_pagamento_nome (empresa_id, nome)', 'SELECT 1');
PREPARE stmt FROM @sql_fp_uq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_forn_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fornecedores' AND INDEX_NAME = 'idx_documento_unico');
SET @sql_forn_uq := IF(@exist_forn_uq > 0, 'ALTER TABLE fornecedores DROP INDEX idx_documento_unico, ADD UNIQUE KEY idx_documento_unico (empresa_id, documento)', 'SELECT 1');
PREPARE stmt FROM @sql_forn_uq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS login;
