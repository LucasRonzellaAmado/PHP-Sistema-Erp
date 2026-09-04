let itensOrcamento = [];

function adicionarItemOrcamento() {
    const select = document.getElementById('select_produto');
    const qtd = parseInt(document.getElementById('qtd_item').value);
    
    if (!select.value) return Swal.fire('Atenção', 'Selecione um produto', 'warning');

    const option = select.options[select.selectedIndex];
    const id = select.value;
    const nome = option.dataset.nome;
    const preco = parseFloat(option.dataset.preco);

    const index = itensOrcamento.findIndex(i => i.id === id);
    if (index > -1) {
        itensOrcamento[index].qtd += qtd;
    } else {
        itensOrcamento.push({ id, nome, preco, qtd });
    }

    renderizarTabelaOrcamento();
    select.value = '';
    document.getElementById('qtd_item').value = 1;
}

function removerItem(index) {
    itensOrcamento.splice(index, 1);
    renderizarTabelaOrcamento();
}

function renderizarTabelaOrcamento() {
    const tbody = document.querySelector('#tabela_itens_orcamento tbody');
    const descInput = document.getElementById('desconto_orcamento').value;
    const descontoPercent = parseFloat(descInput.replace(',', '.')) || 0;
    
    tbody.innerHTML = '';
    let totalBruto = 0;

    itensOrcamento.forEach((item, index) => {
        const subtotal = item.preco * item.qtd;
        totalBruto += subtotal;

        const tr = document.createElement('tr');

        const tdNome = document.createElement('td');
        tdNome.textContent = item.nome;

        const tdQtd = document.createElement('td');
        tdQtd.className = 'center';
        tdQtd.textContent = item.qtd;

        const tdPreco = document.createElement('td');
        tdPreco.textContent = 'R$ ' + item.preco.toLocaleString('pt-br', {minimumFractionDigits: 2});

        const tdSub = document.createElement('td');
        tdSub.textContent = 'R$ ' + subtotal.toLocaleString('pt-br', {minimumFractionDigits: 2});

        const tdBtn = document.createElement('td');
        tdBtn.className = 'center';
        const btn = document.createElement('button');
        btn.className = 'btn-remove';
        btn.innerHTML = '&times;';
        btn.onclick = () => removerItem(index);
        tdBtn.appendChild(btn);

        tr.append(tdNome, tdQtd, tdPreco, tdSub, tdBtn);
        tbody.appendChild(tr);
    });

    const valorDesconto = totalBruto * (descontoPercent / 100);
    const totalFinal = totalBruto - valorDesconto;

    document.getElementById('total_orcamento').innerText = 
        totalFinal.toLocaleString('pt-br', { style: 'currency', currency: 'BRL' });
}

function salvarOrcamento() {
    if (itensOrcamento.length === 0) return Swal.fire('Erro', 'Adicione itens ao orçamento', 'error');

    const dados = {
        id_cliente: document.getElementById('id_cliente').value,
        validade: document.getElementById('validade').value,
        desconto: document.getElementById('desconto_orcamento').value,
        condicoes: document.getElementById('condicoes').value,
        observacoes: document.getElementById('obs_orcamento').value,
        itens: itensOrcamento
    };

    fetch('api/salvar_orcamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify(dados)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Sucesso!', 'Orçamento gerado: #' + res.id, 'success')
                .then(() => window.location.href = 'historico-orcamento.php');
        } else {
            Swal.fire('Erro', res.message, 'error');
        }
    })
    .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
}