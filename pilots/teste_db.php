<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Iniciando Teste de Conexão...</h1>";

// Tenta incluir a configuração
echo "<p>Tentando carregar config/db.php...</p>";
require '../config/db.php';
echo "<p>Arquivo incluído com sucesso!</p>";

// Tenta verificar se a variavel $pdo existe
if (isset($pdo)) {
    echo "<p style='color:green'>SUCESSO: A variável \$pdo existe!</p>";
    
    // Tenta fazer uma consulta simples
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "<p style='color:green'>SUCESSO: O banco respondeu ao comando SELECT 1.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>ERRO: Conectou, mas não conseguiu consultar: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color:red'>ERRO CRÍTICO: O arquivo config/db.php foi carregado, mas a variável \$pdo não foi criada.</p>";
}
?>