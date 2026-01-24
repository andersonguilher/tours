<?php
// pilots/live_board.php

// DEBUG: ENABLE ERRORS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Caminho para o JSON (Baseado na estrutura de pastas informada)
// live_board.php está em: /var/www/kafly_user/data/www/kafly.com.br/dash/tours/pilots/
// wzp.json está em:      /var/www/kafly_user/data/www/kafly.com.br/mapa/3d/data/wzp.json
// Caminho relativo: ../../../mapa/3d/data/wzp.json
$jsonPath = __DIR__ . '/../../../mapa/3d/data/wzp.json';

// Conectar ao banco de dados para validar pernas de tour
require '../config/db.php';

// Carregar pernas de tour válidas (apenas pares DEP-ARR para performance)
// Carregar pilotos que estão ATIVAMENTE voando tours (status 'In Progress')
$activeTourPilots = [];
try {
    // Busca: Quem está fazendo tour? Qual tour? Qual perna (Dep/Arr)?
    // JOIN correto com a tabela de pilotos para pegar o VID/CID (vatsim_id, ivao_id)
    // Assegura que as variáveis de configuração existam
    $dbNameFinal = isset($dbPilotosName) ? $dbPilotosName : 'u378005298_hEatD';
    $tbNameFinal = isset($tb_pilotos) ? $tb_pilotos : 'Dados_dos_Pilotos';
    $colIdFinal = isset($col_id_piloto) ? $col_id_piloto : 'id_piloto';

    $sqlActive = "
        SELECT 
            tp.pilot_id, 
            p.vatsim_id, p.ivao_id,
            tl.dep_icao, tl.arr_icao, 
            tt.title as tour_title,
            tp.navlog_json,
            d.latitude_deg as dep_lat, d.longitude_deg as dep_lon, d.municipality as dep_city,
            a.latitude_deg as arr_lat, a.longitude_deg as arr_lon, a.municipality as arr_city
        FROM tour_progress tp
        JOIN tour_legs tl ON tp.current_leg_id = tl.id
        JOIN tour_tours tt ON tp.tour_id = tt.id
        JOIN $dbNameFinal.$tbNameFinal p ON tp.pilot_id = p.$colIdFinal
        LEFT JOIN airports_2 d ON tl.dep_icao = d.ident
        LEFT JOIN airports_2 a ON tl.arr_icao = a.ident
        WHERE tp.status = 'In Progress'
    ";
    
    $stmt = $pdo->query($sqlActive);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // O JSON do mapa usa o ID da rede (VATSIM/IVAO)
        // Então precisamos mapear o Active Tour para esses IDs de rede, não o ID interno do piloto
        
        $flightData = [
            'internal_id'  => $row['pilot_id'],
            'expected_dep' => $row['dep_icao'],
            'expected_arr' => $row['arr_icao'],
            'tour_title'   => $row['tour_title'],
            'navlog'       => $row['navlog_json'],
            'pilot_name'   => $row['pilot_real_name'] ?? 'Comandante',
            'dep_city'     => $row['dep_city'] ?? '',
            'arr_city'     => $row['arr_city'] ?? '',
            'route_coords' => [
                'dep_lat' => $row['dep_lat'],
                'dep_lon' => $row['dep_lon'],
                'arr_lat' => $row['arr_lat'],
                'arr_lon' => $row['arr_lon']
            ]
        ];

        // Mapeia para VATSIM ID se existir
        if (!empty($row['vatsim_id'])) {
            $activeTourPilots[(int)$row['vatsim_id']] = $flightData;
        }
        
        // Mapeia para IVAO ID se existir
        if (!empty($row['ivao_id'])) {
            $activeTourPilots[(int)$row['ivao_id']] = $flightData;
        }
    }

} catch (PDOException $e) {
    $errorMsg .= " DB Error: " . $e->getMessage();
}

