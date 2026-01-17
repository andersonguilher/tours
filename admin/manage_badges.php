<?php
// admin/manage_badges.php
// Gerenciamento de Medalhas (CRUD)
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

// CORREÇÃO: tour_badges
$stmt = $pdo->query("SELECT * FROM tour_badges ORDER BY created_at DESC");
$badges = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Medalhas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-5xl mx-auto py-10 px-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <a href="index.php" class="text-sm text-slate-500 hover:text-blue-600"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
            <h1 class="text-3xl font-bold mt-2">Galeria de Medalhas</h1>
        </div>
        <button onclick="document.getElementById('modal-create').classList.remove('hidden')" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded shadow transition">
            <i class="fa-solid fa-plus"></i> Nova Medalha
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($badges as $badge): ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-slate-100 relative group">
            <div class="h-32 bg-slate-50 flex items-center justify-center p-4">
                <img src="<?php echo htmlspecialchars($badge['image_url']); ?>" class="h-24 w-24 object-contain drop-shadow-lg group-hover:scale-110 transition duration-300">
            </div>
            <div class="p-5">
                <h3 class="font-bold text-lg mb-1"><?php echo htmlspecialchars($badge['title']); ?></h3>
                <p class="text-sm text-slate-500 line-clamp-2 h-10 mb-4"><?php echo htmlspecialchars($badge['description']); ?></p>
                
                <div class="flex justify-between items-center border-t pt-4">
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded font-bold uppercase"><?php echo $badge['condition_type']; ?></span>
                    
                    <form action="process_badge.php" method="POST" onsubmit="return confirm('Excluir esta medalha?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $badge['id']; ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 transition"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="modal-create" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-8 relative">
            <button onclick="document.getElementById('modal-create').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600"><i class="fa-solid fa-times text-xl"></i></button>
            
            <h2 class="text-2xl font-bold mb-6">Criar Nova Medalha</h2>
            
            <form action="process_badge.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                    <input type="text" name="title" class="w-full border p-2 rounded focus:ring-2 ring-purple-500 outline-none" required>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descrição</label>
                    <textarea name="description" class="w-full border p-2 rounded focus:ring-2 ring-purple-500 outline-none" rows="3"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Imagem (PNG/Transparente)</label>
                    <input type="file" name="image_file" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100" accept="image/*" required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Condição (Sistema)</label>
                    <select name="condition_type" class="w-full border p-2 rounded focus:ring-2 ring-purple-500 outline-none">
                        <option value="tour_complete">Completar Tour</option>
                        <option value="manual">Atribuição Manual</option>
                    </select>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg shadow-lg transition">Salvar Medalha</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>