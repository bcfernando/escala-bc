<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/funcoes.php';

$response = ['success' => false, 'message' => 'Não foi possível finalizar a escala.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Identifica quem iniciou a seleção neste mês (o primeiro da ordem atual)
    //    OU quem estava na vez quando clicou em finalizar?
    //    A regra diz: "identifica quem foi o bombeiro que INICIOU a seleção neste mês."
    $ordem_atual = get_ordem_escolha_ids($conn);
    $quem_iniciou_este_mes_id = !empty($ordem_atual) ? $ordem_atual[0] : null;

    if ($quem_iniciou_este_mes_id) {
        // 2. Salva o ID desse bombeiro na configuração ultimo_bc_iniciou_mes
        $ok1 = set_config('ultimo_bc_iniciou_mes', $quem_iniciou_este_mes_id, $conn);

        // 3. Limpa a configuração bc_da_vez_id
        $ok2 = set_config('bc_da_vez_id', null, $conn); // Usa NULL explicitamente

        // 4. Reseta contadores (opcional, mas bom)
        set_config('contagem_de_pulos_consecutivos', '0', $conn);
        set_config('id_da_ultima_acao_bc', null, $conn);

        if ($ok1 && $ok2) {
            $response['success'] = true;
            $response['message'] = 'Seleção de escala finalizada com sucesso! O próximo mês começará após ' . get_nome_bombeiro($quem_iniciou_este_mes_id, $conn) . '.';
        } else {
            error_log("Erro ao finalizar escala: ok1=$ok1, ok2=$ok2");
            $response['message'] = 'Erro ao atualizar as configurações do banco de dados ao finalizar.';
        }
    } else {
         $response['message'] = 'Não foi possível determinar quem iniciou a seleção (nenhum bombeiro ativo?). A finalização não foi realizada.';
    }

} else {
     $response['message'] = 'Método de requisição inválido.';
}

$conn->close();
echo json_encode($response);
?>