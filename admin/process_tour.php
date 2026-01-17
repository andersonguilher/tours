<?php
// admin/process_tour.php
// Lógica de Upload + Banco de Dados
require '../config/db.php';

$action = $_POST['action'] ?? '';

// Função auxiliar para Upload
function uploadBanner($fileInputName) {
    // Define a pasta de destino (cria se não existir)
    // Caminho relativo ao script process_tour.php
    $targetDir = "../assets/banners/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName    = $_FILES[$fileInputName]['name'];
        $fileSize    = $_FILES[$fileInputName]['size'];
        $fileType    = $_FILES[$fileInputName]['type'];
        
        // Pega a extensão
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Sanitiza nome (tour_timestamp.ext)
        $newFileName = 'tour_' . time() . '.' . $fileExtension;
        $dest_path = $targetDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            // Retorna o caminho para salvar no banco
            // Como o view_tour.php está em /pilots/ e a imagem em /assets/,
            // o caminho deve ser "../assets/banners/..."
            return "../assets/banners/" . $newFileName;
        }
    }
    return null; // Falha ou nenhum arquivo enviado
}

// --- CRIAR ---
if ($action == 'create') {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $diff  = $_POST['difficulty'];
    
    // Processa Upload (Obrigatório na criação?)
    // Se falhar, você pode colocar uma imagem padrão placeholder
    $bannerPath = uploadBanner('banner_file');
    if (!$bannerPath) {
        $bannerPath = 'https://via.placeholder.com/1920x400?text=Sem+Banner';
    }

    $rules = json_encode($_POST['rules']);

    $sql = "INSERT INTO tours (title, description, difficulty, banner_url, rules_json, status) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $diff, $bannerPath, $rules]);
    
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
    
    // Verifica se enviou nova imagem
    $newBanner = uploadBanner('banner_file');
    
    // Se enviou, usa a nova. Se não, mantém a antiga (hidden field)
    $bannerPath = $newBanner ? $newBanner : $_POST['old_banner_url'];

    $rules = json_encode($_POST['rules']);

    $sql = "UPDATE tours SET title=?, description=?, difficulty=?, banner_url=?, rules_json=?, status=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $desc, $diff, $bannerPath, $rules, $status, $id]);
    
    header("Location: index.php");
    exit;
}

// --- DELETAR ---
if ($action == 'delete') {
    $id = $_POST['id'];
    
    // Opcional: Deletar a imagem do servidor para não acumular lixo
    /*
    $stmt = $pdo->prepare("SELECT banner_url FROM tours WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && file_exists(__DIR__ . '/' . $row['banner_url'])) {
        unlink(__DIR__ . '/' . $row['banner_url']);
    }
    */

    $pdo->prepare("DELETE FROM pilot_tour_progress WHERE tour_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tour_legs WHERE tour_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tours WHERE id = ?")->execute([$id]);
    
    header("Location: index.php");
    exit;
}
?>