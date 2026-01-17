<?php
// pilots/view_tour.php
// CENTRAL DE OPERAÇÕES DE TOUR: Mapa + Despacho + SimBrief API v1 Full Features

// --- 1. CARREGAMENTO ROBUSTO DO WORDPRESS ---
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
    die("Erro Crítico: Não foi possível carregar o WordPress. Verifique o caminho em view_tour.php");
}

if (!is_user_logged_in()) { die('Acesso restrito.'); }

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

// --- 2. INTEGRAÇÃO SIMBRIEF API & PROCESSAMENTO AVANÇADO ---
require_once '../includes/simbrief.apiv1.php';

$ofpData = null;
$realRoutePoints = []; // Array para o mapa

if (isset($_GET['ofp_id'])) {
    $sb = new SimBrief($_GET['ofp_id']);
    if ($sb->ofp_avail) {
        $ofpData = $sb->ofp_array;
        
        // EXTRAÇÃO DA ROTA REAL (LAT/LON)
        if (isset($ofpData['navlog']['fix'])) {
            foreach ($ofpData['navlog']['fix'] as $fix) {
                $realRoutePoints[] = [
                    'lat' => floatval($fix['pos_lat']),
                    'lng' => floatval($fix['pos_long']),
                    'name' => $fix['ident']
                ];
            }
        }
    }
}

// --- 3. CONFIGURAÇÕES E BANCO ---
$settingsFile = __DIR__ . '/../../settings.json'; 
$tb_pilotos = 'Dados_dos_Pilotos'; 
$col_matricula = 'matricula';
$col_id_piloto = 'id_piloto';

if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['database_mappings']['pilots_table'])) $tb_pilotos = $settings['database_mappings']['pilots_table'];
    $cols = $settings['database_mappings']['columns'] ?? [];
    if (isset($cols['matricula'])) $col_matricula = $cols['matricula'];
    if (isset($cols['id_piloto'])) $col_id_piloto = $cols['id_piloto'];
}

$dbPath = __DIR__ . '/../config/db.php';
if (file_exists($dbPath)) {
    require $dbPath; 
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} else {
    die("Erro: Arquivo de configuração de banco de dados não encontrado em $dbPath");
}

// --- 4. IDENTIFICAÇÃO DO PILOTO ---
$raw_callsign = "";
try {
    $host_p = defined('DB_SERVERNAME') ? DB_SERVERNAME : 'localhost';
    $user_p = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
    $pass_p = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
    $name_p = defined('DB_PILOTOS_NAME') ? DB_PILOTOS_NAME : 'u378005298_hEatD';

    $pdoPilots = new PDO("mysql:host=$host_p;dbname=$name_p;charset=utf8mb4", $user_p, $pass_p);
    $pdoPilots->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdoPilots->prepare("SELECT $col_matricula FROM $tb_pilotos WHERE $col_id_piloto = ? LIMIT 1");
    $stmt->execute([$wp_user_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $raw_callsign = ($res) ? strtoupper($res[$col_matricula]) : strtoupper($current_user->user_login);
} catch (Exception $e) {
    $raw_callsign = strtoupper($current_user->user_login);
}

$simbrief_airline = substr($raw_callsign, 0, 3);
$simbrief_number = preg_replace('/[^0-9]/', '', substr($raw_callsign, 3));
if (strlen($simbrief_airline) < 3) $simbrief_airline = 'KFY'; 
if (empty($simbrief_number)) $simbrief_number = '0001';
$display_callsign = $simbrief_airline . $simbrief_number;

// --- 5. DADOS DO TOUR ---
$tour_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tour_id) die("ID Inválido");

$stmt = $pdo->prepare("SELECT * FROM tour_tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado.");

// Helpers
function getMetar($icao) {
    // Fallback se não tiver SimBrief data
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://metar.vatsim.net/metar.php?id=" . $icao);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $metar = curl_exec($ch);
        curl_close($ch);
    } else {
        $metar = @file_get_contents("https://metar.vatsim.net/metar.php?id=" . $icao);
    }
    return $metar ? trim($metar) : "N/A";
}

