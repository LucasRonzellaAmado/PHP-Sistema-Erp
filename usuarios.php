<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$meu_nivel = strtolower($_SESSION['nivel'] ?? '');
if (!in_array($meu_nivel, ['admin', 'gerente'], true)) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}
$sou_admin = $meu_nivel === 'admin';

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

if ($busca !== '') {
    $like = "%$busca%";
    $stmt = $mysql->prepare("SELECT id, usuario, nome, nivel, status FROM usuarios WHERE nome LIKE ? OR usuario LIKE ? ORDER BY nome ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $mysql->query("SELECT id, usuario, nome, nivel, status FROM usuarios ORDER BY nome ASC");
}

$NIVEIS_LABEL = [
    'admin' => 'Administrador',
    'gerente' => 'Gerente',
    'vendedor' => 'Vendedor',
    'caixa' => 'Caixa',
    'estoque' => 'Estoque',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Usuários - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/estoque_lista.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-estoque">
            <div class="title-group">
                <h1>👤 Usuários do Sistema</h1>
                <p>Gerencie contas de acesso e níveis de permissão</p>
            </div>
            <div class="actions-group">
                <a href="cad_usuario.php" class="btn-novo">+ Novo Usuário</a>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <script>Swal.fire({icon:'success', title:'Sucesso!', text:'Alteração salva com sucesso.', timer:2000, showConfirmButton:false});</script>
        <?php endif; ?>
        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'usuario_existe'): ?>
            <script>Swal.fire({icon:'error', title:'Erro', text:'Já existe um usuário com esse login.'});</script>
        <?php endif; ?>

        <div class="card-erp">
            <div class="filter-bar">
                <form method="GET" action="usuarios.php" class="search-form">
                    <input type="text" name="busca" placeholder="Buscar por nome ou login..." value="<?= htmlspecialchars($busca) ?>">
                    <button type="submit">🔍 Filtrar</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table-erp">
                    <thead>
                        <tr>
                            <th>Login</th>
                            <th>Nome</th>
                            <th>Nível</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while ($row = $res->fetch_assoc()):
                                $ativo = (string)$row['status'] === '1';
                                $nivel_key = strtolower($row['nivel']);
                            ?>
                            <tr>
                                <td class="txt-bold"><?= htmlspecialchars($row['usuario']) ?></td>
                                <td><?= htmlspecialchars($row['nome']) ?></td>
                                <td><span class="badge-categoria"><?= htmlspecialchars($NIVEIS_LABEL[$nivel_key] ?? $row['nivel']) ?></span></td>
                                <td><span class="status-dot <?= $ativo ? 'status-active' : 'status-inactive' ?>"><?= $ativo ? 'ATIVO' : 'INATIVO' ?></span></td>
                                <td class="actions-cell">
                                    <?php if ($sou_admin || $nivel_key !== 'admin'): ?>
                                        <a href="editar_usuario.php?id=<?= (int)$row['id'] ?>" class="btn-edit" title="Editar">✏️</a>
                                        <?php if ($row['id'] != $_SESSION['id']): ?>
                                            <button onclick="confirmarToggle(<?= (int)$row['id'] ?>, <?= $ativo ? 'true' : 'false' ?>)" class="btn-delete" title="<?= $ativo ? 'Desativar' : 'Reativar' ?>">
                                                <?= $ativo ? '🚫' : '✅' ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small style="color:#94a3b8;">Somente admin</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">Nenhum usuário encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarToggle(id, ativo) {
    Swal.fire({
        title: ativo ? 'Desativar usuário?' : 'Reativar usuário?',
        text: ativo ? 'O usuário não conseguirá mais fazer login.' : 'O usuário voltará a conseguir fazer login.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, confirmar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'api/toggle_usuario_status.php?id=' + id + '&csrf=' + encodeURIComponent(window.CSRF_TOKEN);
        }
    });
}
</script>
</body>
</html>
