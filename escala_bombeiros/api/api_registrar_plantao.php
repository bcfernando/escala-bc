<?php
header('Content-Type: application/json');
if (!isset($conn) || !$conn instanceof mysqli) { require_once __DIR__ . '/../includes/db.php'; }
if (!isset($conn) || !$conn instanceof mysqli) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Falha DB API RegistrarPlantao.']); exit; }
require_once __DIR__ . '/../includes/funcoes.php';

$response = ['success' => false, 'message' => 'Dados inválidos.'];
// ... (O restante do código desta API permanece o mesmo da última versão completa fornecida) ...
// Garanta que o corpo da função use a variável $conn que foi verificada no topo.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bombeiro_id = filter_input(INPUT_POST, 'bombeiro_id', FILTER_VALIDATE_INT);
    $data_str = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_SPECIAL_CHARS);
    $turno = filter_input(INPUT_POST, 'turno', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!$bombeiro_id || !$data_str || !in_array($turno, ['D', 'N', 'I'])) { $response['message'] = 'Dados de entrada inválidos (ID, Data, Turno).'; http_response_code(400); echo json_encode($response); exit; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_str)) { $response['message'] = 'Formato de data inválido. Use YYYY-MM-DD.'; http_response_code(400); echo json_encode($response); exit; }
    try { $dateTime = new DateTime($data_str); if ($dateTime->format('Y-m-d') !== $data_str) { throw new Exception('Data inválida.'); } $data = $data_str; }
    catch (Exception $e) { $response['message'] = "Erro na validação da data: " . $e->getMessage(); http_response_code(400); echo json_encode($response); exit; }

    mysqli_begin_transaction($conn);
    try {
        $sql_ativo = "SELECT tipo FROM bombeiros WHERE id = ? AND ativo = 1"; $bombeiro_tipo = null;
        if($stmt_ativo = mysqli_prepare($conn, $sql_ativo)) { mysqli_stmt_bind_param($stmt_ativo, "i", $bombeiro_id); mysqli_stmt_execute($stmt_ativo); mysqli_stmt_bind_result($stmt_ativo, $bombeiro_tipo); if (!mysqli_stmt_fetch($stmt_ativo)) { throw new Exception('Bombeiro selecionado não encontrado ou está inativo.'); } mysqli_stmt_close($stmt_ativo); } else { throw new Exception('Erro ao verificar status do bombeiro.'); }
        $sql_extra_check = "SELECT id FROM plantoes WHERE bombeiro_id = ? AND data = ? AND turno = ?";
        if ($stmt_extra_check = mysqli_prepare($conn, $sql_extra_check)) { mysqli_stmt_bind_param($stmt_extra_check, "iss", $bombeiro_id, $data, $turno); mysqli_stmt_execute($stmt_extra_check); mysqli_stmt_store_result($stmt_extra_check); if (mysqli_stmt_num_rows($stmt_extra_check) > 0) { throw new Exception('Este bombeiro já possui um plantão extra registrado manualmente neste dia/turno.'); } mysqli_stmt_close($stmt_extra_check); } else { throw new Exception('Erro ao verificar plantões extras existentes.'); }
        $vagas_d = 2; $vagas_n = 2; $fixo_servico_calculado = get_fixo_de_servico($data, $conn); if ($fixo_servico_calculado) { $vagas_d--; $vagas_n--; }
        $sql_vagas = "SELECT turno FROM plantoes WHERE data = ?";
        if ($stmt_vagas = mysqli_prepare($conn, $sql_vagas)) { mysqli_stmt_bind_param($stmt_vagas, "s", $data); mysqli_stmt_execute($stmt_vagas); mysqli_stmt_bind_result($stmt_vagas, $outro_turno); while(mysqli_stmt_fetch($stmt_vagas)) { if ($outro_turno == 'D') $vagas_d--; elseif ($outro_turno == 'N') $vagas_n--; elseif ($outro_turno == 'I') { $vagas_d--; $vagas_n--; } } mysqli_stmt_close($stmt_vagas); } else { throw new Exception('Erro ao verificar vagas existentes em plantoes.'); }
        $vagas_d = max(0, $vagas_d); $vagas_n = max(0, $vagas_n);
        $pode_registrar = false; if ($turno == 'D' && $vagas_d > 0) $pode_registrar = true; if ($turno == 'N' && $vagas_n > 0) $pode_registrar = true; if ($turno == 'I' && $vagas_d > 0 && $vagas_n > 0) $pode_registrar = true;
        if (!$pode_registrar) { throw new Exception("Não há vagas disponíveis para o turno '$turno' neste dia (Vagas D: $vagas_d, Vagas N: $vagas_n)."); }
        $sql_insert = "INSERT INTO plantoes (bombeiro_id, data, turno) VALUES (?, ?, ?)";
        if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) { mysqli_stmt_bind_param($stmt_insert, "iss", $bombeiro_id, $data, $turno); if (!mysqli_stmt_execute($stmt_insert)) { if (mysqli_errno($conn) == 1062) { throw new Exception('Falha ao registrar: Este bombeiro já parece estar neste turno específico (Unique Key).'); } else { throw new Exception('Erro desconhecido ao registrar o plantão: ' . mysqli_error($conn)); } } mysqli_stmt_close($stmt_insert); } else { throw new Exception('Erro ao preparar query de inserção.'); }
        $bc_da_vez_id = get_config('bc_da_vez_id', $conn); if ($bombeiro_tipo == 'BC' && $bc_da_vez_id == $bombeiro_id) { avancar_e_salvar_proximo_id($conn); }
        mysqli_commit($conn); $response['success'] = true; $response['message'] = 'Plantão registrado com sucesso!';
    } catch (Exception $e) { mysqli_rollback($conn); $response['message'] = $e->getMessage(); http_response_code(400); error_log("Erro em api_registrar_plantao: " . $e->getMessage()); }
} else { $response['message'] = 'Método não permitido.'; http_response_code(405); }
echo json_encode($response);
?>