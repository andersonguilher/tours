<?php
// pilots/passport.php - PASSAPORTE E CONQUISTAS
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; } else { die("Erro: WP não encontrado."); }
if (!is_user_logged_in()) { die('Acesso restrito.'); }

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

require '../config/db.php';

// Busca medalhas do piloto
// ATUALIZADO: tabelas tour_badges e tour_pilot_badges
$stmtBadges = $pdo->prepare("
    SELECT b.*, pb.awarded_at 
    FROM tour_badges b 
    JOIN tour_pilot_badges pb ON b.id = pb.badge_id 
    WHERE pb.pilot_id = ?
    ORDER BY pb.awarded_at DESC
");
$stmtBadges->execute([$wp_user_id]);
$myBadges = $stmtBadges->fetchAll();

// Busca estatísticas
// ATUALIZADO: tabela tour_history
$stmtStats = $pdo->prepare("
    SELECT COUNT(id) as total_legs, MIN(landing_rate) as best_landing 
    FROM tour_history WHERE pilot_id = ?
");
$stmtStats->execute([$wp_user_id]);
$stats = $stmtStats->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Passaporte - <?php echo $current_user->user_login; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-950 text-white font-sans min-h-screen">

    <nav class="h-16 bg-slate-900 border-b border-slate-800 flex justify-between items-center px-6 sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-passport text-yellow-500 text-xl"></i>
            <span class="font-bold text-lg">PASSAPORTE</span>
        </div>
        <a href="index.php" class="text-slate-400 hover:text-white transition text-sm">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
    </nav>

    <div class="max-w-4xl mx-auto px-6 py-12">
        
        <div class="bg-slate-800 rounded-2xl p-8 mb-8 flex flex-col md:flex-row items-center gap-8 border border-slate-700 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <i class="fa-solid fa-earth-americas text-9xl"></i>
            </div>
            
            <div class="w-32 h-32 bg-slate-700 rounded-full flex items-center justify-center border-4 border-yellow-500 shadow-lg shrink-0 overflow-hidden">
                <img src="<?php echo get_avatar_url($wp_user_id); ?>" class="w-full h-full object-cover">
            </div>

            <div class="text-center md:text-left z-10">
                <h1 class="text-3xl font-bold text-white mb-1"><?php echo $current_user->display_name; ?></h1>
                <p class="text-slate-400 font-mono text-sm uppercase mb-4">PILOT ID: <?php echo str_pad($wp_user_id, 4, '0', STR_PAD_LEFT); ?></p>
                
                <div class="flex gap-6 justify-center md:justify-start">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400"><?php echo $stats['total_legs']; ?></div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Voos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-400"><?php echo count($myBadges); ?></div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Medalhas</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-400"><?php echo $stats['best_landing'] ?? '-'; ?></div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Melhor Pouso</div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
            <i class="fa-solid fa-medal text-yellow-500"></i> Conquistas e Carimbos
        </h2>

        <?php if(count($myBadges) == 0): ?>
            <div class="text-center py-16 border-2 border-dashed border-slate-800 rounded-xl text-slate-500">
                <i class="fa-solid fa-box-open text-4xl mb-2"></i>
                <p>Ainda não possui conquistas. Complete seu primeiro Tour!</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach($myBadges as $badge): ?>
                <div class="bg-slate-800 p-6 rounded-xl text-center border border-yellow-500/30 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition duration-300">
                    <div class="absolute top-0 right-0 bg-yellow-500 text-slate-900 text-[9px] font-bold px-2 py-0.5">CONQUISTADO</div>
                    
                    <div class="w-20 h-20 mx-auto mb-4 relative">
                        <div class="absolute inset-0 bg-yellow-500/20 rounded-full blur-xl group-hover:bg-yellow-500/40 transition"></div>
                        <img src="<?php echo $badge['image_url']; ?>" class="w-full h-full object-contain relative z-10 drop-shadow-xl transform group-hover:scale-110 transition duration-500">
                    </div>

                    <h3 class="font-bold text-white text-sm mb-1"><?php echo $badge['title']; ?></h3>
                    <p class="text-[10px] text-slate-400 mb-3"><?php echo $badge['description']; ?></p>
                    <div class="text-[9px] font-mono text-slate-500 border-t border-slate-700 pt-2">
                        <?php echo date('d/m/Y', strtotime($badge['awarded_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>