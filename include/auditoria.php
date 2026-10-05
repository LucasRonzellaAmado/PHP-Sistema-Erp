<?php
function registrar_log($mysql, $acao, $entidade = null, $entidade_id = null, $detalhes = null) {
    $usuario_id = $_SESSION['id'] ?? null;
    $usuario_nome = $_SESSION['nome'] ?? null;
    $empresa_id = $_SESSION['empresa_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = $mysql->prepare("INSERT INTO log_auditoria (usuario_id, usuario_nome, acao, entidade, entidade_id, detalhes, ip, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssissi", $usuario_id, $usuario_nome, $acao, $entidade, $entidade_id, $detalhes, $ip, $empresa_id);
        $stmt->execute();
    } catch (Throwable $e) {
        // Auditoria nunca deve derrubar a ação principal do usuário
        error_log("registrar_log falhou: " . $e->getMessage());
    }
}
