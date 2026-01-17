<?php
// pilots/dispatch.php
require '../config/db.php';

$leg_id = $_GET['leg_id'] ?? 0;
$pilot_name = "Comandante"; // Pegue da sessão do piloto logado

// Busca dados da perna
$stmt = $pdo->prepare("SELECT * FROM tour_legs WHERE id = ?");
$stmt->execute([$leg_id]);
$leg = $stmt->fetch();

if (!$leg) die("Perna não encontrada.");

// Gerador de Link SimBrief
// Documentação: https://www.simbrief.com/api/xml.fetcher.php
$simbrief_url = "https://www.simbrief.com/system/dispatch.php?sharefleet="; 
$simbrief_params = [
    'orig' => $leg['dep_icao'],
    'dest' => $leg['arr_icao'],
    'route' => $leg['route_string'],
    'cpt' => $pilot_name,
];
$simbrief_link = "https://www.simbrief.com/system/dispatch.php?" . http_build_query($simbrief_params);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Despacho Operacional</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: sans-serif; background: #f1f5f9; padding: 20px; }
        .paper { background: white; max-width: 900px; margin: 0 auto; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 8px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px; }
        .route-info { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; text-align: center; }
        .icao-box { background: #1e293b; color: white; padding: 20px; border-radius: 8px; }
        .icao-box h2 { margin: 0; font-size: 2.5rem; }
        .map-box { height: 400px; background: #ddd; border-radius: 8px; margin-bottom: 30px; }
        .action-btn { background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; }
        .action-btn:hover { background: #b91c1c; }
        .metar-section { background: #f8fafc; padding: 20px; border-left: 4px solid #3b82f6; margin-top: 20px; font-family: monospace; }
    </style>
</head>
<body>

<div class="paper">
    <div class="header">
        <div>
            <h1 style="margin:0">Briefing de Voo</h1>
            <p style="margin:5px 0; color: #64748b;">Ordem de Operação #<?= $leg['id'] ?></p>
        </div>
        <img src="../assets/logo.png" alt="Logo" style="height: 50px;"> </div>

    <div class="route-info">
        <div class="icao-box">
            <small>ORIGEM</small>
            <h2><?= $leg['dep_icao'] ?></h2>
        </div>
        <div style="display:flex; align-items:center; justify-content:center; font-size:2rem; color:#cbd5e1;">
            ✈️
        </div>
        <div class="icao-box">
            <small>DESTINO</small>
            <h2><?= $leg['arr_icao'] ?></h2>
        </div>
    </div>

    <div id="flightMap" class="map-box"></div>

    <div style="display: flex; gap: 20px; align-items: flex-start;">
        <div style="flex: 1;">
            <h3>Rota Autorizada</h3>
            <div style="background: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace;">
                <?= $leg['route_string'] ? $leg['route_string'] : 'DIRECT' ?>
            </div>
            
            <div class="metar-section">
                <strong>METAR <?= $leg['dep_icao'] ?>:</strong><br>
                <span id="metar-dep">Carregando...</span>
            </div>
            <div class="metar-section">
                <strong>METAR <?= $leg['arr_icao'] ?>:</strong><br>
                <span id="metar-arr">Carregando...</span>
            </div>
        </div>
        
        <div style="text-align: right;">
            <a href="<?= $simbrief_link ?>" target="_blank" class="action-btn">
                📄 GERAR OFP SIMBRIEF
            </a>
            <p style="color: #64748b; font-size: 0.9rem; margin-top: 10px;">Abre em nova janela</p>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // 1. Inicializa o Mapa
    var map = L.map('flightMap').setView([0, 0], 3);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
    }).addTo(map);

    // Nota: Para linhas reais precisas, seria ideal ter as coordenadas lat/lon dos aeroportos no DB.
    // Como fallback, usamos uma API pública para pegar coordenadas (OpenStreetMap Nominatim) ou placeholders.
    // Aqui farei um exemplo genérico:
    
    async function plotRoute(dep, arr) {
        // Função auxiliar para buscar lat/lon (Simulação)
        // Em produção, salve lat/lon na tabela tour_legs ou airport_db para não depender de API externa no front
        try {
            const getCoords = async (icao) => {
                const response = await fetch(`https://api.7timer.info/bin/astro.php?lon=113.2&lat=23.1&ac=0&unit=metric&output=json&tzshift=0`); 
                // Nota: Acima é placeholder. Use uma API real de aeroportos ou seu DB.
                // Para simplificar este exemplo sem API Key, vou usar coordenadas fixas (exemplo Rio -> SP)
                // VOCÊ DEVE SUBSTITUIR ISSO PELA CONSULTA AO SEU BANCO DE DADOS
                if(icao === 'SBGL') return [-22.80, -43.25]; 
                if(icao === 'SBGR') return [-23.43, -46.47];
                return [-10, -50]; // Ponto genérico
            };

            // Traça a linha (apenas visual)
            // L.polyline([coordsDep, coordsArr], {color: 'red'}).addTo(map);
            // map.fitBounds(polyline.getBounds());
            
            // Para METAR (Gratuito via AVWX ou CheckWX)
            // Exemplo usando uma API pública que não requer chave (se existir) ou placeholder:
            document.getElementById('metar-dep').innerText = "METAR DATA UNAVAILABLE (API KEY REQUIRED)";
            document.getElementById('metar-arr').innerText = "METAR DATA UNAVAILABLE (API KEY REQUIRED)";
            
        } catch (e) { console.error(e); }
    }
    
    plotRoute('<?= $leg['dep_icao'] ?>', '<?= $leg['arr_icao'] ?>');
</script>

</body>
</html>