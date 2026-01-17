<?php
// admin/manage_legs.php
require_once '../config/db.php';
require_once '../includes/navbar.php';
require_once '../includes/simbrief.apiv1.php';

// --- 1. PROCESSAMENTO SIMBRIEF ---
$sb_route = "";
$sb_dep = "";
$sb_arr = "";
$is_sb_return = false;

if (isset($_GET['ofp_id'])) {
    $sb = new SimBrief($_GET['ofp_id']);
    if ($sb->ofp_avail) {
        $sb_data = $sb->ofp_array;
        $sb_dep = $sb_data['origin']['icao_code'];
        $sb_arr = $sb_data['destination']['icao_code'];
        $sb_route = $sb_data['general']['route'];
        $is_sb_return = true;
    }
}

// --- 2. VALIDAÇÕES INICIAIS ---
if (!isset($_GET['tour_id'])) {
    header("Location: index.php");
    exit;
}
$tour_id = $_GET['tour_id'];

$stmt = $pdo->prepare("SELECT title FROM tour_tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado.");

// Busca todas as pernas
$stmt = $pdo->prepare("SELECT * FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC");
$stmt->execute([$tour_id]);
$legs = $stmt->fetchAll();

// --- 3. LÓGICA DE SEQUENCIAMENTO & EDIÇÃO ---

// A) Detectar último aeroporto para sugerir (Lógica de Sequência)
$suggested_dep = "";
if (count($legs) > 0) {
    $last_leg = end($legs); // Pega o último array
    $suggested_dep = $last_leg['arr_icao'];
}

// B) Detectar Modo de Edição
$editing_leg = null;
$action_mode = "add"; // Padrão
$form_title = "Adicionar Nova Etapa";
$btn_text = "Salvar Etapa";
$leg_order_val = count($legs) + 1; // Padrão: Próximo número

if (isset($_GET['edit_leg'])) {
    $edit_id = $_GET['edit_leg'];
    $stmtEdit = $pdo->prepare("SELECT * FROM tour_legs WHERE id = ? AND tour_id = ?");
    $stmtEdit->execute([$edit_id, $tour_id]);
    $editing_leg = $stmtEdit->fetch();

    if ($editing_leg) {
        $action_mode = "edit";
        $form_title = "Editando Etapa #" . $editing_leg['leg_order'];
        $btn_text = "Atualizar Etapa";
        $leg_order_val = $editing_leg['leg_order'];
        
        // Se NÃO voltamos do SimBrief agora, usamos os dados do banco
        if (!$is_sb_return) {
            $sb_dep = $editing_leg['dep_icao'];
            $sb_arr = $editing_leg['arr_icao'];
            $sb_route = $editing_leg['route_string'];
        }
    }
}

