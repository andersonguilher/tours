<?php
// admin/manage_legs.php
// GERENCIADOR DE ETAPAS - Versão Final Corrigida (Admin)

require_once '../config/db.php';

// Segurança e WP Load
$possiblePaths = [__DIR__ . '/../../../wp-load.php', $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'];
foreach ($possiblePaths as $path) { if (file_exists($path)) { require_once $path; break; } }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }

require_once '../includes/navbar.php';
require_once '../includes/simbrief.apiv1.php';

// --- PROCESSAMENTO PHP (SIMBRIEF) ---
$sb_route = ""; $sb_dep = ""; $sb_arr = ""; $sb_airport_data = []; $is_sb_return = false;
if (isset($_GET['ofp_id'])) {
    $sb = new SimBrief($_GET['ofp_id']);
    if ($sb->ofp_avail) {
        $sb_data = $sb->ofp_array;
        $sb_dep = $sb_data['origin']['icao_code'];
        $sb_arr = $sb_data['destination']['icao_code'];
        $sb_route = $sb_data['general']['route'];
        $sb_airport_data['dep'] = ['icao' => $sb_dep, 'name' => $sb_data['origin']['name'], 'lat' => $sb_data['origin']['pos_lat'], 'lon' => $sb_data['origin']['pos_long'], 'elev' => $sb_data['origin']['elevation']];
        $sb_airport_data['arr'] = ['icao' => $sb_arr, 'name' => $sb_data['destination']['name'], 'lat' => $sb_data['destination']['pos_lat'], 'lon' => $sb_data['destination']['pos_long'], 'elev' => $sb_data['destination']['elevation']];
        $is_sb_return = true;
    }
}