// --- 6. AÇÃO: INICIAR TOUR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_tour'])) {
    $check = $pdo->prepare("SELECT id FROM tour_progress WHERE pilot_id = ? AND tour_id = ?");
    $check->execute([$wp_user_id, $tour_id]);
    
    if (!$check->fetch()) {
        $stmtLeg = $pdo->prepare("SELECT id FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC LIMIT 1");
        $stmtLeg->execute([$tour_id]);
        $first = $stmtLeg->fetch();
        
        if ($first) {
            $pdo->prepare("INSERT INTO tour_progress (pilot_id, tour_id, current_leg_id, status) VALUES (?, ?, ?, 'In Progress')")
                ->execute([$wp_user_id, $tour_id, $first['id']]);
            echo "<script>window.location.href='view_tour.php?id=$tour_id';</script>";
            exit;
        }
    }
}

// --- 7. STATUS E PERNAS ---
$stmtProg = $pdo->prepare("SELECT * FROM tour_progress WHERE pilot_id = ? AND tour_id = ?");
$stmtProg->execute([$wp_user_id, $tour_id]);
$progress = $stmtProg->fetch();

$currentLegId = $progress ? $progress['current_leg_id'] : 0;
$tourStatus = $progress ? $progress['status'] : 'Not Started';

$allLegs = [];
try {
    $sqlLegs = "SELECT l.*, 
                dep.name as dep_name, dep.latitude_deg as dep_lat, dep.longitude_deg as dep_lon, dep.municipality as dep_city, dep.flag_url as dep_flag, 
                arr.name as arr_name, arr.latitude_deg as arr_lat, arr.longitude_deg as arr_lon, arr.municipality as arr_city, arr.flag_url as arr_flag 
                FROM tour_legs l 
                LEFT JOIN airports_2 dep ON l.dep_icao = dep.ident 
                LEFT JOIN airports_2 arr ON l.arr_icao = arr.ident 
                WHERE l.tour_id = ? ORDER BY l.leg_order ASC";
    
    $stmtLegs = $pdo->prepare($sqlLegs);
    if (!$stmtLegs) throw new PDOException("Falha ao preparar query");
    $stmtLegs->execute([$tour_id]);
    $allLegs = $stmtLegs->fetchAll();
} catch (PDOException $e) {
    $sqlLegsFallback = "SELECT l.*, 
                        l.dep_icao as dep_name, '' as dep_lat, '' as dep_lon, '' as dep_city, '' as dep_flag,
                        l.arr_icao as arr_name, '' as arr_lat, '' as arr_lon, '' as arr_city, '' as arr_flag
                        FROM tour_legs l
                        WHERE l.tour_id = ? ORDER BY l.leg_order ASC";
    $stmtLegs = $pdo->prepare($sqlLegsFallback);
    $stmtLegs->execute([$tour_id]);
    $allLegs = $stmtLegs->fetchAll();
}

$currentLegOrder = 0;
if ($progress) {
    if ($tourStatus == 'Completed') {
        $currentLegOrder = 9999;
    } else {
        foreach ($allLegs as $l) {
            if ($l['id'] == $currentLegId) {
                $currentLegOrder = $l['leg_order'];
                break;
            }
        }
    }
}

