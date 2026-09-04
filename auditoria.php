<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || strtolower($_SESSION['nivel']) !== 'admin') {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$acao_filtro = $_GET['acao'] ?? '';
$usuario_filtro = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

$where = [];
$params = [];
$types = '';

if ($acao_filtro !== '') {
    $where[] = "acao = ?";
    $params[] = $acao_filtro;
    $types .= 's';
}
if ($usuario_filtro > 0) {
    $where[] = "usuario_id = ?";
    $params[] = $usuario_filtro;
    $types .= 'i';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt_count = $mysql->prepare("SELECT COUNT(*) as total FROM log_auditoria $where_sql");
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = max(1, ceil($total_registros / $por_pagina));

$sql = "SELECT * FROM log_auditoria $where_sql ORDER BY data_hora DESC LIMIT ? OFFSET ?";
$stmt = $mysql->prepare($sql);
$params_com_paginacao = $params;
$params_com_paginacao[] = $por_pagina;
$params_com_paginacao[] = $offset;
$stmt->bind_param($types . 'ii', ...$params_com_paginacao);
$stmt->execute();
$res = $stmt->get_result();

$res_acoes = $mysql->query("SELECT DISTINCT acao FROM log_auditoria ORDER BY acao ASC");
$res_usuarios = $mysql->query("SELECT id, nome FROM usuarios ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Auditoria - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1>🕵️ Log de Auditoria</h1>
                <p>Registro de ações sensíveis: login, vendas, financeiro, cadastros</p>
            </div>
        </div>

        <div class="card-erp">
            <form method="GET" style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                <select name="acao" class="input-erp">
                    <option value="">Todas as ações</option>
                    <?php while ($a = $res_acoes->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($a['acao']) ?>" <?= $acao_filtro === $a['acao'] ? 'selected' : '' ?>><?= htmlspecialchars($a['acao']) ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="usuario_id" class="input-erp">
                    <option value="">Todos os usuários</option>
                    <?php while ($u = $res_usuarios->fetch_assoc()): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $usuario_filtro === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </form>

            <div class="table-responsive">
                <table class="table-erp">
                    <thead><tr><th>Data/Hora</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i:s', strtotime($row['data_hora'])) ?></td>
                                <td><?= htmlspecialchars($row['usuario_nome'] ?? 'Sistema') ?></td>
                                <td><span class="badge-categoria"><?= htmlspecialchars($row['acao']) ?></span></td>
                                <td><?= htmlspecialchars($row['entidade'] ?? '') ?><?= $row['entidade_id'] ? ' #' . (int)$row['entidade_id'] : '' ?></td>
                                <td><?= htmlspecialchars($row['detalhes'] ?? '') ?></td>
                                <td><small><?= htmlspecialchars($row['ip'] ?? '') ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-state">Nenhum registro encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                <small>Página <?= $pagina ?> de <?= $total_paginas ?> (<?= $total_registros ?> registros)</small>
                <div style="display:flex; gap:10px;">
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=<?= $pagina - 1 ?>&acao=<?= urlencode($acao_filtro) ?>&usuario_id=<?= $usuario_filtro ?>" class="btn-voltar">⬅ Anterior</a>
                    <?php endif; ?>
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&acao=<?= urlencode($acao_filtro) ?>&usuario_id=<?= $usuario_filtro ?>" class="btn-voltar">Próxima ➡</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
