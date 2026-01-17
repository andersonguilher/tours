<?php
// admin/process_tour.php
// Lógica de Upload + Banco de Dados + Datas
require '../config/db.php';

$action = $_POST['action'] ?? '';

// Função auxiliar para Upload
function uploadBanner($fileInputName) {
    $targetDir = "../assets/banners/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName    = $_FILES[$fileInputName]['name'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $newFileName = 'tour_' . time() . '.' . $fileExtension;
        $dest_path = $targetDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            return "../assets/banners/" . $newFileName;
        }
    }
    return null;
}

// --- CRIAR ---
if ($action == 'create') {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $diff  = $_POST['difficulty'];
    
    // Novas Variáveis de Data
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   : null;
    
    $bannerPath = uploadBanner('banner_file');
    if (!$bannerPath) {
        $bannerPath = 'https://via.placeholder.com/1920x400?text=Sem+Banner';
    }

    $rules = json_encode($_POST['rules']);

    // Inserção com datas
    $sql = "INSERT INTO tours (title, description, difficulty, start_date, end_date, banner_url, rules_json, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $diff, $start_date, $end_date, $bannerPath, $rules]);
    
    $newId = $pdo->lastInsertId();
    header("Location: manage_legs.php?tour_id=$newId");
    exit;
}

// --- ATUALIZAR ---
if ($action == 'update') {
    $id    = $_POST['id'];
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $diff  = $_POST['difficulty'];
    $status= $_POST['status'];

    // Novas Variáveis de Data
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   : null;
    
    $newBanner = uploadBanner('banner_file');
    $bannerPath = $newBanner ? $newBanner : $_POST['old_banner_url'];

    $rules = json_encode($_POST['rules']);

    // Update com datas
    $sql = "UPDATE tours SET title=?, description=?, difficulty=?, start_date=?, end_date=?, banner_url=?, rules_json=?, status=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $diff, $start_date, $end_date, $bannerPath, $rules, $status, $id]);
    
    header("Location: index.php");
    exit;
}

// --- DELETAR ---
if ($action == 'delete') {
    $id = $_POST['id'];
    
    $pdo->prepare("DELETE FROM pilot_tour_progress WHERE tour_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tour_legs WHERE tour_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tours WHERE id = ?")->execute([$id]);
    
    header("Location: index.php");
    exit;
}
?>