<?php
// admin/ajax_simbrief_aircraft.php
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }

// Verifica se é administrador
if (!is_user_logged_in() || !current_user_can('administrator')) { 
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Acesso Negado']);
    exit;
}
// --- FIM SEGURANÇA ---

header('Content-Type: application/json');

// Configuração do Cache (Armazena localmente por 24 horas para evitar lentidão)
$cacheFile = __DIR__ . '/../cache/simbrief_aircraft.json';
$cacheTime = 86400; // 24 horas

// Garante que a pasta cache existe
if (!is_dir(dirname($cacheFile))) {
    @mkdir(dirname($cacheFile), 0755, true);
}

// Verifica se o cache é válido
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// Se não tiver cache, busca do SimBrief
$simbriefUrl = 'https://www.simbrief.com/api/inputs.list.json';

// Tenta usar CURL se disponível (mais robusto), senão file_get_contents
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $simbriefUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Opcional, dependendo da config do servidor
    $response = curl_exec($ch);
    curl_close($ch);
} else {
    $response = @file_get_contents($simbriefUrl);
}

if ($response) {
    $data = json_decode($response, true);
    
    // Extrai apenas os códigos ICAO para uma lista simples
    $aircraftList = [];
    
    // A estrutura do SimBrief geralmente é ['aircraft'] => ['ICAO' => 'Nome', ...]
    if (isset($data['aircraft']) && is_array($data['aircraft'])) {
        foreach ($data['aircraft'] as $icao => $name) {
            $aircraftList[] = [
                'icao' => (string)$icao,
                'name' => (string)$name
            ];
        }
    }
    
    // Salva o resultado limpo no cache
    $jsonOutput = json_encode($aircraftList);
    file_put_contents($cacheFile, $jsonOutput);
    echo $jsonOutput;
} else {
    // Fallback se falhar a conexão (retorna vazio ou cache antigo se existir)
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        echo json_encode([]);
    }
}
?>