<?php
// pilots/index.php
// VITRINE DE TOURS - Integração WP + Dual DB + Datas de Vigência

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

// 3. CONEXÃO DUPLA
require '../config/db.php'; 

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
} catch (Exception $e) {
    // Mantém fallback
}

// 4. BUSCA TOURS
try {
    // Busca tours ativos (status=1)
    // Vamos filtrar as datas na exibição ou aqui, preferencialmente na exibição para mostrar "Em Breve"
    $stmt = $pdo->query("SELECT * FROM tours WHERE status = 1 ORDER BY start_date DESC, id DESC");
    $tours = $stmt->fetchAll();

    $progresso = [];
    $stmtProg = $pdo->prepare("SELECT tour_id, status FROM pilot_tour_progress WHERE pilot_id = ?");
    $stmtProg->execute([$wp_user_id]);
    while ($row = $stmtProg->fetch()) {
        $progresso[$row['tour_id']] = $row['status'];
    }
} catch (PDOException $e) {
    die("Erro ao carregar tours: " . $e->getMessage());
}

// Helper de Data
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
    <style>
        body { background-color: #0f172a; } 
    </style>
</head>
<body class="text-white font-sans min-h-screen">

    <nav class="h-16 bg-slate-950 border-b border-slate-800 flex justify-between items-center px-6 sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-earth-americas text-blue-500 text-xl"></i>
            <span class="font-bold text-lg tracking-widest">SKY<span class="text-blue-500">TOURS</span></span>
        </div>
        
        <div class="flex items-center gap-4 text-sm">
            <div class="text-right hidden sm:block">
                <div class="text-[10px] text-slate-500 uppercase">Comandante</div>
                <div class="font-bold font-mono text-yellow-400"><?php echo $display_callsign; ?></div>
            </div>
            <a href="../../" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair</a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-12">
        
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4 bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">
                Missões & Eventos
            </h1>
            <p class="text-slate-400 max-w-2xl mx-auto">
                Selecione um tour para iniciar sua jornada. Fique atento às datas de vigência.
            </p>
        </div>

        <?php if (count($tours) == 0): ?>
            <div class="text-center py-20 border-2 border-dashed border-slate-800 rounded-2xl">
                <i class="fa-solid fa-plane-slash text-6xl text-slate-700 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-500">Nenhum tour disponível no momento.</h3>
            </div>
        <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($tours as $tour): ?>
                <?php 
                    $userStatus = $progresso[$tour['id']] ?? 'New';
                    
                    // --- LÓGICA DE DATAS E STATUS ---
                    $isUpcoming = ($tour['start_date'] && $today < $tour['start_date']);
                    $isClosed   = ($tour['end_date'] && $today > $tour['end_date']);
                    
                    // Padrão (Aberto/Novo)
                    $badgeClass = "bg-blue-600";
                    $badgeText = "NOVO";
                    $btnText = "Iniciar Tour";
                    $btnClass = "bg-blue-600 hover:bg-blue-500 shadow-blue-900/20";
                    $borderClass = "border-slate-700";
                    $btnDisabled = false;
                    $overlayIcon = "";

                    // Lógica de Estado do Usuário
                    if ($userStatus == 'In Progress') {
                        $badgeClass = "bg-yellow-500 text-yellow-950";
                        $badgeText = "EM ANDAMENTO";
                        $btnText = "Continuar";
                        $btnClass = "bg-yellow-500 hover:bg-yellow-400 text-yellow-950 shadow-yellow-900/20";
                        $borderClass = "border-yellow-500/50";
                    } elseif ($userStatus == 'Completed') {
                        $badgeClass = "bg-green-500 text-green-950";
                        $badgeText = "CONCLUÍDO";
                        $btnText = "Ver Conquista";
                        $btnClass = "bg-green-600 hover:bg-green-500 shadow-green-900/20";
                        $borderClass = "border-green-500/50";
                    }

                    // Lógica de Datas (Sobrescreve se necessário)
                    if ($isUpcoming) {
                        $badgeClass = "bg-slate-600";
                        $badgeText = "EM BREVE";
                        $btnText = "Disponível em " . formatarData($tour['start_date']);
                        $btnClass = "bg-slate-700 text-slate-400 cursor-not-allowed";
                        $btnDisabled = true;
                        $overlayIcon = "<div class='absolute inset-0 bg-black/50 z-20 flex items-center justify-center'><i class='fa-solid fa-clock text-4xl text-white/50'></i></div>";
                    } elseif ($isClosed) {
                        $badgeClass = "bg-red-600";
                        $badgeText = "ENCERRADO";
                        // Se completou, deixa ver, se não, bloqueia
                        if ($userStatus != 'Completed') {
                            $btnText = "Evento Encerrado";
                            $btnClass = "bg-red-900/50 text-red-400 cursor-not-allowed border border-red-900";
                            $btnDisabled = true;
                        } else {
                            $btnText = "Ver Histórico"; // Permite ver se já completou
                        }
                    }
                ?>

                <div class="group bg-slate-800 rounded-2xl overflow-hidden shadow-xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border <?php echo $borderClass; ?> flex flex-col h-full relative">
                    
                    <div class="h-48 bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($tour['banner_url']); ?>');">
                        <?php echo $overlayIcon; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent opacity-90"></div>
                        
                        <div class="absolute top-4 left-4">
                            <span class="bg-black/60 backdrop-blur text-white text-[10px] font-bold px-2 py-1 rounded border border-white/10 uppercase">
                                <?php echo $tour['difficulty']; ?>
                            </span>
                        </div>

                        <div class="absolute top-4 right-4">
                            <span class="<?php echo $badgeClass; ?> text-[10px] font-bold px-2 py-1 rounded shadow uppercase">
                                <?php echo $badgeText; ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-6 flex-grow flex flex-col -mt-12 relative z-10">
                        <h3 class="text-xl font-bold mb-2 text-white group-hover:text-blue-400 transition leading-tight">
                            <?php echo htmlspecialchars($tour['title']); ?>
                        </h3>
                        
                        <div class="flex items-center gap-4 text-[10px] text-slate-400 mb-4 font-mono uppercase">
                            <div title="Início">
                                <i class="fa-regular fa-calendar-plus text-blue-500"></i> 
                                <?php echo formatarData($tour['start_date']); ?>
                            </div>
                            <div title="Término">
                                <i class="fa-regular fa-calendar-xmark text-red-500"></i> 
                                <?php echo formatarData($tour['end_date']); ?>
                            </div>
                        </div>

                        <div class="text-sm text-slate-400 mb-6 line-clamp-3 leading-relaxed">
                            <?php echo strip_tags($tour['description']); ?>
                        </div>

                        <div class="mt-auto">
                            <?php if($btnDisabled): ?>
                                <button disabled class="block w-full text-center <?php echo $btnClass; ?> font-bold py-3 rounded-xl transition shadow-lg flex items-center justify-center gap-2">
                                    <?php echo $btnText; ?>
                                </button>
                            <?php else: ?>
                                <a href="view_tour.php?id=<?php echo $tour['id']; ?>" class="block w-full text-center <?php echo $btnClass; ?> text-white font-bold py-3 rounded-xl transition shadow-lg flex items-center justify-center gap-2">
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

</body>
</html>