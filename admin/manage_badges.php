<?php
// admin/manage_badges.php
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

// Buscar medalhas existentes
$badges = $pdo->query("SELECT * FROM badges ORDER BY id DESC")->fetchAll();

// Modo Edição?
$editMode = false;
$badgeToEdit = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM badges WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $badgeToEdit = $stmt->fetch();
    if ($badgeToEdit) $editMode = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Medalhas - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-6xl mx-auto py-10 px-6">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <a href="index.php" class="text-sm text-slate-500 hover:text-blue-600"><i class="fa-solid fa-arrow-left"></i> Voltar ao Dashboard</a>
            <h1 class="text-3xl font-bold mt-2">Central de Medalhas</h1>
            <p class="text-slate-500">Crie condecorações para o Passaporte dos pilotos.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-xl shadow-lg border border-slate-200 sticky top-10">
                <h3 class="text-xl font-bold mb-4 border-b pb-2">
                    <?php echo $editMode ? 'Editar Medalha' : 'Nova Medalha'; ?>
                </h3>
                
                <form action="process_badge.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
                    <?php if($editMode): ?>
                        <input type="hidden" name="id" value="<?php echo $badgeToEdit['id']; ?>">
                        <input type="hidden" name="old_image_url" value="<?php echo htmlspecialchars($badgeToEdit['image_url']); ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                        <input type="text" name="title" value="<?php echo $editMode ? htmlspecialchars($badgeToEdit['title']) : ''; ?>" class="w-full border rounded p-2" required placeholder="Ex: Rei do Caribe">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descrição</label>
                        <textarea name="description" class="w-full border rounded p-2" rows="3" required placeholder="Ex: Completou o Tour Cuba"><?php echo $editMode ? htmlspecialchars($badgeToEdit['description']) : ''; ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de Condição (Código)</label>
                        <select name="condition_type" class="w-full border rounded p-2 bg-white">
                            <?php $c = $editMode ? $badgeToEdit['condition_type'] : ''; ?>
                            <option value="manual" <?php if($c=='manual') echo 'selected'; ?>>Manual (Atribuição Admin)</option>
                            <option value="tour_complete" <?php if($c=='tour_complete') echo 'selected'; ?>>Completar Tour</option>
                            <option value="first_flight" <?php if($c=='first_flight') echo 'selected'; ?>>Primeiro Voo</option>
                            <option value="landing_king" <?php if($c=='landing_king') echo 'selected'; ?>>Pouso Perfeito</option>
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">Usado para automações futuras.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Imagem</label>
                        <?php if($editMode): ?>
                            <div class="flex items-center gap-2 mb-2 bg-slate-50 p-2 rounded">
                                <img src="<?php echo htmlspecialchars($badgeToEdit['image_url']); ?>" class="w-8 h-8 object-contain">
                                <span class="text-xs text-slate-400">Atual</span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image_file" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div class="pt-2">
                        <?php if($editMode): ?>
                            <div class="flex gap-2">
                                <a href="manage_badges.php" class="flex-1 bg-gray-300 text-center py-2 rounded text-sm font-bold">Cancelar</a>
                                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded text-sm font-bold">Salvar</button>
                            </div>
                        <?php else: ?>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded font-bold shadow transition">Criar Medalha</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold border-b">
                        <tr>
                            <th class="p-4 w-16">Ícone</th>
                            <th class="p-4">Detalhes</th>
                            <th class="p-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($badges as $b): ?>
                        <tr class="hover:bg-slate-50 group">
                            <td class="p-4">
                                <img src="<?php echo htmlspecialchars($b['image_url']); ?>" class="w-12 h-12 object-contain drop-shadow-md">
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($b['title']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($b['description']); ?></div>
                                <span class="inline-block mt-1 text-[9px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded font-mono">
                                    <?php echo $b['condition_type']; ?>
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <a href="?edit_id=<?php echo $b['id']; ?>" class="inline-block text-blue-600 hover:bg-blue-50 p-2 rounded transition" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form action="process_badge.php" method="POST" class="inline-block" onsubmit="return confirm('Apagar esta medalha?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded transition" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(count($badges) == 0): ?>
                        <tr>
                            <td colspan="3" class="p-8 text-center text-slate-400">Nenhuma medalha criada ainda.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>