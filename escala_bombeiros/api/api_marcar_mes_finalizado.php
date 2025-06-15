<?php
// api/api_marcar_mes_finalizado.php

header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/funcoes.php'; // Para get_ordem_escolha_ids

$response = ['success' => false, 'message' => 'Dados inválidos recebidos.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mes'], $_POST['ano'])) {
    $mes = filter_var($_POST['mes'], FILTER_VALIDATE_INT);
    $ano = filter_var($_POST['ano'], FILTER_VALIDATE_INT);

    if (!$mes || !$ano || $mes < 1 || $mes > 12 || $ano < 2020) {
        $response['message'] = 'Mês ou ano inválido.';
    } else {
        $conn->begin_transaction(); // Inicia transação para garantir consistência
        $ok1 = false; $ok2 = false; $ok3 = false;

        try {
            // 1. Determina quem iniciaria a seleção neste mês/ano
            $ordem_inicio = get_ordem_escolha_ids($conn);
            $quem_iniciou_neste_mes_id = !empty($ordem_inicio) ? $ordem_inicio[0] : null;

            if ($quem_iniciou_neste_mes_id) {
                // 2. Salva quem iniciou para a rotação do PRÓXIMO mês
                $ok1 = set_config('ultimo_bc_iniciou_mes', $quem_iniciou_neste_mes_id, $conn);
            } else {
                $ok1 = true; // Considera sucesso se não houver ninguém para salvar
                error_log("Aviso: Nenhum bombeiro encontrado para registrar como início do mês $mes/$ano.");
            }

            // 3. Marca o mês como finalizado (para estado do botão)
            $chave_mes_finalizado = 'mes_finalizado_' . $ano . str_pad($mes, 2, '0', STR_PAD_LEFT);
            $ok2 = set_config($chave_mes_finalizado, '1', $conn);

            // 4. LIMPA a config 'bc_da_vez_id' - Garante que próximo acesso a outro mês recalcule
            $ok3 = set_config('bc_da_vez_id', null, $conn);

            if ($ok1 && $ok2 && $ok3) {
                $conn->commit(); // Confirma as alterações
                $response['success'] = true;
                $response['message'] = 'Mês marcado como finalizado e referência salva para o próximo mês.';
            } else {
                throw new Exception("Falha ao salvar uma das configurações."); // Força o rollback
            }
        } catch (Exception $e) {
            $conn->rollback(); // Desfaz alterações em caso de erro
            $response['message'] = 'Erro ao salvar configurações: ' . $e->getMessage();
            error_log("Erro ao marcar mes $mes/$ano finalizado: " . $e->getMessage() . " ok1=$ok1, ok2=$ok2, ok3=$ok3");
        }
    }
} else {
    $response['message'] = 'Método ou parâmetros inválidos.';
}

$conn->close();
echo json_encode($response);
?>