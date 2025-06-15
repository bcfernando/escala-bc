<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/funcoes.php'; // Precisa para get_turno_icon

// --- Lógica do Calendário ---
$mes_atual = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$ano_atual = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($mes_atual < 1 || $mes_atual > 12) $mes_atual = date('m');
if ($ano_atual < 1970 || $ano_atual > 2100) $ano_atual = date('Y');

$timestamp_primeiro_dia = mktime(0, 0, 0, $mes_atual, 1, $ano_atual);
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
$nome_mes = ucfirst(strftime('%B', $timestamp_primeiro_dia));

$dias_no_mes = date('t', $timestamp_primeiro_dia);
$dia_semana_primeiro = date('w', $timestamp_primeiro_dia);

$mes_anterior = $mes_atual - 1; $ano_anterior = $ano_atual;
if ($mes_anterior < 1) { $mes_anterior = 12; $ano_anterior--; }
$mes_proximo = $mes_atual + 1; $ano_proximo = $ano_atual;
if ($mes_proximo > 12) { $mes_proximo = 1; $ano_proximo++; }

// --- Busca Dados para o Mês Inteiro ---
$data_inicio_mes = "$ano_atual-$mes_atual-01";
$data_fim_mes = "$ano_atual-$mes_atual-$dias_no_mes";
$plantoes_mes = []; $fixos_servico_mes = []; $vagas_dia_mes = [];

$sql_plantoes_mes = "SELECT p.id as plantao_id, p.bombeiro_id, p.data, p.turno, b.nome_completo, b.tipo
                     FROM plantoes p JOIN bombeiros b ON p.bombeiro_id = b.id
                     WHERE p.data BETWEEN ? AND ? AND b.ativo = 1";
if ($stmt_plantoes = mysqli_prepare($conn, $sql_plantoes_mes)) {
    mysqli_stmt_bind_param($stmt_plantoes, "ss", $data_inicio_mes, $data_fim_mes);
    mysqli_stmt_execute($stmt_plantoes);
    $result_plantoes = mysqli_stmt_get_result($stmt_plantoes);
    while ($row = mysqli_fetch_assoc($result_plantoes)) {
        if (!isset($plantoes_mes[$row['data']])) $plantoes_mes[$row['data']] = [];
        $plantoes_mes[$row['data']][$row['bombeiro_id']] = $row;
    }
    mysqli_free_result($result_plantoes); mysqli_stmt_close($stmt_plantoes);
} else { echo "Erro ao buscar plantões: " . mysqli_error($conn); }

for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
    $data_corrente = sprintf("%s-%s-%02d", $ano_atual, str_pad($mes_atual, 2, '0', STR_PAD_LEFT), $dia);
    $fixo_dia = get_fixo_de_servico($data_corrente, $conn);
    $fixos_servico_mes[$data_corrente] = $fixo_dia;
    $vagas_d = 2; $vagas_n = 2;
    if ($fixo_dia) { $vagas_d--; $vagas_n--; }
    if (isset($plantoes_mes[$data_corrente])) {
        foreach ($plantoes_mes[$data_corrente] as $plantao) {
            if ($plantao['turno'] == 'D') $vagas_d--;
            elseif ($plantao['turno'] == 'N') $vagas_n--;
            elseif ($plantao['turno'] == 'I') { $vagas_d--; $vagas_n--; }
        }
    }
    $vagas_dia_mes[$data_corrente] = ['D' => max(0, $vagas_d), 'N' => max(0, $vagas_n)];
}

