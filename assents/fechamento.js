document.addEventListener('DOMContentLoaded', function() {
    const inputsMoeda = document.querySelectorAll('.c-input');
    const saldoEsperado = parseFloat(document.getElementById('esperado-js').dataset.valor);
    const btnAbrirModal = document.getElementById('btn-abrir-modal');
    const btnFecharModal = document.getElementById('btn-fechar-modal');
    const btnConfirmar = document.getElementById('btn-enviar-fechamento');
    const modal = document.getElementById('modalConfirmacao');

    inputsMoeda.forEach(input => {
        input.addEventListener('input', function() {
            mascaraMoeda(this);
            calcularTotal();
        });
        input.addEventListener('click', function() { this.select(); });
    });

    function mascaraMoeda(campo) {
        let valor = campo.value.replace(/\D/g, "");
        if (valor === "") valor = "0";
        let valorDecimal = (parseFloat(valor) / 100).toFixed(2);
        campo.value = valorDecimal.replace(".", ",").replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    }

    function calcularTotal() {
        let total = 0;
        inputsMoeda.forEach(input => {
            let val = parseFloat(input.value.replace(/\./g, "").replace(",", "."));
            total += isNaN(val) ? 0 : val;
        });

        document.getElementById('display-total').innerText = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('valor_total_final').value = total.toFixed(2);

        const status = document.getElementById('status-conferencia');
        status.style.display = 'block';
        const dif = total - saldoEsperado;

        if (Math.abs(dif) < 0.01) {
            status.className = 'status-batido';
            status.innerHTML = '<i class="bi bi-check-circle"></i> CAIXA CONFERIDO E BATIDO';
        } else {
            status.className = dif > 0 ? 'status-sobra' : 'status-falta';
            status.innerHTML = (dif > 0 ? '<i class="bi bi-exclamation-triangle"></i> SOBRA: ' : '<i class="bi bi-x-circle"></i> FALTA: ') + 'R$ ' + Math.abs(dif).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        }
    }

    btnAbrirModal.onclick = function() {
        const totalContado = document.getElementById('display-total').innerText;
        const valorFinal = parseFloat(document.getElementById('valor_total_final').value);
        const dif = valorFinal - saldoEsperado;
        
        let msg = `Você contou um total de <strong>${totalContado}</strong>.`;
        if (Math.abs(dif) > 0.01) {
            msg += `<br><span style="color:#dc2626">Diferença de R$ ${Math.abs(dif).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>`;
        }
        
        document.getElementById('m-resumo').innerHTML = msg;
        modal.style.display = 'flex';
    };

    btnFecharModal.onclick = () => modal.style.display = 'none';

    btnConfirmar.onclick = function() {
        const form = document.getElementById('formFechamento');
        const inputHidden = document.createElement('input');
        inputHidden.type = 'hidden';
        inputHidden.name = 'confirmar_fechamento';
        inputHidden.value = '1';
        form.appendChild(inputHidden);
        form.submit();
    };
});