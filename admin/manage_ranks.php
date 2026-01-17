<?php
// admin/manage_ranks.php
// GESTÃO DE PATENTES COMPLETA (Visual CSS + Suporte a Estrelas)
require '../config/db.php';
session_start();

// Verificação de Admin (Descomente em produção)
// if (!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }

// --- 1. PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // EXCLUIR
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM tour_ranks WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        } 
        
        // SALVAR (Novo ou Edição)
        elseif ($_POST['action'] === 'save') {
            $title = $_POST['rank_title'];
            $hours = (int)$_POST['min_hours'];
            $stripes = (int)$_POST['stripes'];
            // Captura o checkbox da estrela (1 se marcado, 0 se não)
            $has_star = isset($_POST['has_star']) ? 1 : 0;
            
            if (!empty($_POST['id'])) {
                // Update
                $stmt = $pdo->prepare("UPDATE tour_ranks SET rank_title=?, min_hours=?, stripes=?, has_star=? WHERE id=?");
                $stmt->execute([$title, $hours, $stripes, $has_star, $_POST['id']]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO tour_ranks (rank_title, min_hours, stripes, has_star) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $hours, $stripes, $has_star]);
            }
        }
    }
    // Redireciona para evitar reenvio do formulário ao atualizar
    header("Location: manage_ranks.php");
    exit;
}

