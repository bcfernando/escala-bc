<?php
// ATENÇÃO: Este script pode modificar dados. Execute com cautela.
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

echo "<pre>";
echo "========================================\n";
echo " INICIANDO TESTES AUTOMATIZADOS DO SISTEMA\n";
echo "========================================\n\n";

$tests_run = 0; $failures = 0;

function test_assert($condition, $message) { global $tests_run, $failures; $tests_run++; if ($condition) { echo "[SUCESSO] $message\n"; } else { echo "[FALHA]   $message\n"; $failures++; } }
function test_assert_equals($expected, $actual, $message) { global $tests_run, $failures; $tests_run++; $expected_export = var_export($expected, true); $actual_export = var_export($actual, true); if ($expected === $actual) { if (is_array($expected) && is_array($actual) && $expected == $actual) { echo "[SUCESSO] $message\n"; } else if ($expected === $actual) { echo "[SUCESSO] $message (Esperado: $expected_export, Recebido: $actual_export)\n"; } else { echo "[FALHA]   $message (Esperado: $expected_export, Recebido: $actual_export) - Tipos/Valores diferentes\n"; $failures++; } } else { echo "[FALHA]   $message (Esperado: $expected_export, Recebido: $actual_export)\n"; $failures++; } }
function test_assert_array_has_key($key, $array, $message) { global $tests_run, $failures; $tests_run++; if (is_array($array) && array_key_exists($key, $array)) { echo "[SUCESSO] $message (Chave '$key' encontrada)\n"; } else { echo "[FALHA]   $message (Chave '$key' NÃO encontrada no array: " . var_export($array, true) . ")\n"; $failures++; } }

// SIMULAÇÃO DE API - VERSÃO SIMPLIFICADA
function call_api_post($script_path, $post_data) {
    // Salva superglobais originais
    $original_post = $_POST;
    $original_server_method = $_SERVER['REQUEST_METHOD'] ?? null;

    $_POST = $post_data; // Define $_POST para a simulação
    $_SERVER['REQUEST_METHOD'] = 'POST'; // Simula o método

    ob_start();
    include $script_path; // O script da API fará seus próprios includes de db.php e funcoes.php
    $output = ob_get_clean();

    // Restaura superglobais
    $_POST = $original_post;
    if ($original_server_method !== null) $_SERVER['REQUEST_METHOD'] = $original_server_method;
    else unset($_SERVER['REQUEST_METHOD']);


    $decoded = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) { echo "[AVISO API POST] Resposta não JSON. Script: $script_path Output: $output\n"; return ['success' => false, 'message' => 'Resposta API inválida', 'raw_output' => $output]; }
    return $decoded;
}

function call_api_get($script_path, $get_data) {
    // Salva superglobais originais
    $original_get = $_GET;
    $original_server_method = $_SERVER['REQUEST_METHOD'] ?? null;

    $_GET = $get_data; // Define $_GET para a simulação
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    include $script_path;
    $output = ob_get_clean();

    // Restaura superglobais
    $_GET = $original_get;
    if ($original_server_method !== null) $_SERVER['REQUEST_METHOD'] = $original_server_method;
    else unset($_SERVER['REQUEST_METHOD']);

    $decoded = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) { echo "[AVISO API GET] Resposta não JSON. Script: $script_path Output: $output\n"; return ['success' => false, 'message' => 'Resposta API inválida', 'raw_output' => $output]; }
    return $decoded;
}

echo "--- Configurando Ambiente de Teste ---\n";
require_once __DIR__ . '/includes/db.php'; // Define $conn para funções chamadas DIRETAMENTE pelo teste
if (!$conn || $conn->connect_error) { die("[ERRO CRÍTICO] Falha DB: " . ($conn ? $conn->connect_error : mysqli_connect_error()) . "\n</pre>"); }
require_once __DIR__ . '/includes/funcoes.php';

function reset_database(mysqli $conn_param) { /* ...igual à última versão bem-sucedida... */ }
if (!reset_database($conn)) { die("[ERRO CRÍTICO] Falha reset DB.\n</pre>"); }
echo "\n";

define('ID_BC_ANDREA', 1); define('ID_BC_ANELI', 2); define('ID_FIXO_BRIAN', 4);
define('ID_FIXO_CLEIMAR', 5); define('ID_FIXO_CRISTIAN_K', 7); define('ID_FIXO_KELVIN', 12);

// --- Testes de Funções PHP Diretas (Ciclo, Ordem, CRUD) ---
// ... (COLE AQUI OS BLOCOS DE TESTE DE FUNÇÕES PHP DIRETAS DA ÚLTIMA RESPOSTA QUE PASSARAM) ...
// Exemplo:
echo "--- Testes: Funções de Ciclo Fixo ---\n";
$fixo_brian_data = ['id' => ID_FIXO_BRIAN, 'fixo_ref_data' => '2025-04-03', 'fixo_ref_dia_ciclo' => 1];
$data_servico_brian = '2025-05-01'; // Brian (ID 4) Ref 03/04 dia 1. 01/05 é 28 dias depois. (28%4)=0. Ciclo (0+1-1)%4+1 = 1.
test_assert_equals(1, calcular_dia_ciclo_fixo($fixo_brian_data, $data_servico_brian), "Ciclo Brian em $data_servico_brian");
// ... mais testes de funções ...
echo "\n";


