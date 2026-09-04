-- Log de auditoria: registra quem fez o quê no sistema (login, vendas, alterações de cadastro,
-- movimentações financeiras). Rode depois das migrações 001-003.

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