// GERA JSON PARA O MAPA
$mapSegments = [];
foreach($allLegs as $leg) {
    if (!empty($leg['dep_lat']) && !empty($leg['dep_lon']) && !empty($leg['arr_lat']) && !empty($leg['arr_lon'])) {
        $status = 'locked'; 
        if ($progress) {
            if ($leg['id'] == $currentLegId && $tourStatus != 'Completed') {
                $status = 'active'; 
            } elseif ($leg['leg_order'] < $currentLegOrder || $tourStatus == 'Completed') {
                $status = 'completed'; 
            }
        }
        $mapSegments[] = [
            'start' => ['lat' => $leg['dep_lat'], 'lon' => $leg['dep_lon'], 'code' => $leg['dep_icao'], 'city' => $leg['dep_city'], 'name' => $leg['dep_name'], 'flag' => $leg['dep_flag']],
            'end'   => ['lat' => $leg['arr_lat'], 'lon' => $leg['arr_lon'], 'code' => $leg['arr_icao'], 'city' => $leg['arr_city'], 'name' => $leg['arr_name'], 'flag' => $leg['arr_flag']],
            'status' => $status
        ];
    }
}
$jsMapSegments = json_encode($mapSegments);
$jsRealRoute = json_encode($realRoutePoints); // Passa a rota real para o JS
$rules = json_decode($tour['rules_json'], true);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($tour['title']); ?> - Despacho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; } 
        ::-webkit-scrollbar-track { background: #0f172a; } 
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .flag-icon { width: 20px; height: 14px; object-fit: cover; border-radius: 2px; display: inline-block; }
        .btn-glow { animation: glow 2s infinite; }
        @keyframes glow { 0% { box-shadow: 0 0 5px #22c55e; } 50% { box-shadow: 0 0 20px #22c55e; } 100% { box-shadow: 0 0 5px #22c55e; } }
        
        .custom-tooltip {
            background-color: rgba(15, 23, 42, 0.95) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important; border-radius: 8px !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
        }
        .custom-tooltip::before { border-top-color: rgba(15, 23, 42, 0.95) !important; }
        .tooltip-content { padding: 8px 12px; text-align: center; }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }

        /* Remove o estilo padrão branco quadrado do Leaflet */
        .leaflet-tooltip {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            color: white !important;
        }
        /* Seta do tooltip */
        .leaflet-tooltip-top:before {
            border-top-color: rgba(15, 23, 42, 0.9) !important; /* Cor do slate-900 */
        }        
    </style>
</head>
<body class="bg-slate-950 text-white h-screen flex flex-col font-sans overflow-hidden">

<?php if ($ofpData): ?>
    <div id="sb-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
        <div class="bg-slate-900 border border-blue-500/50 rounded-xl shadow-2xl max-w-5xl w-full overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="bg-gradient-to-r from-blue-900 to-slate-900 p-4 flex justify-between items-center border-b border-blue-500/30 shrink-0">
                <div class="flex items-center gap-4">
                    <div class="bg-blue-600 px-3 py-1 rounded text-sm font-bold shadow"><?php echo $ofpData['general']['icao_airline'] . $ofpData['general']['flight_number']; ?></div>
                    <div class="text-white font-bold text-lg flex items-center gap-2">
                        <span><?php echo $ofpData['origin']['icao_code']; ?></span>
                        <i class="fa-solid fa-plane text-sm text-slate-400"></i>
                        <span><?php echo $ofpData['destination']['icao_code']; ?></span>
                    </div>
                </div>
                
                <button onclick="document.getElementById('sb-modal').remove(); window.history.replaceState({}, document.title, window.location.pathname + '?id=<?php echo $tour_id; ?>');" 
                        class="text-slate-400 hover:text-white transition bg-slate-800 hover:bg-red-600 rounded-full w-8 h-8 flex items-center justify-center cursor-pointer shadow-lg border border-slate-700">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scrollbar">
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-gas-pump"></i> Block Fuel</div>
                        <div class="text-2xl font-mono font-bold text-green-400"><?php echo $ofpData['fuel']['plan_ramp']; ?> <span class="text-xs text-slate-500">KG</span></div>
                    </div>
                    <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-stopwatch"></i> Trip Time</div>
                        <div class="text-2xl font-mono font-bold text-blue-400"><?php echo gmdate("H:i", $ofpData['times']['est_time_enroute']); ?></div>
                    </div>
                    <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-weight-hanging"></i> Est. ZFW</div>
                        <div class="text-2xl font-mono font-bold text-white"><?php echo $ofpData['weights']['est_zfw']; ?> <span class="text-xs text-slate-500">KG</span></div>
                    </div>
                    <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-plane-departure"></i> Est. TOW</div>
                        <div class="text-2xl font-mono font-bold text-yellow-400"><?php echo $ofpData['weights']['est_tow']; ?> <span class="text-xs text-slate-500">KG</span></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-slate-900 border border-slate-700 rounded p-3">
                        <div class="text-[10px] text-blue-400 font-bold uppercase mb-2">Pistas Previstas</div>
                        <div class="flex justify-between items-center text-sm font-mono">
                            <div><span class="text-slate-500">DEP:</span> <span class="text-white font-bold"><?php echo $ofpData['origin']['plan_rwy']; ?></span></div>
                            <i class="fa-solid fa-arrow-right text-slate-600 text-xs"></i>
                            <div><span class="text-slate-500">ARR:</span> <span class="text-white font-bold"><?php echo $ofpData['destination']['plan_rwy']; ?></span></div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-900 border border-slate-700 rounded p-3">
                        <div class="text-[10px] text-blue-400 font-bold uppercase mb-2">Carregamento</div>
                        <div class="flex justify-around items-center text-sm">
                            <div class="text-center">
                                <i class="fa-solid fa-users text-slate-500 mb-1 block"></i>
                                <span class="font-bold"><?php echo $ofpData['general']['passengers']; ?></span> <span class="text-[10px] text-slate-500">PAX</span>
                            </div>
                            <div class="text-center">
                                <i class="fa-solid fa-box text-slate-500 mb-1 block"></i>
                                <span class="font-bold"><?php echo $ofpData['weights']['cargo']; ?></span> <span class="text-[10px] text-slate-500">KG</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-700 rounded p-3">
                         <div class="text-[10px] text-blue-400 font-bold uppercase mb-2">Alternativo</div>
                         <div class="flex items-center gap-3">
                             <div class="text-xl font-bold text-red-400"><?php echo $ofpData['alternate']['icao_code']; ?></div>
                             <div class="text-xs text-slate-400">
                                 <div>Fuel: <?php echo $ofpData['alternate']['burn']; ?> KG</div>
                                 <div>FL<?php echo $ofpData['alternate']['plan_alt']; ?></div>
                             </div>
                         </div>
                    </div>
                </div>

                <div class="bg-slate-950 p-4 rounded border border-slate-800 font-mono text-xs text-slate-300 break-all mb-6 relative">
                    <div class="absolute top-0 left-0 bg-blue-600 text-[9px] font-bold px-2 py-0.5 rounded-br text-white">ROTA</div>
                    <div class="mt-3 leading-relaxed"><?php echo $ofpData['general']['route']; ?></div>
                </div>

                <div class="space-y-2 mb-6">
                    <div class="text-[10px] text-slate-500 uppercase font-bold">Meteorologia Atual</div>
                    <div class="bg-black/30 p-2 rounded border-l-2 border-green-500 text-[10px] font-mono text-slate-300">
                        <strong class="text-green-500"><?php echo $ofpData['origin']['icao_code']; ?>:</strong> <?php echo $ofpData['weather']['orig_metar']; ?>
                    </div>
                    <div class="bg-black/30 p-2 rounded border-l-2 border-blue-500 text-[10px] font-mono text-slate-300">
                        <strong class="text-blue-500"><?php echo $ofpData['destination']['icao_code']; ?>:</strong> <?php echo $ofpData['weather']['dest_metar']; ?>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-slate-800">
                    <button onclick="document.getElementById('sb-modal').remove(); window.history.replaceState({}, document.title, window.location.pathname + '?id=<?php echo $tour_id; ?>');" class="md:hidden bg-slate-700 text-white font-bold py-2 px-4 rounded text-sm">
                        Fechar Janela
                    </button>
                    
                    <a href="<?php echo $ofpData['files']['pdf']['link']; ?>" target="_blank" class="flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white font-bold py-2 px-6 rounded transition shadow-lg hover:shadow-red-900/20">
                        <i class="fa-solid fa-file-pdf"></i> PDF Oficial
                    </a>
                    
                    <div class="h-10 w-px bg-slate-700 mx-2 hidden md:block"></div>

                    <a href="<?php echo $ofpData['prefile']['vatsim']['link']; ?>" target="_blank" class="flex items-center gap-2 bg-slate-700 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition text-sm">
                        <i class="fa-solid fa-paper-plane"></i> VATSIM
                    </a>
                     <a href="<?php echo $ofpData['prefile']['ivao']['link']; ?>" target="_blank" class="flex items-center gap-2 bg-slate-700 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition text-sm">
                        <i class="fa-solid fa-paper-plane"></i> IVAO
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="h-16 bg-slate-900 border-b border-slate-800 flex justify-between items-center px-6 z-50 shrink-0 shadow-lg">
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-slate-400 hover:text-white transition text-sm flex items-center gap-2 group">
                <i class="fa-solid fa-arrow-left group-hover:-translate-x-1 transition"></i> <span class="hidden md:inline">Sair do Despacho</span>
            </a>
            <div class="h-4 w-px bg-slate-700"></div>
            <h1 class="font-bold text-lg text-white truncate max-w-[200px] md:max-w-md"><?php echo htmlspecialchars($tour['title']); ?></h1>
        </div>
        
        <div class="flex items-center gap-6">
            <a href="live_board.php" target="_blank" class="flex items-center gap-2 text-xs font-bold bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded border border-slate-700 transition text-green-400 animate-pulse">
                <i class="fa-solid fa-satellite-dish"></i> <span class="hidden sm:inline">LIVE TRAFFIC</span>
            </a>

            <div class="text-right hidden sm:block">
                <div class="text-[9px] text-slate-500 uppercase font-bold">Callsign Operacional</div>
                <div class="font-bold font-mono text-yellow-400 text-sm"><?php echo $display_callsign; ?></div>
            </div>
        </div>
    </div>

    <div class="flex-grow flex overflow-hidden">
        
        <div class="w-full md:w-[450px] bg-slate-900 border-r border-slate-800 flex flex-col h-full z-20 shrink-0 shadow-2xl relative">
            <div class="h-32 bg-cover bg-center relative shrink-0" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent"></div>
                <div class="absolute bottom-2 left-4">
                    <span class="bg-blue-600/90 text-white text-[9px] font-bold px-2 py-0.5 rounded uppercase shadow"><?php echo $tour['difficulty']; ?></span>
                </div>
            </div>

            <div class="flex-grow overflow-y-auto p-4 custom-scrollbar flex flex-col">
                
                <?php if ($progress): ?>
                <div class="mb-5 bg-slate-800/50 p-3 rounded-lg border border-slate-700">
                    <div class="flex justify-between text-[10px] text-slate-400 uppercase font-bold mb-1.5">
                        <span>Conclusão da Missão</span>
                        <?php 
                            $total = count($allLegs); $done = 0;
                            if($tourStatus=='Completed') $done=$total; else foreach($allLegs as $l){if($l['id'] < $currentLegId) $done++;}
                            $pct = $total > 0 ? round(($done/$total)*100) : 0;
                        ?>
                        <span class="text-blue-400"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-1.5">
                        <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-1000" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-slate-800 p-2 rounded border border-slate-700 text-center">
                        <span class="text-[9px] text-slate-500 uppercase block">Aeronave</span>
                        <span class="font-mono text-xs text-white font-bold truncate block"><?php echo $rules['allowed_aircraft'] ?? 'Livre'; ?></span>
                    </div>
                    <div class="bg-slate-800 p-2 rounded border border-slate-700 text-center">
                        <span class="text-[9px] text-slate-500 uppercase block">Max Speed</span>
                        <span class="font-mono text-xs text-red-400 font-bold block"><?php echo $rules['speed_fl100'] ?? '250'; ?> kts</span>
                    </div>
                </div>

                <div class="space-y-3 pb-20"> <?php foreach($allLegs as $leg): 
                        if (!$progress) {
                            $isCurrent = false; $isDone = false;
                        } else {
                            $isCurrent = ($leg['id'] == $currentLegId && $tourStatus != 'Completed');
                            $isDone = ($tourStatus == 'Completed') || ($leg['id'] < $currentLegId && !$isCurrent);
                        }
                        
                        $fD = $leg['dep_flag'] ? "<img src='{$leg['dep_flag']}' class='flag-icon mr-1 opacity-80'>" : "";
                        $fA = $leg['arr_flag'] ? "<img src='{$leg['arr_flag']}' class='flag-icon mr-1 opacity-80'>" : "";
                        
                        if ($isCurrent) {
                            $cardClass = "bg-slate-800 border border-blue-500 shadow-lg shadow-blue-900/20 relative overflow-hidden";
                            $iconStatus = "<span class='text-[9px] bg-blue-600 text-white px-2 py-0.5 rounded font-bold animate-pulse'>PRÓXIMA</span>";
                        } elseif ($isDone) {
                            $cardClass = "bg-slate-900/50 border border-green-900/30 opacity-60";
                            $iconStatus = "<i class='fa-solid fa-check text-green-500'></i>";
                        } else {
                            $cardClass = "bg-slate-900/30 border border-slate-800 opacity-40 grayscale";
                            $iconStatus = "<i class='fa-solid fa-lock text-slate-600'></i>";
                        }
                    ?>

                    <div class="<?php echo $cardClass; ?> p-3 rounded-lg transition-all duration-300">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Etapa <?php echo str_pad($leg['leg_order'], 2, '0', STR_PAD_LEFT); ?></span>
                            <?php echo $iconStatus; ?>
                        </div>

                        <div class="flex items-center justify-between font-mono mb-2">
                            <div class="text-center">
                                <div class="text-sm font-bold text-white"><?php echo $fD . $leg['dep_icao']; ?></div>
                            </div>
                            <div class="flex-1 px-2 flex flex-col items-center">
                                <i class="fa-solid fa-plane text-slate-600 text-[10px]"></i>
                                <div class="w-full h-px bg-slate-700 my-1"></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm font-bold text-white"><?php echo $fA . $leg['arr_icao']; ?></div>
                            </div>
                        </div>

                        <?php if($isCurrent): ?>
                            <div class="mt-3 pt-3 border-t border-slate-700/50 space-y-3">
                                
                                <div class="bg-black/20 rounded p-2 text-[10px] font-mono text-cyan-200 border border-white/5">
                                    <div class="mb-1"><strong class="text-white">DEP:</strong> <?php echo substr(getMetar($leg['dep_icao']), 0, 40); ?>...</div>
                                    <div><strong class="text-white">ARR:</strong> <?php echo substr(getMetar($leg['arr_icao']), 0, 40); ?>...</div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <button onclick="generateOFP('<?php echo $leg['dep_icao']; ?>', '<?php echo $leg['arr_icao']; ?>', '<?php echo $leg['route_string']; ?>', '<?php echo $rules['allowed_aircraft'] ?? ''; ?>')" 
                                            class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2 rounded text-center shadow transition flex items-center justify-center gap-1 w-full btn-glow">
                                        <i class="fa-solid fa-cloud-bolt"></i> Gerar OFP (API)
                                    </button>

                                    <?php if(!empty($tour['scenery_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($tour['scenery_link']); ?>" target="_blank" class="bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold py-2 rounded text-center shadow transition">
                                        <i class="fa-solid fa-map"></i> Cenário
                                    </a>
                                    <?php else: ?>
                                    <button disabled class="bg-slate-700 text-slate-500 text-xs font-bold py-2 rounded cursor-not-allowed">
                                        Sem Cenário
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center">
                                     <button onclick="requestManualValidation(<?php echo $leg['id']; ?>, '<?php echo $leg['dep_icao']; ?>', '<?php echo $leg['arr_icao']; ?>')" class="text-[9px] text-slate-500 hover:text-white underline decoration-dotted transition">
                                        Reportar Problema / Validar Manualmente
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="sticky bottom-0 bg-slate-900 pt-4 pb-2 border-t border-slate-800">
                    <?php if (!$progress): ?>
                        <form method="POST">
                            <input type="hidden" name="start_tour" value="1">
                            <button type="submit" class="w-full btn-glow bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-lg shadow-xl transition transform hover:-translate-y-1 text-sm uppercase tracking-widest">
                                Iniciar Tour
                            </button>
                        </form>
                    <?php elseif ($tourStatus == 'Completed'): ?>
                        <a href="certificate.php?tour_id=<?php echo $tour_id; ?>" target="_blank" class="block w-full text-center bg-yellow-600 hover:bg-yellow-500 text-white font-bold py-3 rounded-lg shadow-lg transition">
                            <i class="fa-solid fa-certificate"></i> Baixar Certificado
                        </a>
                    <?php else: ?>
                        <div class="text-center text-[10px] text-green-400 font-bold bg-green-900/20 p-2 rounded border border-green-900/50">
                            <i class="fa-solid fa-radar animate-pulse"></i> RASTREADOR ATIVO
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div class="flex-grow h-full bg-slate-950 relative z-10">
            <div id="map" class="h-full w-full"></div>
            
            <div class="absolute bottom-6 right-6 z-[400] bg-slate-900/90 backdrop-blur px-4 py-2 rounded-lg text-xs text-slate-300 border border-slate-700 shadow-xl">
                <div class="flex items-center gap-2 mb-1"><div class="w-3 h-1 bg-yellow-400"></div> Ativo</div>
                <div class="flex items-center gap-2 mb-1"><div class="w-3 h-1 bg-green-500"></div> Voado</div>
                <div class="flex items-center gap-2 mb-1"><div class="w-3 h-1 bg-slate-600 border border-slate-500 border-dashed"></div> Pendente</div>
                <?php if($ofpData): ?><div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-700 text-pink-400"><i class="fa-solid fa-route"></i> Rota Real SimBrief</div><?php endif; ?>
            </div>
        </div>
    </div>

    <form id="sbapiform" style="display:none;">
        <input type="text" name="orig">
        <input type="text" name="dest">
        <input type="text" name="route">
        <input type="text" name="type">
        <input type="text" name="airline" value="<?php echo $simbrief_airline; ?>">
        <input type="text" name="fltnum" value="<?php echo $simbrief_number; ?>">
        <input type="text" name="units" value="KGS"> 
        <input type="text" name="navlog" value="1">
        <input type="text" name="static_id" value="TOUR_<?php echo $tour_id; ?>">
        <input type="text" name="cpt" value="<?php echo $current_user->display_name; ?>">
    </form>

    <script src="../scripts/simbrief.apiv1.js"></script>
    <script>
        var api_dir = '../includes/';

        function generateOFP(dep, arr, route, acft) {
            let aircraftType = acft.split(',')[0].trim();
            if(!aircraftType || aircraftType === "Livre") aircraftType = "B738";

            document.getElementsByName('orig')[0].value = dep;
            document.getElementsByName('dest')[0].value = arr;
            document.getElementsByName('route')[0].value = route;
            document.getElementsByName('type')[0].value = aircraftType;
            
            simbriefsubmit(window.location.href);
        }
    </script>

    <script>
        const segments = <?php echo $jsMapSegments; ?>;
        const realRoute = <?php echo $jsRealRoute; ?>; // Pontos da rota real
        
        var map = L.map('map', {zoomControl: false, scrollWheelZoom: true, attributionControl: false});
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 18 }).addTo(map);
        L.control.zoom({position: 'topright'}).addTo(map);

        function getGreatCirclePoints(start, end, numPoints = 60) {
            function toRad(deg) { return deg * Math.PI / 180; }
            function toDeg(rad) { return rad * 180 / Math.PI; }
            var lat1 = toRad(start.lat), lon1 = toRad(start.lon);
            var lat2 = toRad(end.lat), lon2 = toRad(end.lon);
            var d = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin((lat2 - lat1) / 2), 2) + Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin((lon2 - lon1) / 2), 2)));
            var points = [];
            for (var i = 0; i <= numPoints; i++) {
                var f = i / numPoints;
                var A = Math.sin((1 - f) * d) / Math.sin(d);
                var B = Math.sin(f * d) / Math.sin(d);
                var x = A * Math.cos(lat1) * Math.cos(lon1) + B * Math.cos(lat2) * Math.cos(lon2);
                var y = A * Math.cos(lat1) * Math.sin(lon1) + B * Math.cos(lat2) * Math.sin(lon2);
                var z = A * Math.sin(lat1) + B * Math.sin(lat2);
                var lat = Math.atan2(z, Math.sqrt(x * x + y * y));
                var lon = Math.atan2(y, x);
                points.push([toDeg(lat), toDeg(lon)]);
            }
            return points;
        }

        var bounds = L.latLngBounds();
        
        // 1. Desenha os segmentos do Tour (Linhas curvas entre aeroportos)
        if (segments.length > 0) {
            
            // Verifica se temos uma rota real carregada para esta sessão
            const hasRealRoute = (typeof realRoute !== 'undefined' && realRoute.length > 0);

            segments.forEach(seg => {
                var curvePoints = getGreatCirclePoints(seg.start, seg.end);
                
                let color = '#475569'; let weight = 2; let opacity = 0.4; let dash = '5, 5'; 
                if (seg.status === 'active') { color = '#fbbf24'; weight = 3; opacity = 1; dash = '8, 8'; } 
                else if (seg.status === 'completed') { color = '#10b981'; weight = 3; opacity = 1; dash = null; }

                // --- LÓGICA DE OCULTAÇÃO DA LINHA AMARELA ---
                // Se temos a Rota Real (SimBrief) E esta é a perna ativa, NÃO desenhamos a linha curva amarela.
                // Desenhamos a linha apenas se: (Não tem rota real) OU (A perna não é a ativa)
                if (!hasRealRoute || seg.status !== 'active') {
                    L.polyline(curvePoints, {color: color, weight: weight, opacity: opacity, dashArray: dash}).addTo(map);
                }

                // --- MARCADORES DOS AEROPORTOS (Sempre desenha) ---
                // Mantemos os pontos de origem/destino mesmo se a linha sumir, para mostrar as bandeiras e infos
                const createTooltip = (point) => {
                    let html = "<div class='tooltip-content'>";
                    if(point.flag) html += `<img src='${point.flag}' class='w-6 h-4 mb-1 mx-auto rounded shadow-sm block'>`;
                    html += `<div class='font-bold text-sm'>${point.code}</div>`;
                    if(point.city) html += `<div class='text-[10px] text-slate-300'>${point.city}</div>`;
                    html += "</div>";
                    return html;
                };

                L.circleMarker([seg.start.lat, seg.start.lon], {radius: 3, color: '#fff', fillColor: '#3b82f6', fillOpacity: 1})
                 .bindTooltip(createTooltip(seg.start), {className: 'custom-tooltip', direction: 'top', offset: [0, -5]}).addTo(map);
                
                L.circleMarker([seg.end.lat, seg.end.lon], {radius: 3, color: '#fff', fillColor: '#3b82f6', fillOpacity: 1})
                 .bindTooltip(createTooltip(seg.end), {className: 'custom-tooltip', direction: 'top', offset: [0, -5]}).addTo(map);
                
                // Se não tiver rota real, o mapa foca baseado nessas curvas
                if (!hasRealRoute) {
                    bounds.extend(curvePoints);
                }
            });
        }

        // 2. Desenha a ROTA REAL IMPORTADA (Estilo Radar Moderno)
        if (typeof realRoute !== 'undefined' && realRoute.length > 0) {
            
            var routeLatLons = realRoute.map(p => [p.lat, p.lng]);
            
            // A) Efeito "Glow" (Sombra colorida larga e transparente)
            L.polyline(routeLatLons, {
                color: '#f472b6', // Rosa claro
                weight: 10,       // Largura maior
                opacity: 0.2,     // Bem transparente
                lineCap: 'round'
            }).addTo(map);

            // B) Linha Principal (Núcleo fino e sólido)
            var polyline = L.polyline(routeLatLons, {
                color: '#ec4899', // Rosa Pink vibrante
                weight: 3,
                opacity: 1,
                dashArray: null   // Linha sólida
            }).addTo(map);

            // C) Adicionar pontos nos Waypoints (Fixos)
            realRoute.forEach((point, index) => {
                // Pula o primeiro e último (já são os aeroportos) para não poluir
                if (index === 0 || index === realRoute.length - 1) return;

                L.circleMarker([point.lat, point.lng], {
                    radius: 3,              // Ponto pequeno
                    color: 'transparent',   // Sem borda
                    fillColor: '#fff',      // Miolo branco
                    fillOpacity: 0.8        
                }).bindTooltip(
                    `<div class="font-bold text-xs text-pink-400 font-mono tracking-widest">${point.name}</div>`, 
                    {
                        permanent: false,   // Só aparece ao passar o mouse
                        direction: 'top',
                        className: 'bg-slate-900 border border-pink-500/30 px-2 py-1 rounded shadow-xl' // Tailwind classes
                    }
                ).addTo(map);
            });
            
            // D) Ajusta o zoom para focar na rota real com margem confortável
            var realBounds = L.latLngBounds(routeLatLons);
            map.fitBounds(realBounds, {paddingTopLeft: [50, 50], paddingBottomRight: [50, 50]});

        } else if (segments.length > 0) {
             // Fallback para rota padrão se não houver SimBrief
             map.fitBounds(bounds, {padding: [80, 80]});
        } else {
            map.setView([20, -40], 3); 
        }

        function requestManualValidation(legId, dep, arr) {
            const msg = `Comandante <?php echo $display_callsign; ?>: Solicito validação manual da Etapa ${legId} (${dep}-${arr}) do Tour <?php echo $tour_id; ?>.`;
            window.open(`https://wa.me/5521999999999?text=${encodeURIComponent(msg)}`, '_blank');
        }
    </script>
</body>
</html>