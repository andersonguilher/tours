<?php
// pilots/index.php
// DASHBOARD REDESIGN (Visual Moderno + Correção Definitiva de Rank)

define('BASE_PATH', dirname(__DIR__));

// --- 1. CARREGAMENTO WP & SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }

if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}
$current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : (object)['ID' => 0, 'display_name' => 'Visitante'];
$pilot_id = $current_user->ID;

// --- 2. BANCO DE DADOS PRINCIPAL (TOURS) ---
$dbPath = __DIR__ . '/../config/db.php';
if (file_exists($dbPath)) require_once $dbPath;

// --- 3. SISTEMA DE RANK ---
$rankFile = __DIR__ . '/../includes/RankSystem.php';
if (file_exists($rankFile)) require_once $rankFile;

// --- 4. DADOS PESSOAIS (CONEXÃO SECUNDÁRIA) ---
$pdoPilots = null;
try {
    $host_p = defined('DB_PILOTOS_HOST') ? DB_PILOTOS_HOST : 'localhost'; 
    $user_p = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
    $pass_p = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
    $name_p = 'u378005298_hEatD'; // Nome do banco de pilotos
    
    $pdoPilots = new PDO("mysql:host=$host_p;dbname=$name_p;charset=utf8mb4", $user_p, $pass_p);
    $pdoPilots->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Falha silenciosa se não conectar ao banco secundário
}

$pilotData = [
    'name' => strtoupper($current_user->display_name),
    'matricula' => 'KFY0000',
    'foto' => "https://ui-avatars.com/api/?name=" . urlencode($current_user->display_name) . "&background=0f172a&color=fff&size=200"
];

if ($pdoPilots) {
    $stmt = $pdoPilots->prepare("SELECT first_name, last_name, matricula, foto_perfil FROM Dados_dos_Pilotos WHERE id_piloto = ? LIMIT 1");
    $stmt->execute([$pilot_id]);
    $res = $stmt->fetch();
    if ($res) {
        $pilotData['name'] = strtoupper(trim(($res['first_name']??'') . ' ' . ($res['last_name']??'')));
        if (!empty($res['matricula'])) $pilotData['matricula'] = $res['matricula'];
        if (!empty($res['foto_perfil'])) $pilotData['foto'] = $res['foto_perfil'];
    }
}

// --- 5. STATS & RANK (LÓGICA CORRIGIDA) ---
$stats = ['flights' => 0, 'hours' => 0, 'last_loc' => 'SBGL'];
$rankData = ['title' => 'Aluno', 'stripes' => 1, 'has_star' => 0];
$progress = 0;

if (isset($pdo)) {
    try {
        // Stats Gerais
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(duration_minutes) as mins FROM tour_history WHERE pilot_id = ?");
        $stmt->execute([$pilot_id]);
        $row = $stmt->fetch();
        
        $totalMinutes = $row['mins'] ?? 0;
        $stats['flights'] = $row['total'] ?? 0;
        $stats['hours'] = floor($totalMinutes / 60);

        // Última Localização
        $stmtLoc = $pdo->prepare("SELECT current_location FROM tour_pilots WHERE pilot_id = ?");
        $stmtLoc->execute([$pilot_id]);
        $loc = $stmtLoc->fetchColumn();
        if ($loc) $stats['last_loc'] = $loc;

        // Rank - PASSANDO A CONEXÃO $pdo EXPLÍCITAMENTE
        if (class_exists('RankSystem')) {
            $rankData = RankSystem::getRank($totalMinutes, $pdo);
            $progress = RankSystem::getNextRankProgress($totalMinutes, $pdo);
        }
    } catch (Exception $e) {}
}

