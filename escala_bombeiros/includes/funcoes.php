<?php
require_once __DIR__ . '/db.php';

// --- Funções de Configuração ---
function get_config(string $chave, mysqli $conn): ?string {
    $sql = "SELECT valor FROM configuracoes WHERE chave = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $chave);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $valor);
            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);
                return $valor;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Erro ao preparar get_config para chave '$chave': " . mysqli_error($conn));
    }
    return null;
}

function set_config(string $chave, ?string $valor, mysqli $conn): bool {
    $sql = "INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $chave, $valor, $valor);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        }
        error_log("Erro ao executar set_config para chave '$chave': " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    } else {
        error_log("Erro ao preparar set_config para chave '$chave': " . mysqli_error($conn));
    }
    return false;
}

// --- Funções dos Bombeiros Fixos ---
function calcular_dia_ciclo_fixo(array $fixo, string $data_alvo): ?int {
    if (empty($fixo['fixo_ref_data']) || !isset($fixo['fixo_ref_dia_ciclo'])) {
        error_log("calcular_dia_ciclo_fixo: Dados de referência incompletos. Fixo: " . print_r($fixo, true) . ", Alvo: $data_alvo");
        return null;
    }
    $ref_dia_ciclo = (int) $fixo['fixo_ref_dia_ciclo'];
    if ($ref_dia_ciclo < 1 || $ref_dia_ciclo > 4) {
        error_log("calcular_dia_ciclo_fixo: Dia do ciclo de referência ({$ref_dia_ciclo}) inválido.");
        return null;
    }
    try {
        $alvo_dt_obj = new DateTime($data_alvo);
        $ref_data_obj = new DateTime($fixo['fixo_ref_data']);
        $intervalo = $ref_data_obj->diff($alvo_dt_obj);
        $dias_diff = (int) $intervalo->format('%r%a');
        return (($dias_diff + $ref_dia_ciclo - 1) % 4 + 4) % 4 + 1;
    } catch (Exception $e) {
        error_log("calcular_dia_ciclo_fixo: Erro de data: " . $e->getMessage());
        return null;
    }
}

function verificar_excecao_fixo(int $bombeiro_id, string $data, mysqli $conn): bool {
    $sql = "SELECT id FROM excecoes_ciclo_fixo WHERE bombeiro_id = ? AND data = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $bombeiro_id, $data);
        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            $num_rows = mysqli_stmt_num_rows($stmt);
            mysqli_stmt_close($stmt);
            return $num_rows > 0;
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function get_fixo_de_servico(string $data, mysqli $conn): ?array {
    $fixos_ativos = [];
    $sql = "SELECT id, nome_completo, fixo_ref_data, fixo_ref_dia_ciclo FROM bombeiros WHERE tipo = 'Fixo' AND ativo = 1";
    if ($result = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fixos_ativos[] = $row;
        }
        mysqli_free_result($result);
    }
    if (empty($fixos_ativos)) { return null; }
    
    foreach ($fixos_ativos as $fixo) {
        $dia_ciclo = calcular_dia_ciclo_fixo($fixo, $data);
        if ($dia_ciclo === 1) { // Dia 1 do ciclo é o dia de serviço
            if (!verificar_excecao_fixo((int)$fixo['id'], $data, $conn)) {
                return $fixo; // Retorna o primeiro fixo de serviço sem exceção
            }
        }
    }
    return null;
}

/**
 * --- FUNÇÃO CORRIGIDA ---
 * Registra uma exceção para um bombeiro fixo em uma data específica.
 * A versão anterior era muito complexa e propensa a erros. Esta versão é
 * simplificada para ter uma única responsabilidade: inserir o registro no banco.
 *
 * @param int $bombeiro_id O ID do bombeiro fixo.
 * @param string $data A data no formato 'YYYY-MM-DD'.
 * @param mysqli $conn A conexão com o banco de dados.
 * @return bool True em sucesso, false em falha.
 */
function registrar_excecao_fixo(int $bombeiro_id, string $data, mysqli $conn): bool {
    // Usamos INSERT IGNORE para que, se a exceção já existir, não ocorra um erro.
    // O banco de dados simplesmente ignorará a inserção duplicada.
    $sql = "INSERT IGNORE INTO excecoes_ciclo_fixo (bombeiro_id, data) VALUES (?, ?)";

    // Prepara a declaração e verifica se a preparação foi bem-sucedida
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $bombeiro_id, $data);
        
        $success = mysqli_stmt_execute($stmt);

        if (!$success) {
            error_log("registrar_excecao_fixo: Erro ao executar o INSERT: " . mysqli_stmt_error($stmt));
        }
        
        mysqli_stmt_close($stmt);
        return $success;
    } else {
        error_log("registrar_excecao_fixo: Erro ao preparar o INSERT: " . mysqli_error($conn));
        return false;
    }
}

function remover_excecao_fixo(int $bombeiro_id, string $data, mysqli $conn): bool {
    $sql = "DELETE FROM excecoes_ciclo_fixo WHERE bombeiro_id = ? AND data = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $bombeiro_id, $data);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        }
        error_log("remover_excecao_fixo: Erro executar delete: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    } else {
        error_log("remover_excecao_fixo: Erro preparar delete: " . mysqli_error($conn));
    }
    return false;
}

