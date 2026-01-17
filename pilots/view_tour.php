<?php
// pilots/view_tour.php
// CENTRAL DE OPERAÇÕES DE TOUR: Mapa + Despacho + SimBrief + METAR

// 1. WORDPRESS & LOGIN
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; } else { die("Erro: WP não encontrado."); }
if (!is_user_logged_in()) { die('Acesso restrito.'); }

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

// 2. CONFIGURAÇÕES
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

require '../config/db.php'; 

// 3. IDENTIFICAÇÃO DO PILOTO (Para SimBrief)
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

// Formata para SimBrief (3 letras ICAO + Numero)
$simbrief_airline = substr($raw_callsign, 0, 3);
$simbrief_number = preg_replace('/[^0-9]/', '', substr($raw_callsign, 3));
if (strlen($simbrief_airline) < 3) $simbrief_airline = 'KFY'; // Fallback
if (empty($simbrief_number)) $simbrief_number = '0001';
$display_callsign = $simbrief_airline . $simbrief_number;

// 4. DADOS DO TOUR
$tour_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tour_id) die("ID Inválido");

$stmt = $pdo->prepare("SELECT * FROM tour_tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado.");

// Helpers
function formatarData($date) {
    if (!$date) return "Indefinido";
    return date("d/m/Y", strtotime($date));
}

function getMetar($icao) {
    // API Simples da VATSIM (Pública e gratuita)
    $url = "https://metar.vatsim.net/metar.php?id=" . $icao;
    $metar = @file_get_contents($url);
    return $metar ? trim($metar) : "Dados meteorológicos indisponíveis.";
}

// 5. AÇÃO: INICIAR TOUR
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
            header("Location: view_tour.php?id=" . $tour_id);
            exit;
        }
    }
}

// 6. STATUS E PERNAS
$stmtProg = $pdo->prepare("SELECT * FROM tour_progress WHERE pilot_id = ? AND tour_id = ?");
$stmtProg->execute([$wp_user_id, $tour_id]);
$progress = $stmtProg->fetch();

$currentLegId = $progress ? $progress['current_leg_id'] : 0;
$tourStatus = $progress ? $progress['status'] : 'Not Started';

// Busca todas as pernas com dados de aeroportos (JOIN)
$sqlLegs = "SELECT l.*, 
            dep.name as dep_name, dep.latitude_deg as dep_lat, dep.longitude_deg as dep_lon, dep.municipality as dep_city, dep.flag_url as dep_flag, 
            arr.name as arr_name, arr.latitude_deg as arr_lat, arr.longitude_deg as arr_lon, arr.municipality as arr_city, arr.flag_url as arr_flag 
            FROM tour_legs l 
            LEFT JOIN airports_2 dep ON l.dep_icao = dep.ident 
            LEFT JOIN airports_2 arr ON l.arr_icao = arr.ident 
            WHERE l.tour_id = ? ORDER BY l.leg_order ASC";
$stmtLegs = $pdo->prepare($sqlLegs);
$stmtLegs->execute([$tour_id]);
$allLegs = $stmtLegs->fetchAll();

// Descobre a ordem atual para colorir o mapa
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

// GERA JSON PARA O MAPA (Leaflet)
$mapSegments = [];
foreach($allLegs as $leg) {
    if ($leg['dep_lat'] && $leg['dep_lon'] && $leg['arr_lat'] && $leg['arr_lon']) {
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

function getSimBriefLink($leg, $airline, $fltnum) {
    return "https://dispatch.simbrief.com/options/custom?" . http_build_query([
        'airline' => $airline, 
        'fltnum' => $fltnum, 
        'orig' => $leg['dep_icao'], 
        'dest' => $leg['arr_icao'], 
        'route' => $leg['route_string'], 
        'static_id' => 'TOUR_'.$leg['tour_id']
    ]);
}
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
        .glass-card { background: rgba(15, 23, 42, 0.90); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-glow { animation: glow 2s infinite; }
        @keyframes glow { 0% { box-shadow: 0 0 5px #22c55e; } 50% { box-shadow: 0 0 20px #22c55e; } 100% { box-shadow: 0 0 5px #22c55e; } }
        .custom-tooltip {
            background-color: rgba(15, 23, 42, 0.95) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important; border-radius: 8px !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
        }
        .custom-tooltip::before { border-top-color: rgba(15, 23, 42, 0.95) !important; }
        .tooltip-content { padding: 8px 12px; text-align: center; }
    </style>
</head>
<body class="bg-slate-950 text-white h-screen flex flex-col font-sans overflow-hidden">

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
                        
                        // Estilos
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
                                    <a href="<?php echo getSimBriefLink($leg, $simbrief_airline, $simbrief_number); ?>" target="_blank" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2 rounded text-center shadow transition flex items-center justify-center gap-1">
                                        <i class="fa-solid fa-cloud-arrow-up"></i> SimBrief OFP
                                    </a>
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
                <div class="flex items-center gap-2"><div class="w-3 h-1 bg-slate-600 border border-slate-500 border-dashed"></div> Pendente</div>
            </div>
        </div>
    </div>

    <script>
        const segments = <?php echo $jsMapSegments; ?>;
        // Inicializa Mapa
        var map = L.map('map', {zoomControl: false, scrollWheelZoom: true, attributionControl: false});
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 18 }).addTo(map);
        L.control.zoom({position: 'topright'}).addTo(map);

        // Função Curva da Terra (Geodésica)
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
        
        if (segments.length > 0) {
            segments.forEach(seg => {
                var curvePoints = getGreatCirclePoints(seg.start, seg.end);
                
                let color = '#475569'; let weight = 2; let opacity = 0.4; let dash = '5, 5'; 
                if (seg.status === 'active') { color = '#fbbf24'; weight = 3; opacity = 1; dash = '8, 8'; } 
                else if (seg.status === 'completed') { color = '#10b981'; weight = 3; opacity = 1; dash = null; }

                L.polyline(curvePoints, {color: color, weight: weight, opacity: opacity, dashArray: dash}).addTo(map);
                
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
                
                bounds.extend(curvePoints);
            });
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