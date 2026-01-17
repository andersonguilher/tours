<?php
// admin/index.php
// Dashboard Administrativo
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

// 1. Contagem de Tours
// CORREÇÃO: tour_tours
$stmt = $pdo->query("SELECT * FROM tour_tours ORDER BY created_at DESC");
$tours = $stmt->fetchAll();

// 2. Estatísticas Gerais
// CORREÇÃO: tour_progress e tour_history
$totalFlights = $pdo->query("SELECT COUNT(id) FROM tour_history")->fetchColumn();
$activePilots = $pdo->query("SELECT COUNT(DISTINCT pilot_id) FROM tour_progress WHERE status = 'In Progress'")->fetchColumn();
$completedTours = $pdo->query("SELECT COUNT(id) FROM tour_progress WHERE status = 'Completed'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Kafly Tours - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-6xl mx-auto py-10 px-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Painel de Controle</h1>
            <p class="text-slate-500">Gerenciamento de Tours e Eventos</p>
        </div>
        <div class="flex gap-3">
            <a href="manage_badges.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded shadow transition">
                <i class="fa-solid fa-medal"></i> Medalhas
            </a>
            <a href="create_tour.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow transition">
                <i class="fa-solid fa-plus"></i> Novo Tour
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase">Voos Validados</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $totalFlights; ?></p>
            </div>
            <i class="fa-solid fa-plane-arrival text-3xl text-blue-100"></i>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase">Pilotos Ativos</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $activePilots; ?></p>
            </div>
            <i class="fa-solid fa-users text-3xl text-green-100"></i>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-yellow-500 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase">Tours Completos</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo $completedTours; ?></p>
            </div>
            <i class="fa-solid fa-trophy text-3xl text-yellow-100"></i>
        </div>
    </div>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h2 class="font-bold text-slate-700">Tours Cadastrados</h2>
            <span class="text-xs bg-slate-200 text-slate-600 px-2 py-1 rounded-full"><?php echo count($tours); ?> Total</span>
        </div>

        <?php if (count($tours) == 0): ?>
            <div class="p-10 text-center text-slate-400">
                <i class="fa-solid fa-folder-open text-4xl mb-3"></i>
                <p>Nenhum tour criado ainda.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold border-b">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Banner</th>
                        <th class="p-4">Título</th>
                        <th class="p-4">Vigência</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($tours as $tour): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4 text-slate-400 font-mono text-xs">#<?php echo $tour['id']; ?></td>
                        <td class="p-4">
                            <img src="<?php echo htmlspecialchars($tour['banner_url']); ?>" class="w-16 h-10 object-cover rounded shadow-sm">
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($tour['title']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo $tour['difficulty']; ?></div>
                        </td>
                        <td class="p-4 text-xs text-slate-600">
                            <?php 
                                if($tour['start_date']) echo date('d/m/y', strtotime($tour['start_date'])); 
                                else echo '...';
                            ?> 
                            <i class="fa-solid fa-arrow-right mx-1 text-slate-300"></i>
                            <?php 
                                if($tour['end_date']) echo date('d/m/y', strtotime($tour['end_date'])); 
                                else echo '...';
                            ?>
                        </td>
                        <td class="p-4 text-center">
                            <?php if($tour['status'] == 1): ?>
                                <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Ativo</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <a href="manage_legs.php?tour_id=<?php echo $tour['id']; ?>" class="text-slate-400 hover:text-blue-600 transition" title="Rotas">
                                <i class="fa-solid fa-route"></i>
                            </a>
                            <a href="edit_tour.php?id=<?php echo $tour['id']; ?>" class="text-slate-400 hover:text-yellow-600 transition" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>