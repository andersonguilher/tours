<?php
// admin/process_leg.php
// Gerencia Adição e Exclusão de Pernas (Rotas)
require '../config/db.php';

$action = $_POST['action'] ?? '';
$tour_id = $_POST['tour_id'] ?? 0;

if ($tour_id == 0) {
    die("Erro: Tour ID não informado.");
}

// --- ADICIONAR NOVA PERNA ---
if ($action == 'add') {
    $dep = strtoupper(trim($_POST['dep_icao']));
    $arr = strtoupper(trim($_POST['arr_icao']));
    $route = trim($_POST['route']);
    
    // Descobre qual é o próximo número de ordem (leg_order)
    $stmt = $pdo->prepare("SELECT MAX(leg_order) FROM tour_legs WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $maxOrder = $stmt->fetchColumn();
    $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

    $sql = "INSERT INTO tour_legs (tour_id, leg_order, dep_icao, arr_icao, route_string) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([$tour_id, $nextOrder, $dep, $arr, $route]);
        header("Location: manage_legs.php?tour_id=$tour_id&msg=added");
    } catch (PDOException $e) {
        die("Erro ao adicionar perna: " . $e->getMessage());
    }
}

// --- EXCLUIR PERNA (A CORREÇÃO QUE VOCÊ PRECISA) ---
if ($action == 'delete') {
    $leg_id = $_POST['leg_id'];

    try {
        // 1. Deleta a perna
        $stmt = $pdo->prepare("DELETE FROM tour_legs WHERE id = ? AND tour_id = ?");
        $stmt->execute([$leg_id, $tour_id]);

        // 2. Reorganiza a numeração (Opcional, mas recomendado)
        // Isso evita buracos na numeração (Ex: 1, 2, 4 -> vira 1, 2, 3)
        $stmtAll = $pdo->prepare("SELECT id FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC");
        $stmtAll->execute([$tour_id]);
        $legs = $stmtAll->fetchAll();
        
        $order = 1;
        foreach ($legs as $leg) {
            $pdo->prepare("UPDATE tour_legs SET leg_order = ? WHERE id = ?")->execute([$order, $leg['id']]);
            $order++;
        }

        header("Location: manage_legs.php?tour_id=$tour_id&msg=deleted");
    } catch (PDOException $e) {
        die("Erro ao excluir: " . $e->getMessage());
    }
}
?>