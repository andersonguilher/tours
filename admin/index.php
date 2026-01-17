<?php
// admin/index.php
// Dashboard Administrativo - Redesign Premium
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

// 1. Contagem de Tours
$stmt = $pdo->query("SELECT * FROM tour_tours ORDER BY created_at DESC");
$tours = $stmt->fetchAll();

// 2. Estatísticas Gerais
$totalFlights = $pdo->query("SELECT COUNT(id) FROM tour_history")->fetchColumn();
$activePilots = $pdo->query("SELECT COUNT(DISTINCT pilot_id) FROM tour_progress WHERE status = 'In Progress'")->fetchColumn();
$completedTours = $pdo->query("SELECT COUNT(id) FROM tour_progress WHERE status = 'Completed'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Kafly Ops Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; }
        .card-stat { transition: all 0.3s ease; }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="text-slate-800 pb-20">

    <div class="h-2 w-full bg-gradient-to-r from-blue-600 via-blue-500 to-cyan-400"></div>

    <div class="max-w-7xl mx-auto py-10 px-6">
        
        <div class="flex flex-col md:flex-row justify-between items-end md:items-center mb-10 gap-4">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-blue-600/30">
                        <i class="fa-solid fa-tower-observation text-lg"></i>
                    </div>
                    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Painel de Controle</h1>
                </div>
                <p class="text-slate-500 font-medium ml-1">Centro de Operações de Eventos e Tours</p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <a href="manage_ranks.php" class="bg-white text-amber-600 border border-amber-200 hover:bg-amber-50 font-bold py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2 group">
                    <i class="fa-solid fa-ranking-star group-hover:scale-110 transition-transform"></i> Patentes
                </a>
                
                <a href="manage_badges.php" class="bg-white text-purple-600 border border-purple-200 hover:bg-purple-50 font-bold py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2 group">
                    <i class="fa-solid fa-medal group-hover:scale-110 transition-transform"></i> Medalhas
                </a>
                
                <a href="create_tour.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-lg shadow-blue-600/20 transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Novo Tour
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden card-stat group">
                <div class="absolute -right-6 -top-6 text-slate-50 opacity-10 group-hover:opacity-20 transition-opacity transform rotate-12">
                    <i class="fa-solid fa-plane-arrival text-9xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Voos Validados</p>
                    <div class="flex items-baseline gap-2">
                        <p class="text-4xl font-bold text-slate-800"><?php echo number_format($totalFlights); ?></p>
                        <span class="text-xs font-bold text-green-500 bg-green-50 px-2 py-0.5 rounded-full"><i class="fa-solid fa-check"></i> OK</span>
                    </div>
                </div>
                <div class="h-1 w-full bg-blue-500 absolute bottom-0 left-0"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden card-stat group">
                <div class="absolute -right-6 -top-6 text-slate-50 opacity-10 group-hover:opacity-20 transition-opacity transform rotate-12">
                    <i class="fa-solid fa-users-viewfinder text-9xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pilotos em Rota</p>
                    <div class="flex items-baseline gap-2">
                        <p class="text-4xl font-bold text-slate-800"><?php echo number_format($activePilots); ?></p>
                        <span class="text-xs font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full">Ativos Agora</span>
                    </div>
                </div>
                <div class="h-1 w-full bg-green-500 absolute bottom-0 left-0"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden card-stat group">
                <div class="absolute -right-6 -top-6 text-slate-50 opacity-10 group-hover:opacity-20 transition-opacity transform rotate-12">
                    <i class="fa-solid fa-trophy text-9xl"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Tours Completos</p>
                    <div class="flex items-baseline gap-2">
                        <p class="text-4xl font-bold text-slate-800"><?php echo number_format($completedTours); ?></p>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded-full">Concluídos</span>
                    </div>
                </div>
                <div class="h-1 w-full bg-yellow-500 absolute bottom-0 left-0"></div>
            </div>
        </div>

        <div class="bg-white shadow-xl shadow-slate-200/50 rounded-2xl overflow-hidden border border-slate-200">
            <div class="bg-white px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-slate-800">Tours Cadastrados</h2>
                    <p class="text-sm text-slate-400">Gerencie a vigência e rotas dos eventos.</p>
                </div>
                <span class="text-xs font-bold bg-slate-100 text-slate-600 px-3 py-1 rounded-full border border-slate-200">
                    Total: <?php echo count($tours); ?>
                </span>
            </div>

            <?php if (count($tours) == 0): ?>
                <div class="p-16 text-center text-slate-400 flex flex-col items-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <i class="fa-solid fa-folder-open text-4xl text-slate-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-600">Nenhum tour encontrado</h3>
                    <p class="mb-6 max-w-sm mx-auto">Comece criando um novo evento para engajar seus pilotos.</p>
                    <a href="create_tour.php" class="text-blue-600 font-bold hover:underline">Criar Primeiro Tour</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 text-slate-500 uppercase text-[11px] font-bold tracking-wider border-b border-slate-100">
                                <th class="pl-8 py-4">Banner & ID</th>
                                <th class="px-4 py-4">Detalhes do Evento</th>
                                <th class="px-4 py-4">Vigência</th>
                                <th class="px-4 py-4 text-center">Status</th>
                                <th class="pr-8 py-4 text-right">Gerenciar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($tours as $tour): ?>
                            <tr class="hover:bg-slate-50/80 transition group">
                                <td class="pl-8 py-4 w-48">
                                    <div class="relative">
                                        <img src="<?php echo htmlspecialchars($tour['banner_url']); ?>" class="w-32 h-16 object-cover rounded-lg shadow-sm border border-slate-200 group-hover:shadow-md transition">
                                        <span class="absolute -top-2 -left-2 bg-slate-800 text-white text-[10px] font-mono py-0.5 px-2 rounded shadow">#<?php echo $tour['id']; ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-800 text-base mb-1"><?php echo htmlspecialchars($tour['title']); ?></div>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold border 
                                        <?php 
                                            echo match($tour['difficulty']) {
                                                'Easy' => 'bg-green-50 text-green-700 border-green-200',
                                                'Medium' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                                'Hard' => 'bg-red-50 text-red-700 border-red-200',
                                                default => 'bg-slate-50 text-slate-600 border-slate-200'
                                            };
                                        ?>">
                                        <i class="fa-solid fa-gauge-high"></i> <?php echo $tour['difficulty']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col gap-1 text-xs font-mono text-slate-500">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-regular fa-calendar-plus w-4 text-green-500"></i> 
                                            <?php echo $tour['start_date'] ? date('d/m/Y', strtotime($tour['start_date'])) : '<span class="opacity-50">...</span>'; ?>
                                        </div>
                                        <div class="w-px h-2 bg-slate-200 ml-2"></div>
                                        <div class="flex items-center gap-2">
                                            <i class="fa-regular fa-calendar-xmark w-4 text-red-400"></i> 
                                            <?php echo $tour['end_date'] ? date('d/m/Y', strtotime($tour['end_date'])) : '<span class="opacity-50">...</span>'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if($tour['status'] == 1): ?>
                                        <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full border border-green-200 shadow-sm">
                                            <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div> ATIVO
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 text-[11px] font-bold px-3 py-1 rounded-full border border-slate-200">
                                            <div class="w-1.5 h-1.5 rounded-full bg-slate-400"></div> INATIVO
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pr-8 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="manage_legs.php?tour_id=<?php echo $tour['id']; ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-300 hover:shadow-md transition" title="Rotas">
                                            <i class="fa-solid fa-route"></i>
                                        </a>
                                        <a href="edit_tour.php?id=<?php echo $tour['id']; ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-amber-600 hover:border-amber-300 hover:shadow-md transition" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>