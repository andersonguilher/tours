<?php
// view_tour.php
// VERSÃO FINAL: Mapa com Status + Datas + METAR + Scenery + Link Passaporte

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

// 3. MATRÍCULA
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
if (empty($simbrief_number)) $simbrief_number = '9999';
$display_callsign = $simbrief_airline . $simbrief_number;

// 4. DADOS DO TOUR
$tour_id = $_GET['id'] ?? 0;
if ($tour_id == 0) die("ID Inválido");

$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado.");

// Helpers
function formatarData($date) {
    if (!$date) return "Indefinido";
    return date("d/m/Y", strtotime($date));
}

function getMetar($icao) {
    $url = "https://metar.vatsim.net/metar.php?id=" . $icao;
    $metar = @file_get_contents($url);
    return $metar ? trim($metar) : "Indisponível";
}

// 5. AÇÃO: INICIAR TOUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_tour'])) {
    $check = $pdo->prepare("SELECT id FROM pilot_tour_progress WHERE pilot_id = ? AND tour_id = ?");
    $check->execute([$wp_user_id, $tour_id]);
    
    if (!$check->fetch()) {
        $stmtLeg = $pdo->prepare("SELECT id FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC LIMIT 1");
        $stmtLeg->execute([$tour_id]);
        $first = $stmtLeg->fetch();
        
        if ($first) {
            $pdo->prepare("INSERT INTO pilot_tour_progress (pilot_id, tour_id, current_leg_id, status) VALUES (?, ?, ?, 'In Progress')")
                ->execute([$wp_user_id, $tour_id, $first['id']]);
            header("Location: view_tour.php?id=" . $tour_id);
            exit;
        }
    }
}

// 6. STATUS & PERNAS
$stmtProg = $pdo->prepare("SELECT * FROM pilot_tour_progress WHERE pilot_id = ? AND tour_id = ?");
$stmtProg->execute([$wp_user_id, $tour_id]);
$progress = $stmtProg->fetch();

$currentLegId = $progress ? $progress['current_leg_id'] : 0;
$tourStatus = $progress ? $progress['status'] : 'Not Started';

// Carrega Pernas
$sqlLegs = "SELECT l.*, dep.name as dep_name, dep.latitude_deg as dep_lat, dep.longitude_deg as dep_lon, dep.municipality as dep_city, dep.flag_url as dep_flag, arr.name as arr_name, arr.latitude_deg as arr_lat, arr.longitude_deg as arr_lon, arr.municipality as arr_city, arr.flag_url as arr_flag FROM tour_legs l LEFT JOIN airports_2 dep ON l.dep_icao = dep.ident LEFT JOIN airports_2 arr ON l.arr_icao = arr.ident WHERE l.tour_id = ? ORDER BY l.leg_order ASC";
$stmtLegs = $pdo->prepare($sqlLegs);
$stmtLegs->execute([$tour_id]);
$allLegs = $stmtLegs->fetchAll();

// Descobre a ORDEM da perna atual
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

