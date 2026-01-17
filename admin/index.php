<?php
// admin/index.php - DASHBOARD ADMINISTRATIVO
session_start();
require '../config/db.php';

// --- SEGURANÇA: INTEGRAÇÃO WORDPRESS ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { 
    require_once $wpLoadPath; 
} else { 
    die("Erro: WordPress não encontrado para verificação de segurança."); 
}

if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_die('<h1>Acesso Negado</h1><p>Você não tem permissão para aceder a esta página.</p>', 'Erro de Permissão', ['response' => 403]);
}
// --- FIM SEGURANÇA ---

$stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM tour_legs WHERE tour_id = t.id) as total_legs FROM tours t ORDER BY t.id DESC");
$tours = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin Tours - Kafly/Cubana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans">

<div class="min-h-screen flex flex-col">
    
    <nav class="bg-slate-900 text-white p-4 shadow-lg">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="font-bold text-xl flex items-center gap-2">
                <i class="fa-solid fa-screwdriver-wrench text-yellow-500"></i>
                ADMIN<span class="text-yellow-500">TOURS</span>
            </div>
            <div class="space-x-4 flex items-center">
                <a href="manage_badges.php" class="text-sm text-yellow-400 hover:text-white transition font-bold" title="Gerenciar Medalhas">
                    <i class="fa-solid fa-medal"></i> Medalhas
                </a>
                <span class="text-slate-700">|</span>
                <a href="settings.php" class="text-sm text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-cog"></i> Configs
                </a>
                <span class="text-slate-700">|</span>
                <a href="../pilots/index.php" target="_blank" class="text-sm text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-eye"></i> Ver como Piloto
                </a>
            </div>
        </div>
    </nav>

    <div class="flex-grow max-w-6xl mx-auto w-full p-8">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Gerenciamento de Tours</h1>
                <p class="text-slate-500">Crie eventos e defina as rotas para os pilotos.</p>
            </div>
            <div class="flex gap-3">
                <a href="manage_badges.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:shadow-purple-500/30 transition flex items-center gap-2">
                    <i class="fa-solid fa-medal"></i> Medalhas
                </a>
                <a href="create_tour.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:shadow-blue-500/30 transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Novo Tour
                </a>
            </div>
        </div>

        <?php if (count($tours) == 0): ?>
            <div class="bg-white p-12 rounded-xl shadow border border-slate-200 text-center">
                <i class="fa-solid fa-box-open text-6xl text-slate-300 mb-4"></i>
                <h3 class="text-xl font-bold text-slate-600">Nenhum Tour Criado</h3>
                <p class="text-slate-400 mb-6">Comece criando o primeiro evento para sua VA.</p>
                <a href="create_tour.php" class="text-blue-600 hover:underline">Criar Tour Agora</a>
            </div>
        <?php else: ?>

            <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold border-b">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">Tour</th>
                            <th class="p-4">Dificuldade</th>
                            <th class="p-4 text-center">Pernas</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($tours as $tour): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4 font-mono text-slate-400">#<?php echo $tour['id']; ?></td>
                            
                            <td class="p-4">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($tour['title']); ?></div>
                                <div class="text-xs text-slate-400 truncate max-w-xs">
                                    <i class="fa-solid fa-image"></i> Banner Configurado
                                </div>
                            </td>
                            
                            <td class="p-4">
                                <?php 
                                $color = 'bg-gray-100 text-gray-800';
                                if($tour['difficulty'] == 'Medium') $color = 'bg-yellow-100 text-yellow-800';
                                if($tour['difficulty'] == 'Hard') $color = 'bg-red-100 text-red-800';
                                ?>
                                <span class="<?php echo $color; ?> px-2 py-1 rounded text-xs font-bold uppercase">
                                    <?php echo $tour['difficulty']; ?>
                                </span>
                            </td>

                            <td class="p-4 text-center">
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full font-bold text-xs">
                                    <?php echo $tour['total_legs']; ?> Legs
                                </span>
                            </td>

                            <td class="p-4 text-center">
                                <?php if($tour['status'] == 1): ?>
                                    <span class="text-green-600 font-bold text-xs flex items-center justify-center gap-1">
                                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div> Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 font-bold text-xs">Inativo</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-4 text-right space-x-2">
                                <a href="manage_legs.php?tour_id=<?php echo $tour['id']; ?>" class="inline-block bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded text-sm font-bold transition" title="Editar Rotas">
                                    <i class="fa-solid fa-map-location-dot"></i> Rotas
                                </a>

                                <a href="edit_tour.php?id=<?php echo $tour['id']; ?>" class="inline-block bg-white border border-slate-300 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 text-slate-500 px-3 py-2 rounded text-sm transition" title="Editar Configurações">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
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