document.addEventListener('DOMContentLoaded', () => {

    // --- Sistema de Notificação e Confirmação ---
    const showToast = (message, type = 'info') => {
        const container = document.getElementById('toast-container'); if (!container) return;
        const toast = document.createElement('div'); toast.className = `toast ${type}`;
        let icon = 'ℹ️'; if (type === 'success') icon = '✅'; if (type === 'error') icon = '❌';
        toast.innerHTML = `<span class="toast-icon">${icon}</span> <span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => { toast.classList.remove('show'); toast.addEventListener('transitionend', () => toast.remove()); }, 4000);
    };

    /**
     * Nova função de confirmação que retorna uma Promise.
     * Permite usar: if (await showConfirm("...")) { ... }
     */
    const showConfirm = (text) => {
        return new Promise(resolve => {
            const modal = document.getElementById('confirm-modal');
            const textEl = document.getElementById('confirm-modal-text');
            const btnYes = document.getElementById('confirm-modal-btn-yes');
            const btnNo = document.getElementById('confirm-modal-btn-no');

            if (!modal || !textEl || !btnYes || !btnNo) {
                // Fallback para o confirm padrão se os elementos não existirem
                resolve(confirm(text));
                return;
            }

            textEl.textContent = text;
            modal.style.display = 'flex';

            const closeHandler = (decision) => {
                modal.style.display = 'none';
                // Remove os event listeners para evitar múltiplos cliques
                btnYes.onclick = null;
                btnNo.onclick = null;
                resolve(decision);
            };

            btnYes.onclick = () => closeHandler(true);
            btnNo.onclick = () => closeHandler(false);
        });
    };
    
    // --- Gerenciamento de Bombeiros (CRUD) ---
    const deleteForms = document.querySelectorAll('form[action="bombeiros.php"]');
    deleteForms.forEach(form => {
        if (form.querySelector('input[name="delete_bombeiro_id"]')) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault(); // Previne o envio padrão do formulário
                if (await showConfirm('Tem certeza que deseja marcar este bombeiro como INATIVO?')) {
                    form.submit(); // Envia o formulário somente se o usuário confirmar
                }
            });
        }
    });

    // O resto do seu código permanece igual, mas com 'confirm()' substituído por 'await showConfirm()'

    // --- Código existente (com as substituições) ---
    const tipoSelect = document.getElementById('tipo');
    const fixoFields = document.getElementById('fixo-fields');
    if (tipoSelect && fixoFields) {
        const toggleFixoFields = () => {
            const isFixo = tipoSelect.value === 'Fixo';
            fixoFields.classList.toggle('visible', isFixo);
            fixoFields.querySelectorAll('input[type="date"], select').forEach(el => { el.required = isFixo; });
        };
        tipoSelect.addEventListener('change', toggleFixoFields);
        toggleFixoFields();
    }
    const modal = document.getElementById("detailsModal");
    const modalTitle = document.getElementById("modalDate");
    const modalOcupantesList = document.getElementById("modalOcupantesList");
    const modalSelecaoDiv = document.getElementById("modalSelecao");
    const modalSugestao = document.getElementById("modalSugestao");
    const modalSelectBombeiro = document.getElementById("modalSelectBombeiro");
    const modalBtnD = document.getElementById("modalBtnD");
    const modalBtnN = document.getElementById("modalBtnN");
    const modalBtnI = document.getElementById("modalBtnI");
    const closeModalButton = document.querySelector(".modal .close-button");
    let currentModalDate = null;
    let currentVagas = { D: 0, N: 0 };
    if (!modal || !closeModalButton || !modalSelectBombeiro || !modalBtnD || !modalBtnN || !modalBtnI) { console.error("Um ou mais elementos essenciais do modal não foram encontrados no DOM!"); }
    const openModal = async (button) => { currentModalDate = button.dataset.date; if (!currentModalDate) { console.error("Botão sem data-date"); return; } modalTitle.textContent = 'Carregando...'; modalOcupantesList.innerHTML = '<li>Carregando...</li>'; modalSelecaoDiv.style.display = 'none'; modalSelectBombeiro.innerHTML = '<option value="">-- Carregando --</option>'; modalSelectBombeiro.disabled = true; modalSugestao.textContent = 'Sugestão: Carregando...'; if (modalBtnD) modalBtnD.disabled = true; if (modalBtnN) modalBtnN.disabled = true; if (modalBtnI) modalBtnI.disabled = true; currentVagas = { D: 0, N: 0 }; modal.style.display = "block"; try { const response = await fetch(`api/api_get_details.php?date=${currentModalDate}`); if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); } const data = await response.json(); if (data.success) { const [year, month, day] = currentModalDate.split('-'); modalTitle.textContent = `${day}/${month}/${year}`; currentVagas = data.vagas; modalOcupantesList.innerHTML = ''; if (data.fixo_calculado) { const fixo = data.fixo_calculado; const temExcecao = fixo.tem_excecao; let acaoBotaoHtml = temExcecao ? `<button class="btn-restore-fixo" data-bombeiro-id="${fixo.id}" data-date="${currentModalDate}" title="Restaurar ciclo">✅</button>` : `<button class="btn-add-excecao-fixo" data-bombeiro-id="${fixo.id}" data-date="${currentModalDate}" title="Remover do Ciclo (Exceção)">❌</button>`; modalOcupantesList.innerHTML += `<li class="${temExcecao ? 'tem-excecao' : ''}"><span class="bombeiro-info">${escapeHtml(fixo.nome_completo)} <span class="bombeiro-tipo">(Fixo - Ciclo)</span></span><span class="turno-icon-wrapper">${getTurnoIconJS(null, true, temExcecao)}${acaoBotaoHtml}</span></li>`; } if (data.extras && data.extras.length > 0) { data.extras.forEach(extra => { modalOcupantesList.innerHTML += `<li><span class="bombeiro-info">${escapeHtml(extra.nome_completo)} <span class="bombeiro-tipo">(${escapeHtml(extra.tipo)})</span></span><span class="turno-icon-wrapper">${getTurnoIconJS(extra.turno)}<button class="btn-remover-plantao" data-plantao-id="${extra.plantao_id}" title="Remover Extra">X</button></span></li>`; }); } if (modalOcupantesList.innerHTML === '') { modalOcupantesList.innerHTML = '<li>Nenhum bombeiro alocado.</li>'; } modalSelecaoDiv.style.display = 'block'; modalSelectBombeiro.disabled = false; modalSelectBombeiro.innerHTML = '<option value="">-- Selecione --</option>'; if (data.bombeiros_ativos && data.bombeiros_ativos.length > 0) { data.bombeiros_ativos.forEach(b => { const opt = document.createElement('option'); opt.value = b.id; opt.textContent = `${escapeHtml(b.nome_completo)} (${escapeHtml(b.tipo)})`; modalSelectBombeiro.appendChild(opt); }); } else { modalSelectBombeiro.innerHTML = '<option value="">-- Nenhum ativo --</option>'; modalSelectBombeiro.disabled = true; } if (data.proximo_sugerido && data.proximo_sugerido.id) { modalSugestao.innerHTML = `Sugestão: <strong>${escapeHtml(data.proximo_sugerido.nome)}</strong>`; const sugOpt = modalSelectBombeiro.querySelector(`option[value="${data.proximo_sugerido.id}"]`); if (sugOpt) sugOpt.selected = true; } else { modalSugestao.textContent = 'Sugestão: (Nenhum)'; } handleBombeiroSelectionChange(); } else { modalTitle.textContent = 'Erro ao Carregar'; modalOcupantesList.innerHTML = `<li>${escapeHtml(data.message || 'Falha ao carregar detalhes.')}</li>`; } } catch (error) { console.error('Erro ao buscar detalhes do dia:', error); modalTitle.textContent = `Erro de Comunicação`; modalOcupantesList.innerHTML = `<li>Não foi possível carregar os detalhes.</li>`; } };
    const closeModal = () => { if(modal) { modal.style.display = "none"; currentModalDate = null; } };
    const updateTurnoButton = (button, turno) => { if (!button) return; const vagasSpan = button.querySelector('.vagas'); let isPossible = false; const bombeiroSelecionado = modalSelectBombeiro && modalSelectBombeiro.value !== ''; if (turno === 'D') { isPossible = currentVagas.D > 0; if(vagasSpan) vagasSpan.textContent = `(${currentVagas.D} Vaga${currentVagas.D !== 1 ? 's' : ''})`; } else if (turno === 'N') { isPossible = currentVagas.N > 0; if(vagasSpan) vagasSpan.textContent = `(${currentVagas.N} Vaga${currentVagas.N !== 1 ? 's' : ''})`; } else if (turno === 'I') { isPossible = currentVagas.D > 0 && currentVagas.N > 0; if(vagasSpan) vagasSpan.textContent = isPossible ? "(OK)" : "(X)"; } button.disabled = !(isPossible && bombeiroSelecionado); };
    const handleBombeiroSelectionChange = () => { updateTurnoButton(modalBtnD, 'D'); updateTurnoButton(modalBtnN, 'N'); updateTurnoButton(modalBtnI, 'I'); };
    document.querySelectorAll('.btn-detalhes').forEach(button => button.addEventListener('click', () => openModal(button)));
    if (closeModalButton) closeModalButton.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => { if (event.target == modal) closeModal(); });
    if (modalSelectBombeiro) modalSelectBombeiro.addEventListener('change', handleBombeiroSelectionChange);
    const postData = async (url, data) => { try { const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data) }); const responseData = await response.json(); if (!response.ok) { throw new Error(responseData.message || `HTTP ${response.status}`); } return responseData; } catch (error) { console.error(`POST ${url} Error:`, error); return { success: false, message: error.message || 'Erro de comunicação.' }; } };
    const handleSuccessfulAction = async (date) => { if (!date) return; closeModal(); try { const response = await fetch(`api/api_get_details.php?date=${date}`); const data = await response.json(); if (data.success) { updateCalendarCell(date, data); } } catch (error) { console.error('Falha ao atualizar a célula:', error); } };
    const registrarPlantao = async (turno) => { const bombeiroId = modalSelectBombeiro.value; const date = currentModalDate; if (!bombeiroId || !date) return; [modalBtnD, modalBtnN, modalBtnI].forEach(b => b.disabled = true); const result = await postData('api/api_registrar_plantao.php', { bombeiro_id: bombeiroId, data: date, turno: turno }); if (result.success) { showToast('Plantão registrado com sucesso!', 'success'); handleSuccessfulAction(date); } else { showToast(result.message || 'Falha ao registrar.', 'error'); handleBombeiroSelectionChange(); } };
    if (modalBtnD) modalBtnD.addEventListener('click', () => registrarPlantao('D'));
    if (modalBtnN) modalBtnN.addEventListener('click', () => registrarPlantao('N'));
    if (modalBtnI) modalBtnI.addEventListener('click', () => registrarPlantao('I'));
    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (target.closest('#modalOcupantesList')) {
            const date = currentModalDate; if (!date) return;
            let result;
            if (target.classList.contains('btn-remover-plantao')) {
                if (await showConfirm('Confirma remoção deste plantão?')) {
                    result = await postData('api/api_remover_plantao.php', { plantao_id: target.dataset.plantaoId });
                    if (result.success) showToast('Plantão removido.', 'success');
                } else { return; }
            } else if (target.classList.contains('btn-add-excecao-fixo')) {
                if (await showConfirm('Remover bombeiro do ciclo fixo para esta data?')) {
                    result = await postData('api/api_registrar_excecao_fixo.php', { bombeiro_id: target.dataset.bombeiroId, data: date });
                    if (result.success) showToast('Exceção adicionada.', 'success');
                } else { return; }
            } else if (target.classList.contains('btn-restore-fixo')) {
                if (await showConfirm('Restaurar bombeiro ao ciclo fixo para esta data?')) {
                    result = await postData('api/api_remover_excecao_fixo.php', { bombeiro_id: target.dataset.bombeiroId, data: date });
                    if (result.success) showToast('Ciclo restaurado.', 'success');
                } else { return; }
            }
            if (result && result.success) { handleSuccessfulAction(date); } 
            else if (result) { showToast(result.message || 'Ação falhou.', 'error'); }
        }
        if (target.id === 'btnAvancarOrdem') {
            const btnAvancarOrdem = target; const displayProximoSugerido = document.getElementById('displayProximoSugerido'); const originalButtonText = btnAvancarOrdem.textContent; btnAvancarOrdem.disabled = true; btnAvancarOrdem.textContent = 'Avançando...';
            try { const response = await fetch('api/api_avancar_ordem.php', { method: 'POST' }); const result = await response.json(); if (!response.ok) throw new Error(result.message); if (displayProximoSugerido) { displayProximoSugerido.textContent = result.novo_nome || 'Ordem atualizada'; } showToast(result.message || 'Ordem avançada com sucesso.', 'success');
            } catch (error) { console.error("Erro ao avançar a ordem:", error); showToast('Erro na comunicação: ' + error.message, 'error'); } 
            finally { btnAvancarOrdem.disabled = false; btnAvancarOrdem.textContent = originalButtonText; }
        }
    });
    function getTurnoIconJS(turno, isFixoCycle = false, hasExcecao = false) { if (isFixoCycle) return hasExcecao ? '<span class="turno-icon fixo-excecao" title="Fixo Removido (Exceção)">🚫</span>' : '<span class="turno-icon fixo-ciclo" title="Fixo - Ciclo 24h">⏰</span>'; switch (turno) { case 'D': return '<span class="turno-icon turno-D" title="Diurno">☀️</span>'; case 'N': return '<span class="turno-icon turno-N" title="Noturno">🌑</span>'; case 'I': return '<span class="turno-icon turno-I" title="Integral 24h">📅</span>'; default: return ''; } }
    function updateCalendarCell(date, cellData) { const button = document.querySelector(`.btn-detalhes[data-date="${date}"]`); if (!button) return; const cell = button.closest('td'); if (!cell) return; const vagas = cellData.vagas; const statusDot = cell.querySelector('.status-dot'); if (statusDot) statusDot.className = `status-dot ${vagas.D > 0 || vagas.N > 0 ? 'green' : 'red'}`; const vagaD = cell.querySelector('.availability-D'); if (vagaD) { vagaD.textContent = `☀️ ${vagas.D}`; vagaD.className = `availability-slot availability-D ${vagas.D > 0 ? 'disponivel' : 'lotado'}`; } const vagaN = cell.querySelector('.availability-N'); if (vagaN) { vagaN.textContent = `🌑 ${vagas.N}`; vagaN.className = `availability-slot availability-N ${vagas.N > 0 ? 'disponivel' : 'lotado'}`; } let vagaI = cell.querySelector('.availability-I'); if (vagas.D > 0 && vagas.N > 0) { if (!vagaI) { const containerVagas = cell.querySelector('.cell-availability-info'); if (containerVagas) { vagaI = document.createElement('span'); vagaI.className = 'availability-slot availability-I disponivel'; vagaI.title = 'Vaga Integral (24h) Disponível'; vagaI.innerHTML = '📅'; containerVagas.appendChild(vagaI); } } } else { if (vagaI) vagaI.remove(); } const plantoesContainer = cell.querySelector('.plantoes-do-dia'); if (plantoesContainer) { let newHtml = ''; if (cellData.fixo_calculado && !cellData.fixo_calculado.tem_excecao) { newHtml += `<span class="plantao-item fixo">${escapeHtml(abreviarNomeJS(cellData.fixo_calculado.nome_completo, 12))}${getTurnoIconJS(null, true)}</span>`; } if (cellData.extras) { cellData.extras.forEach(plantao => { newHtml += `<span class="plantao-item bc">${escapeHtml(abreviarNomeJS(plantao.nome_completo, 10))}${getTurnoIconJS(plantao.turno)}</span>`; }); } plantoesContainer.innerHTML = newHtml; } }
    function abreviarNomeJS(nomeCompleto, maxLength = 12) { if (!nomeCompleto) return ''; if (nomeCompleto.length <= maxLength) return nomeCompleto; const partes = nomeCompleto.trim().split(' '); if (partes.length > 1) { const primeiroNome = partes[0]; const ultimaInicial = partes[partes.length - 1].charAt(0); const abreviado = `${primeiroNome} ${ultimaInicial}.`; return abreviado.length <= maxLength ? abreviado : primeiroNome.substring(0, maxLength - 2) + '..'; } return nomeCompleto.substring(0, maxLength - 1) + '…'; }
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, (m) => {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return m;
        }
    });
}

});