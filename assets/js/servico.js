/**
 * ============================================================================
 * servico.js — Gerencia as linhas de PAGAMENTO no formulário de serviço
 * (admin/servico-novo.php). Usado tanto para lançar um serviço novo quanto
 * para editar um existente — a página PHP já entrega os pagamentos iniciais
 * prontos (window.PAGAMENTOS_INICIAIS), então este arquivo só cuida da
 * parte dinâmica: adicionar/remover linhas e montar o JSON antes de enviar.
 * ============================================================================
 */
(function () {
    'use strict';

    const corpo = document.getElementById('corpo-pagamentos');
    const form = document.getElementById('form-servico');
    const campoJson = document.getElementById('pagamentos_json');
    const btnAdicionar = document.getElementById('btn-adicionar-pagamento');

    if (!corpo || !form) return;

    // Conta/maquininha só faz sentido pra Pix e Cartão (débito/crédito) —
    // em dinheiro ou "outro" não há conta/máquina pra identificar.
    const FORMAS_COM_CONTA = ['pix', 'debito', 'credito'];

    let pagamentos = Array.isArray(window.PAGAMENTOS_INICIAIS) && window.PAGAMENTOS_INICIAIS.length
        ? window.PAGAMENTOS_INICIAIS.map(function (p) { return { forma: p.forma, valor: p.valor, parcelas: p.parcelas || 1, conta: p.conta || '' }; })
        : [{ forma: 'pix', valor: '', parcelas: 1, conta: '' }];

    function renderizar() {
        corpo.innerHTML = '';
        pagamentos.forEach(function (pag, index) {
            const linha = document.createElement('div');
            linha.className = 'linha-pagamento';
            const mostrarConta = FORMAS_COM_CONTA.indexOf(pag.forma) !== -1;
            linha.innerHTML =
                '<select class="campo-forma">' +
                    '<option value="pix"' + (pag.forma === 'pix' ? ' selected' : '') + '>Pix</option>' +
                    '<option value="dinheiro"' + (pag.forma === 'dinheiro' ? ' selected' : '') + '>Dinheiro</option>' +
                    '<option value="debito"' + (pag.forma === 'debito' ? ' selected' : '') + '>Cartão de débito</option>' +
                    '<option value="credito"' + (pag.forma === 'credito' ? ' selected' : '') + '>Cartão de crédito</option>' +
                    '<option value="outro"' + (pag.forma === 'outro' ? ' selected' : '') + '>Outro</option>' +
                '</select>' +
                '<input type="number" step="0.01" min="0" class="campo-valor" placeholder="Valor (R$)" value="' + (pag.valor || '') + '">' +
                '<input type="number" min="1" max="24" class="campo-parcelas" placeholder="Parcelas" value="' + (pag.parcelas || 1) + '" style="display:' + (pag.forma === 'credito' ? 'inline-block' : 'none') + ';width:90px;">' +
                '<input type="text" class="campo-conta" placeholder="Conta/maquininha (opcional)" value="' + (pag.conta ? pag.conta.replace(/"/g, '&quot;') : '') + '" style="display:' + (mostrarConta ? 'inline-block' : 'none') + ';width:180px;">' +
                '<button type="button" class="btn btn-perigo btn-sm btn-remover-pagamento">🗑️</button>';

            linha.querySelector('.campo-forma').addEventListener('change', function (e) {
                pag.forma = e.target.value;
                renderizar();
            });
            linha.querySelector('.campo-valor').addEventListener('input', function (e) { pag.valor = e.target.value; });
            const campoParcelas = linha.querySelector('.campo-parcelas');
            if (campoParcelas) campoParcelas.addEventListener('input', function (e) { pag.parcelas = e.target.value; });
            const campoConta = linha.querySelector('.campo-conta');
            if (campoConta) campoConta.addEventListener('input', function (e) { pag.conta = e.target.value; });
            linha.querySelector('.btn-remover-pagamento').addEventListener('click', function () {
                pagamentos.splice(index, 1);
                if (pagamentos.length === 0) pagamentos.push({ forma: 'pix', valor: '', parcelas: 1, conta: '' });
                renderizar();
            });

            corpo.appendChild(linha);
        });
    }

    btnAdicionar.addEventListener('click', function () {
        pagamentos.push({ forma: 'pix', valor: '', parcelas: 1, conta: '' });
        renderizar();
    });

    form.addEventListener('submit', function () {
        campoJson.value = JSON.stringify(pagamentos.filter(function (p) { return parseFloat(p.valor) > 0; }));
    });

    renderizar();
})();
