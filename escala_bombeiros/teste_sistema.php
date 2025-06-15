<?php
// ATENÇÃO: Este script pode modificar dados no banco, especialmente se a função de reset for usada.
// NÃO o exponha publicamente. Execute via linha de comando ou proteja o acesso.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "=======================================\n";
echo " INICIANDO TESTES AUTOMATIZADOS BÁSICOS\n";
echo "=======================================\n\n";

$test_count = 0;
$failures = 0;

// --- Helpers de Teste ---
function assert_equals($expected, $actual, string $message): void {
    global $test_count, $failures;
    $test_count++;
    // Usar comparação estrita (===) para tipo e valor
    if ($expected === $actual) {
        // Para arrays, var_export pode ser muito longo, usar print_r ou json_encode pode ser melhor
        // Vamos simplificar a saída para arrays para não poluir muito
        $actual_output = is_array($actual) ? '[Array]' : var_export($actual, true);
        $expected_output = is_array($expected) ? '[Array]' : var_export($expected, true);
         if (is_array($expected) && is_array($actual)) {
             // Se for array e passou, só mostra a mensagem
              echo "[SUCESSO] $message\n";
         } else {
             echo "[SUCESSO] $message (Esperado: " . $expected_output . ", Recebido: " . $actual_output . ")\n";
         }
    } else {
        $failures++;
        // Mostrar mais detalhes na falha
        echo "[FALHA]   $message (Esperado: " . var_export($expected, true) . ", Recebido: " . var_export($actual, true) . ")\n";
    }
}
function assert_not_null($actual, string $message): void {
    global $test_count, $failures; $test_count++;
    if ($actual !== null) { echo "[SUCESSO] $message (Recebido não é NULL)\n"; }
    else { $failures++; echo "[FALHA]   $message (Recebido foi NULL)\n"; }
}
function assert_true($actual, string $message): void { assert_equals(true, (bool)$actual, $message); }
function assert_false($actual, string $message): void { assert_equals(false, (bool)$actual, $message); }

function run_setup(mysqli $conn): bool {
     echo "\n--- Executando Setup Inicial (Limpando Dados) ---\n";
     $sql_setup = file_get_contents(__DIR__ . '/setup_inicial.sql');
     if ($sql_setup === false) { echo "[ERRO CRÍTICO] Não foi possível ler setup_inicial.sql\n"; return false; }
     if (mysqli_multi_query($conn, $sql_setup)) {
         while (mysqli_more_results($conn)) {
             if (!mysqli_next_result($conn)) { echo "[ERRO CRÍTICO] Erro multi_query clear: " . mysqli_error($conn) . "\n"; return false; }
             if ($result = mysqli_store_result($conn)) { mysqli_free_result($result); }
         }
         echo "[SUCESSO] Setup inicial executado.\n"; return true;
     } else { echo "[ERRO CRÍTICO] Falha multi_query: " . mysqli_error($conn) . "\n"; return false; }
 }

// --- Início dos Testes ---

echo "--- Teste 1: Conexão e Includes ---\n";
require_once __DIR__ . '/includes/db.php'; // $conn e DB_NAME (constante) definidos aqui
require_once __DIR__ . '/includes/funcoes.php';
// CORREÇÃO: Usa a constante DB_NAME definida em db.php
assert_not_null($conn, "Conexão com o banco de dados (" . DB_NAME . ")");
if (!$conn) { die("Teste abortado: Falha na conexão com DB.\n</pre>"); }
echo "\n";

// Descomente com CUIDADO!
// if (!run_setup($conn)) { die("Teste abortado: Falha no setup.\n</pre>"); }

echo "--- Teste 2: Funções de Ciclo Fixo ---\n";
$fixo_cleimar = ['fixo_ref_data' => '2025-04-01', 'fixo_ref_dia_ciclo' => 1];
$fixo_brian = ['fixo_ref_data' => '2025-04-03', 'fixo_ref_dia_ciclo' => 1];
assert_equals(1, calcular_dia_ciclo_fixo($fixo_cleimar, '2025-04-01'), "Cálculo dia ciclo Cleimar em 01/04");
assert_equals(2, calcular_dia_ciclo_fixo($fixo_cleimar, '2025-04-02'), "Cálculo dia ciclo Cleimar em 02/04");
assert_equals(3, calcular_dia_ciclo_fixo($fixo_cleimar, '2025-04-03'), "Cálculo dia ciclo Cleimar em 03/04");
assert_equals(4, calcular_dia_ciclo_fixo($fixo_cleimar, '2025-04-04'), "Cálculo dia ciclo Cleimar em 04/04");
assert_equals(1, calcular_dia_ciclo_fixo($fixo_cleimar, '2025-04-05'), "Cálculo dia ciclo Cleimar em 05/04 (volta)");
assert_equals(3, calcular_dia_ciclo_fixo($fixo_brian, '2025-04-01'), "Cálculo dia ciclo Brian em 01/04 (ref 03/04)");
$fixo_servico_01_04 = get_fixo_de_servico('2025-04-01', $conn);
$fixo_servico_03_04 = get_fixo_de_servico('2025-04-03', $conn);
assert_not_null($fixo_servico_01_04, "Encontrar fixo de serviço em 01/04");
assert_equals("CLEIMAR BOETTCHER", $fixo_servico_01_04['nome_completo'] ?? null, "Nome do fixo de serviço em 01/04");
assert_not_null($fixo_servico_03_04, "Encontrar fixo de serviço em 03/04");
assert_equals("BRIAN DEIV HENRICH COSMAN", $fixo_servico_03_04['nome_completo'] ?? null, "Nome do fixo de serviço em 03/04");
// CORREÇÃO DO TESTE: Cristian K. está de serviço em 06/04
$fixo_servico_06_04 = get_fixo_de_servico('2025-04-06', $conn);
assert_not_null($fixo_servico_06_04, "Encontrar fixo de serviço em 06/04");
assert_equals("CRISTIAN KONCZIKOSKI", $fixo_servico_06_04['nome_completo'] ?? null, "Nome do fixo de serviço em 06/04");
echo "\n";

