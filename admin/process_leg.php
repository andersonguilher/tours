<?php
// admin/process_leg.php
// PROCESSADOR DE DADOS DAS PERNAS - Suporta INSERT, UPDATE e DELETE
// Inclui função de atualização automática de aeroportos via SimBrief Data

require '../config/db.php';

// --- SEGURANÇA ---
$possiblePaths = [
    __DIR__ . '/../../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
];
foreach ($possiblePaths as $path) { if (file_exists($path)) { require_once $path; break; } }

if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

$action = $_POST['action'] ?? '';
$tour_id = $_POST['tour_id'] ?? 0;

if ($tour_id == 0) {
    die("Erro: Tour ID não informado.");
}

/**
 * Função Auxiliar: Atualiza ou Cria aeroportos com dados do SimBrief
 * Garante que o mapa tenha coordenadas para desenhar a rota
 */
function updateAirportDB($pdo, $data) {
    if (!$data || !is_array($data)) return;
    
    foreach ($data as $apt) {
        if (!isset($apt['icao'])) continue;

        // Verifica se o aeroporto existe
        $stmt = $pdo->prepare("SELECT ident FROM airports_2 WHERE ident = ?");
        $stmt->execute([$apt['icao']]);
        
        if ($stmt->fetch()) {
            // EXISTE: Atualiza coordenadas e elevação para garantir precisão
            $sql = "UPDATE airports_2 SET name = ?, latitude_deg = ?, longitude_deg = ?, elevation_ft = ? WHERE ident = ?";
            $pdo->prepare($sql)->execute([$apt['name'], $apt['lat'], $apt['lon'], $apt['elev'], $apt['icao']]);
        } else {
            // NÃO EXISTE: Insere novo
            // Preenchemos colunas obrigatórias com valores padrão se necessário
            $sql = "INSERT INTO airports_2 (ident, type, name, latitude_deg, longitude_deg, elevation_ft, iso_country, municipality) 
                    VALUES (?, 'large_airport', ?, ?, ?, ?, 'XX', 'SimBrief Import')";
            $pdo->prepare($sql)->execute([$apt['icao'], $apt['name'], $apt['lat'], $apt['lon'], $apt['elev']]);
        }
    }
}

try {
    
    // --- PASSO 0: AUTO-ENRIQUECIMENTO ---
    // Se o formulário enviou dados de aeroporto (JSON), atualiza a tabela airports_2 antes de salvar a perna
    if (isset($_POST['sb_data_json']) && !empty($_POST['sb_data_json'])) {
        $sb_data = json_decode($_POST['sb_data_json'], true);
        updateAirportDB($pdo, $sb_data);
    }

    // --- AÇÃO: ADICIONAR ---
    if ($action == 'add') {
        $dep = strtoupper(trim($_POST['dep_icao']));
        $arr = strtoupper(trim($_POST['arr_icao']));
        $route = strtoupper(trim($_POST['route_string']));
        $leg_order = $_POST['leg_order'];

        // Fallback de ordem
        if(empty($leg_order)) {
            $stmt = $pdo->prepare("SELECT MAX(leg_order) FROM tour_legs WHERE tour_id = ?");
            $stmt->execute([$tour_id]);
            $maxOrder = $stmt->fetchColumn();
            $leg_order = $maxOrder ? $maxOrder + 1 : 1;
        }

        $sql = "INSERT INTO tour_legs (tour_id, leg_order, dep_icao, arr_icao, route_string) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tour_id, $leg_order, $dep, $arr, $route]);
        
        header("Location: manage_legs.php?tour_id=$tour_id&msg=added");
        exit;
    }

    // --- AÇÃO: EDITAR (UPDATE) ---
    elseif ($action == 'edit') {
        $leg_id = $_POST['leg_id'];
        $dep = strtoupper(trim($_POST['dep_icao']));
        $arr = strtoupper(trim($_POST['arr_icao']));
        $route = strtoupper(trim($_POST['route_string']));
        $leg_order = $_POST['leg_order'];

        $sql = "UPDATE tour_legs SET leg_order = ?, dep_icao = ?, arr_icao = ?, route_string = ? WHERE id = ? AND tour_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$leg_order, $dep, $arr, $route, $leg_id, $tour_id]);

        // Retorna limpo (sem parâmetros de edição na URL)
        header("Location: manage_legs.php?tour_id=$tour_id&msg=updated");
        exit;
    }

    // --- AÇÃO: DELETAR ---
    elseif ($action == 'delete') {
        $leg_id = $_POST['leg_id'];

        // 1. Apaga
        $stmt = $pdo->prepare("DELETE FROM tour_legs WHERE id = ? AND tour_id = ?");
        $stmt->execute([$leg_id, $tour_id]);

        // 2. Reorganiza a sequência numérica
        $stmtAll = $pdo->prepare("SELECT id FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC");
        $stmtAll->execute([$tour_id]);
        $legs = $stmtAll->fetchAll();
        
        $order = 1;
        foreach ($legs as $leg) {
            $pdo->prepare("UPDATE tour_legs SET leg_order = ? WHERE id = ?")->execute([$order, $leg['id']]);
            $order++;
        }

        header("Location: manage_legs.php?tour_id=$tour_id&msg=deleted");
        exit;
    }

} catch (PDOException $e) {
    die("Erro Crítico no Banco de Dados: " . $e->getMessage());
}

// Fallback
header("Location: manage_legs.php?tour_id=$tour_id");
exit;
?>