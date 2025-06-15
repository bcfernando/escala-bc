<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/funcoes.php';

$erro = '';
$bombeiros = [];

// --- Processamento de Adição ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_bombeiro'])) {
    $nome = trim($_POST['nome_completo'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');

    if (empty($nome) || empty($tipo)) {
        set_flash_message('error', 'Nome completo e Tipo são obrigatórios.');
    } elseif (!in_array($tipo, ['BC', 'Fixo'])) {
        set_flash_message('error', 'Tipo inválido.');
    } else {
        $sql_insert = "INSERT INTO bombeiros (nome_completo, tipo, ativo) VALUES (?, ?, 1)";
        if ($stmt = mysqli_prepare($conn, $sql_insert)) {
            mysqli_stmt_bind_param($stmt, "ss", $nome, $tipo);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Bombeiro adicionado com sucesso!');
            } else {
                set_flash_message('error', 'Erro ao adicionar bombeiro: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
             set_flash_message('error', 'Erro ao preparar query: ' . mysqli_error($conn));
        }
        // Redireciona para evitar reenvio do formulário com F5
        header("Location: bombeiros.php");
        exit;
    }
}

// --- Processamento de Marcação como Inativo (Soft Delete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_bombeiro_id'])) {
    $id_delete = filter_var($_POST['delete_bombeiro_id'], FILTER_VALIDATE_INT);
    if ($id_delete) {
        // Apenas marca como inativo
        $sql_delete = "UPDATE bombeiros SET ativo = 0 WHERE id = ?";
         if ($stmt = mysqli_prepare($conn, $sql_delete)) {
            mysqli_stmt_bind_param($stmt, "i", $id_delete);
            if (mysqli_stmt_execute($stmt)) {
                 // Limpa o bombeiro da vez se ele for o inativado
                 $bc_da_vez = get_config('bc_da_vez_id', $conn);
                 if ($bc_da_vez == $id_delete) {
                     set_config('bc_da_vez_id', null, $conn); // Força recálculo na próxima vez
                 }
                set_flash_message('success', 'Bombeiro marcado como inativo.');
            } else {
                set_flash_message('error', 'Erro ao inativar bombeiro: ' . mysqli_error($conn));
                // Nota: ON DELETE CASCADE na tabela 'plantoes' removerá os plantões futuros
                // Se não quisesse isso, precisaria de lógica adicional.
            }
            mysqli_stmt_close($stmt);
        } else {
             set_flash_message('error', 'Erro ao preparar query de inativação: ' . mysqli_error($conn));
        }
        header("Location: bombeiros.php");
        exit;
    }
}


// --- Busca de Bombeiros para Listagem ---
$sql_select = "SELECT id, nome_completo, tipo, ativo, fixo_ref_data, fixo_ref_dia_ciclo FROM bombeiros ORDER BY nome_completo ASC";
if ($result = mysqli_query($conn, $sql_select)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bombeiros[] = $row;
    }
    mysqli_free_result($result);
} else {
    $erro = "Erro ao buscar bombeiros: " . mysqli_error($conn);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Bombeiros</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <h1>Gerenciar Bombeiros</h1>
    <p><a href="escala.php">« Voltar para Escala</a></p>

    <?php display_flash_message(); ?>
    <?php if ($erro): ?>
        <p class="flash-message error"><?php echo htmlspecialchars($erro); ?></p>
    <?php endif; ?>

    <h2>Adicionar Novo Bombeiro</h2>
    <form action="bombeiros.php" method="post">
        <div>
            <label for="nome_completo">Nome Completo:</label>
            <input type="text" id="nome_completo" name="nome_completo" required>
        </div>
        <div>
            <label for="add_tipo">Tipo:</label>
            <select id="add_tipo" name="tipo" required>
                <option value="">Selecione...</option>
                <option value="BC">BC (Voluntário)</option>
                <option value="Fixo">Fixo (Efetivo)</option>
            </select>
        </div>
        <button type="submit" name="add_bombeiro">Adicionar Bombeiro</button>
    </form>

    <h2>Lista de Bombeiros</h2>
    <?php if (!empty($bombeiros)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Completo</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Ref. Fixo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bombeiros as $b): ?>
                    <tr class="<?php echo $b['ativo'] ? '' : 'bombeiro-inativo'; ?>">
                        <td><?php echo $b['id']; ?></td>
                        <td><?php echo htmlspecialchars($b['nome_completo']); ?></td>
                        <td><?php echo $b['tipo'] == 'BC' ? 'Voluntário' : 'Fixo'; ?></td>
                        <td><?php echo $b['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                        <td>
                            <?php if ($b['tipo'] == 'Fixo' && $b['fixo_ref_data']): ?>
                                <?php echo date('d/m/Y', strtotime($b['fixo_ref_data'])) . ' (Dia ' . $b['fixo_ref_dia_ciclo'] . ')'; ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="editar_bombeiro.php?id=<?php echo $b['id']; ?>" class="btn-secondary" style="padding: 5px 8px; font-size: 0.9em; color:white;">Editar</a>
                            <?php if ($b['ativo']): // Só mostra botão de inativar para ativos ?>
                                <form action="bombeiros.php" method="post" style="display: inline; padding: 0; margin: 0; box-shadow: none; background: none;">
                                    <input type="hidden" name="delete_bombeiro_id" value="<?php echo $b['id']; ?>">
                                    <button type="submit" class="btn-danger btn-delete-bombeiro" style="padding: 5px 8px; font-size: 0.9em;">Inativar</button>
                                </form>
                            <?php else: // Opcional: Botão para reativar ?>
                                <!-- <a href="reativar_bombeiro.php?id=<?php echo $b['id']; ?>" class="btn-success">Reativar</a> -->
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum bombeiro cadastrado.</p>
    <?php endif; ?>

    <script src="js/script.js"></script>

</body>
</html>