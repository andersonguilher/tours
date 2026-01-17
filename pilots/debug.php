<?php
// ATIVAR EXIBIÇÃO DE ERROS (Obrigatório para diagnosticar Erro 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Início do Diagnóstico</h1>";

// 1. VERIFICAR ARQUIVO DE CONFIGURAÇÃO (DB)
$dbFile = __DIR__ . '/../config/db.php';
echo "Tentando carregar banco de dados em: <code>$dbFile</code>... ";

if (!file_exists($dbFile)) {
    die("<span style='color:red; font-weight:bold'>❌ FALHA: O arquivo config/db.php não existe! Verifique o caminho.</span>");
}

try {
    require_once $dbFile;
    echo "<span style='color:green; font-weight:bold'>✅ OK</span><br>";
} catch (Exception $e) {
    die("<span style='color:red'>❌ ERRO AO INCLUIR DB: " . $e->getMessage() . "</span>");
}

// Verifica se a variável $pdo foi criada
if (!isset($pdo)) {
    die("<span style='color:red'>❌ ERRO CRÍTICO: O arquivo db.php foi carregado, mas a variável \$pdo não existe. Verifique o conteúdo de db.php.</span>");
} else {
    echo "Conexão \$pdo detectada com sucesso.<br>";
}

// 2. VERIFICAR ARQUIVO DE RANKING
$rankFile = __DIR__ . '/../includes/RankSystem.php';
echo "Tentando carregar RankSystem em: <code>$rankFile</code>... ";

if (!file_exists($rankFile)) {
    die("<span style='color:red; font-weight:bold'>❌ FALHA: O arquivo includes/RankSystem.php não existe! Verifique o caminho.</span>");
}

try {
    require_once $rankFile;
    echo "<span style='color:green; font-weight:bold'>✅ OK</span><br>";
} catch (Exception $e) {
    die("<span style='color:red'>❌ ERRO AO INCLUIR RANKSYSTEM (Provável erro de sintaxe no arquivo): " . $e->getMessage() . "</span>");
}

if (!class_exists('RankSystem')) {
    die("<span style='color:red'>❌ ERRO: O arquivo foi carregado, mas a Classe 'RankSystem' não foi encontrada dentro dele.</span>");
}

// 3. DIAGNÓSTICO DO BANCO DE DADOS
echo "<hr><h3>📊 Teste de Dados</h3>";

$pilot_id = 24; // Seu ID

// Buscar horas
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) as total FROM tour_history WHERE pilot_id = ?");
$stmt->execute([$pilot_id]);
$mins = $stmt->fetchColumn() ?: 0;
$hours = floor($mins / 60);

echo "Piloto ID: $pilot_id <br>";
echo "Minutos Totais: $mins <br>";
echo "Horas Totais: <strong>$hours</strong> <br><br>";

// Buscar Regras
echo "<strong>Regras na Tabela tour_ranks:</strong><br>";
$stmt = $pdo->query("SELECT * FROM tour_ranks ORDER BY min_hours ASC");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rules) == 0) {
    echo "<span style='color:red; font-weight:bold'>⚠️ ATENÇÃO: A tabela tour_ranks está VAZIA!</span><br>";
    echo "O sistema vai sempre retornar 'Aluno' se não houver regras.<br>";
    echo "SOLUÇÃO: Rode o INSERT INTO tour_ranks no seu banco de dados.";
} else {
    echo "<ul>";
    foreach($rules as $r) {
        $star = $r['has_star'] ? "⭐" : "";
        echo "<li>{$r['min_hours']}h = {$r['rank_title']} (Stripes: {$r['stripes']} $star)</li>";
    }
    echo "</ul>";
}

// 4. TESTE FINAL DE CÁLCULO
echo "<hr><h3>🧪 Teste de Cálculo</h3>";
echo "Tentando calcular patente para $mins minutos ($hours horas)...<br>";

$rank = RankSystem::getRank($mins, $pdo);

echo "Resultado do PHP: <span style='font-size:20px; font-weight:bold; color:blue'>" . $rank['title'] . "</span><br>";
echo "Stripes: " . $rank['stripes'] . "<br>";

if ($hours >= 10 && $rank['title'] == 'Aluno' && count($rules) > 0) {
    echo "<br><span style='color:red; font-weight:bold'>❌ DIAGNÓSTICO: O cálculo falhou mesmo com regras no banco. Verifique a lógica do RankSystem.php.</span>";
} elseif ($hours >= 10 && $rank['title'] != 'Aluno') {
    echo "<br><span style='color:green; font-weight:bold'>✅ SUCESSO: O sistema está calculando corretamente! Limpe o cache do navegador no index.php.</span>";
}

?>