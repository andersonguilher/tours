<?php
// admin/process_badge.php
// Lógica de Gestão de Medalhas (CRUD + Upload)
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

$action = $_POST['action'] ?? '';

// Função auxiliar para Upload de Badge
function uploadBadgeImage($fileInputName) {
    // Caminho relativo ao script: ../assets/badges/
    $targetDir = "../assets/badges/";
    
    // Cria a pasta se não existir
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName    = $_FILES[$fileInputName]['name'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Nome único para evitar conflitos
        $newFileName = 'badge_' . time() . '.' . $fileExtension;
        $dest_path = $targetDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            // Retorna o caminho absoluto para o banco de dados
            // Ajuste conforme a sua estrutura: /dash/tours/assets/badges/
            return "/dash/tours/assets/badges/" . $newFileName;
        }
    }
    return null;
}

// --- CRIAR ---
if ($action == 'create') {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $cond  = $_POST['condition_type'];
    
    $imagePath = uploadBadgeImage('image_file');
    if (!$imagePath) {
        $imagePath = 'https://cdn-icons-png.flaticon.com/512/3176/3176294.png'; // Fallback
    }

    // ATUALIZADO: tabela tour_badges
    $sql = "INSERT INTO tour_badges (title, description, image_url, condition_type) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $imagePath, $cond]);
    
    header("Location: manage_badges.php?msg=created");
    exit;
}

// --- ATUALIZAR ---
if ($action == 'update') {
    $id    = $_POST['id'];
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $cond  = $_POST['condition_type'];
    
    $newImage = uploadBadgeImage('image_file');
    $imagePath = $newImage ? $newImage : $_POST['old_image_url'];

    // ATUALIZADO: tabela tour_badges
    $sql = "UPDATE tour_badges SET title=?, description=?, image_url=?, condition_type=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $imagePath, $cond, $id]);
    
    header("Location: manage_badges.php?msg=updated");
    exit;
}

// --- DELETAR ---
if ($action == 'delete') {
    $id = $_POST['id'];
    
    // Primeiro remove as atribuições aos pilotos
    // ATUALIZADO: tabela tour_pilot_badges
    $pdo->prepare("DELETE FROM tour_pilot_badges WHERE badge_id = ?")->execute([$id]);
    // Depois remove a medalha
    // ATUALIZADO: tabela tour_badges
    $pdo->prepare("DELETE FROM tour_badges WHERE id = ?")->execute([$id]);
    
    header("Location: manage_badges.php?msg=deleted");
    exit;
}
?>