<?php
// pilots/live_board.php
require '../config/db.php';

// Busca voos ao vivo
// Nota: Ajuste 'Dados_dos_Pilotos' e colunas conforme seu DB real de pilotos
$sql = "
    SELECT 
        s.state, s.arrival_checks,
        p.nome_guerra as pilot_name, p.matricula as callsign,
        l.dep_icao, l.arr_icao,
        t.title as tour_title
    FROM tour_live_sessions s
    JOIN Dados_dos_Pilotos p ON s.pilot_id = p.id_piloto -- Ajuste ID se necessário
    JOIN tour_legs l ON s.leg_id = l.id
    JOIN tour_tours t ON s.tour_id = t.id
    ORDER BY s.last_seen DESC
";
$stmt = $pdo->query($sql);
$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Kafly Live Operations</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #fbbf24; font-family: 'Share Tech Mono', monospace; margin: 0; padding: 20px; }
        .board-container { max-width: 1200px; margin: 0 auto; border: 4px solid #334155; border-radius: 10px; padding: 10px; background: #1e293b; }
        .header { text-align: center; font-size: 2.5rem; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 5px; color: #38bdf8; text-shadow: 0 0 10px #38bdf8; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #475569; color: #94a3b8; font-size: 1.2rem; }
        td { padding: 15px; border-bottom: 1px solid #334155; font-size: 1.5rem; }
        
        .status-boarding { color: #22c55e; animation: blink 1s infinite; }
        .status-enroute { color: #fbbf24; }
        .status-landed { color: #ef4444; }
        
        @keyframes blink { 50% { opacity: 0.5; } }
        
        .no-flights { text-align: center; padding: 50px; color: #64748b; font-size: 1.5rem; }
    </style>
    <meta http-equiv="refresh" content="60"> </head>
<body>

<div class="board-container">
    <div class="header">✈️ Partidas & Operações ✈️</div>
    
    <table>
        <thead>
            <tr>
                <th>Voo</th>
                <th>Piloto</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Tour</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($flights) > 0): ?>
                <?php foreach ($flights as $f): ?>
                    <?php 
                        $statusClass = 'status-enroute';
                        $statusText = 'EM ROTA';
                        
                        if ($f['state'] == 'Pre-Flight') {
                            $statusClass = 'status-boarding';
                            $statusText = 'EMBARQUE';
                        } elseif ($f['arrival_checks'] > 0) {
                            $statusClass = 'status-landed';
                            $statusText = 'ATERRIZADO';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($f['callsign']) ?></td>
                        <td><?= htmlspecialchars($f['pilot_name']) ?></td>
                        <td><?= htmlspecialchars($f['dep_icao']) ?></td>
                        <td><?= htmlspecialchars($f['arr_icao']) ?></td>
                        <td><?= htmlspecialchars($f['tour_title']) ?></td>
                        <td class="<?= $statusClass ?>"><?= $statusText ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-flights">NENHUM VOO ATIVO NO MOMENTO</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>