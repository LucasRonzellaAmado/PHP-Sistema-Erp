<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!in_array($_SESSION['nivel'], ['gerente', 'admin', 'caixa', 'vendedor'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$status_filtro = $_GET['status'] ?? 'Pendentes';
$where = ($status_filtro === 'Finalizadas') ? "v.status_entrega = 'Entregue'" : "v.status_entrega IN ('Pendente', 'Em Rota')";

$sql = "SELECT v.id, v.data_venda, v.status_entrega, v.valor_total, 
               e.logradouro, e.numero, e.bairro, e.valor_frete, e.entregador,
               c.nome as cliente_nome, c.telefone
        FROM vendas v
        JOIN venda_entregas e ON v.id = e.id_venda
        LEFT JOIN clientes c ON v.id_cliente = c.id
        WHERE $where
        ORDER BY v.data_venda DESC";

$res = $mysql->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Entregas</title>
    <link rel="stylesheet" href="assents/layout.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary: #3b82f6;
            --success: #10b981;
            --bg-body: #f8fafc;
            --border: #e2e8f0;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            background-color: var(--bg-body);
            display: flex; 
        }

        .main-container { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            padding: 40px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .header-page { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header-page h2 { margin: 0; font-size: 28px; font-weight: 800; color: #1e293b; }

        .nav-tabs { display: flex; gap: 5px; background: #e2e8f0; padding: 5px; border-radius: 10px; }
        .tab-btn { 
            padding: 8px 20px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            color: #64748b; 
            border-radius: 8px; 
            transition: 0.2s;
        }
        .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .delivery-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 20px; 
        }

        .delivery-card { 
            background: white; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .delivery-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

        .card-header { padding: 15px 20px; background: #fafafa; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; flex-grow: 1; }
        .card-footer { padding: 15px; background: #fafafa; display: flex; gap: 8px; border-top: 1px solid var(--border); }

        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-Pendente { background: #fff7ed; color: #c2410c; }
        .status-Em-Rota { background: #eff6ff; color: #1d4ed8; }
        .status-Entregue { background: #ecfdf5; color: #047857; }

        .info-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; display: block; }
        .info-value { font-size: 14px; color: #1e293b; font-weight: 600; margin-bottom: 12px; }

        .btn-action { flex: 1; padding: 10px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px; transition: opacity 0.2s; }
        .btn-action:hover { opacity: 0.8; }
        .btn-blue { background: var(--primary); color: white; }
        .btn-green { background: var(--success); color: white; }
        .btn-print { background: #f1f5f9; color: #475569; width: 45px; flex: none; }

        .empty-state { 
            grid-column: 1 / -1; 
            text-align: center; 
            padding: 100px; 
            background: white; 
            border-radius: 15px; 
            border: 2px dashed #e2e8f0;
        }
    </style>
</head>
<body>

    <?php include 'include/sidebar.php'; ?>

    <div class="main-container">
        <header class="header-page">
            <div>
                <h2>🛵 Entregas</h2>
                <p style="margin: 5px 0 0; color: #64748b;">Gerencie o despacho e acompanhamento de pedidos.</p>
            </div>
            
            <div class="nav-tabs">
                <a href="?status=Pendentes" class="tab-btn <?= $status_filtro == 'Pendentes' ? 'active' : '' ?>">Pendentes</a>
                <a href="?status=Finalizadas" class="tab-btn <?= $status_filtro == 'Finalizadas' ? 'active' : '' ?>">Histórico</a>
            </div>
        </header>

        <div class="delivery-grid">
            <?php if ($res->num_rows > 0): ?>
                <?php while($ent = $res->fetch_assoc()): ?>
                    <div class="delivery-card">
                        <div class="card-header">
                            <span style="font-weight: 800;">PEDIDO #<?= (int)$ent['id'] ?></span>
                            <span class="status-pill status-<?= htmlspecialchars(str_replace(' ', '-', $ent['status_entrega'])) ?>">
                                <?= htmlspecialchars($ent['status_entrega']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <span class="info-label">Cliente</span>
                            <div class="info-value"><?= htmlspecialchars($ent['cliente_nome'] ?? '') ?> (<?= htmlspecialchars($ent['telefone'] ?? '') ?>)</div>

                            <span class="info-label">Endereço</span>
                            <div class="info-value"><?= htmlspecialchars($ent['logradouro']) ?>, <?= htmlspecialchars($ent['numero']) ?> - <?= htmlspecialchars($ent['bairro']) ?></div>

                            <?php if(!empty($ent['entregador'])): ?>
                                <span class="info-label">Entregador Responsável</span>
                                <div class="info-value" style="color: var(--primary);">👤 <?= htmlspecialchars($ent['entregador']) ?></div>
                            <?php endif; ?>

                            <div style="display: flex; justify-content: space-between; background: #f8fafc; padding: 10px; border-radius: 8px; margin-top: 10px;">
                                <div><span class="info-label">Frete</span> <b>R$ <?= number_format($ent['valor_frete'], 2, ',', '.') ?></b></div>
                                <div style="text-align: right;"><span class="info-label">Total</span> <b>R$ <?= number_format($ent['valor_total'], 2, ',', '.') ?></b></div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <?php if ($ent['status_entrega'] == 'Pendente'): ?>
                                <button class="btn-action btn-blue" onclick="despacharPedido(<?= $ent['id'] ?>)">DESPACHAR</button>
                            <?php endif; ?>
                            
                            <?php if ($ent['status_entrega'] == 'Em Rota'): ?>
                                <button class="btn-action btn-green" onclick="concluirEntrega(<?= $ent['id'] ?>)">CONCLUIR ENTREGA</button>
                            <?php endif; ?>

                            <button class="btn-action btn-print" onclick="window.open('imprimir_cupom.php?id=<?= $ent['id'] ?>')">🖨️</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <img src="https://cdn-icons-png.flaticon.com/512/4076/4076432.png" width="80" style="opacity: 0.5; margin-bottom: 20px;">
                    <h3 style="color: #94a3b8;">NENHUM PEDIDO ENCONTRADO.</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Função para Despachar (Pedir nome do entregador)
        function despacharPedido(idVenda) {
            Swal.fire({
                title: 'Despachar Pedido #' + idVenda,
                text: 'Informe o nome do entregador:',
                input: 'text',
                inputPlaceholder: 'Nome do motoboy...',
                showCancelButton: true,
                confirmButtonText: 'Confirmar Saída',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Você precisa digitar o nome do entregador!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const entregador = result.value;
                    
                    fetch('action/atualizar_status_entrega.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                        body: JSON.stringify({
                            id_venda: idVenda,
                            entregador: entregador,
                            status: 'Em Rota'
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.sucesso) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Pedido despachado com ' + entregador,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Erro', data.mensagem, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Erro', 'Falha na conexão', 'error'));
                }
            });
        }

        // Função para Concluir (Sem pedir nome, apenas muda status)
        function concluirEntrega(idVenda) {
            Swal.fire({
                title: 'Concluir Entrega?',
                text: "O pedido #" + idVenda + " foi entregue ao cliente?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Sim, Entregue!',
                cancelButtonText: 'Voltar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('action/atualizar_status_entrega.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                        body: JSON.stringify({
                            id_venda: idVenda,
                            status: 'Entregue'
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.sucesso) {
                            Swal.fire('Finalizado!', 'Entrega confirmada.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Erro', data.mensagem, 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>