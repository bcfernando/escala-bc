<?php
header('Content-Type: application/json');

// Garante que a conexão com o banco de dados e as funções essenciais estejam disponíveis
if (!isset($conn) || !$conn instanceof mysqli) { 
    require_once __DIR__ . '/../includes/db.php'; 
}
if (!isset($conn) || !$conn instanceof mysqli) { 
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados.']); 
    exit; 
}
require_once __DIR__ . '/../includes/funcoes.php';

// Define uma resposta padrão em caso de falha inesperada
$response = ['success' => false, 'message' => 'Erro desconhecido ao processar a requisição.'];

// Verifica se a requisição foi feita usando o método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Inicia uma transação para garantir a integridade dos dados (ou tudo funciona, ou nada é salvo)
    mysqli_begin_transaction($conn);
    try {
        // Chama a função que avança a ordem e retorna o ID do novo bombeiro da vez
        $novo_id_da_vez = avancar_e_salvar_proximo_id($conn);

        if ($novo_id_da_vez === null) {
            // Se não há mais bombeiros na fila, a operação é um sucesso, mas informamos isso.
            mysqli_commit($conn); // Confirma a transação (mesmo que nada tenha mudado)
            $response['success'] = true;
            $response['message'] = 'Não há mais bombeiros na ordem de escolha.';
            $response['novo_nome'] = '(Fim da lista)'; // Envia um texto claro para o display
        
        } else {
            // Se um novo ID foi retornado, busca o nome correspondente
            $nome_novo_da_vez = get_bombeiro_nome($novo_id_da_vez, $conn);
            
            if ($nome_novo_da_vez) {
                // Se o nome foi encontrado, a operação foi um sucesso completo.
                mysqli_commit($conn); // Confirma a transação, salvando as alterações.
                $response['success'] = true;
                $response['message'] = 'Ordem avançada com sucesso.';
                
                // --- CORREÇÃO PRINCIPAL APLICADA AQUI ---
                // Adiciona a chave 'novo_nome' na resposta, que é o que o JavaScript espera.
                $response['novo_nome'] = $nome_novo_da_vez;

            } else {
                // Se o ID foi retornado mas o nome não, é um erro de integridade de dados.
                // Lança uma exceção para que a transação seja desfeita.
                throw new Exception('ID do próximo bombeiro encontrado, mas falha ao obter o nome correspondente.');
            }
        }
    } catch (Exception $e) {
        // Se qualquer erro (Exception) ocorrer dentro do bloco 'try', desfaz a transação
        mysqli_rollback($conn);
        $response['message'] = 'Erro no servidor: ' . $e->getMessage();
        error_log("Erro em api_avancar_ordem.php: " . $e->getMessage()); // Loga o erro para o desenvolvedor
        http_response_code(500); // É um erro interno, então o status 500 é apropriado
    }
} else {
    // Se a requisição não for POST, retorna um erro específico.
    $response['message'] = 'Método de requisição não permitido. Use POST.';
    http_response_code(405); // Código de status para "Method Not Allowed"
}

// Envia a resposta final para o JavaScript em formato JSON
echo json_encode($response);
?>