// --- Funções da Ordem de Escolha (BC) ---
function get_ordem_escolha_ids(mysqli $conn): array {
    $ids_bcs_ativos = [];
    $sql = "SELECT id FROM bombeiros WHERE tipo = 'BC' AND ativo = 1 ORDER BY id ASC";
    if ($result = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $ids_bcs_ativos[] = (string)$row['id'];
        }
        mysqli_free_result($result);
    }
    if (empty($ids_bcs_ativos)) { return []; }

    $ultimo_iniciou_id = get_config('ultimo_bc_iniciou_mes', $conn);
    if ($ultimo_iniciou_id !== null && ($key = array_search($ultimo_iniciou_id, $ids_bcs_ativos)) !== false) {
        $indice_proximo_inicio = ($key + 1) % count($ids_bcs_ativos);
        $parte1 = array_slice($ids_bcs_ativos, $indice_proximo_inicio);
        $parte2 = array_slice($ids_bcs_ativos, 0, $indice_proximo_inicio);
        return array_merge($parte1, $parte2);
    }
    return $ids_bcs_ativos;
}

function get_proximo_a_escolher_id(mysqli $conn): ?string {
    $bc_da_vez_id = get_config('bc_da_vez_id', $conn);
    if ($bc_da_vez_id === null) {
        $ordem_atual = get_ordem_escolha_ids($conn);
        if (empty($ordem_atual)) { return null; }
        $primeiro_da_ordem = $ordem_atual[0];
        set_config('bc_da_vez_id', $primeiro_da_ordem, $conn);
        set_config('ultimo_bc_iniciou_mes', $primeiro_da_ordem, $conn);
        return $primeiro_da_ordem;
    }
    
    $sql_check = "SELECT id FROM bombeiros WHERE id = ? AND tipo = 'BC' AND ativo = 1";
    if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "s", $bc_da_vez_id);
        if (mysqli_stmt_execute($stmt_check)) {
            mysqli_stmt_store_result($stmt_check);
            if(mysqli_stmt_num_rows($stmt_check) == 0){
                mysqli_stmt_close($stmt_check);
                set_config('bc_da_vez_id', null, $conn);
                return get_proximo_a_escolher_id($conn);
            }
        }
        mysqli_stmt_close($stmt_check);
    }
    return $bc_da_vez_id;
}

function avancar_e_salvar_proximo_id(mysqli $conn): ?string {
    $id_atual = get_config('bc_da_vez_id', $conn);
    $ordem_atual = get_ordem_escolha_ids($conn);
    if (empty($ordem_atual)) {
        set_config('bc_da_vez_id', null, $conn);
        return null;
    }

    $proximo_id = $ordem_atual[0]; // Padrão
    if ($id_atual !== null) {
        $indice_atual = array_search($id_atual, $ordem_atual);
        if ($indice_atual !== false) {
             $proximo_indice = ($indice_atual + 1) % count($ordem_atual);
             $proximo_id = $ordem_atual[$proximo_indice];
        }
    }
    set_config('bc_da_vez_id', $proximo_id, $conn);
    return $proximo_id;
}

// --- Funções Auxiliares ---
function get_bombeiro_nome(int|string $bombeiro_id, mysqli $conn): ?string {
    $sql = "SELECT nome_completo FROM bombeiros WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $id_int = (int) $bombeiro_id;
        mysqli_stmt_bind_param($stmt, "i", $id_int);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $nome);
            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);
                return $nome;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

function set_flash_message(string $tipo, string $mensagem): void { if (session_status() == PHP_SESSION_NONE) { session_start(); } $_SESSION['flash_message'] = ['tipo' => $tipo, 'mensagem' => $mensagem]; }
function display_flash_message(): void { if (isset($_SESSION['flash_message'])) { $flash = $_SESSION['flash_message']; echo '<div class="flash-message ' . htmlspecialchars($flash['tipo']) . '">' . htmlspecialchars($flash['mensagem']) . '</div>'; unset($_SESSION['flash_message']); } }
function abreviar_nome(string $nome_completo, int $max_len = 10): string { if (mb_strlen($nome_completo) <= $max_len) { return $nome_completo; } $partes = explode(' ', $nome_completo); $primeiro_nome = $partes[0]; if (count($partes) > 1) { $ultima_inicial = mb_substr(end($partes), 0, 1) . '.'; $abreviado = $primeiro_nome . ' ' . $ultima_inicial; if (mb_strlen($abreviado) <= $max_len) { return $abreviado; } } return mb_substr($nome_completo, 0, $max_len - 2) . '..'; }
function get_turno_icon(?string $turno, bool $is_fixo_cycle = false, bool $has_excecao = false): string { if ($is_fixo_cycle) { return $has_excecao ? '<span class="turno-icon fixo-excecao" title="Fixo Removido (Exceção)">🚫</span>' : '<span class="turno-icon fixo-ciclo" title="Fixo - Ciclo 24h">⏰</span>'; } switch ($turno) { case 'D': return '<span class="turno-icon turno-D" title="Diurno">☀️</span>'; case 'N': return '<span class="turno-icon turno-N" title="Noturno">🌑</span>'; case 'I': return '<span class="turno-icon turno-I" title="Integral 24h">📅</span>'; default: return ''; } }
?>