// --- 6. DADOS GERAIS (Tours, Histórico) ---
$tours = []; $recentFlights = []; $progresso = [];
if (isset($pdo)) {
    try {
        $tours = $pdo->query("SELECT * FROM tour_tours WHERE status = 1 ORDER BY start_date DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        
        $recentFlights = $pdo->query("SELECT * FROM tour_history WHERE pilot_id = $pilot_id ORDER BY date_flown DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        $stmtProg = $pdo->prepare("SELECT tour_id, status FROM tour_progress WHERE pilot_id = ?");
        $stmtProg->execute([$pilot_id]);
        while ($row = $stmtProg->fetch()) $progresso[$row['tour_id']] = $row['status'];
    } catch (Exception $e) {}
}

function formatarData($date) { return $date ? date("d/m", strtotime($date)) : "--/--"; }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cockpit - <?php echo htmlspecialchars($pilotData['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0b0f19; }
        .glass-panel { background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(148, 163, 184, 0.1); }
        .stripe-gradient { background: linear-gradient(90deg, #ca8a04 0%, #facc15 50%, #ca8a04 100%); }
        /* Animação suave da barra */
        .progress-bar { transition: width 1.5s ease-in-out; }
    </style>
</head>
<body class="text-slate-300 min-h-screen">

    <?php 
    $navPath = __DIR__ . '/../includes/navbar.php';
    if(file_exists($navPath)) include $navPath; 
    ?>

    <div class="max-w-7xl mx-auto p-4 lg:p-8 space-y-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-4 space-y-6">
                
                <div class="glass-panel rounded-2xl p-6 relative overflow-hidden shadow-2xl group">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-cyan-400"></div>
                    
                    <div class="flex flex-col items-center text-center">
                        <div class="relative mb-4">
                            <img src="<?php echo $pilotData['foto']; ?>" class="w-24 h-24 rounded-full border-2 border-slate-700 shadow-lg object-cover">
                            <div class="absolute bottom-1 right-1 w-4 h-4 bg-green-500 border-2 border-slate-900 rounded-full" title="Online no Sistema"></div>
                        </div>
                        
                        <h2 class="text-lg font-bold text-white"><?php echo $pilotData['name']; ?></h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-slate-400 font-mono bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><?php echo $pilotData['matricula']; ?></span>
                            <span class="text-xs text-blue-400 font-bold"><i class="fa-solid fa-location-dot"></i> <?php echo $stats['last_loc']; ?></span>
                        </div>

                        <div class="mt-6 w-full bg-slate-950/50 rounded-xl p-4 border border-slate-800 flex items-center gap-4">
                            <div class="relative w-12 h-14 bg-slate-900 border border-slate-700 rounded-t shadow-lg flex flex-col justify-end items-center pb-1 shrink-0">
                                <div class="absolute inset-0 opacity-30 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                                <div class="absolute top-1 w-1.5 h-1.5 rounded-full bg-yellow-500 shadow border border-yellow-700 z-10"></div>
                                
                                <?php if($rankData['has_star']): ?>
                                    <i class="fa-solid fa-star text-[8px] text-yellow-400 absolute top-3 z-10 animate-pulse"></i>
                                <?php endif; ?>
                                
                                <div class="flex flex-col gap-0.5 w-full px-0.5 z-10">
                                    <?php for($i=0; $i<$rankData['stripes']; $i++): ?>
                                        <div class="h-1.5 w-full stripe-gradient rounded-[1px] shadow-sm"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="text-left flex-1">
                                <div class="text-[10px] uppercase text-slate-500 font-bold tracking-wider">Patente Atual</div>
                                <div class="text-sm font-bold text-white"><?php echo $rankData['title']; ?></div>
                                
                                <div class="w-full bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden border border-slate-700/50">
                                    <div class="bg-blue-500 h-full progress-bar shadow-[0_0_10px_rgba(59,130,246,0.5)]" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="text-[9px] text-right text-blue-400 mt-0.5 font-bold"><?php echo $progress; ?>% Próx. Nível</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="glass-panel p-4 rounded-xl text-center hover:bg-slate-800 transition cursor-default">
                        <div class="text-2xl font-bold text-white"><?php echo $stats['hours']; ?>h</div>
                        <div class="text-[10px] uppercase text-slate-500 font-bold flex justify-center items-center gap-1">
                            <i class="fa-regular fa-clock"></i> Horas
                        </div>
                    </div>
                    <div class="glass-panel p-4 rounded-xl text-center hover:bg-slate-800 transition cursor-default">
                        <div class="text-2xl font-bold text-white"><?php echo $stats['flights']; ?></div>
                        <div class="text-[10px] uppercase text-slate-500 font-bold flex justify-center items-center gap-1">
                            <i class="fa-solid fa-plane"></i> Voos
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-6">
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="live_board.php" class="bg-slate-800 p-4 rounded-xl border border-slate-700 hover:border-blue-500 transition shadow-lg group relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-3 opacity-5 text-6xl group-hover:scale-110 transition"><i class="fa-solid fa-radar"></i></div>
                        <i class="fa-solid fa-tower-broadcast text-blue-500 text-2xl mb-2 group-hover:animate-pulse"></i>
                        <h3 class="font-bold text-white">Live Ops</h3>
                        <p class="text-xs text-slate-500 mt-1">Tráfego em tempo real</p>
                    </a>
                    
                    <a href="passport_book.php?pilot_id=<?php echo $pilot_id; ?>" class="bg-slate-800 p-4 rounded-xl border border-slate-700 hover:border-amber-500 transition shadow-lg group relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-3 opacity-5 text-6xl group-hover:scale-110 transition"><i class="fa-solid fa-passport"></i></div>
                        <i class="fa-solid fa-book-atlas text-amber-500 text-2xl mb-2 group-hover:-rotate-12 transition"></i>
                        <h3 class="font-bold text-white">Passaporte</h3>
                        <p class="text-xs text-slate-500 mt-1">Carimbos e histórico</p>
                    </a>
                    
                    <a href="rankings.php" class="bg-slate-800 p-4 rounded-xl border border-slate-700 hover:border-yellow-500 transition shadow-lg group relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-3 opacity-5 text-6xl group-hover:scale-110 transition"><i class="fa-solid fa-trophy"></i></div>
                        <i class="fa-solid fa-medal text-yellow-500 text-2xl mb-2 group-hover:-translate-y-1 transition"></i>
                        <h3 class="font-bold text-white">Rankings</h3>
                        <p class="text-xs text-slate-500 mt-1">Top pilotos da cia</p>
                    </a>
                </div>

                <div class="glass-panel rounded-xl p-5">
                    <h3 class="font-bold text-white mb-4 text-xs uppercase tracking-wider border-b border-slate-700/50 pb-2">
                        <i class="fa-solid fa-list-ul text-slate-500 mr-2"></i> Últimos Registros
                    </h3>
                    <div class="space-y-2">
                        <?php if(empty($recentFlights)): ?>
                            <div class="text-center text-slate-600 text-sm py-4 italic">Nenhum voo registrado ainda.</div>
                        <?php else: ?>
                            <?php foreach($recentFlights as $rf): ?>
                            <div class="flex justify-between items-center bg-slate-800/50 p-3 rounded-lg border border-slate-700/50 hover:bg-slate-800 transition group">
                                <div class="flex gap-3 text-sm font-mono font-bold text-blue-400 group-hover:text-blue-300">
                                    <?php echo $rf['dep_icao']; ?> <i class="fa-solid fa-arrow-right text-slate-600 text-xs mt-1"></i> <?php echo $rf['arr_icao']; ?>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-slate-300 font-bold"><?php echo $rf['aircraft']; ?></div>
                                    <div class="text-[10px] text-slate-500"><?php echo date('d/m/Y', strtotime($rf['date_flown'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-bold text-white mb-4 pl-2 border-l-4 border-blue-500 text-lg">Missões Disponíveis</h3>
                    
                    <?php if(empty($tours)): ?>
                        <div class="p-8 border-2 border-dashed border-slate-800 rounded-xl text-center text-slate-500">
                            Nenhuma missão ativa no momento.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach($tours as $tour): 
                                $st = $progresso[$tour['id']] ?? 'New';
                                // Cores baseadas no status
                                $color = 'blue';
                                $statusLabel = 'Nova';
                                $icon = 'fa-circle-play';

                                if ($st == 'In Progress') { $color = 'yellow'; $statusLabel = 'Em Andamento'; $icon = 'fa-spinner fa-spin'; }
                                if ($st == 'Completed') { $color = 'green'; $statusLabel = 'Concluída'; $icon = 'fa-check'; }
                                
                                $isClosed = ($tour['end_date'] && $today > $tour['end_date']);
                                if ($isClosed && $st != 'Completed') { $color = 'red'; $statusLabel = 'Encerrada'; $icon = 'fa-lock'; }
                            ?>
                            <div class="bg-slate-800 rounded-xl overflow-hidden border border-slate-700 hover:border-<?php echo $color; ?>-500/50 transition relative group shadow-lg flex flex-col h-full">
                                <div class="h-32 bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                                    <div class="absolute inset-0 bg-slate-900/60 group-hover:bg-slate-900/40 transition"></div>
                                    <div class="absolute top-2 right-2 bg-black/60 text-<?php echo $color; ?>-400 text-[10px] font-bold px-2 py-1 rounded backdrop-blur border border-<?php echo $color; ?>-500/30 uppercase flex items-center gap-1">
                                        <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $statusLabel; ?>
                                    </div>
                                </div>
                                
                                <div class="p-4 flex flex-col flex-grow">
                                    <h4 class="font-bold text-white text-lg mb-1 leading-tight"><?php echo $tour['title']; ?></h4>
                                    <div class="text-xs text-slate-400 mb-4 flex items-center gap-2">
                                        <i class="fa-regular fa-calendar"></i> Até <?php echo formatarData($tour['end_date']); ?>
                                    </div>
                                    
                                    <div class="mt-auto pt-3 border-t border-slate-700/50">
                                        <a href="view_tour.php?id=<?php echo $tour['id']; ?>" class="flex items-center justify-between text-sm font-bold text-<?php echo $color; ?>-400 hover:text-<?php echo $color; ?>-300 transition">
                                            <?php echo ($st == 'Completed') ? 'Ver Certificado' : 'Acessar Briefing'; ?> 
                                            <i class="fa-solid fa-arrow-right-long"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center py-6 text-slate-700 text-xs border-t border-slate-800/50 mt-8">
        Kafly Virtual Airline Systems &copy; <?php echo date('Y'); ?>
    </div>

</body>
</html>