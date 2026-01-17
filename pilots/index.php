<?php
// pilots/index.php
// DASHBOARD DO PILOTO: Tours + Patentes + Live Ops + Passaporte

// 1. CARREGAR WORDPRESS
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; } else { die("Erro: WP não encontrado."); }

if (!is_user_logged_in()) {
    $loginUrl = wp_login_url('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    echo "<script>window.location.href='$loginUrl';</script>";
    exit;
}

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

// 2. CONFIGURAÇÕES & DB
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

// 3. RECUPERAR CALLSIGN (Matrícula)
$display_callsign = strtoupper($current_user->user_login); 
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
    if ($res) {
        $display_callsign = strtoupper($res[$col_matricula]);
    }
} catch (Exception $e) { /* Fallback silencioso */ }

// 4. LÓGICA DE RANKING E ESTATÍSTICAS
// Calcula horas totais voadas (Soma de tour_history)
$total_minutes = 0;
try {
    // Verifica se a coluna flight_time_minutes existe antes de consultar (Evita erro fatal)
    // Assumindo que você já rodou o ALTER TABLE sugerido.
    $stmtStats = $pdo->prepare("SELECT SUM(flight_time_minutes) FROM tour_history WHERE pilot_id = ?");
    $stmtStats->execute([$wp_user_id]);
    $total_minutes = $stmtStats->fetchColumn() ?: 0;
} catch (PDOException $e) { $total_minutes = 0; }

// Carrega Sistema de Ranks
$rankFile = __DIR__ . '/../includes/RankSystem.php';
$rankData = ['title' => 'Piloto', 'img' => 'rank_1.png', 'total_hours' => 0]; // Default
$nextRankPct = 0;

if (file_exists($rankFile)) {
    require_once $rankFile;
    $rankData = RankSystem::getRank($total_minutes);
    $nextRankPct = RankSystem::getNextRankProgress($total_minutes);
}

// 5. BUSCA TOURS
try {
    $stmt = $pdo->query("SELECT * FROM tour_tours WHERE status = 1 ORDER BY start_date DESC, id DESC");
    $tours = $stmt->fetchAll();

    $progresso = [];
    $stmtProg = $pdo->prepare("SELECT tour_id, status FROM tour_progress WHERE pilot_id = ?");
    $stmtProg->execute([$wp_user_id]);
    while ($row = $stmtProg->fetch()) {
        $progresso[$row['tour_id']] = $row['status'];
    }
} catch (PDOException $e) { die("Erro DB: " . $e->getMessage()); }

