<?php
// Configurações do Banco de Dados
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Usuário padrão do XAMPP
define('DB_PASSWORD', '');     // Senha padrão do XAMPP (vazia)
define('DB_NAME', 'escala_db'); // Nome do seu banco de dados - CONFIRMADO

// Tenta conectar ao banco de dados MySQL/MariaDB
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão ANTES de tentar usar $conn
if ($conn === false) {
    // Para depuração, podemos logar o erro. Em produção, trate de forma mais elegante.
    $error_msg = "ERRO NO DB.PHP: Não foi possível conectar ao banco de dados. " . mysqli_connect_error();
    error_log($error_msg); // Importante para logs do servidor

    // Se este arquivo for incluído diretamente por uma página que precisa morrer em caso de falha,
    // o die() abaixo pode ser usado. Mas para APIs que devem retornar JSON,
    // é melhor deixar a API verificar $conn e retornar um JSON de erro.
    // die($error_msg); // Comentado para permitir que as APIs tratem a falha.
} else {
    // Define o charset para UTF-8 APENAS se a conexão foi bem-sucedida
    if (!mysqli_set_charset($conn, "utf8mb4")) {
        error_log("ERRO NO DB.PHP: Falha ao definir mysqli_set_charset: " . mysqli_error($conn));
    }
}

// Inicia a sessão se ainda não estiver iniciada (para mensagens flash)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>