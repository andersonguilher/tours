<?php
// pilots/rankings.php
// VERSÃO N8N: Apenas Tours Completos + Últimos Voos (Sem Landing Rate)

// 1. Carregar WordPress e DB
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in()) { die('Acesso restrito.'); }

require '../config/db.php'; 

// 2. Query: Mais Ativos (Tours Completos)
$sqlActive = "
    SELECT pilot_id, COUNT(id) as tours_concluidos
    FROM pilot_tour_progress
    WHERE status = 'Completed'
    GROUP BY pilot_id
    ORDER BY tours_concluidos DESC
    LIMIT 5
";
$topActive = $pdo->query($sqlActive)->fetchAll();

// 3. Query: Últimos Voos (Feed em Tempo Real)
// Mostra as últimas pernas validadas
$sqlLastFlights = "
    SELECT h.*, t.title as tour_title 
    FROM pilot_leg_history h
    JOIN tours t ON h.tour_id = t.id
    ORDER BY h.date_flown DESC
    LIMIT 10
";
$lastFlights = $pdo->query($sqlLastFlights)->fetchAll();

// Função para pegar nome do WP
function getPilotName($wpId) {
    $user = get_userdata($wpId);
    return $user ? $user->display_name : "Piloto #$wpId";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ranking de Pilotos - Tours</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gold { color: #fbbf24; text-shadow: 0 0 10px rgba(251, 191, 36, 0.5); }
        .silver { color: #94a3b8; text-shadow: 0 0 10px rgba(148, 163, 184, 0.5); }
        .bronze { color: #d97706; text-shadow: 0 0 10px rgba(217, 119, 6, 0.5); }
    </style>
</head>
<body class="bg-slate-900 text-white font-sans min-h-screen">

    <div class="h-16 bg-slate-950 border-b border-slate-800 flex items-center px-6 mb-8">
        <a href="index.php" class="text-slate-400 hover:text-white transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
        <div class="h-4 w-px bg-slate-700 mx-4"></div>
        <h1 class="font-bold text-xl uppercase tracking-widest">Hall da Fama</h1>
    </div>

    <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="glass-card rounded-xl p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10 pointer-events-none">
                <i class="fa-solid fa-trophy text-9xl text-yellow-500"></i>
            </div>

            <h2 class="text-2xl font-bold mb-6 flex items-center gap-3 border-b border-slate-700 pb-4">
                <i class="fa-solid fa-crown text-yellow-400"></i>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 to-orange-300">
                    Lendas dos Tours
                </span>
            </h2>

            <div class="space-y-4">
                <?php foreach($topActive as $index => $pilot): 
                    $medal = '';
                    if($index == 0) $medal = '<i class="fa-solid fa-crown gold text-xl"></i>';
                    elseif($index == 1) $medal = '<i class="fa-solid fa-medal silver text-xl"></i>';
                    elseif($index == 2) $medal = '<i class="fa-solid fa-medal bronze text-xl"></i>';
                    else $medal = '<span class="font-mono text-slate-500 font-bold text-lg">#'.($index+1).'</span>';
                ?>
                <div class="flex items-center bg-slate-800/50 p-4 rounded-lg border border-slate-700/50 hover:bg-slate-800 transition">
                    <div class="w-12 text-center mr-4"><?php echo $medal; ?></div>
                    <div class="flex-grow">
                        <div class="font-bold text-lg"><?php echo getPilotName($pilot['pilot_id']); ?></div>
                        <div class="text-xs text-slate-400">Comandante Dedicado</div>
                    </div>
                    <div class="text-right">
                        <span class="block font-bold text-2xl text-white"><?php echo $pilot['tours_concluidos']; ?></span>
                        <span class="text-[10px] text-slate-500 uppercase font-bold">Tours Completos</span>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($topActive)): ?>
                    <div class="text-center text-slate-500 py-10">Nenhum tour completado ainda. Seja o primeiro!</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-card rounded-xl p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10 pointer-events-none">
                <i class="fa-solid fa-radar text-9xl text-blue-500"></i>
            </div>
            
            <h2 class="text-2xl font-bold mb-6 flex items-center gap-3 border-b border-slate-700 pb-4">
                <i class="fa-solid fa-plane-arrival text-blue-400"></i>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-300">
                    Radar: Últimos Voos
                </span>
            </h2>

            <div class="space-y-3">
                <?php foreach($lastFlights as $flight): ?>
                <div class="flex items-center justify-between bg-slate-800/30 p-3 rounded border border-slate-700/30 hover:bg-slate-800/50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                        <div>
                            <div class="font-bold text-sm text-white"><?php echo $flight['callsign']; ?></div>
                            <div class="text-[10px] text-slate-400 truncate w-32 md:w-48"><?php echo $flight['tour_title']; ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-xs text-blue-300"><?php echo $flight['aircraft']; ?></div>
                        <div class="text-[10px] text-slate-500"><?php echo date("d/m H:i", strtotime($flight['date_flown'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($lastFlights)): ?>
                    <div class="text-center text-slate-500 py-10">Radar silencioso... nenhum voo recente.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</body>
</html>