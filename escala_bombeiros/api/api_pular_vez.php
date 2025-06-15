<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/funcoes.php';

$response = ['success' => false, 'message' => 'Não foi possível pular a vez.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_atual = get_config('bc_da_vez_id', $conn);
    $nome_atual = $id_atual ? get_nome_bombeiro($id_atual, $conn) : "ninguém";

    if (!$id_atual) {
         $response['message'] = 'Não há ninguém na vez para pular.';
         echo json_encode($response); exit;
    }

    // Avança para o próximo
    $proximo_id = avancar_vez($conn);

    if ($proximo_id !== null) {
        $nome_proximo = get_nome_bombeiro($proximo_id, $conn);
        $response['success'] = true;
        $response['message'] = "Vez de $nome_atual pulada. Próximo a escolher: $nome_proximo.";
        $response['proximo_a_escolher_id'] = $proximo_id;
        $response['proximo_a_escolher_nome'] = $nome_proximo;

        // Incrementa contador de pulos (para debug ou futuras regras)
        $pulos = (int)get_config('contagem_de_pulos_consecutivos', $conn);
        set_config('contagem_de_pulos_consecutivos', $pulos + 1, $conn);

    } else {
        // Situação onde não há mais ninguém para escolher após o pulo
        $response['success'] = true; // A ação de pular foi concluída
        $response['message'] = "Vez de $nome_atual pulada. Não há mais bombeiros na lista de escolha.";
        $response['proximo_a_escolher_id'] = null;
        $response['proximo_a_escolher_nome'] = "Ninguém";
        set_config('bc_da_vez_id', null, $conn); // Limpa a vez
    }

} else {
     $response['message'] = 'Método de requisição inválido.';
}


$conn->close();
echo json_encode($response);
?>