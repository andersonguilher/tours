<?php
// admin/ajax_airport.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['icao'])) {
    echo json_encode(['found' => false]);
    exit;
}

$icao = strtoupper(trim($_GET['icao']));

try {
    // Busca na tabela airports_2 (que estamos alimentando via SimBrief)
    $stmt = $pdo->prepare("SELECT name, municipality, elevation_ft, latitude_deg, longitude_deg FROM airports_2 WHERE ident = ? LIMIT 1");
    $stmt->execute([$icao]);
    $airport = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($airport) {
        echo json_encode([
            'found' => true,
            'data' => $airport
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['found' => false, 'error' => $e->getMessage()]);
}
?>