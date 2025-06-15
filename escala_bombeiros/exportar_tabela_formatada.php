<?php
require_once __DIR__ . '/includes/db.php';
// A função calcular_dia_ciclo_fixo ainda é necessária se você tiver bombeiros do tipo 'Fixo'
if (file_exists(__DIR__ . '/includes/funcoes.php')) {
    require_once __DIR__ . '/includes/funcoes.php';
}

// <<< MUDANÇA: Mapa de regras específicas para o negrito, corrigido conforme sua lista.
// A chave é o nome completo, e o valor é a parte a ser destacada.
$regras_negrito = [
    'ANDREA ZART' => 'ZART',
    'ANELI MIOTTO TERNUS' => 'ANELI',
    'ANGELICA BOETTCHER' => 'ANGELICA',
    'BRIAN DEIV HENRICH COSMAN' => 'COSMAN',
    'CLEIMAR BOETTCHER' => 'BOETTCHER',
    'CRISTIAN KONCZIKOSKI' => 'CRISTIAN',
    'CRISTIANE BOETTCHER' => 'CRISTIANE',
    'DOUGLAS LUBENOW' => 'DOUGLAS',
    'ELDI GELSI NICHTERWITZ PORTELA' => 'PORTELA',
    'JOSÉ NELSO BOITT' => 'NELSO',
    'KELVIN KERKHOFF' => 'KELVIN',
    'LUIZ FERNANDO HOHN' => 'FERNANDO',
    'MAICON MOHR' => 'MOHR',
    'MARCLEI NICHTERVITZ' => 'MARCLEI',
    'PATRICIA MARIA BOSING HOFFMANN' => 'PATRICIA',
    'PATRICIA BERTOLDI' => 'BERTOLDI',
    'ERASMO LOREIRO' => 'LOREIRO',
    'TATIANE ALTEMAIA' => 'TATIANE'
];

// Função atualizada para usar o mapa de regras.
function formatarNomeDestaque($nomeCompleto, $regras) {
    $nomeUpper = strtoupper($nomeCompleto);
    $nomeDisplay = htmlspecialchars($nomeUpper, ENT_QUOTES, 'UTF-8');

    if (isset($regras[$nomeUpper])) {
        $parteDestaque = $regras[$nomeUpper];
        $parteFormatada = '<b>' . $parteDestaque . '</b>';
        // str_ireplace para ser case-insensitive na substituição
        return str_ireplace($parteDestaque, $parteFormatada, $nomeDisplay);
    }

    // Se não houver uma regra específica, aplica uma regra padrão (negrito na última palavra)
    $palavras = explode(' ', $nomeDisplay);
    if (count($palavras) > 1) {
        $ultimaPalavra = array_pop($palavras);
        return implode(' ', $palavras) . ' <b>' . $ultimaPalavra . '</b>';
    }
    return '<b>' . $nomeDisplay . '</b>';
}


// --- LÓGICA DE BUSCA DE DADOS (Mantida) ---
$mes = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$ano = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($mes < 1 || $mes > 12) $mes = date('m');
if ($ano < 1970 || $ano > 2100) $ano = date('Y');

$dias_no_mes = date('t', mktime(0, 0, 0, $mes, 1, $ano));
$dias_semana_nomes_completos = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'];

$data_inicio = "$ano-$mes-01";
$data_fim = "$ano-$mes-$dias_no_mes";
$bombeiros_ativos = [];
$plantoes_map = [];

$sql_bombeiros = "SELECT id, nome_completo, tipo, fixo_ref_data, fixo_ref_dia_ciclo FROM bombeiros WHERE ativo = 1 ORDER BY nome_completo ASC";
$result_bombeiros = mysqli_query($conn, $sql_bombeiros);
if ($result_bombeiros) {
    while ($row = mysqli_fetch_assoc($result_bombeiros)) {
        $bombeiros_ativos[$row['id']] = $row;
    }
    mysqli_free_result($result_bombeiros);
} else {
    die("Erro ao buscar bombeiros: " . mysqli_error($conn));
}

