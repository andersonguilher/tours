<?php
// admin/edit_tour.php
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---

$id = $_GET['id'] ?? 0;
if ($id == 0) die("ID Inválido");

$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado");

$rules = json_decode($tour['rules_json'], true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Tour - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-5xl mx-auto py-10 px-6">
    <nav class="text-sm text-slate-500 mb-4">
        <a href="index.php" class="hover:underline">Dashboard</a> > <span class="text-slate-800 font-bold">Editar Tour #<?php echo $id; ?></span>
    </nav>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
        <div class="bg-yellow-600 p-6 text-white flex justify-between items-center">
            <h1 class="text-2xl font-bold"><i class="fa-solid fa-pen-to-square mr-2"></i> Editar Tour</h1>
            
            <form action="process_tour.php" method="POST" onsubmit="return confirm('Tem certeza?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-xs font-bold"><i class="fa-solid fa-trash"></i> EXCLUIR</button>
            </form>
        </div>
        
        <form action="process_tour.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <input type="hidden" name="old_banner_url" value="<?php echo htmlspecialchars($tour['banner_url']); ?>">

            <div>
                <h3 class="text-lg font-bold text-slate-700 border-b pb-2 mb-4">1. Identidade</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="label-admin">Título</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($tour['title']); ?>" class="input-admin" required>
                    </div>
                    <div>
                        <label class="label-admin">Dificuldade</label>
                        <select name="difficulty" class="input-admin">
                            <option value="Easy" <?php if($tour['difficulty']=='Easy') echo 'selected'; ?>>Easy</option>
                            <option value="Medium" <?php if($tour['difficulty']=='Medium') echo 'selected'; ?>>Medium</option>
                            <option value="Hard" <?php if($tour['difficulty']=='Hard') echo 'selected'; ?>>Hard</option>
                        </select>
                    </div>

                    <div>
                        <label class="label-admin">Data de Início</label>
                        <input type="date" name="start_date" value="<?php echo $tour['start_date']; ?>" class="input-admin">
                    </div>
                    <div>
                        <label class="label-admin">Data de Término</label>
                        <input type="date" name="end_date" value="<?php echo $tour['end_date']; ?>" class="input-admin">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="label-admin">Banner (Deixe vazio para manter o atual)</label>
                        <div class="flex gap-4 items-start">
                            <div class="w-1/3">
                                <img src="<?php echo htmlspecialchars($tour['banner_url']); ?>" class="rounded-lg shadow border border-slate-200 w-full h-24 object-cover">
                                <p class="text-xs text-center text-slate-400 mt-1">Atual</p>
                            </div>
                            <div class="w-2/3">
                                <input type="file" name="banner_file" class="input-admin" accept="image/*">
                                <p class="text-xs text-slate-400 mt-1">Enviar nova imagem substituirá a atual.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="label-admin">Status</label>
                        <select name="status" class="input-admin">
                            <option value="1" <?php if($tour['status']==1) echo 'selected'; ?>>Ativo</option>
                            <option value="0" <?php if($tour['status']==0) echo 'selected'; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold text-slate-700 border-b pb-2 mb-4">2. Briefing</h3>
                <textarea name="description" rows="5" class="input-admin"><?php echo htmlspecialchars($tour['description']); ?></textarea>
                <div class="mt-4">
                    <label class="label-admin">Link para Cenário (Sugestão)</label>
                    <input type="url" name="scenery_link" value="<?php echo htmlspecialchars($tour['scenery_link'] ?? ''); ?>" class="input-admin">
                </div>
            </div>

            <div class="bg-yellow-50 p-6 rounded-xl border border-yellow-200">
                <h3 class="text-lg font-bold text-yellow-800 border-b border-yellow-200 pb-2 mb-4">3. Regras</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="label-admin text-yellow-800">Aeronaves</label>
                        <input type="text" name="rules[allowed_aircraft]" value="<?php echo $rules['allowed_aircraft'] ?? ''; ?>" class="input-admin border-yellow-300">
                    </div>
                    <div>
                        <label class="label-admin text-yellow-800">Velocidade Máx. (< FL100)</label>
                        <input type="number" name="rules[speed_fl100]" value="<?php echo $rules['speed_fl100'] ?? '250'; ?>" class="input-admin border-yellow-300">
                    </div>
                    <div>
                        <label class="label-admin text-yellow-800">Rede</label>
                        <select name="rules[network]" class="input-admin border-yellow-300">
                            <option value="BOTH" <?php if(($rules['network']??'')=='BOTH') echo 'selected'; ?>>Ambas</option>
                            <option value="IVAO" <?php if(($rules['network']??'')=='IVAO') echo 'selected'; ?>>IVAO</option>
                            <option value="VATSIM" <?php if(($rules['network']??'')=='VATSIM') echo 'selected'; ?>>VATSIM</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4 gap-4">
                <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg transition">Cancelar</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition">Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
    .label-admin { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem; }
    .input-admin { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.75rem; outline: none; transition: all 0.2s; }
    .input-admin:focus { ring: 2px; border-color: #ca8a04; }
</style>
</body>
</html>