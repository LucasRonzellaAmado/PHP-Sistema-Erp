document.addEventListener('DOMContentLoaded', function() {
    let orcamentoAtualId = null;
    const modal = document.getElementById('modalDetalhes');
    const conteudo = document.getElementById('conteudoModal');
    const btnFechar = document.getElementById('btn-fechar-js');
    const btnImprimir = document.getElementById('btn-imprimir-js');
    const btnsView = document.querySelectorAll('.btn-view');

    btnsView.forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');
            verDetalhes(id);
        };
    });

    function verDetalhes(id) {
        orcamentoAtualId = id;
        modal.style.display = 'block';
        conteudo.innerHTML = '<p style="text-align:center;">Carregando detalhes...</p>';
        
        fetch('api/get_orcamento.php?id=' + id)
        .then(r => r.text())
        .then(html => {
            conteudo.innerHTML = html;
        })
        .catch(err => {
            conteudo.innerHTML = '<p style="color:red">Erro ao carregar dados.</p>';
        });
    }

    window.converterEmVenda = function(id) {
        Swal.fire({
            title: 'Converter em venda?',
            text: 'Isso vai gerar uma venda com os itens deste orçamento e baixar o estoque.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, converter',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            fetch('api/converter_orcamento_venda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                body: JSON.stringify({ id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Venda #' + data.venda_id + ' gerada.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.message || 'Não foi possível converter.', 'error');
                }
            })
            .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
        });
    };

    window.cancelarOrcamento = function(id) {
        Swal.fire({
            title: 'Cancelar orçamento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Voltar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            fetch('api/atualizar_status_orcamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                body: JSON.stringify({ id, status: 'Cancelado' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Cancelado', 'Orçamento cancelado.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.message || 'Não foi possível cancelar.', 'error');
                }
            })
            .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
        });
    };

    btnFechar.onclick = function() {
        modal.style.display = 'none';
        orcamentoAtualId = null;
    };

    btnImprimir.onclick = function() {
        if(orcamentoAtualId) {
            window.open('imprimir_orcamento.php?id=' + orcamentoAtualId, '_blank');
        }
    };

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };
});