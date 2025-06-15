<?php
header('Content-Type: application/json');
if (!isset($conn) || !$conn instanceof mysqli) { require_once __DIR__ . '/../includes/db.php'; }
if (!isset($conn) || !$conn instanceof mysqli) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Falha DB API RegExcecao.']); exit; }
require_once __DIR__ . '/../includes/funcoes.php';

$response = ['success' => false, 'message' => 'Dados inválidos.'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bombeiro_id = filter_input(INPUT_POST, 'bombeiro_id', FILTER_VALIDATE_INT);
    $data_str = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($bombeiro_id && $data_str) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_str)) { $response['message'] = 'Formato data inválido.'; http_response_code(400); }
        else {
            if (registrar_excecao_fixo($bombeiro_id, $data_str, $conn)) { $response['success'] = true; $response['message'] = 'Exceção registrada.'; }
            else { $response['message'] = 'Não foi possível registrar (fixo não em serviço ou erro DB).'; http_response_code(400); error_log("API Falha registrar exceção ID $bombeiro_id em $data_str"); }
        }
    } else { $response['message'] = 'ID bombeiro ou data não fornecidos.'; http_response_code(400); }
} else { $response['message'] = 'Método não permitido.'; http_response_code(405); }
echo json_encode($response);
?>