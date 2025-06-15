<?php
header('Content-Type: application/json');

// Verifica se $conn já existe e é válida (passada pelo script de teste via 'global' na função call_api)
// Se não, tenta incluir db.php para definir $conn (para chamadas diretas à API)
if (!isset($conn) || !$conn instanceof mysqli) {
    require_once __DIR__ . '/../includes/db.php'; // Define $conn
}
// Verificação final após tentativa de include
if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha crítica na conexão com o banco de dados na API GetDetails.']);
    exit;
}
require_once __DIR__ . '/../includes/funcoes.php';

$response = ['success' => false, 'message' => 'Erro desconhecido na API GetDetails.'];
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);

if ($date) {
    try {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { throw new Exception('Formato de data inválido.'); }
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $date) { throw new Exception('Data inválida.'); }

        $fixo_calculado_data = null;
        $sql_fixos_calc = "SELECT id, nome_completo, fixo_ref_data, fixo_ref_dia_ciclo FROM bombeiros WHERE tipo = 'Fixo' AND ativo = 1";
        if ($result_fc = mysqli_query($conn, $sql_fixos_calc)) {
            while ($row_fc = mysqli_fetch_assoc($result_fc)) {
                if (calcular_dia_ciclo_fixo($row_fc, $date) === 1) { $fixo_calculado_data = $row_fc; break; }
            } mysqli_free_result($result_fc);
        } else { throw new Exception('Erro ao buscar fixos para cálculo: ' . mysqli_error($conn)); }

        $fixo_tem_excecao = false;
        if ($fixo_calculado_data) { $fixo_tem_excecao = verificar_excecao_fixo((int)$fixo_calculado_data['id'], $date, $conn); }

        $fixo_real_servico = get_fixo_de_servico($date, $conn);
        $extras = []; $vagas_d = 2; $vagas_n = 2;
        if ($fixo_real_servico) { $vagas_d--; $vagas_n--; }

        $sql_extras = "SELECT p.id as plantao_id, p.turno, b.id as bombeiro_id, b.nome_completo, b.tipo FROM plantoes p JOIN bombeiros b ON p.bombeiro_id = b.id WHERE p.data = ? AND b.ativo = 1";
        if ($stmt_extras = mysqli_prepare($conn, $sql_extras)) {
            mysqli_stmt_bind_param($stmt_extras, "s", $date);
            if (mysqli_stmt_execute($stmt_extras)) {
                $result_extras = mysqli_stmt_get_result($stmt_extras);
                while ($row = mysqli_fetch_assoc($result_extras)) { $extras[] = $row; if ($row['turno'] == 'D') $vagas_d--; elseif ($row['turno'] == 'N') $vagas_n--; elseif ($row['turno'] == 'I') { $vagas_d--; $vagas_n--; } }
                mysqli_free_result($result_extras);
            } else { throw new Exception('Erro ao buscar plantões extras: ' . mysqli_stmt_error($stmt_extras)); }
            mysqli_stmt_close($stmt_extras);
        } else { throw new Exception('Erro ao preparar query de extras: ' . mysqli_error($conn)); }

        $vagas_d = max(0, $vagas_d); $vagas_n = max(0, $vagas_n);
        $proximo_sugerido_id = get_proximo_a_escolher_id($conn); $proximo_sugerido = null;
        if ($proximo_sugerido_id) { $nome_sugerido = get_bombeiro_nome($proximo_sugerido_id, $conn); if ($nome_sugerido) { $proximo_sugerido = ['id' => $proximo_sugerido_id, 'nome' => $nome_sugerido]; } }
        $bombeiros_ativos = []; $sql_ativos = "SELECT id, nome_completo, tipo FROM bombeiros WHERE ativo = 1 ORDER BY nome_completo ASC";
        if ($result_ativos = mysqli_query($conn, $sql_ativos)) { while ($row_ativo = mysqli_fetch_assoc($result_ativos)) { $bombeiros_ativos[] = $row_ativo; } mysqli_free_result($result_ativos); }
        else { throw new Exception('Erro ao buscar bombeiros ativos: ' . mysqli_error($conn));}

        $resposta_fixo_obj = null;
        if ($fixo_calculado_data) { $resposta_fixo_obj = [ 'id' => $fixo_calculado_data['id'], 'nome_completo' => $fixo_calculado_data['nome_completo'], 'tem_excecao' => $fixo_tem_excecao ]; }
        $response = [ 'success' => true, 'fixo_calculado' => $resposta_fixo_obj, 'extras' => $extras, 'vagas' => ['D' => $vagas_d, 'N' => $vagas_n], 'proximo_sugerido' => $proximo_sugerido, 'bombeiros_ativos' => $bombeiros_ativos ];
    } catch (Exception $e) {
        error_log("Erro em api_get_details para data $date: " . $e->getMessage());
        $response['message'] = "Erro ao processar detalhes do dia: " . $e->getMessage();
        // Não definir http_response_code(500) aqui se a intenção é sempre retornar JSON
    }
} else {
    $response['message'] = 'Parâmetro de data não fornecido ou inválido.';
    // Não definir http_response_code(400) aqui se a intenção é sempre retornar JSON
}
echo json_encode($response);
?>