// Validações
if (!isset($_GET['tour_id'])) echo "<script>window.location.href='index.php';</script>";
$tour_id = $_GET['tour_id'];
$stmt = $pdo->prepare("SELECT title FROM tour_tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();
if (!$tour) die("Tour não encontrado.");
$stmt = $pdo->prepare("SELECT * FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC");
$stmt->execute([$tour_id]);
$legs = $stmt->fetchAll();

// Lógica de Sugestão e Edição
$suggested_dep = "";
if (count($legs) > 0) { $last_leg = end($legs); $suggested_dep = $last_leg['arr_icao']; }
$editing_leg = null; $action_mode = "add"; $form_title = "Nova Etapa"; $btn_text = "Adicionar Etapa"; $leg_order_val = count($legs) + 1;
if (isset($_GET['edit_leg'])) {
    $edit_id = $_GET['edit_leg'];
    $stmtEdit = $pdo->prepare("SELECT * FROM tour_legs WHERE id = ? AND tour_id = ?");
    $stmtEdit->execute([$edit_id, $tour_id]);
    $editing_leg = $stmtEdit->fetch();
    if ($editing_leg) {
        $action_mode = "edit"; $form_title = "Editar Etapa " . $editing_leg['leg_order']; $btn_text = "Salvar Alterações"; $leg_order_val = $editing_leg['leg_order'];
        if (!$is_sb_return) { $sb_dep = $editing_leg['dep_icao']; $sb_arr = $editing_leg['arr_icao']; $sb_route = $editing_leg['route_string']; }
    }
}
if ($action_mode == 'add' && !$is_sb_return) { $sb_dep = $suggested_dep; }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Rotas - <?php echo htmlspecialchars($tour['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        
        /* Popover Flutuante */
        .airport-popover {
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 0; transform: translateY(-10px) scale(0.95); pointer-events: none;
            position: absolute; left: 0; right: 0; z-index: 50; /* Z-Index alto para flutuar */
        }
        .airport-popover.visible {
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto;
        }
        
        /* Inputs */
        .input-icon-wrap { position: relative; z-index: 10; }
        .input-icon-wrap i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .input-with-icon { padding-left: 2.75rem; transition: all 0.2s; }
        .input-with-icon:focus { border-color: #3b82f6; ring: 2px solid #3b82f6; }
    </style>
</head>
<body class="text-slate-800" onclick="closeAllPopups()">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl sm:truncate tracking-tight">
                Planejamento de Missão
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Tour: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($tour['title']); ?></span>
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition">
                <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
            </a>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="rounded-lg p-4 mb-6 shadow-sm flex items-center gap-3 <?php echo ($_GET['msg']=='deleted') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'; ?>">
            <i class="fa-solid fa-circle-check text-xl"></i>
            <span class="font-medium text-sm">Operação realizada com sucesso!</span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-xl border border-slate-100 overflow-visible sticky top-6">
                
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full <?php echo $action_mode == 'edit' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'; ?> flex items-center justify-center text-sm">
                            <i class="fa-solid <?php echo $action_mode == 'edit' ? 'fa-pen' : 'fa-plus'; ?>"></i>
                        </span>
                        <?php echo $form_title; ?>
                    </h3>
                    <?php if($action_mode == 'edit'): ?>
                        <a href="manage_legs.php?tour_id=<?php echo $tour_id; ?>" class="text-xs text-red-500 hover:text-red-700 font-semibold uppercase">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if(!empty($sb_airport_data)): ?>
                <div class="bg-blue-50/50 px-6 py-3 border-b border-blue-100 flex gap-3">
                    <div class="mt-1"><i class="fa-solid fa-database text-blue-500"></i></div>
                    <div>
                        <p class="text-xs font-bold text-blue-700 uppercase">Dados Importados</p>
                        <p class="text-xs text-blue-600/80">Coordenadas capturadas do SimBrief.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="p-6 overflow-visible"> <form action="process_leg.php" method="POST" id="legForm" class="space-y-5">
                        <input type="hidden" name="action" value="<?php echo $action_mode; ?>">
                        <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
                        <?php if($editing_leg): ?><input type="hidden" name="leg_id" value="<?php echo $editing_leg['id']; ?>"><?php endif; ?>
                        <?php if(!empty($sb_airport_data)): ?><input type="hidden" name="sb_data_json" value='<?php echo json_encode($sb_airport_data); ?>'><?php endif; ?>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Sequência</label>
                            <input type="number" name="leg_order" class="w-20 text-center font-bold text-slate-700 border-slate-300 rounded-lg focus:ring-blue-500 block sm:text-sm" value="<?php echo $leg_order_val; ?>">
                        </div>

                        <div class="grid grid-cols-2 gap-4 relative">
                            <div class="relative group">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Origem</label>
                                <div class="input-icon-wrap">
                                    <input type="text" name="dep_icao" id="dep_icao" maxlength="4" placeholder="AAAA" autocomplete="off"
                                           class="input-with-icon block w-full rounded-lg border-slate-300 uppercase font-mono font-bold text-slate-800 placeholder-slate-300 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?php echo $sb_dep; ?>" oninput="liveSearch('dep')" onclick="event.stopPropagation()">
                                    <i class="fa-solid fa-plane-departure"></i>
                                </div>
                                <div id="info_dep" class="airport-popover mt-2 cursor-pointer shadow-2xl" onclick="this.classList.remove('visible')"></div>
                            </div>
                            
                            <div class="relative group">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Destino</label>
                                <div class="input-icon-wrap">
                                    <input type="text" name="arr_icao" id="arr_icao" maxlength="4" placeholder="BBBB" autocomplete="off"
                                           class="input-with-icon block w-full rounded-lg border-slate-300 uppercase font-mono font-bold text-slate-800 placeholder-slate-300 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?php echo $sb_arr; ?>" oninput="liveSearch('arr')" onclick="event.stopPropagation()">
                                    <i class="fa-solid fa-plane-arrival"></i>
                                </div>
                                <div id="info_arr" class="airport-popover mt-2 cursor-pointer shadow-2xl" onclick="this.classList.remove('visible')"></div>
                            </div>
                        </div>

                        <button type="button" onclick="fetchRoute()" 
                                class="w-full relative flex justify-center items-center py-2.5 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 transition shadow-md hover:shadow-lg z-0">
                            <i class="fa-solid fa-bolt text-yellow-400 mr-2"></i>
                            Auto-Completar via SimBrief
                        </button>

                        <div class="relative z-0">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Rota de Voo</label>
                            <textarea name="route_string" id="route_string" rows="3" 
                                      class="block w-full rounded-lg border-slate-300 bg-slate-50 text-xs font-mono uppercase text-slate-600 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                      placeholder="Rota gerada aparecerá aqui..."><?php echo $sb_route; ?></textarea>
                        </div>

                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white <?php echo $action_mode == 'edit' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'; ?> transition transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-save mr-2"></i> <?php echo $btn_text; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                    <h3 class="font-bold text-slate-700">Etapas do Tour</h3>
                    <span class="bg-slate-200 text-slate-600 py-1 px-3 rounded-full text-xs font-bold"><?php echo count($legs); ?> Pernas</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Seq</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Rota</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Detalhes</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($legs as $leg): ?>
                            <tr class="hover:bg-slate-50 transition <?php echo ($editing_leg && $editing_leg['id'] == $leg['id']) ? 'bg-amber-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-mono">#<?php echo str_pad($leg['leg_order'], 2, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-blue-100 text-blue-600 text-xs font-bold px-2 py-1 rounded">DEP</div>
                                        <div class="ml-2 text-sm font-bold text-slate-900"><?php echo $leg['dep_icao']; ?></div>
                                        <i class="fa-solid fa-arrow-right mx-3 text-slate-300 text-xs"></i>
                                        <div class="bg-green-100 text-green-600 text-xs font-bold px-2 py-1 rounded">ARR</div>
                                        <div class="ml-2 text-sm font-bold text-slate-900"><?php echo $leg['arr_icao']; ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($leg['route_string']): ?>
                                        <div class="max-w-[150px] truncate text-xs font-mono text-slate-500 bg-slate-100 px-2 py-1 rounded border border-slate-200" title="<?php echo htmlspecialchars($leg['route_string']); ?>">
                                            <?php echo htmlspecialchars($leg['route_string']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-red-400">Sem Rota</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="manage_legs.php?tour_id=<?php echo $tour_id; ?>&edit_leg=<?php echo $leg['id']; ?>" class="text-amber-600 hover:text-amber-900 mr-4"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <form action="process_leg.php" method="POST" onsubmit="return confirm('Tem certeza?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="leg_id" value="<?php echo $leg['id']; ?>"><input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="sbapiform" style="display:none;">
    <input type="text" name="orig"><input type="text" name="dest"><input type="text" name="route"><input type="text" name="type" value="B738">
    <input type="text" name="airline" value="ADM"><input type="text" name="fltnum" value="101"><input type="text" name="units" value="KGS"><input type="text" name="navlog" value="0">
</form>

<script src="../scripts/simbrief.apiv1.js"></script>
<script>
    var api_dir = '../includes/';

    // Fecha popups ao clicar fora
    function closeAllPopups() {
        document.querySelectorAll('.airport-popover').forEach(el => el.classList.remove('visible'));
    }

    async function liveSearch(type) {
        let input = document.getElementById(type + '_icao');
        let infoBox = document.getElementById('info_' + type);
        let icao = input.value.toUpperCase();
        
        // Fecha outros popups para não poluir a tela
        if (type === 'dep') document.getElementById('info_arr').classList.remove('visible');
        if (type === 'arr') document.getElementById('info_dep').classList.remove('visible');

        updateSbInputs(); // Atualiza o form oculto

        if (icao.length < 4) {
            infoBox.classList.remove('visible');
            return;
        }

        try {
            let response = await fetch('ajax_airport.php?icao=' + icao);
            let result = await response.json();

            if (result.found) {
                let apt = result.data;
                infoBox.innerHTML = `
                    <div class="bg-slate-800 text-white rounded-lg shadow-2xl p-3 border border-slate-600 z-50 relative">
                        <div class="flex items-center justify-between"><span class="font-bold text-emerald-400 text-lg">${icao}</span><span class="text-[10px] bg-slate-700 px-2 rounded">OK</span></div>
                        <div class="text-xs truncate text-slate-300">${apt.name}</div>
                        <div class="text-[10px] text-slate-500 mt-1 cursor-pointer hover:text-white" onclick="this.parentElement.parentElement.classList.remove('visible')">Clique para fechar</div>
                    </div>`;
            } else {
                infoBox.innerHTML = `
                    <div class="bg-amber-100 text-amber-900 rounded-lg shadow-2xl p-3 border border-amber-300 z-50 relative">
                        <div class="font-bold text-xs"><i class="fa-solid fa-triangle-exclamation"></i> Aeroporto Novo</div>
                        <div class="text-[10px]">Use o Auto-Completar.</div>
                    </div>`;
            }
            // Só mostra o popup se o input estiver em foco (evita abrir ao carregar se tivesse auto-focus)
            if (document.activeElement === input) {
                infoBox.classList.add('visible');
            }
        } catch (e) { console.error(e); }
    }

    function updateSbInputs() {
        // Verifica se os elementos existem antes de tentar acessar o valor
        let depEl = document.getElementById('dep_icao');
        let arrEl = document.getElementById('arr_icao');
        
        if (depEl && arrEl) {
            document.getElementsByName('orig')[0].value = depEl.value;
            document.getElementsByName('dest')[0].value = arrEl.value;
        }
    }

    function fetchRoute() {
        let dep = document.getElementById('dep_icao').value;
        let arr = document.getElementById('arr_icao').value;
        if(!dep || !arr) { alert("Preencha Origem e Destino."); return; }
        updateSbInputs();
        simbriefsubmit(window.location.href);
    }
    
    // CORREÇÃO AQUI:
    // Apenas sincroniza o formulário oculto ao carregar, mas NÃO chama o liveSearch visualmente.
    window.addEventListener('load', () => {
        updateSbInputs();
    });
</script>

</body>
</html>