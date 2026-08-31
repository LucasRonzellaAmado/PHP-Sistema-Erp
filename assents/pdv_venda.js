let itensVenda = [];

function buscarClientePorId(id) {
    const select = document.getElementById('id_cliente');
    if(id && select) select.value = id;
}

function toggleParcelas() {
    const formaSelect = document.getElementById('forma_pagamento');
    const divParcelas = document.getElementById('div_parcelas');
    if(formaSelect && divParcelas) {
        divParcelas.className = (formaSelect.value === 'Cartão Crédito') ? '' : 'hidden';
    }
}

function toggleEntregaInterface() {
    const tipoSelect = document.getElementById('tipo_venda');
    const area = document.getElementById('area_entrega');
    if (tipoSelect && area) {
        area.style.display = (tipoSelect.value === 'Entrega') ? 'block' : 'none';
    }
    recalcularPDV();
}

function adicionarItemPDV() {
    if (event) event.preventDefault();

    const select = document.getElementById('select_produto');
    const qtdInput = document.getElementById('qtd_item');
    
    if(!select || !select.value) return;

    const option = select.options[select.selectedIndex];
    const item = {
        id: select.value,
        nome: option.dataset.nome,
        preco: parseFloat(option.dataset.preco) || 0,
        qtd: parseInt(qtdInput.value) || 1
    };

    const index = itensVenda.findIndex(i => i.id === item.id);
    if(index > -1) {
        itensVenda[index].qtd += item.qtd;
    } else {
        itensVenda.push(item);
    }

    select.value = "";
    qtdInput.value = 1;
    recalcularPDV();
}

function recalcularPDV() {
    const tbody = document.querySelector('#tabela_itens_venda tbody');
    const descInput = document.getElementById('desconto_geral');
    const freteInput = document.getElementById('ent_frete');
    const tipoVenda = document.getElementById('tipo_venda');
    
    const desconto = parseFloat(descInput ? descInput.value : 0) || 0;
    const frete = (tipoVenda && tipoVenda.value === 'Entrega') ? (parseFloat(freteInput ? freteInput.value : 0) || 0) : 0;
    
    let subtotal = 0;

    if(tbody) {
        tbody.innerHTML = '';
        itensVenda.forEach((item, index) => {
            const totalItem = item.preco * item.qtd;
            subtotal += totalItem;
            tbody.innerHTML += `
                <tr>
                    <td>${item.nome}</td>
                    <td class="center">${item.qtd}</td>
                    <td>R$ ${item.preco.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td>R$ ${totalItem.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td class="center">
                        <button type="button" class="btn-remove-item" onclick="removerItemPDV(${index})">×</button>
                    </td>
                </tr>
            `;
        });
    }

    const totalFinal = Math.max(0, (subtotal + frete) - desconto);
    
    const resSubtotal = document.getElementById('res_subtotal');
    const totalExibicao = document.getElementById('total_final_exibicao');

    if(resSubtotal) resSubtotal.innerText = `R$ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
    if(totalExibicao) {
        totalExibicao.innerText = `R$ ${totalFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
        totalExibicao.dataset.valor = totalFinal;
    }
}

function removerItemPDV(index) {
    itensVenda.splice(index, 1);
    recalcularPDV();
}

function finalizarVendaPDV() {
    const elementoTotal = document.getElementById('total_final_exibicao');
    if(!elementoTotal) return;

    // Lê o total do data-valor (número puro, sem formatação)
    const totalFinal = parseFloat(elementoTotal.dataset.valor) || 0;

    if (itensVenda.length === 0) {
        Swal.fire('Atenção', 'Adicione produtos ao carrinho antes de finalizar.', 'warning');
        return;
    }

    const idCliente = document.getElementById('id_cliente')?.value || '1';
    const formaPgto = document.getElementById('forma_pagamento')?.value || 'Dinheiro';
    const tipoVenda = document.getElementById('tipo_venda')?.value || 'Local';
    const desconto = parseFloat(document.getElementById('desconto_geral')?.value || 0) || 0;
    const emitirNota = document.getElementById('emitir_nota')?.checked ? 1 : 0;

    const dados = {
        id_cliente: idCliente,
        forma_pagamento: formaPgto,
        tipo_venda: tipoVenda,
        total: totalFinal,
        desconto: desconto,
        itens: itensVenda,
        gerar_nf: emitirNota,
        entrega: {
            rua: document.getElementById('ent_logradouro')?.value || '',
            num: document.getElementById('ent_numero')?.value || '',
            bairro: document.getElementById('ent_bairro')?.value || '',
            frete: parseFloat(document.getElementById('ent_frete')?.value || 0) || 0
        }
    };

    fetch('action/processa_venda.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => {
        if (!response.ok) throw new Error('Caminho não encontrado ou erro no servidor.');
        return response.json();
    })
    .then(data => {
        if (data.sucesso) {
            imprimirCupomSilencioso(data.venda_id);
            Swal.fire('Sucesso!', 'Venda finalizada.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Erro', data.mensagem || 'Erro ao processar', 'error');
        }
    })
    .catch(error => {
        console.error('Erro detalhado:', error);
        Swal.fire('Erro Crítico', 'Não foi possível falar com o servidor. Verifique o arquivo processa_venda.php', 'error');
    });
}
function imprimirCupomSilencioso(vendaId) {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = 'imprimir_cupom.php?id=' + vendaId;
    document.body.appendChild(iframe);

    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(function() {
            document.body.removeChild(iframe);
        }, 1000);
    };
}

// ===== LEITOR DE CÓDIGO DE BARRAS =====
document.addEventListener('DOMContentLoaded', function() {
    const inputCodigo = document.getElementById('input_codigo_barras');
    if (!inputCodigo) return;

    inputCodigo.focus();

    inputCodigo.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const codigo = inputCodigo.value.trim();
            inputCodigo.value = '';
            if (codigo) {
                buscarProdutoPorCodigoBarras(codigo);
            }
        }
    });

    document.addEventListener('click', function(e) {
        const tag = e.target.tagName;
        if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA' && tag !== 'BUTTON') {
            inputCodigo.focus();
        }
    });
});

function buscarProdutoPorCodigoBarras(codigo) {
    fetch('api/get_produto_codigo_barras.php?codigo=' + encodeURIComponent(codigo))
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                const p = data.produto;

                if (p.quantidade_estoque <= 0) {
                    Swal.fire('Sem estoque', `"${p.nome}" está sem estoque disponível.`, 'warning');
                    return;
                }

                const index = itensVenda.findIndex(i => i.id == p.id);
                if (index > -1) {
                    itensVenda[index].qtd += 1;
                } else {
                    itensVenda.push({
                        id: p.id,
                        nome: p.nome,
                        preco: parseFloat(p.preco) || 0,
                        qtd: 1
                    });
                }
                recalcularPDV();

                const toastFeedback = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1200,
                    timerProgressBar: true
                });
                toastFeedback.fire({ icon: 'success', title: p.nome });

            } else {
                Swal.fire('Não encontrado', data.mensagem || 'Código de barras não localizado.', 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar código de barras:', error);
            Swal.fire('Erro', 'Não foi possível consultar o código de barras.', 'error');
        })
        .finally(() => {
            const inputCodigo = document.getElementById('input_codigo_barras');
            if (inputCodigo) inputCodigo.focus();
        });
}
