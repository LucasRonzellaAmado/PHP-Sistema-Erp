DELIMITER $$

DROP PROCEDURE IF EXISTS _add_index_if_missing$$
CREATE PROCEDURE _add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl   VARCHAR(255)
)
BEGIN
    DECLARE v_existe INT DEFAULT 0;

    SELECT COUNT(*) INTO v_existe
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index;

    IF v_existe = 0 THEN
        SET @sqlstmt = p_ddl;
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL _add_index_if_missing('estoque', 'idx_estoque_codigo_barras', 'CREATE INDEX idx_estoque_codigo_barras ON estoque (codigo_barras)');
CALL _add_index_if_missing('estoque', 'idx_estoque_status', 'CREATE INDEX idx_estoque_status ON estoque (status)');
CALL _add_index_if_missing('vendas', 'idx_vendas_data_venda', 'CREATE INDEX idx_vendas_data_venda ON vendas (data_venda)');
CALL _add_index_if_missing('vendas', 'idx_vendas_id_cliente', 'CREATE INDEX idx_vendas_id_cliente ON vendas (id_cliente)');
CALL _add_index_if_missing('vendas', 'idx_vendas_status_entrega', 'CREATE INDEX idx_vendas_status_entrega ON vendas (status_entrega)');
CALL _add_index_if_missing('orcamentos', 'idx_orcamentos_status', 'CREATE INDEX idx_orcamentos_status ON orcamentos (status)');
CALL _add_index_if_missing('clientes', 'idx_clientes_nome', 'CREATE INDEX idx_clientes_nome ON clientes (nome)');

DROP PROCEDURE IF EXISTS _add_index_if_missing;

ALTER TABLE estoque MODIFY preco DECIMAL(10,2) NOT NULL DEFAULT 0;
