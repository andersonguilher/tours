<?php
// admin/process_leg.php
// Gerencia Adição, Edição e Exclusão de Pernas (Rotas)
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

$action = $_POST['action'] ?? '';
$tour_id = $_POST['tour_id'] ?? 0;

if ($tour_id == 0) {
    die("Erro: Tour ID não informado.");
}

try {
    // --- ADICIONAR NOVA PERNA ---
    if ($action == 'add') {
        $dep = strtoupper(trim($_POST['dep_icao']));
        $arr = strtoupper(trim($_POST['arr_icao']));
        $route = strtoupper(trim($_POST['route_string']));
        $leg_order = $_POST['leg_order'];

        // Se a ordem não for enviada (fallback), pega a última + 1
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

    // --- EDITAR PERNA (NOVO) ---
    elseif ($action == 'edit') {
        $leg_id = $_POST['leg_id'];
        $dep = strtoupper(trim($_POST['dep_icao']));
        $arr = strtoupper(trim($_POST['arr_icao']));
        $route = strtoupper(trim($_POST['route_string']));
        $leg_order = $_POST['leg_order'];

        $sql = "UPDATE tour_legs SET leg_order = ?, dep_icao = ?, arr_icao = ?, route_string = ? WHERE id = ? AND tour_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$leg_order, $dep, $arr, $route, $leg_id, $tour_id]);

        // Retorna para a página limpa (sem o modo de edição ativo na URL)
        header("Location: manage_legs.php?tour_id=$tour_id&msg=updated");
        exit;
    }

    // --- EXCLUIR PERNA ---
    elseif ($action == 'delete') {
        $leg_id = $_POST['leg_id'];

        // 1. Deleta a perna
        $stmt = $pdo->prepare("DELETE FROM tour_legs WHERE id = ? AND tour_id = ?");
        $stmt->execute([$leg_id, $tour_id]);

        // 2. Reorganiza a numeração (opcional, mas recomendado para manter 1, 2, 3...)
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
    die("Erro no banco de dados: " . $e->getMessage());
}

// Se nenhuma ação for encontrada
header("Location: manage_legs.php?tour_id=$tour_id");
exit;
?>