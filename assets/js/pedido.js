/**
 * ============================================================================
 * pedido.js — Gerencia a lista de itens do pedido no navegador, ANTES de
 * enviar ao servidor. Usado tanto na criação de um pedido novo (index.php)
 * quanto na edição de um pedido existente (pedido.php), enquanto o modo de
 * edição estiver ativo.
 * ============================================================================
 * Resumo das responsabilidades deste arquivo:
 *   1) Desenhar a tabela de itens a partir do array `itens` em memória.
 *   2) Adicionar/remover linhas quando os botões correspondentes são clicados.
 *   3) MESCLAR automaticamente dois itens com o mesmo Modelo/Nome: ao digitar
 *      um nome que já existe em outra linha, a quantidade é somada na linha
 *      já existente e a linha duplicada é removida (evita pedidos com o
 *      mesmo modelo repetido em duas linhas diferentes).
 *   4) Calcular e mostrar o total de peças em tempo real.
 *   5) Validar o formulário antes de enviar (todo item precisa de nome e
 *      quantidade válida) e serializar a lista para o campo oculto
 *      "itens_json", que é o que realmente é enviado ao servidor.
 * ============================================================================
 */
(function () {
    'use strict';

    const corpoTabela = document.getElementById('corpo-itens');
    const formPedido = document.getElementById('form-pedido');
    const campoItensJson = document.getElementById('itens_json');
    const btnAdicionar = document.getElementById('btn-adicionar-item');
    const totalPecasEl = document.getElementById('total-pecas');
    const semItensAviso = document.getElementById('sem-itens');

    if (!corpoTabela || !formPedido) return;

    // Itens iniciais (edição) injetados pela página via window.ITENS_INICIAIS
    let itens = Array.isArray(window.ITENS_INICIAIS) ? window.ITENS_INICIAIS.slice() : [];
    let proximoId = itens.length + 1;

    /** Normaliza um nome para comparação (mesma lógica usada no PHP). */
    function normalizarNome(nome) {
        return String(nome || '').trim().toLowerCase();
    }

    function novoItemVazio() {
        return { _id: 'novo-' + (proximoId++), modelo_nome: '', numero_serie: '', quantidade: 1, situacao: 'normal' };
    }

    /**
     * Verifica se já existe outro item (diferente de `indiceAtual`) com o
     * mesmo Modelo/Nome. Se existir, soma a quantidade do item atual nele,
     * remove o item atual da lista e avisa o usuário. Retorna true se uma
     * mesclagem aconteceu (nesse caso o chamador não deve continuar
     * editando o item que acabou de ser removido).
     */
    function mesclarSeDuplicado(indiceAtual) {
        const atual = itens[indiceAtual];
        const nomeAtual = normalizarNome(atual.modelo_nome);
        if (nomeAtual === '') return false;

        const indiceExistente = itens.findIndex(function (item, idx) {
            return idx !== indiceAtual && normalizarNome(item.modelo_nome) === nomeAtual;
        });

        if (indiceExistente === -1) return false;

        itens[indiceExistente].quantidade = (parseInt(itens[indiceExistente].quantidade, 10) || 0) + (parseInt(atual.quantidade, 10) || 0);
        itens.splice(indiceAtual, 1);
        renderizar();
        alert('Já havia um item "' + itens[indiceExistente].modelo_nome + '" na lista — as quantidades foram somadas automaticamente.');
        return true;
    }

    function renderizar() {
        corpoTabela.innerHTML = '';
        semItensAviso.style.display = itens.length === 0 ? 'block' : 'none';

        itens.forEach(function (item, index) {
            const tr = document.createElement('tr');

            tr.innerHTML =
                '<td data-rotulo="Modelo/Nome"><input type="text" class="campo-modelo" value="' + escapeAttr(item.modelo_nome) + '" placeholder="Ex.: Onix" required></td>' +
                '<td data-rotulo="Nº de série"><input type="text" class="campo-serie" value="' + escapeAttr(item.numero_serie || '') + '" placeholder="Opcional"></td>' +
                '<td data-rotulo="Quantidade" class="num"><input type="number" min="1" class="campo-quantidade" value="' + escapeAttr(item.quantidade || 1) + '" required style="width:90px;text-align:right"></td>' +
                '<td data-rotulo="Situação"><select class="campo-situacao">' +
                    '<option value="normal"' + (item.situacao === 'normal' ? ' selected' : '') + '>🔵 Normal</option>' +
                    '<option value="importante"' + (item.situacao === 'importante' ? ' selected' : '') + '>🟡 Importante</option>' +
                    '<option value="urgente"' + (item.situacao === 'urgente' ? ' selected' : '') + '>🔴 Urgente</option>' +
                '</select></td>' +
                '<td data-rotulo="Ação" class="center"><button type="button" class="btn btn-perigo btn-sm btn-remover" title="Remover item">🗑️ Remover</button></td>';

            tr.querySelector('.campo-modelo').addEventListener('input', function (e) { item.modelo_nome = e.target.value; });
            tr.querySelector('.campo-modelo').addEventListener('blur', function () { mesclarSeDuplicado(index); });
            tr.querySelector('.campo-serie').addEventListener('input', function (e) { item.numero_serie = e.target.value; });
            tr.querySelector('.campo-quantidade').addEventListener('input', function (e) { item.quantidade = parseInt(e.target.value, 10) || 0; atualizarTotal(); });
            tr.querySelector('.campo-situacao').addEventListener('change', function (e) { item.situacao = e.target.value; });
            tr.querySelector('.btn-remover').addEventListener('click', function () {
                itens.splice(index, 1);
                renderizar();
            });

            corpoTabela.appendChild(tr);
        });

        atualizarTotal();
    }

    function atualizarTotal() {
        const total = itens.reduce(function (soma, item) { return soma + (parseInt(item.quantidade, 10) || 0); }, 0);
        if (totalPecasEl) totalPecasEl.textContent = total;
    }

    function escapeAttr(valor) {
        return String(valor === undefined || valor === null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    btnAdicionar.addEventListener('click', function () {
        itens.push(novoItemVazio());
        renderizar();
    });

    formPedido.addEventListener('submit', function (e) {
        if (itens.length === 0) {
            e.preventDefault();
            alert('Adicione pelo menos um item ao pedido antes de enviar.');
            return;
        }
        for (const item of itens) {
            if (!item.modelo_nome || !item.modelo_nome.trim()) {
                e.preventDefault();
                alert('Preencha o campo "Modelo/Nome" de todos os itens.');
                return;
            }
            if (!item.quantidade || item.quantidade < 1) {
                e.preventDefault();
                alert('Informe uma quantidade válida (maior que zero) para todos os itens.');
                return;
            }
        }
        campoItensJson.value = JSON.stringify(itens);
    });

    renderizar();
})();
