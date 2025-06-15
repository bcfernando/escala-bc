<?php
header('Content-Type: application/json');
if (!isset($conn) || !$conn instanceof mysqli) { require_once __DIR__ . '/../includes/db.php'; }
if (!isset($conn) || !$conn instanceof mysqli) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Falha DB API RemoverPlantao.']); exit; }
require_once __DIR__ . '/../includes/funcoes.php';

$response = ['success' => false, 'message' => 'ID não fornecido.'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plantao_id = filter_input(INPUT_POST, 'plantao_id', FILTER_VALIDATE_INT);
    if ($plantao_id) {
        mysqli_begin_transaction($conn);
        try {
            $sql_delete = "DELETE FROM plantoes WHERE id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete)) { mysqli_stmt_bind_param($stmt_delete, "i", $plantao_id); if (mysqli_stmt_execute($stmt_delete)) { if (mysqli_stmt_affected_rows($stmt_delete) > 0) { mysqli_commit($conn); $response['success'] = true; $response['message'] = 'Removido!'; } else { throw new Exception('ID não encontrado ou não afetou linhas.'); } } else { throw new Exception('Erro executar: ' . mysqli_error($conn)); } mysqli_stmt_close($stmt_delete);
            } else { throw new Exception('Erro prepare delete: ' . mysqli_error($conn)); }
        } catch (Exception $e) { mysqli_rollback($conn); $response['message'] = $e->getMessage(); http_response_code(400); error_log("Erro api_remover_plantao: " . $e->getMessage()); }
    } else { $response['message'] = 'ID inválido.'; http_response_code(400); }
} else { $response['message'] = 'Método não permitido.'; http_response_code(405);}
echo json_encode($response);
?>