// Se não estamos editando e nem voltando do SimBrief, usa a sugestão
if ($action_mode == 'add' && !$is_sb_return) {
    $sb_dep = $suggested_dep;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pernas - <?php echo htmlspecialchars($tour['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-900">

<div class="container mx-auto px-4 py-8">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Gerenciar Rotas</h1>
            <p class="text-gray-600 text-sm">Tour: <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($tour['title']); ?></span></p>
        </div>
        <a href="index.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="bg-white p-6 rounded shadow-md mb-8 border-t-4 <?php echo $action_mode == 'edit' ? 'border-yellow-500' : 'border-blue-600'; ?>">
        
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <i class="fa-solid <?php echo $action_mode == 'edit' ? 'fa-pen-to-square' : 'fa-plus-circle'; ?>"></i> 
                <?php echo $form_title; ?>
            </h2>
            <?php if($action_mode == 'edit'): ?>
                <a href="manage_legs.php?tour_id=<?php echo $tour_id; ?>" class="text-sm text-red-500 hover:underline">Cancelar Edição</a>
            <?php endif; ?>
        </div>
        
        <?php if($is_sb_return): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-pulse">
                <strong class="font-bold"><i class="fa-solid fa-check"></i> SimBrief Capturado!</strong>
                <span class="block sm:inline">Dados da rota preenchidos automaticamente. Revise e salve.</span>
            </div>
        <?php endif; ?>

        <form action="process_leg.php" method="POST" id="legForm">
            <input type="hidden" name="action" value="<?php echo $action_mode; ?>">
            <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
            <?php if($editing_leg): ?>
                <input type="hidden" name="leg_id" value="<?php echo $editing_leg['id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wide">Ordem</label>
                    <input type="number" name="leg_order" required class="w-full border border-gray-300 rounded px-3 py-2 focus:border-blue-500 outline-none" 
                           value="<?php echo $leg_order_val; ?>">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wide">Origem (ICAO)</label>
                    <input type="text" name="dep_icao" id="dep_icao" required class="w-full border border-gray-300 rounded px-3 py-2 uppercase font-mono" 
                           value="<?php echo $sb_dep; ?>" placeholder="Ex: SBGL" onchange="updateSbInputs()">
                    <?php if($action_mode == 'add' && $sb_dep == $suggested_dep && $suggested_dep != ""): ?>
                        <span class="text-[10px] text-green-600"><i class="fa-solid fa-link"></i> Sugerido da última chegada</span>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wide">Destino (ICAO)</label>
                    <input type="text" name="arr_icao" id="arr_icao" required class="w-full border border-gray-300 rounded px-3 py-2 uppercase font-mono" 
                           value="<?php echo $sb_arr; ?>" placeholder="Ex: SBGR" onchange="updateSbInputs()">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wide">Assistente</label>
                    <button type="button" onclick="fetchRoute()" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded shadow transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-magic"></i> Gerar Rota SimBrief
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wide">String de Rota</label>
                <div class="relative">
                    <textarea name="route_string" id="route_string" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 font-mono text-sm bg-slate-50 uppercase resize-none" 
                           placeholder="Gere via botão acima ou cole a rota aqui..."><?php echo $sb_route; ?></textarea>
                    <i class="fa-solid fa-route absolute right-3 top-3 text-gray-400"></i>
                </div>
            </div>

            <button type="submit" class="<?php echo $action_mode == 'edit' ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-6 py-2 rounded font-bold shadow-lg transition w-full md:w-auto">
                <i class="fa-solid fa-save"></i> <?php echo $btn_text; ?>
            </button>
        </form>
    </div>

    <div class="bg-white rounded shadow-md overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-200">
            <h3 class="font-bold text-gray-700">Etapas Cadastradas (<?php echo count($legs); ?>)</h3>
        </div>
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-16">Seq</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-24">Dep</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-24">Arr</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rota</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-32">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($legs as $leg): ?>
                <tr class="hover:bg-gray-50 transition <?php echo ($editing_leg && $editing_leg['id'] == $leg['id']) ? 'bg-yellow-50' : ''; ?>">
                    <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-500">#<?php echo $leg['leg_order']; ?></td>
                    <td class="px-5 py-4 border-b border-gray-200 text-sm font-bold text-slate-700"><?php echo $leg['dep_icao']; ?></td>
                    <td class="px-5 py-4 border-b border-gray-200 text-sm font-bold text-slate-700"><?php echo $leg['arr_icao']; ?></td>
                    <td class="px-5 py-4 border-b border-gray-200 text-xs font-mono text-gray-500 truncate max-w-xs" title="<?php echo htmlspecialchars($leg['route_string']); ?>">
                        <?php echo $leg['route_string'] ? substr($leg['route_string'], 0, 50) . (strlen($leg['route_string']) > 50 ? '...' : '') : '<span class="italic text-gray-300">Sem rota</span>'; ?>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 text-sm text-right flex justify-end gap-2">
                        <a href="manage_legs.php?tour_id=<?php echo $tour_id; ?>&edit_leg=<?php echo $leg['id']; ?>" class="bg-yellow-100 text-yellow-600 hover:text-yellow-800 p-2 rounded hover:bg-yellow-200 transition" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        
                        <form action="process_leg.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar esta perna?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="leg_id" value="<?php echo $leg['id']; ?>">
                            <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
                            <button type="submit" class="bg-red-100 text-red-600 hover:text-red-800 p-2 rounded hover:bg-red-200 transition" title="Excluir">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($legs) == 0): ?>
                    <tr><td colspan="5" class="p-6 text-center text-gray-400">Nenhuma etapa cadastrada ainda. Use o formulário acima.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="sbapiform" style="display:none;">
    <input type="text" name="orig">
    <input type="text" name="dest">
    <input type="text" name="route">
    <input type="text" name="type" value="B738">
    <input type="text" name="airline" value="ADM">
    <input type="text" name="fltnum" value="101">
    <input type="text" name="units" value="KGS">
    <input type="text" name="navlog" value="0">
</form>

<script src="../scripts/simbrief.apiv1.js"></script>
<script>
    var api_dir = '../includes/';

    function updateSbInputs() {
        let dep = document.getElementById('dep_icao').value;
        let arr = document.getElementById('arr_icao').value;
        document.getElementsByName('orig')[0].value = dep;
        document.getElementsByName('dest')[0].value = arr;
    }

    function fetchRoute() {
        let dep = document.getElementById('dep_icao').value;
        let arr = document.getElementById('arr_icao').value;

        if(!dep || !arr) {
            alert("Preencha Origem e Destino antes de gerar a rota.");
            return;
        }
        updateSbInputs();
        
        // Mantém os parâmetros de edição na URL se existirem, para voltar para o modo edição
        let returnUrl = window.location.href;
        simbriefsubmit(returnUrl);
    }
    
    // Inicializa inputs na carga (caso já tenha valor)
    updateSbInputs();
</script>

</body>
</html>