const inputSearch = document.getElementById('smart_search');
const resultsDiv = document.getElementById('search_results');

inputSearch.addEventListener('input', function() {
    const termo = this.value.toLowerCase();
    resultsDiv.innerHTML = '';
    if (termo.length < 1) { resultsDiv.style.display = 'none'; return; }

    const filtrados = listaClientes.filter(c => c.nome.toLowerCase().includes(termo) || c.id.toString().includes(termo));

    if (filtrados.length > 0) {
        filtrados.forEach(c => {
            const item = document.createElement('div');
            item.className = 'search-item';
            const spanNome = document.createElement('span');
            spanNome.textContent = c.nome;
            const bId = document.createElement('b');
            bId.textContent = `#${c.id}`;
            item.appendChild(spanNome);
            item.appendChild(document.createTextNode(' '));
            item.appendChild(bId);
            item.onclick = () => {
                inputSearch.value = `${c.nome} (#${c.id})`;
                resultsDiv.style.display = 'none';
                carregarHistorico(c.id);
            };
            resultsDiv.appendChild(item);
        });
        resultsDiv.style.display = 'block';
    } else {
        resultsDiv.style.display = 'none';
    }
});

function carregarHistorico(idCliente) {
    const corpo = document.getElementById('corpo_historico');
    corpo.innerHTML = "<tr><td colspan='7' style='text-align:center;'>🔄 Carregando dados...</td></tr>";

    fetch(`api/get_saldo_devedor.php?id_cliente=${idCliente}`)
        .then(r => r.json())
        .then(dados => {
            const box = document.getElementById('box_saldo_devedor');
            const valorEl = document.getElementById('valor_saldo_devedor');
            if (dados.saldo > 0) {
                let texto = 'R$ ' + dados.saldo.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                if (dados.validar_limite) {
                    texto += ' de R$ ' + dados.limite.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' de limite';
                }
                valorEl.innerText = texto;
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        })
        .catch(() => {});

    fetch(`api/get_historico.php?id_cliente=${idCliente}`)
        .then(response => response.json())
        .then(vendas => {
            corpo.innerHTML = "";
            if (vendas.length === 0) {
                corpo.innerHTML = "<tr><td colspan='7' style='text-align:center;'>Nenhuma venda encontrada.</td></tr>";
                return;
            }
            vendas.forEach(v => {
                const valorUnitario = parseFloat(v.valor_venda || 0);
                const qtd = parseFloat(v.quantidade || 0);
                const subtotal = valorUnitario * qtd;

                const tr = document.createElement('tr');

                const tdData = document.createElement('td');
                tdData.textContent = v.data_venda || '---';

                const tdProduto = document.createElement('td');
                tdProduto.textContent = v.nome_produto || '';

                const tdQtd = document.createElement('td');
                tdQtd.textContent = qtd;

                const tdUnit = document.createElement('td');
                tdUnit.textContent = 'R$ ' + valorUnitario.toLocaleString('pt-BR', {minimumFractionDigits: 2});

                const tdSub = document.createElement('td');
                tdSub.textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});

                const tdPgto = document.createElement('td');
                const badgePgto = document.createElement('span');
                badgePgto.className = 'badge-pgto';
                badgePgto.textContent = v.metodo_pagamento || 'DINHEIRO';
                tdPgto.appendChild(badgePgto);

                const tdAcoes = document.createElement('td');
                const btnVer = document.createElement('button');
                btnVer.className = 'btn-action btn-view';
                btnVer.textContent = '👁️';
                btnVer.onclick = () => verDetalhes(v.id, v.nome_produto, qtd, valorUnitario, v.data_venda);
                const btnImprimir = document.createElement('button');
                btnImprimir.className = 'btn-action btn-print';
                btnImprimir.textContent = '🖨️';
                btnImprimir.onclick = () => imprimirVenda(v.id);
                tdAcoes.appendChild(btnVer);
                tdAcoes.appendChild(btnImprimir);

                tr.append(tdData, tdProduto, tdQtd, tdUnit, tdSub, tdPgto, tdAcoes);
                corpo.appendChild(tr);
            });
        }).catch(() => {
            corpo.innerHTML = "<tr><td colspan='7' class='txt-red'>❌ Erro de conexão.</td></tr>";
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function verDetalhes(id, produto, qtd, valor, data) {
    const total = parseFloat(qtd) * parseFloat(valor);
    Swal.fire({
        title: `Detalhes da Venda #${escapeHtml(id)}`,
        html: `<div style="text-align: left; line-height: 2;"><hr>
                <b>Data:</b> ${escapeHtml(data)}<br><b>Produto:</b> ${escapeHtml(produto)}<br>
                <b>Quantidade:</b> ${escapeHtml(qtd)}<br><b>Valor Unit.:</b> R$ ${parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}<br>
                <b style="color: #2563eb; font-size: 1.2em;">Total: R$ ${total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</b></div>`,
        icon: 'info', confirmButtonColor: '#2563eb'
    });
}

function imprimirVenda(idVenda) {
    if(!idVenda) return;
    window.open(`imprimir_cupom.php?id=${idVenda}`, '_blank');
}

function toggleCard(id, iconId) {
    const el = document.getElementById(id);
    const icon = document.getElementById(iconId);
    const txt = document.getElementById('txt_botao');
    el.classList.toggle('collapsed');
    icon.classList.toggle('rotate');
    txt.innerText = el.classList.contains('collapsed') ? "CLIQUE PARA ABRIR" : "RECOLHER FORMULÁRIO";
}

function toggleCampos(tipo) {
    const boxFantasia = document.getElementById('box_fantasia');
    const labelNome = document.getElementById('label_nome');
    const labelDoc = document.getElementById('label_doc');

    if(tipo === 'PJ') {
        boxFantasia.style.display = 'flex';
        labelNome.innerText = 'Razão Social';
        labelDoc.innerText = 'CNPJ';
    } else {
        boxFantasia.style.display = 'none';
        labelNome.innerText = 'Nome Completo';
        labelDoc.innerText = 'CPF';
    }
}

function toggleLimite(v) {
    const inp = document.getElementById('limite_credito');
    if(v == "0") { inp.value = "ILIMITADO"; inp.readOnly = true; }
    else { inp.value = "0,00"; inp.readOnly = false; }
}

function buscaCEP(cep) {
    fetch(`https://viacep.com.br/ws/${cep.replace(/\D/g, '')}/json/`)
    .then(r => r.json()).then(d => {
        if(!d.erro) {
            document.getElementById('logradouro').value = d.logradouro;
            document.getElementById('bairro').value = d.bairro;
            document.getElementById('cidade').value = d.localidade;
            document.getElementById('uf').value = d.uf;
        }
    });
}

function confirmarCadastro(id, nome) {
    Swal.fire({
        title: '✅ Cliente Cadastrado!',
        html: `<div class="swal-custom-box"><b>ID:</b> #${id}<br><b>Nome:</b> ${nome}</div>`,
        icon: 'success', confirmButtonColor: '#2563eb'
    }).then(() => { window.location='cliente.php'; });
}

document.querySelectorAll('.money-mask').forEach(input => {
    input.addEventListener('input', function() {
        let v = this.value.replace(/\D/g, "");
        v = (parseFloat(v) / 100).toFixed(2).replace(".", ",");
        this.value = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    });
});

document.addEventListener('click', (e) => { if (!inputSearch.contains(e.target)) resultsDiv.style.display = 'none'; });