function formatarData($date) {
    if (!$date) return "Indefinido";
    return date("d/m/Y", strtotime($date));
}
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Central de Tours - Kafly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style> body { background-color: #0f172a; } </style>
</head>
<body class="text-white font-sans min-h-screen flex flex-col">

    <nav class="h-16 bg-slate-950 border-b border-slate-800 flex justify-between items-center px-6 sticky top-0 z-50 shadow-lg">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-earth-americas text-blue-500 text-xl"></i>
            <span class="font-bold text-lg tracking-widest">SKY<span class="text-blue-500">TOURS</span></span>
        </div>
        <div class="flex items-center gap-6 text-sm">
            <div class="text-right hidden sm:block">
                <div class="text-[10px] text-slate-500 uppercase">Bem-vindo</div>
                <div class="font-bold font-mono text-yellow-400"><?php echo $display_callsign; ?></div>
            </div>
            <a href="../../" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
        </div>
    </nav>

    <div class="bg-slate-900 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                
                <div class="flex items-center gap-6 w-full md:w-auto">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-yellow-600 to-amber-400 rounded-full blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                        <img src="../assets/ranks/<?php echo $rankData['img']; ?>" alt="Rank" class="relative w-20 h-20 object-contain drop-shadow-xl">
                    </div>
                    
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-white uppercase tracking-wider font-mono">
                            <?php echo $rankData['title']; ?>
                        </h2>
                        <div class="text-slate-400 text-sm mb-2 flex items-center gap-2">
                            <i class="fa-solid fa-clock text-blue-500"></i> 
                            <span><?php echo number_format($rankData['total_hours'], 1); ?> Horas de Voo</span>
                        </div>
                        
                        <div class="w-full md:w-64 bg-slate-800 rounded-full h-2.5 border border-slate-700 overflow-hidden relative group cursor-help">
                            <div class="bg-gradient-to-r from-blue-600 to-cyan-400 h-2.5 rounded-full transition-all duration-1000" style="width: <?php echo $nextRankPct; ?>%"></div>
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-[9px] font-bold text-white drop-shadow-md">
                                <?php echo $nextRankPct; ?>% para promoção
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 w-full md:w-auto">
                    <a href="live_board.php" target="_blank" class="flex-1 md:flex-none group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl transition shadow-lg flex flex-col items-center justify-center gap-2 min-w-[120px]">
                        <i class="fa-solid fa-tower-broadcast text-2xl text-green-500 group-hover:animate-pulse"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Ao Vivo</span>
                    </a>

                    <a href="passport_book.php?pilot_id=<?php echo $wp_user_id; ?>" target="_blank" class="flex-1 md:flex-none group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl transition shadow-lg flex flex-col items-center justify-center gap-2 min-w-[120px]">
                        <i class="fa-solid fa-book-atlas text-2xl text-amber-500 group-hover:scale-110 transition"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Passaporte</span>
                    </a>

                    <a href="rankings.php" class="flex-1 md:flex-none group bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-6 py-4 rounded-xl transition shadow-lg flex flex-col items-center justify-center gap-2 min-w-[120px]">
                        <i class="fa-solid fa-trophy text-2xl text-yellow-500 group-hover:-translate-y-1 transition"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Rankings</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-12 flex-grow w-full">
        
        <div class="text-center mb-12">
            <h1 class="text-3xl md:text-4xl font-extrabold mb-3 text-white">
                Missões Disponíveis
            </h1>
            <p class="text-slate-400">Selecione uma operação para iniciar o briefing.</p>
        </div>

        <?php if (count($tours) == 0): ?>
            <div class="text-center py-20 border-2 border-dashed border-slate-800 rounded-2xl bg-slate-900/50">
                <i class="fa-solid fa-plane-slash text-5xl text-slate-700 mb-4"></i>
                <h3 class="text-lg font-bold text-slate-500">Nenhuma missão ativa.</h3>
            </div>
        <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($tours as $tour): ?>
                <?php 
                    $userStatus = $progresso[$tour['id']] ?? 'New';
                    $isUpcoming = ($tour['start_date'] && $today < $tour['start_date']);
                    $isClosed   = ($tour['end_date'] && $today > $tour['end_date']);
                    
                    // Configuração Visual Padrão
                    $badgeClass = "bg-blue-600"; $badgeText = "NOVO";
                    $btnText = "Iniciar Briefing"; $btnClass = "bg-blue-600 hover:bg-blue-500 shadow-blue-900/20";
                    $disabled = false;

                    if ($userStatus == 'In Progress') {
                        $badgeClass = "bg-yellow-500 text-yellow-950"; $badgeText = "EM ANDAMENTO";
                        $btnText = "Continuar Missão"; $btnClass = "bg-yellow-500 hover:bg-yellow-400 text-yellow-950";
                    } elseif ($userStatus == 'Completed') {
                        $badgeClass = "bg-green-500 text-green-950"; $badgeText = "CONCLUÍDO";
                        $btnText = "Ver Certificado"; $btnClass = "bg-green-600 hover:bg-green-500 shadow-green-900/20";
                    }

                    if ($isUpcoming) {
                        $badgeClass = "bg-slate-600"; $badgeText = "EM BREVE";
                        $btnText = "Aguarde: " . formatarData($tour['start_date']);
                        $btnClass = "bg-slate-700 text-slate-400 cursor-not-allowed"; $disabled = true;
                    } elseif ($isClosed) {
                        $badgeClass = "bg-red-600"; $badgeText = "ENCERRADO";
                        if ($userStatus != 'Completed') {
                            $btnText = "Evento Encerrado";
                            $btnClass = "bg-red-900/50 text-red-400 cursor-not-allowed"; $disabled = true;
                        }
                    }
                ?>

                <div class="group bg-slate-800 rounded-xl overflow-hidden shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 border border-slate-700 flex flex-col h-full">
                    <div class="h-44 bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-transparent opacity-90"></div>
                        <div class="absolute top-3 right-3">
                            <span class="<?php echo $badgeClass; ?> text-[10px] font-bold px-2 py-1 rounded shadow uppercase tracking-wide">
                                <?php echo $badgeText; ?>
                            </span>
                        </div>
                        <div class="absolute bottom-3 left-3">
                             <span class="bg-black/60 backdrop-blur text-white text-[10px] font-bold px-2 py-0.5 rounded border border-white/10 uppercase">
                                <?php echo $tour['difficulty']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-5 flex-grow flex flex-col">
                        <h3 class="text-lg font-bold mb-2 text-white group-hover:text-blue-400 transition">
                            <?php echo htmlspecialchars($tour['title']); ?>
                        </h3>
                        
                        <div class="flex items-center gap-4 text-[10px] text-slate-400 mb-4 font-mono uppercase">
                            <div title="Vigência">
                                <i class="fa-regular fa-calendar text-slate-500"></i> 
                                <?php echo formatarData($tour['start_date']); ?> - <?php echo formatarData($tour['end_date']); ?>
                            </div>
                        </div>

                        <div class="text-sm text-slate-400 mb-6 line-clamp-3 leading-relaxed font-light">
                            <?php echo strip_tags($tour['description']); ?>
                        </div>

                        <div class="mt-auto">
                            <?php if($disabled): ?>
                                <button disabled class="w-full <?php echo $btnClass; ?> font-bold py-3 rounded-lg transition text-sm">
                                    <?php echo $btnText; ?>
                                </button>
                            <?php else: ?>
                                <a href="view_tour.php?id=<?php echo $tour['id']; ?>" class="block w-full text-center <?php echo $btnClass; ?> text-white font-bold py-3 rounded-lg transition text-sm flex items-center justify-center gap-2">
                                    <?php echo $btnText; ?> <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?php echo date('Y'); ?> Kafly Virtual Airline Systems. All rights reserved.
    </footer>

</body>
</html>