# NexusFlow ERP

Sistema de Gestão Empresarial (ERP) desenvolvido em PHP para pequenos e médios negócios, cobrindo PDV/vendas, estoque, orçamentos, financeiro, cadastros e controle de acesso por usuário.

Projeto pessoal, também utilizado como Trabalho de Conclusão de Curso (TCC).

---

## Funcionalidades

### Vendas e Atendimento
- **PDV (Frente de Caixa)**: venda por busca de produto ou leitor de código de barras, desconto, entrega com taxa de frete, emissão de nota (flag), e pagamento em Dinheiro, Pix, Cartão de Débito/Crédito ou **Fiado** (com checagem de limite de crédito do cliente).
- **Orçamentos**: criação de propostas comerciais, aprovação, cancelamento, conversão direta em venda (com baixa de estoque) e impressão em PDF.
- **Entregas**: painel de despacho (define entregador) e conclusão de entregas em rota.
- **Histórico de Vendas / Orçamentos**: filtro por período e status, reimpressão de cupom.

### Caixa
- Abertura e fechamento de turno, com conferência física (dinheiro/cartão/PIX) contra o valor esperado pelo sistema.
- Lançamentos manuais de sangria (retirada) e suprimento (entrada).

### Cadastros
- **Clientes**: pessoa física/jurídica, endereço, limite de crédito, histórico de compras e saldo devedor (fiado em aberto).
- **Fornecedores**: dados fiscais, endereço, contato e dados bancários/PIX.
- **Estoque**: cadastro completo de produto (preços, tributação, fornecedor, categoria/marca, lote/validade), desativação (sem apagar histórico de vendas).
- **Categorias, Marcas e Formas de Pagamento**: cadastros mestres para evitar texto livre inconsistente.

### Compras e Financeiro
- **Pedido de Compra**: monta pedido a partir dos produtos de um fornecedor; gera automaticamente uma conta a pagar.
- **Contas a Pagar / Contas a Receber**: lançamento manual, filtro por status (pendente/atrasado/pago), baixa com um clique.

### Fiscal
- Gestão interna de notas fiscais (status, filtro, cancelamento com motivo, exportação de XMLs em lote). **Não emite nota fiscal real junto à SEFAZ** — isso exige certificado digital e contratação de um provedor homologado (ex.: Focus NFe, PlugNotas), fora do escopo deste projeto.

### Relatórios Gerenciais
- Curva ABC de produtos, comissão de vendedores (% configurável), estoque valorizado, ranking de clientes e DRE simplificado.

### Administração
- **Usuários e Permissões**: 5 níveis de acesso (Administrador, Gerente, Vendedor, Caixa, Estoque), cada um vendo só as telas relevantes ao seu papel.
- **Meu Perfil**: qualquer usuário troca a própria senha.
- **Auditoria**: log de ações sensíveis (login, vendas, financeiro, alterações de cadastro) com filtro por usuário/ação.

---

## Tecnologias

- **Backend**: PHP 8.x, MySQL/MariaDB via `mysqli` (prepared statements)
- **Frontend**: HTML5, CSS3, JavaScript (vanilla + SweetAlert2, Select2 e Chart.js via CDN)
- **Sem framework e sem gerenciador de dependências** (não usa Composer) — projeto propositalmente enxuto, cada página é um arquivo PHP autocontido

---

## Estrutura do Projeto

```
├── action/            # Endpoints de ação (login/logout, processar venda, etc.)
├── api/               # Endpoints JSON/AJAX (fetch), consumidos pelo JS das telas
├── include/           # Núcleo compartilhado: conexão, sessão, auth, CSRF, auditoria, sidebar
├── assents/           # CSS, JS e imagens (assets)
├── migrations/        # Scripts SQL para criar/corrigir tabelas
├── *.php (raiz)       # Páginas do sistema (uma por funcionalidade)
├── .env               # Credenciais do banco (não versionado)
└── .env.example       # Modelo do .env
```

Nível de acesso de cada página é sempre checado no próprio arquivo PHP (`$_SESSION['nivel']`), nunca só escondido no menu.

---

## Segurança implementada

- Senhas com `password_hash`/`password_verify` (bcrypt), com migração automática de contas antigas em texto puro no primeiro login
- Prepared statements (mysqli) em praticamente todo o sistema
- Token CSRF em todos os formulários e chamadas `fetch()` que alteram dados
- Bloqueio por tentativas de login (5 tentativas / 1 minuto)
- Sessão com cookies `HttpOnly`, `SameSite` e `Secure` (quando em HTTPS)
- Preço e estoque de uma venda **sempre revalidados no servidor** contra o banco — nunca aceita o valor que o navegador enviar
- Log de auditoria das ações mais sensíveis