// PREPARAÇÃO DO MAPA (JS)
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
    return "https://dispatch.simbrief.com/options/custom?" . http_build_query(['airline' => $airline, 'fltnum' => $fltnum, 'orig' => $leg['dep_icao'], 'dest' => $leg['arr_icao'], 'route' => $leg['route_string'], 'static_id' => 'TOUR_'.$leg['tour_id']]);
}
$rules = json_decode($tour['rules_json'], true);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($tour['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; } 
        ::-webkit-scrollbar-track { background: #0f172a; } 
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .flag-icon { width: 20px; height: 14px; object-fit: cover; border-radius: 2px; display: inline-block; }
        .glass-panel { background: rgba(30, 41, 59, 0.95); border-right: 1px solid rgba(255,255,255,0.08); }
        .glass-card { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-glow { animation: glow 2s infinite; }
        @keyframes glow { 0% { box-shadow: 0 0 5px #22c55e; } 50% { box-shadow: 0 0 20px #22c55e; } 100% { box-shadow: 0 0 5px #22c55e; } }
        .custom-tooltip {
            background-color: rgba(15, 23, 42, 0.95) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
            font-family: sans-serif !important;
        }
        .custom-tooltip::before { border-top-color: rgba(15, 23, 42, 0.95) !important; }
        .tooltip-content { padding: 8px 12px; text-align: center; }
    </style>
</head>
<body class="bg-slate-950 text-white h-screen flex flex-col font-sans overflow-hidden">

    <div class="h-16 bg-slate-900 border-b border-slate-800 flex justify-between items-center px-6 z-50 shrink-0 shadow-lg">
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-slate-400 hover:text-white transition text-sm flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> <span class="hidden md:inline">Voltar</span>
            </a>
            <div class="h-4 w-px bg-slate-700"></div>
            <h1 class="font-bold text-lg text-white truncate"><?php echo htmlspecialchars($tour['title']); ?></h1>
        </div>
        <div class="flex items-center gap-4">
            
            <a href="passport.php" class="text-slate-400 hover:text-yellow-400 transition" title="Ver Passaporte">
                <i class="fa-solid fa-passport text-xl"></i>
            </a>
            
            <div class="h-8 w-px bg-slate-800"></div>

            <div class="text-right">
                <div class="text-[10px] text-slate-500 uppercase font-bold">Matrícula</div>
                <div class="font-bold font-mono text-yellow-400 text-sm"><?php echo $display_callsign; ?></div>
            </div>
        </div>
    </div>

    <div class="flex-grow flex overflow-hidden">
        
        <div class="w-full md:w-[450px] bg-slate-900 border-r border-slate-800 flex flex-col h-full z-20 shrink-0 shadow-2xl relative">
            <div class="h-40 bg-cover bg-center relative shrink-0" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent"></div>
                <div class="absolute bottom-4 left-4">
                    <span class="bg-blue-600/90 text-white text-[10px] font-bold px-2 py-1 rounded uppercase shadow"><?php echo $tour['difficulty']; ?></span>
                </div>
            </div>

            <div class="flex-grow overflow-y-auto p-6 custom-scrollbar flex flex-col">
                
                <?php if ($tour['start_date'] || $tour['end_date']): ?>
                <div class="mb-4 flex gap-4 text-xs bg-slate-800/50 p-3 rounded border border-slate-700/50">
                    <?php if($tour['start_date']): ?>
                    <div><span class="text-slate-500 uppercase font-bold text-[10px] block">Início</span> <?php echo formatarData($tour['start_date']); ?></div>
                    <?php endif; ?>
                    <?php if($tour['end_date']): ?>
                    <div><span class="text-slate-500 uppercase font-bold text-[10px] block">Término</span> <?php echo formatarData($tour['end_date']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($progress): ?>
                <div class="mb-6 bg-slate-800/50 p-4 rounded-xl border border-slate-700">
                    <div class="flex justify-between text-xs text-slate-400 uppercase font-bold mb-2">
                        <span>Progresso</span>
                        <?php 
                            $total = count($allLegs); $done = 0;
                            if($tourStatus=='Completed') $done=$total; else foreach($allLegs as $l){if($l['id'] < $currentLegId) $done++;}
                            $pct = $total > 0 ? round(($done/$total)*100) : 0;
                        ?>
                        <span class="text-blue-400"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <h2 class="text-xl font-bold text-white mb-2">Briefing da Missão</h2>
                    <div class="text-sm text-slate-300 leading-relaxed font-light text-justify bg-slate-800/30 p-3 rounded border border-white/5">
                        <?php echo nl2br(htmlspecialchars($tour['description'])); ?>
                    </div>
                </div>

                <div class="space-y-3 mb-6">
                    <h3 class="text-xs font-bold text-slate-500 uppercase">Requisitos</h3>
                    <div class="flex justify-between items-center bg-slate-800 p-3 rounded border border-slate-700">
                        <span class="text-slate-400 text-xs">Aeronaves</span>
                        <span class="font-mono text-white text-sm font-bold"><?php echo $rules['allowed_aircraft'] ?? 'Livre'; ?></span>
                    </div>
                    <div class="flex justify-between items-center bg-slate-800 p-3 rounded border border-slate-700">
                        <span class="text-slate-400 text-xs">Velocidade Máx. (< FL100)</span>
                        <span class="font-mono text-red-400 font-bold"><?php echo $rules['speed_fl100'] ?? '250'; ?> kts</span>
                    </div>
                </div>

                <div class="mt-auto pt-4 border-t border-slate-800">
                    <?php if (!$progress): ?>
                        <form method="POST">
                            <input type="hidden" name="start_tour" value="1">
                            <button type="submit" class="w-full btn-glow bg-green-600 hover:bg-green-500 text-white font-bold py-4 rounded-xl shadow-xl transition transform hover:-translate-y-1 flex items-center justify-center gap-3 text-lg group">
                                <span>INICIAR TOUR</span> 
                                <i class="fa-solid fa-plane-departure group-hover:translate-x-1 transition"></i>
                            </button>
                        </form>
                        <p class="text-center text-[10px] text-slate-500 mt-2">Ao iniciar, sua primeira perna será ativada.</p>
                    
                    <?php elseif ($tourStatus == 'Completed'): ?>
                        <div class="text-center space-y-3">
                            <div class="text-green-500 font-bold text-lg flex items-center justify-center gap-2">
                                <i class="fa-solid fa-trophy"></i> Tour Concluído!
                            </div>
                            <a href="certificate.php?tour_id=<?php echo $tour_id; ?>" target="_blank" class="block w-full bg-yellow-600 hover:bg-yellow-500 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-file-pdf"></i> Baixar Certificado
                            </a>
                            <p class="text-[10px] text-slate-500">Parabéns comandante! O seu certificado oficial já está disponível.</p>
                        </div>

                    <?php else: ?>
                        <div class="text-center text-slate-500 text-xs italic">
                            <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i> Tour em andamento. Bons voos!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex-grow h-full bg-slate-950 relative z-10">
            <div id="map" class="h-full w-full"></div>
            
            <div class="absolute top-4 left-4 z-[500] w-80 max-h-[calc(100%-2rem)] overflow-y-auto custom-scrollbar glass-card rounded-xl shadow-2xl flex flex-col">
                <div class="p-3 border-b border-white/10 bg-slate-900/90 backdrop-blur sticky top-0 z-10 flex justify-between items-center">
                    <h3 class="text-xs font-bold text-slate-300 uppercase flex items-center gap-2">
                        <i class="fa-solid fa-list-ul text-blue-500"></i> Plano de Voo
                    </h3>
                    <span class="text-[9px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded"><?php echo count($allLegs); ?> Legs</span>
                </div>
                
                <div class="p-2 space-y-1">
                    <?php foreach($allLegs as $leg): 
                        if (!$progress) {
                            $isCurrent = false; $isDone = false; $isLocked = true;
                        } else {
                            $isCurrent = ($leg['id'] == $currentLegId && $tourStatus != 'Completed');
                            $isDone = ($tourStatus == 'Completed') || ($leg['id'] < $currentLegId && !$isCurrent);
                            $isLocked = (!$isCurrent && !$isDone);
                        }
                        
                        $fD = $leg['dep_flag'] ? "<img src='{$leg['dep_flag']}' class='flag-icon mr-1 opacity-80'>" : "";
                        $fA = $leg['arr_flag'] ? "<img src='{$leg['arr_flag']}' class='flag-icon mr-1 opacity-80'>" : "";

                        if ($isCurrent) {
                            $bg = "bg-blue-600/20 border-l-2 border-yellow-500";
                            $txt = "text-white";
                            $statusIcon = "<span class='text-[9px] bg-yellow-500 text-black font-bold px-2 py-0.5 rounded'>ATIVO</span>";
                        } elseif ($isDone) {
                            $bg = "bg-black/40 border-l-2 border-green-500 opacity-60";
                            $txt = "text-slate-400";
                            $statusIcon = "<i class='fa-solid fa-check text-green-500'></i>";
                        } else {
                            $bg = "bg-transparent border-l-2 border-slate-700 opacity-50";
                            $txt = "text-slate-500";
                            $statusIcon = "<i class='fa-solid fa-lock text-slate-600 text-xs'></i>";
                        }
                    ?>
                    <div class="<?php echo $bg; ?> p-3 rounded transition-all group hover:bg-slate-800/80">
                        <div class="flex justify-between items-center">
                            <div class="flex flex-col">
                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Leg <?php echo str_pad($leg['leg_order'], 2, '0', STR_PAD_LEFT); ?></span>
                                <div class="<?php echo $txt; ?> font-mono font-bold text-sm flex items-center gap-2">
                                    <span title="<?php echo $leg['dep_city']; ?>"><?php echo $fD . $leg['dep_icao']; ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-600"></i>
                                    <span title="<?php echo $leg['arr_city']; ?>"><?php echo $fA . $leg['arr_icao']; ?></span>
                                </div>
                            </div>
                            <?php if(!$isCurrent): ?>
                                <?php echo $statusIcon; ?>
                            <?php endif; ?>
                        </div>

                        <?php if($isCurrent): ?>
                        <div class="mt-3 p-3 bg-black/30 rounded border border-white/10 text-xs space-y-2">
                            
                            <div class="font-mono text-cyan-300 border-b border-white/10 pb-2 mb-2">
                                <div class="mb-1"><span class="font-bold text-white">METAR <?php echo $leg['dep_icao']; ?>:</span> <?php echo getMetar($leg['dep_icao']); ?></div>
                                <div><span class="font-bold text-white">METAR <?php echo $leg['arr_icao']; ?>:</span> <?php echo getMetar($leg['arr_icao']); ?></div>
                            </div>

                            <div class="flex flex-wrap gap-2 mt-2">
                                <a href="<?php echo getSimBriefLink($leg, $simbrief_airline, $simbrief_number); ?>" target="_blank" class="flex-1 text-center bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-3 rounded shadow transition">
                                    <i class="fa-solid fa-file-contract"></i> Gerar OFP
                                </a>
                                
                                <?php if(!empty($tour['scenery_link'])): ?>
                                <a href="<?php echo htmlspecialchars($tour['scenery_link']); ?>" target="_blank" class="flex-1 text-center bg-purple-600 hover:bg-purple-500 text-white font-bold py-2 px-3 rounded shadow transition">
                                    <i class="fa-solid fa-map"></i> Cenário
                                </a>
                                <?php endif; ?>
                            </div>

                        </div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="absolute bottom-6 right-6 z-[400] bg-slate-900/90 backdrop-blur px-3 py-1.5 rounded text-[10px] text-slate-400 border border-slate-700 shadow-xl uppercase tracking-widest font-bold">
                Rota Ortodrômica
            </div>
        </div>

    </div>

    <script>
        const segments = <?php echo $jsMapSegments; ?>;
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
        
        if (segments.length > 0) {
            segments.forEach(seg => {
                var curvePoints = getGreatCirclePoints(seg.start, seg.end);
                
                let color = '#475569'; 
                let weight = 2;
                let opacity = 0.4;
                let dash = '5, 5'; 

                if (seg.status === 'active') {
                    color = '#fbbf24'; 
                    weight = 3;
                    opacity = 1;
                    dash = '8, 8';
                } else if (seg.status === 'completed') {
                    color = '#10b981'; 
                    weight = 3;
                    opacity = 1;
                    dash = null; 
                }

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
    </script>
</body>
</html>