// --- Lógica da Ordem Sugerida ---
$ordem_mes_ids = get_ordem_escolha_ids($conn);
$primeiro_da_ordem_nome = '(Nenhum BC ativo)';
if (!empty($ordem_mes_ids)) { $primeiro_nome_temp = get_bombeiro_nome($ordem_mes_ids[0], $conn); if ($primeiro_nome_temp) $primeiro_da_ordem_nome = $primeiro_nome_temp; }
$proximo_sugerido_id = get_proximo_a_escolher_id($conn);
$proximo_sugerido_nome = '(Nenhum)';
if ($proximo_sugerido_id) { $nome_temp = get_bombeiro_nome($proximo_sugerido_id, $conn); if($nome_temp) $proximo_sugerido_nome = $nome_temp; else { set_config('bc_da_vez_id', null, $conn); $proximo_sugerido_id = null; } }

$dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escala de Plantões - <?php echo htmlspecialchars($nome_mes) . ' ' . $ano_atual; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚒</text></svg>">
</head>
<body>
    <h1>Escala de Plantões - <?php echo htmlspecialchars($nome_mes) . ' ' . $ano_atual; ?></h1>

    <div class="controles-escala">
        <div>
            <p>Ordem deste mês iniciaria com: <strong><?php echo htmlspecialchars($primeiro_da_ordem_nome); ?></strong></p>
            <p>Próximo Sugerido na Ordem: 
                <strong id="displayProximoSugerido"><?php echo htmlspecialchars($proximo_sugerido_nome); ?></strong>
                <button id="btnAvancarOrdem" <?php echo !$proximo_sugerido_id ? 'disabled' : ''; ?>>
                     Avançar Ordem Sugerida 
                </button>
             </p>
        </div>
        <div style="text-align: right;">
             <a href="exportar_tabela_formatada.php?month=<?php echo $mes_atual; ?>&year=<?php echo $ano_atual; ?>" class="button-link btn-secondary" target="_blank" title="Exportar escala em formato de tabela">
                 <span class="turno-icon">📄</span> Exportar
             </a>
             <a href="bombeiros.php" class="button-link btn-secondary" style="margin-left: 10px;" title="Adicionar ou editar bombeiros">
                 <span class="turno-icon">⚙️</span> Gerenciar
             </a>
        </div>
    </div>

    <div class="calendar-nav">
        <a href="?month=<?php echo $mes_anterior; ?>&year=<?php echo $ano_anterior; ?>" class="button-link">« Mês Anterior</a>
        <h2><?php echo htmlspecialchars($nome_mes) . ' ' . $ano_atual; ?></h2>
        <a href="?month=<?php echo $mes_proximo; ?>&year=<?php echo $ano_proximo; ?>" class="button-link">Próximo Mês »</a>
    </div>

    <table class="calendar">
        <thead>
            <tr> <?php foreach ($dias_semana as $dia_nome): ?><th><?php echo $dia_nome; ?></th><?php endforeach; ?> </tr>
        </thead>
        <tbody>
            <tr>
                <?php for ($i = 0; $i < $dia_semana_primeiro; $i++) { echo '<td class="other-month"></td>'; }
                $dia_atual_semana = $dia_semana_primeiro;
                for ($dia = 1; $dia <= $dias_no_mes; $dia++):
                    $data_corrente = sprintf("%s-%s-%02d", $ano_atual, str_pad($mes_atual, 2, '0', STR_PAD_LEFT), $dia);
                    $fixo_do_dia = $fixos_servico_mes[$data_corrente] ?? null;
                    $plantoes_do_dia = $plantoes_mes[$data_corrente] ?? [];
                    $vagas_do_dia = $vagas_dia_mes[$data_corrente];
                    $is_weekend = ($dia_atual_semana == 0 || $dia_atual_semana == 6);
                    $status_dot_class = ($vagas_do_dia['D'] > 0 || $vagas_do_dia['N'] > 0) ? 'green' : 'red';
                    $pode_integral = ($vagas_do_dia['D'] > 0 && $vagas_do_dia['N'] > 0);
                ?>
                    <td class="<?php echo $is_weekend ? 'weekend' : ''; ?>">
                        <span class="day-number"><?php echo $dia; ?></span>
                        <div class="cell-icons-top">
                             <button class="btn-detalhes" data-date="<?php echo $data_corrente; ?>" title="Ver detalhes e registrar plantão">👁️</button>
                            <span class="status-dot <?php echo $status_dot_class; ?>" title="Status Geral (Verde=Vagas, Vermelho=Lotado)"></span>
                            <div class="cell-availability-info">
                                <span class="availability-slot availability-D <?php echo ($vagas_do_dia['D'] > 0) ? 'disponivel' : 'lotado'; ?>" title="Vagas Diurnas: <?php echo $vagas_do_dia['D']; ?>">
                                    ☀️ <?php echo $vagas_do_dia['D']; ?>
                                </span>
                                <span class="availability-slot availability-N <?php echo ($vagas_do_dia['N'] > 0) ? 'disponivel' : 'lotado'; ?>" title="Vagas Noturnas: <?php echo $vagas_do_dia['N']; ?>">
                                    🌑 <?php echo $vagas_do_dia['N']; ?>
                                </span>
                                <?php if ($pode_integral): ?>
                                    <span class="availability-slot availability-I disponivel" title="Vaga Integral (24h) Disponível">
                                        📅
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="plantoes-do-dia">
                            <?php if ($fixo_do_dia): ?>
                                <span class="plantao-item fixo">
                                    <?php echo htmlspecialchars(abreviar_nome($fixo_do_dia['nome_completo'], 12)); ?>
                                    <?php echo get_turno_icon(null, true); ?>
                                </span>
                            <?php endif; ?>
                            <?php foreach ($plantoes_do_dia as $plantao): ?>
                                <span class="plantao-item bc">
                                     <?php echo htmlspecialchars(abreviar_nome($plantao['nome_completo'], 10)); ?>
                                     <?php echo get_turno_icon($plantao['turno']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                <?php
                    $dia_atual_semana++;
                    if ($dia_atual_semana > 6) { echo '</tr><tr>'; $dia_atual_semana = 0; }
                endfor;
                while ($dia_atual_semana > 0 && $dia_atual_semana <= 6) { echo '<td class="other-month"></td>'; $dia_atual_semana++; }
                ?>
            </tr>
        </tbody>
    </table>

    <!-- Modal de Detalhes/Ação -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalDate">Data</h3>
                <span class="close-button" title="Fechar">×</span>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <h4>Ocupantes do Dia</h4>
                    <ul id="modalOcupantesList"><li>Carregando...</li></ul>
                </div>
                <hr class="modal-divider">
                <div id="modalSelecao" class="modal-section" style="display: none;">
                    <h4>Registrar Novo Plantão</h4>
                    <p id="modalSugestao">Sugestão Próximo na Ordem: Carregando...</p>
                    <div>
                        <label for="modalSelectBombeiro">Bombeiro:</label>
                        <select id="modalSelectBombeiro" required><option value="">-- Carregando --</option></select>
                    </div>
                    <div class="modal-botoes-turno">
                        <button id="modalBtnD" disabled><span class="turno-icon">☀️</span> Selecionar D <span class="vagas">(?)</span></button>
                        <button id="modalBtnN" disabled><span class="turno-icon">🌑</span> Selecionar N <span class="vagas">(?)</span></button>
                        <button id="modalBtnI" disabled><span class="turno-icon">📅</span> Selecionar I <span class="vagas">(?)</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Container para as Notificações Toast -->
    <div id="toast-container"></div>

    <!-- MUDANÇA ADICIONADA AQUI -->
    <!-- Modal de Confirmação Personalizado -->
    <div id="confirm-modal" class="confirm-modal-overlay" style="display: none;">
        <div class="confirm-modal-box">
            <h4 id="confirm-modal-title">Confirmar Ação</h4>
            <p id="confirm-modal-text">Você tem certeza?</p>
            <div class="confirm-modal-buttons">
                <button id="confirm-modal-btn-no" class="btn-secondary">Cancelar</button>
                <button id="confirm-modal-btn-yes" class="btn-danger">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>