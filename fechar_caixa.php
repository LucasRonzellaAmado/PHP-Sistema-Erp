<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!in_array($_SESSION['nivel'], ['gerente', 'caixa', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

$caixa_id_sessao = $_SESSION['id_caixa_atual'] ?? null;

function limparValor($valor) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return floatval($valor);
}

$res_caixa = $mysql->query("SELECT * FROM controle_caixas WHERE id = '$caixa_id_sessao' AND status = 'Aberto' LIMIT 1");
$caixa = $res_caixa->fetch_assoc();

if (!$caixa) { 
    header("Location: caixa.php?erro=nenhum_caixa_aberto"); 
    exit; 
}

$caixa_id = $caixa['id'];
$formas_exibicao = ['Dinheiro' => 0, 'Cartão de Crédito' => 0, 'Cartão de Débito' => 0, 'PIX' => 0];

$res_mov = $mysql->query("SELECT forma_pagamento, valor, tipo FROM movimentacoes_caixa WHERE caixa_id = $caixa_id");
$saldo_movimentacoes = 0;

while ($m = $res_mov->fetch_assoc()) {
    $valor = (float)$m['valor'];
    $valor_ajustado = ($m['tipo'] === 'ENTRADA') ? $valor : -$valor;
    $fp = mb_strtoupper($m['forma_pagamento'], 'UTF-8');

    if (strpos($fp, 'DINHEIRO') !== false) $formas_exibicao['Dinheiro'] += $valor_ajustado;
    elseif (strpos($fp, 'CRÉDITO') !== false || strpos($fp, 'CREDITO') !== false) $formas_exibicao['Cartão de Crédito'] += $valor_ajustado;
    elseif (strpos($fp, 'DÉBITO') !== false || strpos($fp, 'DEBITO') !== false) $formas_exibicao['Cartão de Débito'] += $valor_ajustado;
    elseif (strpos($fp, 'PIX') !== false) $formas_exibicao['PIX'] += $valor_ajustado;

    $saldo_movimentacoes += $valor_ajustado;
}

$saldo_esperado = (float)$caixa['valor_inicial'] + $saldo_movimentacoes;

if (isset($_POST['confirmar_fechamento'])) {
    csrf_verify_form();

    $valor_contado = limparValor($_POST['valor_total_final']);
    $data_fechamento = date('Y-m-d H:i:s');

    $sql = "UPDATE controle_caixas SET valor_fechamento_esperado = ?, valor_fechamento_contado = ?, status = 'Fechado', data_fechamento = ? WHERE id = ?";
    $stmt = $mysql->prepare($sql);
    $stmt->bind_param("ddsi", $saldo_esperado, $valor_contado, $data_fechamento, $caixa_id);

    if ($stmt->execute()) {
        $diferenca = $valor_contado - $saldo_esperado;
        registrar_log($mysql, 'fechar_caixa', 'controle_caixas', $caixa_id, "Esperado: R$ " . number_format($saldo_esperado, 2, ',', '.') . ", Contado: R$ " . number_format($valor_contado, 2, ',', '.') . ", Diferença: R$ " . number_format($diferenca, 2, ',', '.'));
        unset($_SESSION['caixa_aberto'], $_SESSION['id_caixa_atual']);
        header("Location: caixa.php?msg=caixa_fechado_sucesso");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fechar Caixa - NexusFlow</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/caixa_fechamento.css">
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    
    <div class="conteudo">
        <div class="header-fechamento">
            <h2>🔒 Fechamento de Turno</h2>
            <p>Compare os valores do sistema com o dinheiro físico no caixa.</p>
        </div>

        <form method="post" id="formFechamento">
            <?php csrf_field(); ?>
            <div class="pdv-grid">
                <div class="col-principal">
                    <div class="card-erp">
                        <h3 class="card-title">📊 RESUMO DO SISTEMA</h3>
                        <table class="tabela-resumo">
                            <tr>
                                <td>Fundo de Caixa (Abertura)</td>
                                <td class="val-resumo">R$ <?= number_format($caixa['valor_inicial'], 2, ',', '.') ?></td>
                            </tr>
                            <?php foreach ($formas_exibicao as $nome => $valor): ?>
                            <tr>
                                <td>Vendas em <?= $nome ?></td>
                                <td class="val-resumo">R$ <?= number_format($valor, 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="linha-total">
                                <td>TOTAL ESPERADO</td>
                                <td class="val-resumo" id="esperado-js" data-valor="<?= $saldo_esperado ?>">R$ <?= number_format($saldo_esperado, 2, ',', '.') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="col-lateral">
                    <div class="card-erp">
                        <h3 class="card-title">💵 CONFERÊNCIA FÍSICA</h3>
                        
                        <div class="input-group">
                            <label>Dinheiro em Espécie</label>
                            <input type="text" class="input-moeda c-input" value="0,00">
                        </div>
                        <div class="input-group">
                            <label>Cartões (Total)</label>
                            <input type="text" class="input-moeda c-input" value="0,00">
                        </div>
                        <div class="input-group">
                            <label>PIX Recebido</label>
                            <input type="text" class="input-moeda c-input" value="0,00">
                        </div>

                        <div class="total-box">
                            <label>TOTAL CONTADO NO CAIXA</label>
                            <h4 id="display-total">R$ 0,00</h4>
                            <input type="hidden" name="valor_total_final" id="valor_total_final" value="0">
                        </div>

                        <div id="status-conferencia"></div>

                        <button type="button" class="btn-finalizar" id="btn-abrir-modal">ENCERRAR CAIXA</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="modalConfirmacao" class="modal-overlay">
    <div class="modal-content">
        <h3>Confirmar Encerramento?</h3>
        <p id="m-resumo"></p>
        <div class="modal-actions">
            <button type="button" class="btn-voltar" id="btn-fechar-modal">VOLTAR</button>
            <button type="button" class="btn-confirmar" id="btn-enviar-fechamento">FECHAR AGORA</button>
        </div>
    </div>
</div>

<script src="assents/fechamento.js"></script>
</body>
</html>