$sql_plantoes = "SELECT bombeiro_id, data, turno FROM plantoes WHERE data BETWEEN ? AND ?";
if ($stmt_plantoes = mysqli_prepare($conn, $sql_plantoes)) {
    mysqli_stmt_bind_param($stmt_plantoes, "ss", $data_inicio, $data_fim);
    mysqli_stmt_execute($stmt_plantoes);
    $result_plantoes = mysqli_stmt_get_result($stmt_plantoes);
    while ($row = mysqli_fetch_assoc($result_plantoes)) {
        $plantoes_map[$row['bombeiro_id']][$row['data']] = $row['turno'];
    }
    mysqli_free_result($result_plantoes);
    mysqli_stmt_close($stmt_plantoes);
} else {
    die("Erro ao buscar plantões: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title></title> 
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #fff;
            margin: 15px;
        }
        .planilha-container {
            width: 100%;
            overflow-x: auto; 
        }
        h1 {
            text-align: center;
            font-size: 16pt;
            margin-bottom: 8px;
            font-weight: bold;
        }
        table {
            border-collapse: collapse;
            font-size: 8pt;
            width: 100%;
        }
        th, td {
            border: 1px solid black;
            padding: 3px;
            text-align: center;
            vertical-align: middle;
            height: 20px;
        }
        thead th {
            background-color: #ffffff;
            font-weight: bold;
        }
        tbody tr:nth-child(odd) {
            background-color: #FFFF00;
        }
        
        .nome-col {
            text-align: left;
            padding-left: 5px;
            min-width: 250px; 
            font-size: 9pt;
            position: sticky;
            left: 0;
            z-index: 1;
            border-right: 1px solid black;
        }
        .dia-col {
            min-width: 30px;
        }
        .shift-cell {
            font-size: 11pt;
        }
        
        tfoot td.assinatura-cell {
            border: none;
            padding-top: 60px;
            text-align: center;
        }
        .assinatura-content p {
            margin: 2px 0;
            font-size: 11pt;
        }
        .assinatura-content hr {
            width: 300px;
            margin: 5px auto;
            border: 0;
            border-top: 1px solid black;
        }
        .branding-text {
            margin-top: 20px !important;
            font-size: 9pt !important;
            color: #555;
            font-style: italic;
        }
        .btn-print {
            display: block;
            margin: 0 auto 15px auto;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }
        .btn-print:hover { background-color: #0056b3; }

        @media print {
            @page { size: landscape; margin: 0.5cm; }
            body { margin: 0; }
            .planilha-container { overflow-x: visible; }
            .btn-print { display: none; }
            .nome-col { position: static; }
            table { page-break-inside: auto; }
            tbody tr { page-break-inside: avoid; }
            tfoot { display: table-footer-group; }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print();">Imprimir / Salvar PDF</button>

    <div class="planilha-container">
        <h1>PLANILHA PLANTÕES BC</h1>
        
        <table>
            <thead>
                <tr>
                    <th class="nome-col">DIAS:</th>
                    <?php for ($d = 1; $d <= $dias_no_mes; $d++): ?>
                        <th class="dia-col"><?php echo $dias_semana_nomes_completos[date('w', mktime(0,0,0,$mes,$d,$ano))]; ?></th>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <th class="nome-col">NOME COMPLETO</th>
                    <?php for ($d = 1; $d <= $dias_no_mes; $d++): ?>
                        <th class="dia-col"><?php echo $d; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>

            <tbody>
                <?php $i = 1; ?>
                <?php foreach ($bombeiros_ativos as $b_id => $bombeiro): ?>
                <tr>
                    <td class="nome-col"><?php echo $i++ . '-' . formatarNomeDestaque($bombeiro['nome_completo'], $regras_negrito); ?></td>
                    <?php for ($d = 1; $d <= $dias_no_mes; $d++): ?>
                        <?php
                            $data_curr = sprintf("%s-%02d-%02d", $ano, $mes, $d);
                            $cell_content = ' ';
                            $is_fixo_cycle_day = false;
                            if (isset($bombeiro['tipo']) && $bombeiro['tipo'] == 'Fixo' && function_exists('calcular_dia_ciclo_fixo')) {
                                $dia_ciclo = calcular_dia_ciclo_fixo($bombeiro, $data_curr);
                                if ($dia_ciclo === 1) {
                                    $is_fixo_cycle_day = true;
                                    $cell_content = 'I';
                                }
                            }
                            if (!$is_fixo_cycle_day && isset($plantoes_map[$b_id][$data_curr])) {
                                $cell_content = $plantoes_map[$b_id][$data_curr];
                            }
                        ?>
                        <td class="shift-cell"><?php echo $cell_content; ?></td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td class="assinatura-cell" colspan="<?php echo $dias_no_mes + 1; ?>">
                        <div class="assinatura-content">
                            <hr>
                            <p>3º SGT Ricardo TOLFO</p>
                            <p>COORDENADOR BC</p>
                            <p>1º/2º/2ª/6ºBBM – São Carlos.</p>
                            <p class="branding-text">
                                Este sistema foi desenvolvido por Luiz Fernando Hohn para facilitar e aprimorar a organização das escalas de plantão.
                            </p>
                        </div>
                    </td>
                </tr>
            </tfoot>

        </table>
    </div>

</body>
</html>
<?php
if (isset($conn)) {
    mysqli_close($conn);
}
?>