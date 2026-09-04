<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'caixa', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$usuario_id = $_SESSION['id'];

function limparValor($valor) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return floatval($valor);
}

$sql_caixa = "SELECT c.*, u.nome as nome_abertura 
              FROM controle_caixas c 
              LEFT JOIN usuarios u ON c.usuario_id = u.id 
              WHERE c.status = 'Aberto' 
              LIMIT 1";

$res_caixa = $mysql->query($sql_caixa);
$caixa_aberto = $res_caixa->fetch_assoc();

if (isset($_POST['abrir_caixa'])) {
    csrf_verify_form();

    $valor_inicial = limparValor($_POST['valor_inicial']);
    $data_abertura = date('Y-m-d H:i:s');

    $stmt = $mysql->prepare("INSERT INTO controle_caixas (usuario_id, valor_inicial, status, data_abertura) VALUES (?, ?, 'Aberto', ?)");
    $stmt->bind_param("ids", $usuario_id, $valor_inicial, $data_abertura);
    $stmt->execute();
    registrar_log($mysql, 'abrir_caixa', 'controle_caixas', $mysql->insert_id, "Valor inicial: R$ " . number_format($valor_inicial, 2, ',', '.'));

    header("Location: caixa.php");
    exit;
}

if (isset($_POST['movimentar']) && $caixa_aberto) {
    csrf_verify_form();

    $caixa_id = $caixa_aberto['id'];
    $tipo = in_array($_POST['tipo_mov'] ?? '', ['ENTRADA', 'SAÍDA'], true) ? $_POST['tipo_mov'] : 'SAÍDA';
    $origem = $tipo === 'ENTRADA' ? 'Suprimento' : 'Sangria';
    $valor = limparValor($_POST['valor_mov']);
    $obs = $_POST['obs_mov'] ?? '';

    $stmt_mov = $mysql->prepare("INSERT INTO movimentacoes_caixa (caixa_id, tipo, origem, valor, observacao, forma_pagamento)
                VALUES (?, ?, ?, ?, ?, 'Dinheiro')");
    $stmt_mov->bind_param("issds", $caixa_id, $tipo, $origem, $valor, $obs);

    if ($stmt_mov->execute()) {
        registrar_log($mysql, 'movimentacao_caixa_manual', 'movimentacoes_caixa', $mysql->insert_id, "$tipo: R$ " . number_format($valor, 2, ',', '.') . " - $obs");
        header("Location: caixa.php");
        exit;
    }
}

$saldo_atual = 0;
if ($caixa_aberto) {
    $cid = $caixa_aberto['id'];
    $res_soma = $mysql->query("SELECT SUM(CASE WHEN tipo = 'ENTRADA' THEN valor ELSE -valor END) as saldo FROM movimentacoes_caixa WHERE caixa_id = $cid");
    $soma = $res_soma->fetch_assoc();
    $saldo_atual = (float)$caixa_aberto['valor_inicial'] + (float)($soma['saldo'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Gestão de Caixa</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/caixa.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo" style="flex:1; padding: 25px;">
        <div class="header-caixa">
            <h2 style="margin:0;">💰 Gestão de Caixa PDV</h2>
            <?php if ($caixa_aberto): ?>
                <div class="badge-operador">
                    👤 Aberto por: <?= htmlspecialchars($caixa_aberto['nome_abertura']) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$caixa_aberto): ?>
        <div class="card-erp">
            <h3 style="color:#2563eb; margin-bottom:15px;">Abertura de Caixa Obrigatória</h3>
            <form method="post">
                <?php csrf_field(); ?>
                <div class="row">
                    <div class="col">
                        <label>Operador Responsável</label>
                        <input type="text" value="<?= htmlspecialchars($_SESSION['nome']) ?>" disabled class="input-disabled">
                    </div>
                    <div class="col">
                        <label>Valor Inicial em Dinheiro</label>
                        <input type="text" name="valor_inicial" value="0,00" class="input-money money-mask" required>
                    </div>
                    <div class="col btn-align-bottom">
                        <button type="submit" name="abrir_caixa" class="btn-primary" style="width:100%">ABRIR TURNO AGORA</button>
                    </div>
                </div>
            </form>
        </div>

        <?php else: ?>
        <div class="row">
            <div class="col">
                <div class="card-erp card-saldo">
                    <label class="label-white">Saldo em Dinheiro (Em mãos)</label>
                    <span class="saldo-valor">R$ <?= number_format($saldo_atual, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="col">
                <div class="card-erp">
                    <label>CAIXA Nº: <?= $caixa_aberto['id'] ?> | <span class="status-aberto">● ABERTO</span></label>
                    <p class="data-info">Iniciado em: <?= date('d/m/Y H:i', strtotime($caixa_aberto['data_abertura'])) ?></p>
                    <button class="btn-warning" onclick="location.href='fechar_caixa.php'" style="width: 100%;">IR PARA FECHAMENTO</button>
                </div>
            </div>
        </div>

        <div class="card-erp">
            <h3>Retiradas e Entradas (Sangria/Suprimento)</h3>
            <form method="post">
                <?php csrf_field(); ?>
                <div class="row">
                    <div class="col">
                        <label>Tipo de Operação</label>
                        <select name="tipo_mov">
                            <option value="SAÍDA">SAÍDA (Sangria / Retirada)</option>
                            <option value="ENTRADA">ENTRADA (Suprimento / Aporte)</option>
                        </select>
                    </div>
                    <div class="col">
                        <label>Valor (R$)</label>
                        <input type="text" name="valor_mov" value="0,00" class="input-money money-mask" required>
                    </div>
                    <div class="col col-2">
                        <label>Motivo / Observação</label>
                        <input type="text" name="obs_mov" placeholder="Ex: Pagamento de frete ou troco" required>
                    </div>
                    <div class="col btn-align-bottom">
                        <button type="submit" name="movimentar" class="btn-primary">Lançar</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-erp">
            <h3>Últimas Movimentações do Turno</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res_logs = $mysql->query("SELECT * FROM movimentacoes_caixa WHERE caixa_id = {$caixa_aberto['id']} ORDER BY id DESC LIMIT 5");
                    while($m = $res_logs->fetch_assoc()):
                        $cor = ($m['tipo'] == 'ENTRADA') ? '#10b981' : '#ef4444';
                    ?>
                    <tr>
                        <td><?= date('H:i', strtotime($m['data_hora'])) ?></td>
                        <td style="color: <?= $cor ?>; font-weight: bold;"><?= $m['tipo'] ?></td>
                        <td style="font-weight: bold;">R$ <?= number_format($m['valor'], 2, ',', '.') ?></td>
                        <td><small><?= htmlspecialchars($m['observacao']) ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="assents/caixa.js"></script>
</body>
</html>