// --- Testes: Simulação de APIs ---
echo "--- Testes: Simulação de APIs ---\n";
$data_api_teste = '2025-05-01'; // Dia em que Brian (ID 4) está de serviço

// 1. Teste api_get_details.php (estado inicial)
$detalhes1 = call_api_get(__DIR__ . '/api/api_get_details.php', ['date' => $data_api_teste]);
test_assert($detalhes1['success'] ?? false, "API GetDetails (inicial): success para $data_api_teste");
if(!($detalhes1['success'] ?? false)) {echo "   MSG GetDetails1: ".($detalhes1['message'] ?? 'N/A')."\n";}
test_assert_array_has_key('vagas', $detalhes1, "API GetDetails (inicial): tem 'vagas'");
if(isset($detalhes1['vagas'])) { test_assert_equals(1, $detalhes1['vagas']['D'], "API GetDetails (inicial): Vagas D (Brian on)"); }
if(isset($detalhes1['fixo_calculado'])) { test_assert_equals(false, $detalhes1['fixo_calculado']['tem_excecao'] ?? true, "API GetDetails (inicial): Brian sem exceção"); }

// 2. Teste api_registrar_excecao_fixo.php
$reg_exc = call_api_post(__DIR__ . '/api/api_registrar_excecao_fixo.php', ['bombeiro_id' => ID_FIXO_BRIAN, 'data' => $data_api_teste]);
test_assert($reg_exc['success'] ?? false, "API RegistrarExcecao: para Brian em $data_api_teste");
if(!($reg_exc['success'] ?? false)) {echo "   MSG RegExcecao: ".($reg_exc['message'] ?? 'N/A')."\n";}

// 3. Teste api_get_details.php (após registrar exceção)
$detalhes2 = call_api_get(__DIR__ . '/api/api_get_details.php', ['date' => $data_api_teste]);
test_assert($detalhes2['success'] ?? false, "API GetDetails (após exceção): success para $data_api_teste");
if(isset($detalhes2['vagas'])) { test_assert_equals(2, $detalhes2['vagas']['D'], "API GetDetails (após exceção): Vagas D (Brian com exceção)"); }
if(isset($detalhes2['fixo_calculado'])) { test_assert_equals(true, $detalhes2['fixo_calculado']['tem_excecao'] ?? false, "API GetDetails (após exceção): Brian TEM exceção"); }

// 4. Teste api_registrar_plantao.php (para um BC no dia da exceção)
$data_plantao = ['bombeiro_id' => ID_BC_ANDREA, 'data' => $data_api_teste, 'turno' => 'D'];
$reg_plantao = call_api_post(__DIR__ . '/api/api_registrar_plantao.php', $data_plantao);
test_assert($reg_plantao['success'] ?? false, "API RegistrarPlantao: Andrea (BC) no dia D da exceção");
if(!($reg_plantao['success'] ?? false)) {echo "   MSG RegPlantao: ".($reg_plantao['message'] ?? 'N/A')."\n";}

// 5. Teste api_get_details.php (após registrar BC)
$detalhes3 = call_api_get(__DIR__ . '/api/api_get_details.php', ['date' => $data_api_teste]);
if(isset($detalhes3['vagas'])) { test_assert_equals(1, $detalhes3['vagas']['D'], "API GetDetails (Andrea on): Vagas D"); }
$andrea_presente = false; if(!empty($detalhes3['extras'])) { foreach($detalhes3['extras'] as $ex){if($ex['bombeiro_id']==ID_BC_ANDREA && $ex['turno']=='D') $andrea_presente=true;}}
test_assert($andrea_presente, "API GetDetails: Andrea encontrada nos extras");

// 6. Teste api_remover_excecao_fixo.php
$rem_exc = call_api_post(__DIR__ . '/api/api_remover_excecao_fixo.php', ['bombeiro_id' => ID_FIXO_BRIAN, 'data' => $data_api_teste]);
test_assert($rem_exc['success'] ?? false, "API RemoverExcecao: para Brian");

// 7. Teste api_get_details.php (final, Brian de volta, Andrea ainda lá)
$detalhes4 = call_api_get(__DIR__ . '/api/api_get_details.php', ['date' => $data_api_teste]);
if(isset($detalhes4['vagas'])) { test_assert_equals(0, $detalhes4['vagas']['D'], "API GetDetails (Final): Vagas D (Brian de volta + Andrea)"); }
if(isset($detalhes4['fixo_calculado'])) { test_assert_equals(false, $detalhes4['fixo_calculado']['tem_excecao'] ?? true, "API GetDetails (Final): Brian SEM exceção"); }

// Limpeza do plantão da Andrea para não interferir em execuções futuras sem reset
$res_andrea_plantao = mysqli_query($conn, "SELECT id FROM plantoes WHERE bombeiro_id = ".ID_BC_ANDREA." AND data = '$data_api_teste' AND turno = 'D'");
$andrea_plantao_row = mysqli_fetch_assoc($res_andrea_plantao);
if($andrea_plantao_row && isset($andrea_plantao_row['id'])) {
    call_api_post(__DIR__ . '/api/api_remover_plantao.php', ['plantao_id' => $andrea_plantao_row['id']]);
}
if($res_andrea_plantao) mysqli_free_result($res_andrea_plantao);
echo "\n";

echo "========================================\n";
// ... (Relatório final igual) ...
mysqli_close($conn);
?>