// --- 2. BUSCAR DADOS ---
try {
    $stmt = $pdo->query("SELECT * FROM tour_ranks ORDER BY min_hours ASC");
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro: Tabela 'tour_ranks' não encontrada ou desatualizada. Verifique se rodou o SQL de criação.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Patentes - Kafly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<div class="max-w-6xl mx-auto p-6">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Hierarquia de Pilotos</h1>
            <p class="text-slate-500">Defina as regras de promoção e o visual das patentes.</p>
        </div>
        <a href="index.php" class="text-slate-600 hover:text-blue-600 font-bold flex items-center gap-2 bg-white px-4 py-2 rounded shadow-sm hover:shadow transition">
            <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-4">
            <?php if (empty($ranks)): ?>
                <div class="bg-yellow-50 text-yellow-800 p-6 rounded-lg border border-yellow-200 text-center">
                    <i class="fa-solid fa-triangle-exclamation text-3xl mb-2"></i><br>
                    Nenhuma patente configurada ainda.<br>Use o formulário ao lado para criar a primeira.
                </div>
            <?php endif; ?>

            <?php foreach ($ranks as $rank): ?>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row justify-between items-center group hover:shadow-md transition gap-4">
                
                <div class="flex items-center gap-5 w-full">
                    
                    <div class="relative w-16 h-20 bg-slate-900 border border-slate-700 rounded-t-lg shadow-inner flex flex-col justify-end items-center pb-2 overflow-hidden shrink-0 select-none">
                        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        
                        <div class="absolute top-1.5 w-2 h-2 rounded-full bg-yellow-500 shadow border border-yellow-700 z-20"></div>
                        
                        <?php if(!empty($rank['has_star'])): ?>
                            <div class="absolute top-4 z-10 text-yellow-400 drop-shadow-md text-[10px]">
                                <i class="fa-solid fa-star"></i>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-col gap-1 w-full px-1 z-10">
                            <?php for($i=0; $i < $rank['stripes']; $i++): ?>
                                <div class="h-2 w-full bg-gradient-to-r from-yellow-600 via-yellow-300 to-yellow-600 shadow-sm rounded-sm"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="flex-1">
                        <h3 class="font-bold text-xl text-slate-800"><?php echo htmlspecialchars($rank['rank_title']); ?></h3>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500 mt-1">
                            <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-mono font-bold border border-blue-200">
                                <i class="fa-regular fa-clock mr-1"></i><?php echo $rank['min_hours']; ?>h
                            </span>
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                <?php echo $rank['stripes']; ?> Faixa(s)
                            </span>
                            <?php if(!empty($rank['has_star'])): ?>
                                <span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded border border-yellow-200 font-bold text-xs uppercase">
                                    <i class="fa-solid fa-star mr-1"></i>Master
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 w-full sm:w-auto justify-end">
                    <button onclick='editRank(<?php echo json_encode($rank); ?>)' 
                            class="w-10 h-10 rounded-full bg-slate-100 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center border border-slate-200" title="Editar">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    
                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta patente?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $rank['id']; ?>">
                        <button type="submit" class="w-10 h-10 rounded-full bg-slate-100 text-red-600 hover:bg-red-600 hover:text-white transition flex items-center justify-center border border-slate-200" title="Excluir">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg border border-slate-200 h-fit sticky top-6">
            <h2 class="text-xl font-bold mb-6 text-slate-800 flex items-center gap-2 pb-4 border-b border-slate-100" id="formTitle">
                <i class="fa-solid fa-plus-circle text-green-600"></i> Nova Patente
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="rankId">

                <div class="mb-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Título da Patente</label>
                    <input type="text" name="rank_title" id="rankTitle" required 
                           class="w-full bg-slate-50 border border-slate-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition font-bold text-slate-700" 
                           placeholder="Ex: Comandante Sênior">
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Horas Mínimas</label>
                    <div class="relative">
                        <input type="number" name="min_hours" id="minHours" required 
                               class="w-full bg-slate-50 border border-slate-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition font-mono" 
                               placeholder="0">
                        <span class="absolute right-4 top-3 text-slate-400 text-sm font-bold">HRS</span>
                    </div>
                </div>

                <div class="mb-5 bg-yellow-50 p-3 rounded-lg border border-yellow-100">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="has_star" id="hasStar" value="1" class="w-5 h-5 text-yellow-600 rounded focus:ring-yellow-500 border-gray-300">
                        <span class="font-bold text-slate-700 text-sm">
                            <i class="fa-solid fa-star text-yellow-500 mr-1"></i> Adicionar Estrela
                        </span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1 ml-8">Para cargos de chefia ou master.</p>
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Quantidade de Faixas</label>
                    <div class="grid grid-cols-5 gap-2">
                        <?php for($s=0; $s<=4; $s++): ?>
                        <label class="cursor-pointer group">
                            <input type="radio" name="stripes" value="<?php echo $s; ?>" class="peer sr-only" <?php echo ($s==1)?'checked':''; ?>>
                            <div class="h-14 bg-slate-50 border-2 border-slate-200 rounded-lg flex flex-col justify-center items-center gap-0.5 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:shadow-md transition group-hover:bg-white">
                                <?php if($s==0): ?>
                                    <span class="text-xs text-slate-400 font-bold">0</span>
                                <?php else: ?>
                                    <?php for($j=0; $j<$s; $j++): ?>
                                        <div class="h-1 w-5 bg-yellow-500 rounded-full shadow-sm"></div>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check"></i> Salvar
                    </button>
                    <button type="button" onclick="resetForm()" class="px-4 py-3 bg-slate-200 text-slate-600 font-bold rounded-lg hover:bg-slate-300 transition">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    function editRank(data) {
        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600"></i> Editar Patente';
        document.getElementById('rankId').value = data.id;
        document.getElementById('rankTitle').value = data.rank_title;
        document.getElementById('minHours').value = data.min_hours;
        
        // Checkbox Estrela
        document.getElementById('hasStar').checked = (data.has_star == 1);
        
        // Radio Faixas
        const radios = document.getElementsByName('stripes');
        for (const radio of radios) {
            if (radio.value == data.stripes) {
                radio.checked = true;
                break;
            }
        }
        
        // Scroll suave para o formulário em telas pequenas
        if(window.innerWidth < 1024) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function resetForm() {
        document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle text-green-600"></i> Nova Patente';
        document.getElementById('rankId').value = '';
        document.getElementById('rankTitle').value = '';
        document.getElementById('minHours').value = '';
        document.getElementById('hasStar').checked = false;
        
        // Resetar para 1 faixa por padrão
        const radios = document.getElementsByName('stripes');
        for (const radio of radios) {
            if (radio.value == "1") {
                radio.checked = true;
                break;
            }
        }
    }
</script>

</body>
</html>