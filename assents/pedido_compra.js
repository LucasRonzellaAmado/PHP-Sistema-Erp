let pedido = [];

function carregarProdutosFornecedor(id_fornecedor) {
    const tbody = document.getElementById('lista_produtos_fornecedor');
    
    if(!id_fornecedor) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Aguardando seleção de fornecedor...</td></tr>';
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Carregando produtos...</td></tr>';

    fetch(`api/get_produtos_fornecedor.php?id=${id_fornecedor}`)
    .then(r => r.json())
    .then(produtos => {
        tbody.innerHTML = '';
        if(produtos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state" style="color:#ef4444">Nenhum produto vinculado.</td></tr>';
            return;
        }

        produtos.forEach(p => {
            const tr = document.createElement('tr');

            const tdNome = document.createElement('td');
            const strong = document.createElement('strong');
            strong.textContent = p.nome;
            tdNome.appendChild(strong);

            const tdQtd = document.createElement('td');
            tdQtd.className = 'center';
            tdQtd.textContent = p.quantidade;

            const tdPreco = document.createElement('td');
            tdPreco.textContent = 'R$ ' + parseFloat(p.preco_custo).toLocaleString('pt-br', {minimumFractionDigits: 2});

            const tdInput = document.createElement('td');
            tdInput.className = 'center';
            const input = document.createElement('input');
            input.type = 'number';
            input.id = `qtd_${p.id}`;
            input.value = 1;
            input.min = 1;
            input.className = 'input-qtd';
            tdInput.appendChild(input);

            const tdBtn = document.createElement('td');
            const btn = document.createElement('button');
            btn.className = 'btn-add';
            btn.textContent = 'ADICIONAR';
            btn.onclick = () => addAoPedido(p.id, p.nome, p.preco_custo);
            tdBtn.appendChild(btn);

            tr.append(tdNome, tdQtd, tdPreco, tdInput, tdBtn);
            tbody.appendChild(tr);
        });
    })
    .catch(err => {
        tbody.innerHTML = `<tr><td colspan="5" class="empty-state" style="color:red">Erro ao carregar dados.</td></tr>`;
    });
}

function addAoPedido(id, nome, preco) {
    const qtd = parseInt(document.getElementById('qtd_'+id).value);
    const index = pedido.findIndex(item => item.id === id);
    
    if (index !== -1) {
        pedido[index].qtd += qtd;
    } else {
        pedido.push({ id, nome, preco: parseFloat(preco), qtd });
    }
    renderizarCarrinho();
}

function renderizarCarrinho() {
    const tbody = document.getElementById('itens_carrinho');
    let total = 0;
    tbody.innerHTML = '';
    
    pedido.forEach((item, index) => {
        const sub = item.preco * item.qtd;
        total += sub;

        const tr = document.createElement('tr');

        const tdItem = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = item.nome;
        const small = document.createElement('small');
        small.textContent = `${item.qtd}un x R$ ${item.preco.toFixed(2)}`;
        tdItem.appendChild(strong);
        tdItem.appendChild(document.createElement('br'));
        tdItem.appendChild(small);

        const tdSub = document.createElement('td');
        tdSub.style.textAlign = 'right';
        tdSub.style.fontWeight = '600';
        tdSub.textContent = `R$ ${sub.toFixed(2)}`;

        const tdBtn = document.createElement('td');
        tdBtn.style.textAlign = 'right';
        tdBtn.style.width = '30px';
        const btn = document.createElement('button');
        btn.className = 'btn-del';
        btn.textContent = '✕';
        btn.onclick = () => { pedido.splice(index, 1); renderizarCarrinho(); };
        tdBtn.appendChild(btn);

        tr.append(tdItem, tdSub, tdBtn);
        tbody.appendChild(tr);
    });
    document.getElementById('total_pedido').innerText = `R$ ${total.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
}

function finalizarPedido() {
    if(pedido.length === 0) return Swal.fire('Atenção', 'O carrinho está vazio!', 'warning');
    
    const idFornecedor = document.getElementById('select_fornecedor').value;

    fetch('api/salvar_pedido_compra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
        body: JSON.stringify({
            id_fornecedor: idFornecedor,
            itens: pedido
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            Swal.fire('Sucesso', 'Pedido de compra gerado com sucesso!', 'success')
            .then(() => location.reload());
        } else {
            Swal.fire('Erro', res.message || 'Não foi possível gerar o pedido.', 'error');
        }
    })
    .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
}