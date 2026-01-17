<?php
// admin/process_tour.php
// VERSÃO FINAL: Suporte a Auto-Badge + Segurança + Discord Alert
require '../config/db.php';

// --- SEGURANÇA WORDPRESS ---
// Busca dinâmica pelo wp-load.php para funcionar em qualquer estrutura de pastas
$possiblePaths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
];

$wpLoaded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wpLoaded = true;
        break;
    }
}

if (!$wpLoaded) {
    die('Erro Crítico: WordPress não detectado. Acesso negado.');
}

if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_die('Acesso Negado: Você não tem permissão para gerenciar tours.');
}
// --- FIM SEGURANÇA ---

$action = $_POST['action'] ?? '';

// Função para Upload Seguro de Imagens
function uploadBanner($fileInputName) {
    $targetDir = __DIR__ . "/../assets/banners/";
    
    // Cria a pasta se não existir (permissão segura 0755)
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileSize    = $_FILES[$fileInputName]['size'];
        
        // 1. Validar Tamanho (Limite de 2MB)
        if ($fileSize > 2 * 1024 * 1024) {
            wp_die('Erro: O banner é muito grande. Máximo permitido: 2MB.');
        }

        // 2. Validar Tipo MIME Real (Evita arquivos maliciosos renomeados)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmpPath);

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            wp_die('Erro: Formato de imagem inválido. Use apenas JPG, PNG ou WEBP.');
        }

        // 3. Gerar Nome Único e Seguro
        $extension = $allowedMimeTypes[$mimeType];
        $newFileName = 'tour_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $dest_path = $targetDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            // Retorna caminho relativo para salvar no banco
            return "../assets/banners/" . $newFileName;
        } else {
            wp_die('Erro ao salvar o arquivo. Verifique as permissões da pasta assets/banners.');
        }
    }
    return null;
}

// --- AÇÃO: CRIAR NOVO TOUR ---
if ($action == 'create') {
    // Sanitização de entradas
    $title = sanitize_text_field($_POST['title']);
    $desc  = wp_kses_post($_POST['description']); // Permite HTML básico
    $diff  = sanitize_text_field($_POST['difficulty']);
    $scenery = esc_url_raw($_POST['scenery_link'] ?? '');
    
    // Tratamento de Datas
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   : null;
    
    // Tratamento do Badge ID (Novo!)
    // Se o valor for vazio ou 0, salva como NULL
    $badge_id = !empty($_POST['badge_id']) ? filter_var($_POST['badge_id'], FILTER_VALIDATE_INT) : null;
    if ($badge_id === 0 || $badge_id === false) $badge_id = null;

    // Upload do Banner
    $bannerPath = uploadBanner('banner_file');
    if (!$bannerPath) {
        $bannerPath = 'https://via.placeholder.com/1920x400?text=Kafly+Tours'; // Fallback
    }

    // Regras em JSON
    $rules = json_encode($_POST['rules'] ?? []);

    try {
        // Inserção no Banco (Incluindo badge_id)
        $sql = "INSERT INTO tours (title, description, difficulty, start_date, end_date, banner_url, rules_json, scenery_link, badge_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $desc, $diff, $start_date, $end_date, $bannerPath, $rules, $scenery, $badge_id]);
        
        $newId = $pdo->lastInsertId();

        // --- INÍCIO ALERTA DISCORD ---
        // Configure sua URL abaixo ou no settings.json
        $webhookurl = "SUA_WEBHOOK_URL_AQUI"; 
        
        if (strpos($webhookurl, 'http') !== false) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            // Gera URL absoluta para a imagem funcionar no Discord
            $bannerFullUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . str_replace('..', '', $bannerPath);
            $tourUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/kafly/tours/pilots/view_tour.php?id=" . $newId;

            $json_data = json_encode([
                "username" => "Kafly Operations",
                "embeds" => [
                    [
                        "title" => "🚀 Novo Tour Lançado: " . $title,
                        "description" => substr(strip_tags($desc), 0, 250) . "...",
                        "url" => $tourUrl,
                        "color" => hexdec("3b82f6"), // Azul
                        "image" => [ "url" => $bannerFullUrl ],
                        "fields" => [
                            ["name" => "Dificuldade", "value" => $diff, "inline" => true],
                            ["name" => "Início", "value" => date("d/m/Y", strtotime($start_date ?? 'now')), "inline" => true],
                            ["name" => "Recompensa", "value" => ($badge_id ? "🏅 Medalha Especial" : "Sem Medalha"), "inline" => true]
                        ],
                        "footer" => ["text" => "Preparem seus planos de voo!"]
                    ]
                ]
            ]);

            $ch = curl_init($webhookurl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // Timeout curto para não travar o carregamento da página se o Discord demorar
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
            curl_exec($ch);
            curl_close($ch);
        }
        // --- FIM ALERTA DISCORD ---
        
        // Redireciona para gerenciar as pernas do novo tour
        header("Location: manage_legs.php?tour_id=$newId");
        exit;

    } catch (PDOException $e) {
        wp_die("Erro ao criar tour no banco de dados: " . $e->getMessage());
    }
}