---

## Instalação

### Requisitos
- PHP 8.0 ou superior, com extensão `mysqli`
- MySQL ou MariaDB
- Servidor web (Apache/XAMPP/WAMP) **ou** o servidor embutido do PHP para testes locais

### Passo a passo

1. Clone o repositório:
   ```
   git clone https://github.com/LucasRonzellaAmado/Erp_System.git
   ```

2. Copie `.env.example` para `.env` e preencha os dados do seu banco:
   ```
   DB_HOST=localhost
   DB_USER=usuario_do_banco
   DB_PASS=senha_do_banco
   DB_NAME=erp
   DB_PORT=3306
   ```

3. Rode as migrações no seu banco (crie o schema base do ERP conforme seu ambiente e, em seguida, rode o script consolidado deste projeto):
   ```
   migrations/000_RODAR_TUDO.sql
   ```
   Isso cria as tabelas de cadastros mestres, financeiro, auditoria e pedido de compra que o sistema usa além do schema principal (`usuarios`, `clientes`, `estoque`, `vendas`, etc.).

4. Suba o sistema:
   - **Teste rápido local**: `php -S localhost:8000` na raiz do projeto e acesse `http://localhost:8000/login.php`
   - **Apache/XAMPP/WAMP**: copie a pasta do projeto para o diretório do servidor (ex.: `C:\wamp64\www\`) e acesse `http://localhost/PHP-Sistema-Erp/login.php`

5. Garanta que existe pelo menos um usuário `admin` na tabela `usuarios` (necessário para criar os demais usuários pela tela). Se precisar promover um usuário existente:
   ```sql
   UPDATE usuarios SET nivel = 'admin' WHERE usuario = 'seu_login';
   ```

---

## Limitações conhecidas

Este sistema **não é** um substituto para um ERP fiscal/contábil completo. Ficam fora do escopo:
- Emissão real de nota fiscal eletrônica (exige certificado digital + provedor homologado com a SEFAZ)
- Folha de pagamento / RH
- Contabilidade formal (livros fiscais, SPED)
- Multi-filial / múltiplas empresas

---

## Status do Projeto

Em desenvolvimento ativo.

---

## Histórico de Melhorias

**Auditoria de segurança e correções críticas**
- Corrigidas vulnerabilidades de SQL Injection (`fiscal.php`, `historico-orcamento.php`, `caixa.php`)
- Senhas migradas de texto puro para hash bcrypt, com migração automática e transparente no login
- Preço e estoque de venda passaram a ser sempre revalidados no servidor (antes o navegador podia enviar qualquer valor)
- Controle de acesso por nível adicionado em páginas que só verificavam login, sem checar o papel do usuário
- Corrigido XSS armazenado em cerca de 15 pontos do sistema
- Removidos arquivos de debug (`teste_*.php`) que alteravam dados de produção sem exigir login

**Funcionalidades que estavam quebradas e foram corrigidas**
- Salvar orçamento, salvar pedido de compra, cancelar nota fiscal e excluir produto não tinham endpoint implementado
- Cadastro de cliente nunca funcionou (inseria numa coluna que não existe no banco: `city` em vez de `cidade`)
- Tabelas `pedidos_compra` e `pedido_compra_itens` nunca existiram no banco — por isso Pedido de Compra nunca funcionou
- Editar um produto no estoque apagava silenciosamente cerca de 20 campos (formulário incompleto vs. UPDATE completo)
- Fornecedor recém-cadastrado nunca aparecia no Pedido de Compra (inconsistência de status)

**Segurança em profundidade**
- Token CSRF implementado em todos os formulários e chamadas AJAX que alteram dados
- Bloqueio de tentativas de login, cookies de sessão seguros (`HttpOnly`/`SameSite`)

**Novos módulos**
- Usuários e Permissões, Financeiro (Contas a Pagar/Receber com integração ao PDV via venda "Fiado"), Cadastros Mestres (Categorias/Marcas/Formas de Pagamento), Relatórios Gerenciais e Log de Auditoria

---

## Objetivo do Projeto

- Aplicar conceitos de desenvolvimento web full-stack
- Praticar modelagem de banco de dados
- Construir um sistema ERP funcional de ponta a ponta
- Implementar autenticação, controle de acesso e boas práticas de segurança
- Servir como Trabalho de Conclusão de Curso (TCC)

---

## Autor

Lucas Amado
Estudante de Ciência da Computação
