<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/funcoes.php';

$bombeiro_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$bombeiro = null;
$erro = '';

if (!$bombeiro_id) {
    set_flash_message('error', 'ID de bombeiro inválido.');
    header('Location: bombeiros.php');
    exit;
}

// --- Processamento da Atualização (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar e pegar os dados do POST
    $id_update = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    // --- Dados Principais (Obrigatórios) ---
    $nome = trim($_POST['nome_completo'] ?? '');
    $cpf = trim($_POST['cpf'] ?? ''); // <<< NOVO CAMPO CPF
    $tipo = trim($_POST['tipo'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    // --- Dados do Ciclo Fixo (Obrigatório SE tipo == Fixo) ---
    $ref_data = trim($_POST['fixo_ref_data'] ?? '');
    $ref_dia = filter_input(INPUT_POST, 'fixo_ref_dia_ciclo', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);

    // --- Dados Opcionais (Salvar NULL se vazio) ---
    $endereco_rua = trim($_POST['endereco_rua'] ?? '');
    $endereco_numero = trim($_POST['endereco_numero'] ?? '');
    $endereco_bairro = trim($_POST['endereco_bairro'] ?? '');
    $endereco_cidade = trim($_POST['endereco_cidade'] ?? '');
    $endereco_uf = trim($_POST['endereco_uf'] ?? '');
    $endereco_cep = trim($_POST['endereco_cep'] ?? '');
    $telefone_principal = trim($_POST['telefone_principal'] ?? '');
    $contato_emergencia_nome = trim($_POST['contato_emergencia_nome'] ?? '');
    $contato_emergencia_fone = trim($_POST['contato_emergencia_fone'] ?? '');
    $tamanho_gandola = trim($_POST['tamanho_gandola'] ?? '');
    $tamanho_camiseta = trim($_POST['tamanho_camiseta'] ?? '');
    $tamanho_calca = trim($_POST['tamanho_calca'] ?? '');
    $tamanho_calcado = trim($_POST['tamanho_calcado'] ?? '');
    $dados_bancarios = trim($_POST['dados_bancarios'] ?? '');

    // --- Validação ---
    if ($id_update != $bombeiro_id) {
         set_flash_message('error', 'Erro: ID no formulário não confere.');
         header('Location: bombeiros.php'); exit;
    }
    // Adicionar validação básica de CPF se desejar (ex: formato)
    // if (!empty($cpf) && !preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
    //     $erro = 'Formato de CPF inválido. Use 000.000.000-00.';
    // } else
    if (empty($nome) || empty($tipo) || !in_array($tipo, ['BC', 'Fixo'])) {
         $erro = 'Nome e Tipo são obrigatórios e válidos.';
    } else if ($tipo == 'Fixo' && (empty($ref_data) || $ref_dia === null || $ref_dia === false)) {
         $erro = 'Para tipo Fixo, Data de Referência e Dia do Ciclo são obrigatórios.';
    } else {
        // --- Preparar Query de UPDATE ---
        $sql_update = "UPDATE bombeiros SET
                        nome_completo = ?, cpf = ?, tipo = ?, ativo = ?,
                        fixo_ref_data = ?, fixo_ref_dia_ciclo = ?,
                        endereco_rua = ?, endereco_numero = ?, endereco_bairro = ?,
                        endereco_cidade = ?, endereco_uf = ?, endereco_cep = ?,
                        telefone_principal = ?, contato_emergencia_nome = ?, contato_emergencia_fone = ?,
                        dados_bancarios = ?, tamanho_gandola = ?, tamanho_camiseta = ?,
                        tamanho_calca = ?, tamanho_calcado = ?
                       WHERE id = ?";

        if ($stmt = mysqli_prepare($conn, $sql_update)) {
             // Prepara valores para o banco (NULL se opcional estiver vazio)
             $db_cpf = !empty($cpf) ? $cpf : null; // <<< Salva NULL se CPF vazio
             $db_ref_data = ($tipo == 'Fixo') ? $ref_data : null;
             $db_ref_dia = ($tipo == 'Fixo') ? $ref_dia : null;
             $db_endereco_rua = !empty($endereco_rua) ? $endereco_rua : null;
             $db_endereco_numero = !empty($endereco_numero) ? $endereco_numero : null;
             $db_endereco_bairro = !empty($endereco_bairro) ? $endereco_bairro : null;
             $db_endereco_cidade = !empty($endereco_cidade) ? $endereco_cidade : null;
             $db_endereco_uf = !empty($endereco_uf) ? $endereco_uf : null;
             $db_endereco_cep = !empty($endereco_cep) ? $endereco_cep : null;
             $db_telefone_principal = !empty($telefone_principal) ? $telefone_principal : null;
             $db_contato_emergencia_nome = !empty($contato_emergencia_nome) ? $contato_emergencia_nome : null;
             $db_contato_emergencia_fone = !empty($contato_emergencia_fone) ? $contato_emergencia_fone : null;
             $db_dados_bancarios = !empty($dados_bancarios) ? $dados_bancarios : null;
             $db_tamanho_gandola = !empty($tamanho_gandola) ? $tamanho_gandola : null;
             $db_tamanho_camiseta = !empty($tamanho_camiseta) ? $tamanho_camiseta : null;
             $db_tamanho_calca = !empty($tamanho_calca) ? $tamanho_calca : null;
             $db_tamanho_calcado = !empty($tamanho_calcado) ? $tamanho_calcado : null;

            // Tipos: s=string, i=integer
            // nome(s), cpf(s), tipo(s), ativo(i), ref_data(s), ref_dia(i),         // 6
            // rua(s), num(s), bairro(s), cidade(s), uf(s), cep(s),                 // 6 = 12
            // fone(s), emerg_nome(s), emerg_fone(s),                               // 3 = 15
            // banco(s), gandola(s), camiseta(s), calca(s), calcado(s),             // 5 = 20
            // id(i)                                                                // 1 = 21
            mysqli_stmt_bind_param($stmt, "sssissssssssssssssssi", // <<< Adicionado um 's' para o CPF
                $nome, $db_cpf, $tipo, $ativo, // <<< $db_cpf adicionado aqui
                $db_ref_data, $db_ref_dia,
                $db_endereco_rua, $db_endereco_numero, $db_endereco_bairro,
                $db_endereco_cidade, $db_endereco_uf, $db_endereco_cep,
                $db_telefone_principal, $db_contato_emergencia_nome, $db_contato_emergencia_fone,
                $db_dados_bancarios, $db_tamanho_gandola, $db_tamanho_camiseta,
                $db_tamanho_calca, $db_tamanho_calcado,
                $bombeiro_id
            );

            if (mysqli_stmt_execute($stmt)) {
                if ($ativo == 0) { $bc_da_vez = get_config('bc_da_vez_id', $conn); if ($bc_da_vez == $bombeiro_id) { set_config('bc_da_vez_id', null, $conn); } }
                set_flash_message('success', 'Bombeiro atualizado com sucesso!');
                header('Location: bombeiros.php');
                exit;
            } else {
                $erro = "Erro ao executar atualização: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $erro = "Erro ao preparar query de atualização: " . mysqli_error($conn);
        }
    }
    if ($erro) {
        $bombeiro = $_POST;
        $bombeiro['id'] = $bombeiro_id;
        $bombeiro['ativo'] = isset($_POST['ativo']) ? 1 : 0;
        set_flash_message('error', $erro);
    }

} else {
    // --- Busca os dados do bombeiro (GET request) ---
    $sql_select = "SELECT * FROM bombeiros WHERE id = ?"; // Pega todas as colunas
    if ($stmt = mysqli_prepare($conn, $sql_select)) {
        mysqli_stmt_bind_param($stmt, "i", $bombeiro_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $bombeiro = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if (!$bombeiro) {
                set_flash_message('error', 'Bombeiro não encontrado.');
                header('Location: bombeiros.php');
                exit;
            }
        } else { $erro = "Erro ao buscar dados: " . mysqli_error($conn); }
        mysqli_stmt_close($stmt);
    } else { $erro = "Erro ao preparar busca: " . mysqli_error($conn); }
    if ($erro) { set_flash_message('error', $erro); }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Bombeiro</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Se quiser máscara de CPF, inclua um script JS como jQuery Mask Plugin ou vanilla JS -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script> -->
</head>
<body>

    <h1>Editar Bombeiro</h1>
    <p><a href="bombeiros.php">« Voltar para Lista</a></p>

    <?php display_flash_message(); ?>

    <?php if ($bombeiro): ?>
    <form action="editar_bombeiro.php?id=<?php echo $bombeiro_id; ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $bombeiro['id']; ?>">

        <div class="form-section">
            <h3>Dados Principais</h3>
            <div>
                <label for="nome_completo">Nome Completo:</label>
                <input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($bombeiro['nome_completo'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="cpf">CPF:</label> <!-- NOVO CAMPO CPF -->
                <input type="text" id="cpf" name="cpf" class="cpf-mask" value="<?php echo htmlspecialchars($bombeiro['cpf'] ?? ''); ?>" placeholder="000.000.000-00">
                <small>(Opcional)</small>
            </div>

            <div>
                <label for="tipo">Tipo:</label>
                <select id="tipo" name="tipo" required>
                    <option value="BC" <?php echo ($bombeiro['tipo'] ?? '') == 'BC' ? 'selected' : ''; ?>>BC (Voluntário)</option>
                    <option value="Fixo" <?php echo ($bombeiro['tipo'] ?? '') == 'Fixo' ? 'selected' : ''; ?>>Fixo (Efetivo)</option>
                </select>
            </div>

            <div id="fixo-fields">
                <p>Configurações do Ciclo (Obrigatório p/ Fixo):</p>
                <div>
                    <label for="fixo_ref_data">Data de Referência:</label>
                    <input type="date" id="fixo_ref_data" name="fixo_ref_data" value="<?php echo htmlspecialchars($bombeiro['fixo_ref_data'] ?? ''); ?>">
                </div>
                <div>
                    <label for="fixo_ref_dia_ciclo">Dia do Ciclo na Data Ref.:</label>
                    <select id="fixo_ref_dia_ciclo" name="fixo_ref_dia_ciclo">
                        <option value="">Selecione...</option>
                        <option value="1" <?php echo ($bombeiro['fixo_ref_dia_ciclo'] ?? '') == '1' ? 'selected' : ''; ?>>1 (Serviço)</option>
                        <option value="2" <?php echo ($bombeiro['fixo_ref_dia_ciclo'] ?? '') == '2' ? 'selected' : ''; ?>>2 (Folga 1)</option>
                        <option value="3" <?php echo ($bombeiro['fixo_ref_dia_ciclo'] ?? '') == '3' ? 'selected' : ''; ?>>3 (Folga 2)</option>
                        <option value="4" <?php echo ($bombeiro['fixo_ref_dia_ciclo'] ?? '') == '4' ? 'selected' : ''; ?>>4 (Folga 3)</option>
                    </select>
                </div>
            </div>

             <div>
                <label for="ativo" style="display: inline-block; margin-right: 10px;">Status:</label>
                <input type="checkbox" id="ativo" name="ativo" value="1" <?php echo ($bombeiro['ativo'] ?? 0) == 1 ? 'checked' : ''; ?> style="width: auto; vertical-align: middle;">
                 Ativo
            </div>
        </div>

        <div class="form-section">
            <h3>Endereço (Opcional)</h3>
            <!-- Campos de endereço como antes -->
             <div><label for="endereco_rua">Rua/Logradouro:</label><input type="text" id="endereco_rua" name="endereco_rua" value="<?php echo htmlspecialchars($bombeiro['endereco_rua'] ?? ''); ?>"></div>
             <div style="display: flex; gap: 15px;">
                 <div style="flex: 1;"><label for="endereco_numero">Número:</label><input type="text" id="endereco_numero" name="endereco_numero" value="<?php echo htmlspecialchars($bombeiro['endereco_numero'] ?? ''); ?>"></div>
                 <div style="flex: 3;"><label for="endereco_bairro">Bairro:</label><input type="text" id="endereco_bairro" name="endereco_bairro" value="<?php echo htmlspecialchars($bombeiro['endereco_bairro'] ?? ''); ?>"></div>
             </div>
             <div style="display: flex; gap: 15px;">
                 <div style="flex: 3;"><label for="endereco_cidade">Cidade:</label><input type="text" id="endereco_cidade" name="endereco_cidade" value="<?php echo htmlspecialchars($bombeiro['endereco_cidade'] ?? ''); ?>"></div>
                 <div style="flex: 1;"><label for="endereco_uf">UF:</label><input type="text" id="endereco_uf" name="endereco_uf" value="<?php echo htmlspecialchars($bombeiro['endereco_uf'] ?? ''); ?>" maxlength="2"></div>
                  <div style="flex: 2;"><label for="endereco_cep">CEP:</label><input type="text" id="endereco_cep" name="endereco_cep" class="cep-mask" value="<?php echo htmlspecialchars($bombeiro['endereco_cep'] ?? ''); ?>" placeholder="00000-000"></div>
             </div>
        </div>

         <div class="form-section">
            <h3>Contato (Opcional)</h3>
            <!-- Campos de contato como antes -->
             <div><label for="telefone_principal">Telefone Principal:</label><input type="text" id="telefone_principal" name="telefone_principal" class="phone-mask" value="<?php echo htmlspecialchars($bombeiro['telefone_principal'] ?? ''); ?>" placeholder="(00) 00000-0000"></div>
             <div><label for="contato_emergencia_nome">Contato de Emergência (Nome):</label><input type="text" id="contato_emergencia_nome" name="contato_emergencia_nome" value="<?php echo htmlspecialchars($bombeiro['contato_emergencia_nome'] ?? ''); ?>"></div>
             <div><label for="contato_emergencia_fone">Contato de Emergência (Telefone):</label><input type="text" id="contato_emergencia_fone" name="contato_emergencia_fone" class="phone-mask" value="<?php echo htmlspecialchars($bombeiro['contato_emergencia_fone'] ?? ''); ?>" placeholder="(00) 00000-0000"></div>
        </div>

        <div class="form-section">
            <h3>Uniformes (Opcional)</h3>
            <!-- Campos de uniforme como antes -->
            <div style="display: flex; gap: 15px;">
                 <div style="flex: 1;"><label for="tamanho_gandola">Gandola:</label><input type="text" id="tamanho_gandola" name="tamanho_gandola" value="<?php echo htmlspecialchars($bombeiro['tamanho_gandola'] ?? ''); ?>" placeholder="Ex: M, G, 42"></div>
                 <div style="flex: 1;"><label for="tamanho_camiseta">Camiseta:</label><input type="text" id="tamanho_camiseta" name="tamanho_camiseta" value="<?php echo htmlspecialchars($bombeiro['tamanho_camiseta'] ?? ''); ?>" placeholder="Ex: P, M, G"></div>
            </div>
             <div style="display: flex; gap: 15px;">
                 <div style="flex: 1;"><label for="tamanho_calca">Calça:</label><input type="text" id="tamanho_calca" name="tamanho_calca" value="<?php echo htmlspecialchars($bombeiro['tamanho_calca'] ?? ''); ?>" placeholder="Ex: 40, 42, M"></div>
                 <div style="flex: 1;"><label for="tamanho_calcado">Calçado (nº):</label><input type="text" id="tamanho_calcado" name="tamanho_calcado" value="<?php echo htmlspecialchars($bombeiro['tamanho_calcado'] ?? ''); ?>" placeholder="Ex: 41, 42"></div>
             </div>
        </div>

         <div class="form-section">
            <h3>Dados Bancários (Opcional)</h3>
            <!-- Campo de banco como antes -->
             <div><label for="dados_bancarios">Informações:</label><textarea id="dados_bancarios" name="dados_bancarios"><?php echo htmlspecialchars($bombeiro['dados_bancarios'] ?? ''); ?></textarea><small>Ex: Banco do Brasil, Ag: 1234-5, C/C: 98765-0, Nome Titular, CPF/CNPJ</small></div>
        </div>


        <button type="submit">Salvar Alterações</button>
        <a href="bombeiros.php" class="button-link btn-secondary" style="margin-left: 10px;">Cancelar</a>

    </form>
    <?php elseif (empty($erro)): ?>
        <p class="flash-message error">Bombeiro não encontrado.</p>
    <?php endif; ?>

    <script src="js/script.js"></script>
    <!-- Script opcional para máscaras -->
    <script>
        // Adiciona máscara de CPF (exemplo simples, pode precisar de biblioteca externa para robustez)
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, ''); // Remove não dígitos
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value.slice(0, 14); // Limita tamanho
            });
        }

         // Adiciona máscara de Telefone (exemplo simples)
         document.querySelectorAll('.phone-mask').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11); // Limita a 11 dígitos
                if (value.length > 10) { // Celular com 9 dígitos
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                } else if (value.length > 6) { // Fixo ou celular começando
                    value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                } else if (value.length > 2) { // DDD
                     value = value.replace(/(\d{2})(\d*)/, '($1) $2');
                } else if (value.length > 0) { // Parênteses inicial
                    value = value.replace(/(\d*)/, '($1');
                }
                e.target.value = value;
            });
         });

        // Adiciona máscara de CEP (exemplo simples)
        const cepInput = document.querySelector('.cep-mask');
        if(cepInput) {
            cepInput.addEventListener('input', (e) => {
                 let value = e.target.value.replace(/\D/g, '');
                 value = value.replace(/(\d{5})(\d)/, '$1-$2');
                 e.target.value = value.slice(0, 9);
            });
        }
    </script>

</body>
</html>