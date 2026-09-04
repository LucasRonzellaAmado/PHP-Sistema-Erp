function detalhesNota(id) {
    document.getElementById('modal_fiscal').style.display = 'block';
    fetch(`api/get_detalhes_nf.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalhes_nf_ajax').innerHTML = html;
        });
}

function fecharModal() {
    document.getElementById('modal_fiscal').style.display = 'none';
}

function cancelarNota(id) {
    Swal.fire({
        title: 'Motivo do Cancelamento',
        input: 'text',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Cancelamento',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            fetch('api/cancelar_nf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                body: JSON.stringify({ id, motivo: result.value })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire('Erro', data.message || 'Não foi possível cancelar.', 'error');
                    }
                })
                .catch(() => Swal.fire('Erro', 'Falha na conexão.', 'error'));
        }
    });
}

function exportarMes() {
    Swal.fire('Processando', 'Gerando pacote de XMLs do mês atual...', 'info');
    window.location.href = 'api/exportar_xml_lote.php';
}