<?php
require_once 'include/auth.php';
require_once 'include/conexao.php';

if (!in_array($_SESSION['nivel'], ['gerente', 'vendedor', 'admin'])) {
    header("Location: home.php?erro=sem_permissao");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify_form();

    $user_id = $_SESSION['id'] ?? 1;

    function limpar($str) {
        return preg_replace('/\D/', '', $str);
    }

    $documento = limpar($_POST['documento']);
    $celular   = limpar($_POST['celular']);
    $cep       = limpar($_POST['cep']);
    $prazo     = (int)$_POST['prazo_entrega_medio'];

    $mysql->begin_transaction();

    try {
        $sql = "INSERT INTO fornecedores (
                    tipo_pessoa, razao_social, nome, nome_fantasia, documento, 
                    celular, email, contato_responsavel, cep, rua, numero, 
                    bairro, cidade, estado, tipo_fornecimento, prazo_entrega_medio, 
                    usuario_cadastrou, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')";
        
        $stmt = $mysql->prepare($sql);
        $stmt->bind_param("ssssssssssssssiii", 
            $_POST['tipo_pessoa'], 
            $_POST['razao_social'], 
            $_POST['razao_social'],
            $_POST['nome_fantasia'], 
            $documento,
            $celular, 
            $_POST['email'], 
            $_POST['contato_responsavel'], 
            $cep,
            $_POST['rua'], 
            $_POST['numero'], 
            $_POST['bairro'], 
            $_POST['cidade'],
            $_POST['estado'], 
            $_POST['tipo_fornecimento'], 
            $prazo, 
            $user_id
        );
        $stmt->execute();
        $id_f = $mysql->insert_id;

        $sql_c = "INSERT INTO fornecedor_contas (id_fornecedor, banco, agencia, conta, chave_pix) VALUES (?, ?, ?, ?, ?)";
        $stmt_c = $mysql->prepare($sql_c);
        $stmt_c->bind_param("issss", $id_f, $_POST['banco'], $_POST['agencia'], $_POST['conta'], $_POST['chave_pix']);
        $stmt_c->execute();

        $mysql->commit();
        $sucesso = true;
    } catch (Exception $e) {
        $mysql->rollback();
        $erro_msg = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>NexusFlow - Cadastro de Fornecedor</title>
    <link rel="stylesheet" href="assents/layout.css">
    <link rel="stylesheet" href="assents/fornecedor.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
</head>
<body>

<div class="container">
    <?php include 'include/sidebar.php'; ?>

    <div class="conteudo">
        <form action="" method="POST">
            <?php csrf_field(); ?>
            <div class="header-form">
                <h2>🚚 Cadastro de Fornecedor</h2>
                <div class="header-actions">
                    <button type="submit" class="btn-finalizar">💾 SALVAR FORNECEDOR</button>
                </div>
            </div>

            <?php if(isset($sucesso)): ?>
                <script>Swal.fire('Sucesso!', 'Fornecedor cadastrado com sucesso!', 'success');</script>
            <?php endif; ?>
            
            <?php if(isset($erro_msg)): ?>
                <div class="alert-error">
                    <b>Erro:</b> <?php echo $erro_msg; ?>
                </div>
            <?php endif; ?>

            <div class="pdv-grid">
                <div class="col-principal">
                    <div class="card-erp">
                        <h3>1. Identificação Principal</h3>
                        <div class="row">
                            <div class="col">
                                <label>Tipo de Pessoa</label>
                                <select name="tipo_pessoa" id="tipo_pessoa" onchange="alternarCampos(this.value)">
                                    <option value="PJ">Pessoa Jurídica (PJ)</option>
                                    <option value="PF">Pessoa Física (PF)</option>
                                </select>
                            </div>
                            <div class="col col-3">
                                <label id="label_nome">Razão Social *</label>
                                <input type="text" name="razao_social" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <label id="label_doc">CNPJ *</label>
                                <input type="text" name="documento" placeholder="Apenas números" required>
                            </div>
                            <div class="col">
                                <label>Nome Fantasia</label>
                                <input type="text" name="nome_fantasia">
                            </div>
                        </div>
                    </div>

                    <div class="card-erp">
                        <h3>2. Localização</h3>
                        <div class="row">
                            <div class="col">
                                <label>CEP</label>
                                <input type="text" name="cep" id="cep" onblur="buscarEndereco(this.value)">
                            </div>
                            <div class="col col-3">
                                <label>Rua</label>
                                <input type="text" name="rua" id="rua">
                            </div>
                            <div class="col">
                                <label>Nº</label>
                                <input type="text" name="numero">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <label>Bairro</label>
                                <input type="text" name="bairro" id="bairro">
                            </div>
                            <div class="col">
                                <label>Cidade</label>
                                <input type="text" name="cidade" id="cidade">
                            </div>
                            <div class="col">
                                <label>UF</label>
                                <input type="text" name="estado" id="uf" maxlength="2">
                            </div>
                        </div>
                    </div>

                    <div class="card-erp">
                        <h3>3. Dados Financeiros</h3>
                        <div class="row">
                            <div class="col">
                                <label>Banco</label>
                                <input type="text" name="banco">
                            </div>
                            <div class="col">
                                <label>Agência</label>
                                <input type="text" name="agencia">
                            </div>
                            <div class="col">
                                <label>Conta</label>
                                <input type="text" name="conta">
                            </div>
                            <div class="col">
                                <label>Chave PIX</label>
                                <input type="text" name="chave_pix">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lateral">
                    <div class="card-erp">
                        <h3>4. Contato</h3>
                        <div class="section-form">
                            <label>Nome do Contato</label>
                            <input type="text" name="contato_responsavel">
                        </div>
                        <div class="section-form">
                            <label>WhatsApp</label>
                            <input type="text" name="celular">
                        </div>
                        <div class="section-form">
                            <label>E-mail</label>
                            <input type="email" name="email">
                        </div>
                    </div>

                    <div class="card-erp">
                        <h3>5. Comercial</h3>
                        <div class="section-form">
                            <label>Tipo de Fornecimento</label>
                            <select name="tipo_fornecimento">
                                <option value="Produto">Produto</option>
                                <option value="Serviço">Serviço</option>
                                <option value="Ambos">Ambos</option>
                            </select>
                        </div>
                        <div class="section-form">
                            <label>Prazo de Entrega (dias)</label>
                            <input type="number" name="prazo_entrega_medio" value="0">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="assents/fornecedor.js"></script>
</body>
</html>