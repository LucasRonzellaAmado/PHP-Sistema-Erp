-- Já confirmado direto no banco: fornecedores.status é ENUM('Ativo','Inativo') e os fornecedores
-- existentes já estão com status = 'Ativo' corretamente. Nada para corrigir aqui — este arquivo
-- fica só como registro. Pode rodar sem risco (não altera nenhum dado, é apenas um SELECT de conferência).

SELECT id, razao_social, status FROM fornecedores;
