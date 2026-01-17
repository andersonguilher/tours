<?php
// pilots/index.php
// DASHBOARD SEGURO (Com tratamento de erros)

// Define caminhos absolutos para evitar erros de inclusão
define('BASE_PATH', dirname(__DIR__));

// 1. CARREGAR WORDPRESS (Segurança)
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { 
    require_once $wpLoadPath; 
} else {
    // Se não achar o WP, tenta carregar apenas o DB para teste local (opcional)
    // die("Erro Crítico: WordPress não encontrado em $wpLoadPath");
}

if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

// Inicializa variáveis para evitar "Undefined Variable"
$current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : (object)['ID' => 0, 'display_name' => 'Visitante'];
$pilot_id = $current_user->ID;

// 2. CONFIGURAÇÃO E DB
$dbPath = __DIR__ . '/../config/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    die("Erro: Arquivo de configuração do banco de dados não encontrado em ../config/db.php");
}

// 3. CARREGAR SISTEMA DE RANK (Com verificação)
$rankFile = __DIR__ . '/../includes/RankSystem.php';
if (file_exists($rankFile)) {
    require_once $rankFile;
}

// 4. CONEXÃO DB PILOTOS (Dados Pessoais)
$pdoPilots = null;
try {
    $host_p = defined('DB_PILOTOS_HOST') ? DB_PILOTOS_HOST : 'localhost'; 
    $user_p = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
    $pass_p = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
    $name_p = 'u378005298_hEatD'; 

    $pdoPilots = new PDO("mysql:host=$host_p;dbname=$name_p;charset=utf8mb4", $user_p, $pass_p);
    $pdoPilots->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoPilots->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Erro silencioso na conexão secundária
}

// 5. RECUPERAR DADOS DO PILOTO
$pilotData = [
    'name' => isset($current_user->display_name) ? strtoupper($current_user->display_name) : 'PILOTO',
    'matricula' => 'KFY0000',
    'foto' => "https://ui-avatars.com/api/?name=Pilot&background=0f172a&color=fff&size=200"
];

if ($pdoPilots && $pilot_id > 0) {
    try {
        $stmt = $pdoPilots->prepare("SELECT first_name, last_name, matricula, foto_perfil FROM Dados_dos_Pilotos WHERE id_piloto = ? LIMIT 1");
        $stmt->execute([$pilot_id]);
        $res = $stmt->fetch();
        if ($res) {
            $pilotData['name'] = strtoupper(trim(($res['first_name']??'') . ' ' . ($res['last_name']??'')));
            if (!empty($res['matricula'])) $pilotData['matricula'] = $res['matricula'];
            if (!empty($res['foto_perfil'])) $pilotData['foto'] = $res['foto_perfil'];
        }
    } catch (Exception $e) {}
}

// 6. ESTATÍSTICAS E RANKING
$stats = ['flights' => 0, 'hours' => 0, 'last_loc' => 'SBGL'];
$rankData = ['title' => 'Piloto', 'stripes' => 1, 'has_star' => 0, 'total_hours' => 0];
$progress = 0;

if (isset($pdo)) {
    try {
        // Totais
        $stmtStats = $pdo->prepare("SELECT COUNT(*) as total_flights, SUM(duration_minutes) as total_minutes FROM tour_history WHERE pilot_id = ?");
        $stmtStats->execute([$pilot_id]);
        $row = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        $total_minutes = $row['total_minutes'] ?? 0;
        $stats['flights'] = $row['total_flights'] ?? 0;
        $stats['hours'] = floor($total_minutes / 60);

        // Última Localização
        $stmtLoc = $pdo->prepare("SELECT current_location FROM tour_pilots WHERE pilot_id = ?");
        $stmtLoc->execute([$pilot_id]);
        $loc = $stmtLoc->fetchColumn();
        if ($loc) $stats['last_loc'] = $loc;

        // Patente (Verifica se a classe existe antes de chamar)
        if (class_exists('RankSystem')) {
            $rankData = RankSystem::getRank($total_minutes);
            $progress = RankSystem::getNextRankProgress($total_minutes);
        }

    } catch (Exception $e) {
        // Erro no DB principal
    }
}

// 7. LISTA DE TOURS
$tours = [];
$progresso = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM tour_tours WHERE status = 1 ORDER BY start_date DESC, id DESC");
        $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtProg = $pdo->prepare("SELECT tour_id, status FROM tour_progress WHERE pilot_id = ?");
        $stmtProg->execute([$pilot_id]);
        while ($row = $stmtProg->fetch(PDO::FETCH_ASSOC)) {
            $progresso[$row['tour_id']] = $row['status'];
        }
    } catch (Exception $e) {}
}

