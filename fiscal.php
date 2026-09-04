<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

$filtro_status = $_GET['status'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';

$sql = "SELECT * FROM notas_fiscais WHERE 1=1";
$params = [];
$types = "";
if ($filtro_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filtro_status;
    $types .= "s";
}
if ($filtro_cliente !== '') {
    $sql .= " AND cliente_nome LIKE ?";
    $params[] = "%$filtro_cliente%";
    $types .= "s";
}
$sql .= " ORDER BY data_emissao DESC";

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
    <title>NexusFlow - Gestão Fiscal</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/fiscal.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container">
        <?php include 'include/sidebar.php'; ?>
        
        <div class="conteudo">
            <header class="fiscal-header">
                <h1>📑 Painel de Notas Fiscais</h1>
                <button class="btn-export" onclick="exportarMes()">Exportar XMLs (Mês)</button>
            </header>

            <div class="card-erp filters">
                <form method="GET">
                    <input type="text" name="cliente" placeholder="Buscar cliente..." value="<?= htmlspecialchars($filtro_cliente) ?>">
                    <select name="status">
                        <option value="">Todos Status</option>
                        <option value="Autorizada">Autorizada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                    <button type="submit">Filtrar</button>
                </form>
            </div>

            <div class="card-erp">
                <table class="table-fiscal">
                    <thead>
                        <tr>
                            <th>Nº/Série</th>
                            <th>Emissão</th>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th>Arquivos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($nf = $res->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($nf['numero_nota']) ?></strong>/<?= htmlspecialchars($nf['serie']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($nf['data_emissao'])) ?></td>
                            <td><?= htmlspecialchars($nf['cliente_nome']) ?></td>
                            <td>R$ <?= number_format($nf['valor_total_nota'], 2, ',', '.') ?></td>
                            <td><span class="status-badge <?= htmlspecialchars(strtolower($nf['status'])) ?>"><?= htmlspecialchars($nf['status']) ?></span></td>
                            <td class="files-cell">
                                <a href="<?= htmlspecialchars($nf['xml_path']) ?>" download>XML</a>
                                <a href="<?= htmlspecialchars($nf['pdf_path']) ?>" download>PDF</a>
                            </td>
                            <td>
                                <button onclick="detalhesNota(<?= $nf['id'] ?>)" class="btn-icon">👁️</button>
                                <button onclick="cancelarNota(<?= $nf['id'] ?>)" class="btn-icon danger">🚫</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modal_fiscal" class="modal-overlay">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <div id="detalhes_nf_ajax"></div>
        </div>
    </div>

    <script src="assents/fiscal.js"></script>
</body>
</html>