// --- AÇÃO: ATUALIZAR TOUR EXISTENTE ---
if ($action == 'update') {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) wp_die('ID de Tour Inválido');

    $title = sanitize_text_field($_POST['title']);
    $desc  = wp_kses_post($_POST['description']);
    $diff  = sanitize_text_field($_POST['difficulty']);
    $status= filter_var($_POST['status'], FILTER_VALIDATE_INT);
    $scenery = esc_url_raw($_POST['scenery_link'] ?? '');

    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   : null;
    
    // Tratamento do Badge ID (Novo!)
    $badge_id = !empty($_POST['badge_id']) ? filter_var($_POST['badge_id'], FILTER_VALIDATE_INT) : null;
    if ($badge_id === 0 || $badge_id === false) $badge_id = null;
    
    // Verifica se houve novo upload, senão mantém o antigo
    $newBanner = uploadBanner('banner_file');
    $bannerPath = $newBanner ? $newBanner : sanitize_text_field($_POST['old_banner_url']);

    $rules = json_encode($_POST['rules'] ?? []);

    try {
        // Atualização no Banco (Incluindo badge_id)
        $sql = "UPDATE tours SET title=?, description=?, difficulty=?, start_date=?, end_date=?, banner_url=?, rules_json=?, scenery_link=?, badge_id=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $desc, $diff, $start_date, $end_date, $bannerPath, $rules, $scenery, $badge_id, $status, $id]);
        
        header("Location: index.php");
        exit;

    } catch (PDOException $e) {
        wp_die("Erro ao atualizar tour: " . $e->getMessage());
    }
}

// --- AÇÃO: DELETAR TOUR ---
if ($action == 'delete') {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) wp_die('ID de Tour Inválido');
    
    try {
        // Transação para garantir limpeza completa
        $pdo->beginTransaction();
        
        // 1. Remove histórico e progresso dos pilotos neste tour
        $pdo->prepare("DELETE FROM pilot_leg_history WHERE tour_id = ?")->execute([$id]); // Opcional: manter histórico? Geralmente deleta-se tudo.
        $pdo->prepare("DELETE FROM pilot_tour_progress WHERE tour_id = ?")->execute([$id]);
        
        // 2. Remove as pernas do tour
        $pdo->prepare("DELETE FROM tour_legs WHERE tour_id = ?")->execute([$id]);
        
        // 3. Remove o tour em si
        $pdo->prepare("DELETE FROM tours WHERE id = ?")->execute([$id]);
        
        $pdo->commit();
        
        header("Location: index.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        wp_die("Erro crítico ao deletar tour: " . $e->getMessage());
    }
}
?>