if (file_exists($jsonPath)) {
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);
    
    if (is_array($data)) {
        foreach ($data as $fly) {
            // DETECÇÃO DE REDE E ID (Mesma lógica do validate_flights.php)
            $network = isset($fly['network']) ? strtoupper($fly['network']) : 'IVAO';
            
            if ($network === 'VATSIM') {
                $vid = (int)($fly['id'] ?? 0);
            } else {
                $vid = (int)($fly['userId'] ?? 0);
            }
            
            // 1. O piloto está na lista de tours ativos?
            if (!isset($activeTourPilots[$vid])) {
                continue; // Não está fazendo tour registrado (ou ID não bate), pula
            }

            // Dados do Piloto/Voo
            $callsign = $fly['callsign'] ?? 'N/A';
            $fp = $fly['flightPlan'] ?? [];
            $dep = $fp['departureId'] ?? '---';
            $arr = $fp['arrivalId'] ?? '---';
            $acft = $fp['aircraft']['icaoCode'] ?? $fp['aircraft'] ?? $fp['aircraftId'] ?? 'N/A';
            if (is_array($acft)) $acft = 'N/A'; // Segurança se ainda for array
            
            // Dados esperados para esse piloto
            $expected = $activeTourPilots[$vid];

            // 2. O piloto está voando a rota correta da perna atual?
            // (Compara o plano de voo conectado com o banco de dados)
            if ($dep !== $expected['expected_dep'] || $arr !== $expected['expected_arr']) {
                // Está online, mas voando outra coisa (ou inverteu rota, etc)
                // Decisão: Não mostrar, ou mostrar como "Rota Incorreta"?
                // USER REQUEST: "verificar se ele está conectado E executando esta leg" -> Filtrar restrito.
                continue; 
            }

            // Se passou, é um voo válido de tour!
            $tourName = $expected['tour_title'];

            
            // Dados de Rastreamento
            $lastTrack = $fly['lastTrack'] ?? [];
            $state = $lastTrack['state'] ?? 'Unknown';
            $ground = $lastTrack['onGround'] ?? false;
            $lat = $lastTrack['latitude'] ?? null;
            $lon = $lastTrack['longitude'] ?? null;
            $hdg = $lastTrack['heading'] ?? 0;
            $lastTrack = $fly['lastTrack'] ?? [];
            $state = $lastTrack['state'] ?? 'Unknown';
            $ground = $lastTrack['onGround'] ?? false;
            $lat = $lastTrack['latitude'] ?? null;
            $lon = $lastTrack['longitude'] ?? null;
            $hdg = $lastTrack['heading'] ?? 0;
            $gs = $lastTrack['groundSpeed'] ?? 0;
            $alt = $lastTrack['altitude'] ?? 0;
            
            // Lógica de Status: Padrão EM ROTA
            $statusDisplay = 'EM ROTA';
            $cssClass = 'status-enroute';
            
            // Se estiver no chão ou muito lento (< 40 kts), considera operações de solo
            if ($ground || $gs < 40) {
                if ($gs < 3) {
                    $statusDisplay = 'NO SOLO / PARADO';
                    $cssClass = 'status-boarding'; // Verde
                } else {
                    $statusDisplay = 'TAXI';
                    $cssClass = 'status-boarding'; // Verde
                }
            } elseif ($state == 'Approach' || $state == 'Descending') {
                $statusDisplay = 'APROXIMAÇÃO';
                $cssClass = 'status-enroute';
            }

            // Tentar descobrir o piloto
            $pilotName = "$vid"; 
            
            // Adiciona à lista
            $flights[] = [
                'callsign' => $callsign,
                'pilot_name' => $pilotName,
                'dep_icao' => $dep,
                'arr_icao' => $arr,
                'tour_title' => $tourName,
                'status_text' => $statusDisplay,
                'status_class' => $cssClass,
                 'acft' => $acft,
                'lat' => $lat,
                'lon' => $lon,
                'hdg' => $hdg,
                'hdg' => $hdg,
                'lon' => $lon,
                'hdg' => $hdg,
                'gs'  => $gs,
                'alt' => $alt,
                'route' => $expected['route_coords'] ?? null,
                'route_string' => $fp['route'] ?? 'N/A',
                'navlog' => $expected['navlog'] ?? null,
                'real_name' => $expected['pilot_name'],
                'dep_city' => $expected['dep_city'],
                'arr_city' => $expected['arr_city']
            ];
        }
    } else {
        $errorMsg = "Formato inválido no arquivo de dados ao vivo.";
    }
} else {
    $errorMsg = "Arquivo de dados ao vivo não encontrado.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Kafly Live Operations</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        body { background-color: #0f172a; color: #fbbf24; font-family: 'Share Tech Mono', monospace; margin: 0; padding: 0; overflow: hidden; }
        .board-container { width: 100vw; height: 100vh; border: none; padding: 0; background: #1e293b; position: relative; }
        
        /* Map Full Screen */
        #map { width: 100%; height: 100%; border: none; border-radius: 0; z-index: 1; }
        
        /* Floating Table Overlay */
        .flights-overlay {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 15px;
            z-index: 1000;
            max-height: 30vh;
            overflow-y: auto;
            backdrop-filter: blur(5px);
        }

        .header { 
            position: absolute; 
            top: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 1000; 
            background: rgba(15, 23, 42, 0.8);
            padding: 10px 20px;
            border-radius: 20px;
            border: 1px solid #334155;
            backdrop-filter: blur(5px);
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px; border-bottom: 2px solid #475569; color: #94a3b8; font-size: 1rem; }
        td { padding: 10px; border-bottom: 1px solid #334155; font-size: 1rem; }
        
        .status-boarding { color: #22c55e; animation: blink 1s infinite; }
        .status-enroute { color: #fbbf24; }
        .status-landed { color: #ef4444; }
        
        @keyframes blink { 50% { opacity: 0.5; } }
        
        .no-flights { text-align: center; padding: 50px; color: #64748b; font-size: 1.5rem; }
        .acft-badge { font-size: 1rem; color: #cbd5e1; background: #334155; padding: 2px 6px; border-radius: 4px; }

        /* Leaflet Dark Popup */
        .leaflet-popup-content-wrapper, .leaflet-popup-tip {
            background: #111827; /* Gray 900 */
            color: #f3f4f6;
            border: 1px solid #374151;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        .leaflet-popup-content { margin: 0 !important; width: 320px !important; }
        .leaflet-container a.leaflet-popup-close-button { color: #9ca3af; top: 8px; right: 8px; }
        
        .vatsim-card { font-family: 'Inter', sans-serif; }
        .vcard-header { background: #1f2937; padding: 12px 16px; border-bottom: 1px solid #374151; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .vcard-body { padding: 16px; }
        .vcard-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .vcard-stat { text-align: center; flex: 1; }
        .vcard-label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .vcard-value { font-size: 14px; font-weight: 700; color: #fff; }
    </style>
    <meta http-equiv="refresh" content="60"> 
</head>
<body>

<div class="board-container">
    <div class="header">✈️ Partidas & Operações ✈️</div>
    
    <div id="map"></div>

    <div class="flights-overlay">
        <table>
            <thead>
                <tr>
                    <th>Voo</th>
                    <th>Piloto (ID)</th>
                    <th>Aeronave</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Rede/Info</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($errorMsg)): ?>
                    <tr>
                       <td colspan="7" style="color: red; text-align: center;">STATUS: <?= htmlspecialchars($errorMsg) ?></td>
                    </tr>
                <?php elseif (count($flights) > 0): ?>
                    <?php foreach ($flights as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['callsign']) ?></td>
                            <td><?= htmlspecialchars($f['real_name'] ?? $f['pilot_name']) ?></td>
                            <td><span class="acft-badge"><?= htmlspecialchars($f['acft']) ?></span></td>
                            <td><?= htmlspecialchars($f['dep_icao']) ?></td>
                            <td><?= htmlspecialchars($f['arr_icao']) ?></td>
                            <td><?= htmlspecialchars($f['tour_title']) ?></td>
                            <td class="<?= $f['status_class'] ?>"><?= $f['status_text'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-flights">NENHUM VOO DETECTADO NO RADAR</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
<script>
    var map = L.map('map', {attributionControl: false}).setView([0, 0], 2);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 18
    }).addTo(map);

    var flights = <?php echo json_encode($flights); ?>;
    var bounds = [];

    flights.forEach(function(f) {
        if(f.lat !== null && f.lon !== null && f.lat !== "" && f.lon !== "") {
            
            // Lógica de Ícones e Tamanho Dinâmico
            var code = f.acft.split('/')[0].toUpperCase();
            var iconHtml = '';
            var rotOffset = 0;
            var isSVG = false;
            
            // Definição de Tamanho Baseado na Altitude
            // Solo (<500ft): 10px (Bem pequeno para não poluir)
            // Cruzeiro (>20000ft): 22px
            var currentSize = 10; // Default Ground Size
            if (f.alt > 500) {
                var factor = Math.min(f.alt / 20000, 1); // 0 a 1
                currentSize = 10 + (factor * 12); // De 10 até 22
            }
            
            // Ícones
            var helis = ['H125','H135','H145','R44','R22','R66','AS35','EC12','EC13','BK11','B206','B407','UH1','H500','MD50','CABRI'];
            var ga_planes = ['C172','C152','C182','PA28','SR22','DA40','DA62','C208','BE58','PA34','PA46','M20','C150','C310','B350','P28A'];
            
            var color = '#fbbf24'; 
            if(f.status_class === 'status-boarding') color = '#22c55e'; 
            if(f.status_class === 'status-landed') color = '#ef4444'; 

            if (helis.some(h => code.includes(h))) {
                // Helicóptero
                var rot = f.hdg - 90;
                iconHtml = '<i class="fa-solid fa-helicopter" style="transform: rotate('+rot+'deg); display:block; width:100%; height:100%; text-align:center; line-height:'+currentSize+'px; font-size:'+currentSize+'px; color: '+color+'; text-shadow: 0 0 5px '+color+';"></i>';
            } else if (ga_planes.some(g => code.includes(g))) {
                // GA Plane
                isSVG = true;
                var svgPath = "M12 2 L14 8 L22 8 L22 11 L14 11 L13 20 L16 22 L8 22 L11 20 L10 11 L2 11 L2 8 L10 8 L12 2 Z";
                iconHtml = `<svg viewBox="0 0 24 24" style="transform: rotate(${f.hdg}deg); filter: drop-shadow(0 0 3px ${color}); width:100%; height:100%;" fill="${color}">
                                <path d="${svgPath}" />
                            </svg>`;
            } else {
                // Airliner
                var rot = f.hdg - 45;
                iconHtml = '<i class="fa-solid fa-plane" style="transform: rotate('+rot+'deg); display:block; width:100%; height:100%; text-align:center; line-height:'+currentSize+'px; font-size:'+currentSize+'px; color: '+color+'; text-shadow: 0 0 5px '+color+';"></i>';
            }
            
            var planeIcon = L.divIcon({
                html: iconHtml,
                className: 'custom-plane-icon',
                iconSize: [currentSize, currentSize],
                iconAnchor: [currentSize/2, currentSize/2],
                popupAnchor: [0, -20]
            });

            
            // Marker com Popup Visual VATSIM Style
            var popupContent = `
                <div class="vatsim-card">
                    <div class="vcard-header">
                        <div>
                            <span style="font-weight:900; color:#3b82f6; font-size:16px; margin-right:8px;">${f.callsign}</span>
                            <span style="font-size:12px; color:#e5e7eb; font-weight:500;">${f.acft}</span>
                        </div>
                        <span style="font-size:11px; color:#3b82f6; font-weight:bold;">${f.tour_title}</span>
                    </div>
                    <div class="vcard-body">
                        <div style="font-size:15px; font-weight:700; color:#fff; margin-bottom:16px;">${f.real_name}</div>
                        
                        <div class="vcard-row" style="background:#111827; padding:10px; border-radius:6px; border:1px solid #374151;">
                            <div style="text-align:left;">
                                <div style="font-weight:900; font-size:16px; color:#fff;">${f.dep_icao}</div>
                                <div style="font-size:10px; color:#9ca3af; max-width:80px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${f.dep_city}</div>
                            </div>
                            <i class="fa-solid fa-plane" style="color:#6b7280; font-size:12px;"></i>
                            <div style="text-align:right;">
                                <div style="font-weight:900; font-size:16px; color:#fff;">${f.arr_icao}</div>
                                <div style="font-size:10px; color:#9ca3af; max-width:80px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${f.arr_city}</div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; gap:10px;">
                            <div class="vcard-stat">
                                <div class="vcard-label">GS</div>
                                <div class="vcard-value">${f.gs} <span style="font-size:10px; font-weight:400; color:#9ca3af;">kts</span></div>
                            </div>
                            <div class="vcard-stat">
                                <div class="vcard-label">Altitude</div>
                                <div class="vcard-value">${f.alt} <span style="font-size:10px; font-weight:400; color:#9ca3af;">ft</span></div>
                            </div>
                            <div class="vcard-stat">
                                <div class="vcard-label">Heading</div>
                                <div class="vcard-value">${f.hdg}°</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            var marker = L.marker([f.lat, f.lon], {icon: planeIcon}).bindPopup(popupContent);
            marker.addTo(map);

            // Rota (Polyline)
            var latlngs = [];
            
            // 1. Tenta usar a rota real do SimBrief (Se salva)
            if (f.navlog) {
                try {
                    var pts = (typeof f.navlog === 'string') ? JSON.parse(f.navlog) : f.navlog;
                    if (Array.isArray(pts)) {
                        pts.forEach(function(p) {
                            latlngs.push([p.lat, p.lng]);
                        });
                    }
                } catch(e) { console.error("Erro rota", e); }
            }

            // 2. Fallback: Linha direta
            if (latlngs.length === 0 && f.route && f.route.dep_lat && f.route.arr_lat) {
                latlngs = [
                    [f.route.dep_lat, f.route.dep_lon],
                    [f.route.arr_lat, f.route.arr_lon]
                ];
            }

            if (latlngs.length > 0) {
                // ... (Poly styles from before) ...
                var polyGlow = L.polyline(latlngs, { color: '#0ea5e9', weight: 6, opacity: 0.2, lineCap: 'round' });
                var poly = L.polyline(latlngs, { color: '#38bdf8', weight: 2, opacity: 1.0, lineJoin: 'round' });
                
                var layers = [polyGlow, poly];

                // Adicionar Bolinhas de Aeroportos (Dep/Arr) se coordenadas existirem
                if (f.route && f.route.dep_lat) {
                    var depMarker = L.circleMarker([f.route.dep_lat, f.route.dep_lon], {
                        radius: 4, fillColor: '#22c55e', color: '#14532d', weight: 1, opacity: 1, fillOpacity: 1
                    }).bindTooltip(f.dep_icao, {permanent: true, direction: 'left', className: 'airport-label'});
                    layers.push(depMarker);
                }
                
                if (f.route && f.route.arr_lat) {
                    var arrMarker = L.circleMarker([f.route.arr_lat, f.route.arr_lon], {
                        radius: 4, fillColor: '#ef4444', color: '#7f1d1d', weight: 1, opacity: 1, fillOpacity: 1
                    }).bindTooltip(f.arr_icao, {permanent: true, direction: 'right', className: 'airport-label'});
                    layers.push(arrMarker);
                }

                var routeGroup = L.layerGroup(layers);

                // Interação: Hover mostra rota
                marker.on('mouseover', function() {
                    routeGroup.addTo(map);
                });
                
                marker.on('mouseout', function() {
                    map.removeLayer(routeGroup);
                });
            }

            bounds.push([f.lat, f.lon]);

            bounds.push([f.lat, f.lon]);
        }
    });

    // NÃO executamos fitBounds para manter o mapa do mundo completo inicialmente
    // map.fitBounds(bounds, {padding: [50, 50]});
    
    // Default World View
    map.setView([20, 0], 2);
</script>
</html>