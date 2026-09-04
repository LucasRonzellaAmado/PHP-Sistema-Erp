<?php
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_token_valido() {
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && !empty($enviado) && hash_equals($_SESSION['csrf_token'], $enviado);
}

// Para formulários tradicionais (POST + redirect): interrompe com uma mensagem legível.
function csrf_verify_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_token_valido()) {
        http_response_code(403);
        exit('Sessão expirada ou requisição inválida. Recarregue a página e tente novamente.');
    }
}

// Para endpoints JSON (fetch): interrompe com uma resposta JSON.
function csrf_verify_json() {
    if (!csrf_token_valido()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'success' => false, 'mensagem' => 'Sessão expirada, recarregue a página.', 'message' => 'Sessão expirada, recarregue a página.']);
        exit;
    }
}
