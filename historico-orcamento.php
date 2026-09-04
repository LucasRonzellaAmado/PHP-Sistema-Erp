<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$nivel_atual = strtolower($_SESSION['nivel']);

if (!in_array($nivel_atual, ['gerente', 'vendedor', 'caixa', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$status_filtro = $_GET['status'] ?? '';
$data_inicio = $_GET['inicio'] ?? '';
$data_fim = $_GET['fim'] ?? '';

$condicoes = [];
$params = [];
$types = "";
if ($status_filtro !== '') { $condicoes[] = "o.status = ?"; $params[] = $status_filtro; $types .= "s"; }
if ($data_inicio !== '')   { $condicoes[] = "o.data_emissao >= ?"; $params[] = $data_inicio; $types .= "s"; }
if ($data_fim !== '')      { $condicoes[] = "o.data_emissao <= ?"; $params[] = $data_fim; $types .= "s"; }

$where = count($condicoes) > 0 ? "WHERE " . implode(" AND ", $condicoes) : "";

$sql = "SELECT o.*, u.nome as nome_vendedor, c.nome as nome_cliente
        FROM orcamentos o
        LEFT JOIN usuarios u ON o.usuario_id = u.id
        LEFT JOIN clientes c ON o.id_cliente = c.id
        $where
        ORDER BY o.id DESC";

$stmt = $mysql->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Orçamentos - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/orcamento_historico.css">
</head>
<body>

<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <div class="header-historico">
            <div>
                <h2>📋 Histórico de Orçamentos</h2>
                <p>Gerencie suas propostas comerciais e conversões.</p>
            </div>
            <a href="orcamento.php" class="btn-primary-custom">+ NOVO ORÇAMENTO</a>
        </div>

        <div class="card-erp-filter">
            <form method="GET" class="filter-grid">
                <div class="filter-item">
                    <label>Status</label>
                    <select name="status" class="input-erp">
                        <option value="">Todos os Status</option>
                        <option value="Pendente" <?= $status_filtro == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="Aprovado" <?= $status_filtro == 'Aprovado' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="Cancelado" <?= $status_filtro == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Início</label>
                    <input type="date" name="inicio" class="input-erp" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="filter-item">
                    <label>Fim</label>
                    <input type="date" name="fim" class="input-erp" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="filter-item-btn">
                    <button type="submit" class="btn-filter">FILTRAR</button>
                </div>
            </form>
        </div>

        <div class="card-erp">
            <table class="table-erp">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Emissão</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($o = $res->fetch_assoc()): 
                        $hoje = new DateTime();
                        $validade = new DateTime($o['validade']);
                        $expirado = ($hoje > $validade && $o['status'] == 'Pendente');
                        
                        $status_label = $o['status'];
                        $class = "status-" . strtolower($o['status']);
                        if ($expirado) { $class = "status-expirado"; $status_label = "Expirado"; }
                    ?>
                    <tr>
                        <td><strong>#<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($o['data_emissao'])) ?></td>
                        <td><?= htmlspecialchars($o['nome_cliente'] ?? 'Consumidor Avulso') ?></td>
                        <td><?= htmlspecialchars($o['nome_vendedor'] ?? 'Sistema') ?></td>
                        <td><strong>R$ <?= number_format($o['valor_total'], 2, ',', '.') ?></strong></td>
                        <td>
                            <span class="badge-status <?= $class ?>">
                                <?= strtoupper($status_label) ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <button class="btn-view" data-id="<?= $o['id'] ?>">🔍 Detalhes</button>
                            <?php if($o['status'] == 'Pendente' && !$expirado): ?>
                                <a href="aprovar_orcamento.php?id=<?= (int)$o['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="btn-approve" title="Aprovar">✅</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalDetalhes" class="modal-overlay">
    <div class="modal-card">
        <div id="conteudoModal">
            <p style="text-align:center;">Carregando detalhes...</p>
        </div>
        <div class="modal-footer">
            <button class="btn-print" id="btn-imprimir-js">🖨️ Imprimir</button>
            <button class="btn-close-modal" id="btn-fechar-js">FECHAR</button>
        </div>
    </div>
</div>

<script src="assents/orcamento_historico.js"></script>
</body>
</html>