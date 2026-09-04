<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

function limparValorMoeda($valor) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return floatval($valor);
}

$alert_script = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_form();

    $tipo = in_array($_POST['tipo_pessoa'] ?? '', ['PF', 'PJ'], true) ? $_POST['tipo_pessoa'] : 'PF';
    $nome = $_POST['nome'] ?? '';
    $fantasia = $_POST['nome_fantasia'] ?? '';
    $cpf_cnpj = $_POST['cpf_cnpj'] ?? '';
    $email = $_POST['email'] ?? '';
    $celular = $_POST['celular'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';

    $validar_limite = intval($_POST['validar_limite'] ?? 0);
    $limite = ($validar_limite === 1) ? limparValorMoeda($_POST['limite_credito'] ?? '0') : 0;

    $stmt = $mysql->prepare("INSERT INTO clientes (tipo_pessoa, nome, nome_fantasia, cpf_cnpj, email, celular, cep, endereco, numero, bairro, cidade, estado, limite_credito, validar_limite)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssssdi", $tipo, $nome, $fantasia, $cpf_cnpj, $email, $celular, $cep, $endereco, $numero, $bairro, $cidade, $estado, $limite, $validar_limite);

    if ($stmt->execute()) {
        $novo_id = $mysql->insert_id;
        $alert_script = "<script>window.onload = () => { confirmarCadastro(" . json_encode($novo_id) . ", " . json_encode($nome) . "); }</script>";
    }
}

$clientes_json = [];
$res_clientes = $mysql->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
while($row = $res_clientes->fetch_assoc()){
    $clientes_json[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Clientes</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/cliente.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container" style="display:flex;">
    <?php include 'include/sidebar.php'; ?>
    <div class="conteudo" style="flex:1; padding: 25px;">
        
        <div class="card-erp card-border-primary">
            <div class="card-header-toggle" onclick="toggleCard('form_cadastro', 'icon_cad')">
                <div class="header-title">
                    <span>👤</span>
                    <h2 class="title-text">Novo Cadastro de Cliente</h2>
                </div>
                <div class="header-controls">
                    <span id="txt_botao" class="btn-text-info">CLIQUE PARA ABRIR</span>
                    <span id="icon_cad" class="rotate">▼</span>
                </div>
            </div>
            
            <div id="form_cadastro" class="collapsed p-25">
                <form method="post">
                    <?php csrf_field(); ?>
                    <div class="section-form">
                        <label class="section-label">1. Dados Básicos</label>
                        <div class="row">
                            <div class="col">
                                <label>Tipo</label>
                                <select name="tipo_pessoa" id="tipo_pessoa" onchange="toggleCampos(this.value)">
                                    <option value="PF">Pessoa Física (PF)</option>
                                    <option value="PJ">Pessoa Jurídica (PJ)</option>
                                </select>
                            </div>
                            <div class="col flex-2">
                                <label id="label_nome">Nome Completo</label>
                                <input type="text" name="nome" required>
                            </div>
                            <div class="col pj-only" id="box_fantasia">
                                <label>Nome Fantasia</label>
                                <input type="text" name="nome_fantasia">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <label id="label_doc">CPF/CNPJ</label>
                                <input type="text" name="cpf_cnpj" required>
                            </div>
                            <div class="col">
                                <label>Celular</label>
                                <input type="text" name="celular">
                            </div>
                        </div>
                    </div>

                    <div class="section-form">
                        <label class="section-label">2. Endereço</label>
                        <div class="row">
                            <div class="col">
                                <label>CEP</label>
                                <input type="text" name="cep" id="cep" onblur="buscaCEP(this.value)">
                            </div>
                            <div class="col flex-2">
                                <label>Rua</label>
                                <input type="text" name="endereco" id="logradouro">
                            </div>
                            <div class="col w-70 no-flex">
                                <label>Nº</label>
                                <input type="text" name="numero">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col"><label>Bairro</label><input type="text" name="bairro" id="bairro"></div>
                            <div class="col"><label>Cidade</label><input type="text" name="cidade" id="cidade"></div>
                            <div class="col w-70 no-flex"><label>UF</label><input type="text" name="estado" id="uf"></div>
                        </div>
                    </div>

                    <div class="section-form">
                        <label class="section-label">3. Financeiro</label>
                        <div class="row">
                            <div class="col">
                                <label>Limite Status</label>
                                <select name="validar_limite" onchange="toggleLimite(this.value)">
                                    <option value="1">Ativado</option>
                                    <option value="0">Desativado (Livre)</option>
                                </select>
                            </div>
                            <div class="col" id="container_limite">
                                <label>Valor do Limite</label>
                                <input type="text" name="limite_credito" id="limite_credito" class="input-money money-mask" value="0,00">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Finalizar Cadastro</button>
                </form>
            </div>
        </div>

        <div class="card-erp">
            <div class="search-header">
                <h3 class="m-0 color-slate">🔎 Histórico de Compras</h3>
            </div>
            <div class="p-20">
                <div class="search-container">
                    <label>Buscar por Nome ou ID do Cliente</label>
                    <input type="text" id="smart_search" placeholder="Ex: Maria ou #10..." autocomplete="off" class="input-search-big">
                    <div id="search_results" class="search-results"></div>
                </div>

                <div id="box_saldo_devedor" style="display:none; margin: 10px 0; padding: 12px 15px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px;">
                    <strong style="color:#b91c1c;">Saldo devedor (fiado em aberto): <span id="valor_saldo_devedor">R$ 0,00</span></strong>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Qtd</th>
                                <th>Unitário</th>
                                <th>Subtotal</th>
                                <th>Pagamento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="corpo_historico">
                            <tr><td colspan="7" class="td-empty">Digite o nome do cliente para carregar as vendas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>const listaClientes = <?= json_encode($clientes_json) ?>;</script>
<script src="assents/cliente.js"></script>
<?= $alert_script ?>
</body>
</html>