echo "--- Teste 3: Funções de Ordem de Escolha BC ---\n";
set_config('ultimo_bc_iniciou_mes', null, $conn); set_config('bc_da_vez_id', null, $conn);
$ordem_inicial = get_ordem_escolha_ids($conn); assert_true(!empty($ordem_inicial), "Obter ordem inicial de BCs");
$ids_bcs_esperados = []; $res_ids = mysqli_query($conn, "SELECT id FROM bombeiros WHERE tipo='BC' AND ativo=1 ORDER BY id ASC"); while($row_id = mysqli_fetch_assoc($res_ids)) { $ids_bcs_esperados[] = (string)$row_id['id']; } mysqli_free_result($res_ids);
assert_equals($ids_bcs_esperados[0] ?? '[Erro: Nenhum BC?]', $ordem_inicial[0] ?? null, "Primeiro BC na ordem inicial (por ID)");
$primeiro_sugerido_id = get_proximo_a_escolher_id($conn);
assert_equals($ids_bcs_esperados[0] ?? '[Erro: Nenhum BC?]', $primeiro_sugerido_id, "Primeiro sugerido é o primeiro da ordem");
assert_equals($primeiro_sugerido_id, get_config('bc_da_vez_id', $conn), "Config 'bc_da_vez_id' salva");
assert_equals($primeiro_sugerido_id, get_config('ultimo_bc_iniciou_mes', $conn), "Config 'ultimo_bc_iniciou_mes' salva na 1a sugestão");
$segundo_sugerido_id_esperado = $ids_bcs_esperados[1] ?? null;
$proximo_id_avancado = avancar_e_salvar_proximo_id($conn);
assert_equals($segundo_sugerido_id_esperado, $proximo_id_avancado, "Avançar para o segundo sugerido");
assert_equals($segundo_sugerido_id_esperado, get_config('bc_da_vez_id', $conn), "Config 'bc_da_vez_id' atualizada pós avanço");
set_config('ultimo_bc_iniciou_mes', $ids_bcs_esperados[0] ?? null, $conn); set_config('bc_da_vez_id', null, $conn);
$ordem_rotacionada = get_ordem_escolha_ids($conn);
assert_equals($segundo_sugerido_id_esperado, $ordem_rotacionada[0] ?? null, "Primeiro da ordem após rotação");
$proximo_sugerido_rotacionado = get_proximo_a_escolher_id($conn);
assert_equals($segundo_sugerido_id_esperado, $proximo_sugerido_rotacionado, "Primeiro sugerido após rotação");
assert_equals($proximo_sugerido_rotacionado, get_config('ultimo_bc_iniciou_mes', $conn), "Config 'ultimo_bc_iniciou_mes' atualizada na rotação");
echo "\n";

echo "--- Teste 4: CRUD Básico (Adicionar/Inativar via DB) ---\n";
$nome_teste = "Bombeiro Teste CRUD " . uniqid();
$sql_add = "INSERT INTO bombeiros (nome_completo, tipo, ativo) VALUES (?, 'BC', 1)";
$stmt_add = mysqli_prepare($conn, $sql_add); mysqli_stmt_bind_param($stmt_add, "s", $nome_teste);
assert_true(mysqli_stmt_execute($stmt_add), "Adicionar bombeiro teste via DB");
$id_teste = mysqli_insert_id($conn); mysqli_stmt_close($stmt_add);
assert_true($id_teste > 0, "Obter ID do bombeiro teste");
$sql_inativar = "UPDATE bombeiros SET ativo = 0 WHERE id = ?";
$stmt_inativar = mysqli_prepare($conn, $sql_inativar); mysqli_stmt_bind_param($stmt_inativar, "i", $id_teste);
assert_true(mysqli_stmt_execute($stmt_inativar), "Inativar bombeiro teste via DB");
mysqli_stmt_close($stmt_inativar);
$sql_check_inativo = "SELECT ativo FROM bombeiros WHERE id = ?";
$stmt_check_inativo = mysqli_prepare($conn, $sql_check_inativo); mysqli_stmt_bind_param($stmt_check_inativo, "i", $id_teste); mysqli_stmt_execute($stmt_check_inativo); mysqli_stmt_bind_result($stmt_check_inativo, $status_ativo); mysqli_stmt_fetch($stmt_check_inativo);
// CORREÇÃO: Comparar com 0 diretamente
assert_equals(0, (int)$status_ativo, "Verificar se bombeiro teste está inativo no DB");
mysqli_stmt_close($stmt_check_inativo);
// Limpeza (opcional, mas recomendado após testes CRUD)
$sql_del = "DELETE FROM bombeiros WHERE id = ?"; $stmt_del=mysqli_prepare($conn, $sql_del); mysqli_stmt_bind_param($stmt_del, "i", $id_teste); mysqli_stmt_execute($stmt_del); mysqli_stmt_close($stmt_del);
echo "\n";

// echo "--- Teste 5: Simulação de APIs (Básico) ---\n";
// echo "[INFO] Testes de API não implementados neste script básico.\n";
// echo "\n";

echo "=======================================\n";
echo " TESTES CONCLUÍDOS\n";
echo " Total de Testes: $test_count\n"; // Recontará com o teste dividido
echo " Falhas: $failures\n";
echo "=======================================\n";
echo "</pre>";

mysqli_close($conn);
?>