// 8. ÚLTIMOS VOOS
$recentFlights = [];
if (isset($pdo)) {
    try {
        $stmtHist = $pdo->prepare("SELECT * FROM tour_history WHERE pilot_id = ? ORDER BY date_flown DESC LIMIT 5");
        $stmtHist->execute([$pilot_id]);
        $recentFlights = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Helper de Data
function formatarData($date) { return $date ? date("d/m/Y", strtotime($date)) : "Indefinido"; }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($pilotData['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; font-family: sans-serif; }
        .progress-bar { transition: width 1.5s ease-in-out; }
    </style>
</head>
<body class="text-white min-h-screen flex flex-col">

    <?php 
    $navPath = __DIR__ . '/../includes/navbar.php';
    if(file_exists($navPath)) { include $navPath; } 
    ?>

    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <div class="bg-slate-800 rounded-2xl p-6 shadow-2xl border border-slate-700 mb-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500 opacity-5 rounded-full blur-3xl -mr-16 -mt-16"></div>
            
            <div class="flex flex-col md:flex-row items-center gap-8 relative z-10">
                <div class="relative group">
                    <img src="<?php echo htmlspecialchars($pilotData['foto']); ?>" class="relative w-28 h-28 rounded-full object-cover border-4 border-slate-800 shadow-xl">
                </div>

                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-3xl font-bold tracking-tight mb-1 flex items-center justify-center md:justify-start gap-2">
                        <?php echo htmlspecialchars($pilotData['name']); ?>
                        <?php if(!empty($rankData['has_star'])): ?><i class="fa-solid fa-certificate text-yellow-500 text-sm"></i><?php endif; ?>
                    </h1>
                    <div class="flex items-center justify-center md:justify-start gap-4 text-sm font-mono text-slate-400">
                        <span class="bg-slate-900 px-2 py-1 rounded border border-slate-700 text-blue-400"><?php echo htmlspecialchars($pilotData['matricula']); ?></span>
                        <span><i class="fa-solid fa-location-dot text-red-500"></i> <?php echo htmlspecialchars($stats['last_loc']); ?></span>
                    </div>
                </div>

                <div class="flex flex-col items-center">
                    <div class="relative w-20 h-24 bg-slate-900 border border-slate-700 rounded-t-lg shadow-xl flex flex-col justify-end items-center pb-2 overflow-hidden">
                        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        <div class="absolute top-2 w-3 h-3 rounded-full bg-yellow-500 shadow-lg border border-yellow-700 z-20"></div>
                        
                        <?php if (!empty($rankData['has_star'])): ?>
                            <div class="mb-1 z-10 text-yellow-400 drop-shadow-md animate-pulse"><i class="fa-solid fa-star text-sm"></i></div>
                        <?php endif; ?>
                        
                        <div class="flex flex-col gap-1.5 w-full px-1 z-10">
                            <?php 
                            $stripes = $rankData['stripes'] ?? 1;
                            for($i=0; $i < $stripes; $i++): ?>
                                <div class="h-2.5 w-full bg-gradient-to-r from-yellow-600 via-yellow-300 to-yellow-600 shadow-sm rounded-sm"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <span class="mt-2 text-xs font-bold uppercase tracking-wider text-slate-400"><?php echo htmlspecialchars($rankData['title']); ?></span>
                </div>
            </div>

            <div class="mt-8">
                <div class="flex justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <span>Progresso de Carreira</span>
                    <span><?php echo $progress; ?>%</span>
                </div>
                <div class="w-full bg-slate-900 rounded-full h-2.5 border border-slate-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-cyan-400 h-2.5 rounded-full progress-bar" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 h-fit">
                <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 shadow flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400"><i class="fa-regular fa-clock text-2xl"></i></div>
                    <div>
                        <div class="text-3xl font-bold"><?php echo number_format($stats['hours']); ?>h</div>
                        <div class="text-xs text-slate-400 uppercase font-bold">Horas Totais</div>
                    </div>
                </div>
                <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 shadow flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-plane-departure text-2xl"></i></div>
                    <div>
                        <div class="text-3xl font-bold"><?php echo number_format($stats['flights']); ?></div>
                        <div class="text-xs text-slate-400 uppercase font-bold">Missões</div>
                    </div>
                </div>

                <div class="sm:col-span-2 flex gap-4 flex-wrap sm:flex-nowrap mt-2">
                    <a href="live_board.php" target="_blank" class="flex-1 group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl shadow-lg flex flex-col items-center justify-center gap-2 h-[100px] transition">
                        <i class="fa-solid fa-tower-broadcast text-2xl text-green-500 group-hover:animate-pulse"></i>
                        <span class="text-xs font-bold uppercase text-center">Ao Vivo</span>
                    </a>
                    <a href="passport_book.php?pilot_id=<?php echo $pilot_id; ?>" class="flex-1 group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl shadow-lg flex flex-col items-center justify-center gap-2 h-[100px] transition">
                        <i class="fa-solid fa-book-atlas text-2xl text-amber-500 group-hover:scale-110"></i>
                        <span class="text-xs font-bold uppercase text-center">Passaporte</span>
                    </a>
                    <a href="rankings.php" class="flex-1 group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl shadow-lg flex flex-col items-center justify-center gap-2 h-[100px] transition">
                        <i class="fa-solid fa-trophy text-2xl text-yellow-500 group-hover:-translate-y-1"></i>
                        <span class="text-xs font-bold uppercase text-center">Rankings</span>
                    </a>
                </div>
            </div>

            <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-lg p-5 h-fit">
                <h3 class="font-bold text-white mb-4 flex items-center gap-2 text-sm uppercase tracking-wider">
                    <i class="fa-solid fa-history text-slate-500"></i> Logbook Recente
                </h3>
                <div class="space-y-3">
                    <?php if (empty($recentFlights)): ?>
                        <div class="text-center py-6 text-slate-500 text-sm">Nenhum voo registrado.</div>
                    <?php else: ?>
                        <?php foreach($recentFlights as $rf): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded-lg border border-slate-700/50">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-blue-400 text-sm"><?php echo htmlspecialchars($rf['dep_icao']); ?></span>
                                <i class="fa-solid fa-arrow-right text-slate-600 text-xs"></i>
                                <span class="font-mono font-bold text-blue-400 text-sm"><?php echo htmlspecialchars($rf['arr_icao']); ?></span>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-bold text-slate-300"><?php echo htmlspecialchars($rf['aircraft']); ?></div>
                                <div class="text-[10px] text-slate-500"><?php echo date('d/m', strtotime($rf['date_flown'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mb-12">
            <h2 class="text-2xl font-bold mb-6 border-l-4 border-blue-500 pl-4">Missões Disponíveis</h2>
            
            <?php if (empty($tours)): ?>
                <div class="text-center py-12 border-2 border-dashed border-slate-800 rounded-xl bg-slate-900/50 text-slate-500">
                    Nenhuma missão ativa no momento.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($tours as $tour): 
                        $userStatus = $progresso[$tour['id']] ?? 'New';
                        $isClosed = ($tour['end_date'] && $today > $tour['end_date']);
                        
                        $badge = ['cls'=>'bg-blue-600', 'txt'=>'NOVO'];
                        $btn = ['cls'=>'bg-blue-600 hover:bg-blue-500', 'txt'=>'Iniciar Briefing'];
                        
                        if ($userStatus == 'In Progress') { 
                            $badge = ['cls'=>'bg-yellow-500 text-yellow-950', 'txt'=>'EM ANDAMENTO'];
                            $btn = ['cls'=>'bg-yellow-500 hover:bg-yellow-400 text-yellow-950', 'txt'=>'Continuar'];
                        } elseif ($userStatus == 'Completed') {
                            $badge = ['cls'=>'bg-green-500 text-green-950', 'txt'=>'CONCLUÍDO'];
                            $btn = ['cls'=>'bg-green-600 hover:bg-green-500', 'txt'=>'Certificado'];
                        } elseif ($isClosed) {
                            $badge = ['cls'=>'bg-red-600', 'txt'=>'ENCERRADO'];
                            $btn = ['cls'=>'bg-slate-700 cursor-not-allowed', 'txt'=>'Fechado'];
                        }
                    ?>
                    <div class="group bg-slate-800 rounded-xl overflow-hidden shadow-lg hover:-translate-y-1 transition border border-slate-700 flex flex-col">
                        <div class="h-40 bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                            <div class="absolute inset-0 bg-black/40 group-hover:bg-transparent transition"></div>
                            <span class="absolute top-2 right-2 <?php echo $badge['cls']; ?> text-[10px] font-bold px-2 py-1 rounded shadow"><?php echo $badge['txt']; ?></span>
                        </div>
                        <div class="p-5 flex-col flex flex-grow">
                            <h3 class="font-bold text-lg mb-2 text-white"><?php echo htmlspecialchars($tour['title']); ?></h3>
                            <div class="text-xs text-slate-400 mb-4 flex gap-2">
                                <i class="fa-regular fa-calendar"></i> <?php echo formatarData($tour['end_date']); ?>
                            </div>
                            <div class="mt-auto">
                                <a href="<?php echo $isClosed ? '#' : 'view_tour.php?id='.$tour['id']; ?>" class="block w-full text-center <?php echo $btn['cls']; ?> text-white font-bold py-2 rounded transition text-sm">
                                    <?php echo $btn['txt']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?php echo date('Y'); ?> Kafly Systems.
    </footer>
</body>
</html>