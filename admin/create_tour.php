<?php
// admin/create_tour.php
require '../config/db.php';

// --- SEGURANÇA ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in() || !current_user_can('administrator')) { wp_die('Acesso Negado'); }
// --- FIM SEGURANÇA ---
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Tour - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-5xl mx-auto py-10 px-6">
    <nav class="text-sm text-slate-500 mb-4">
        <a href="index.php" class="hover:underline">Dashboard</a> > <span class="text-slate-800 font-bold">Novo Tour</span>
    </nav>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
        <div class="bg-blue-900 p-6 text-white">
            <h1 class="text-2xl font-bold"><i class="fa-solid fa-plus-circle mr-2"></i> Criar Novo Tour</h1>
            <p class="text-blue-200 text-sm">Preencha os detalhes operacionais e vigência.</p>
        </div>
        
        <form action="process_tour.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
            <input type="hidden" name="action" value="create">

            <div>
                <h3 class="text-lg font-bold text-slate-700 border-b pb-2 mb-4">1. Identidade do Evento</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="label-admin">Título do Tour</label>
                        <input type="text" name="title" class="input-admin" placeholder="Ex: Rota do Caribe 2026" required>
                    </div>
                    <div>
                        <label class="label-admin">Dificuldade</label>
                        <select name="difficulty" class="input-admin">
                            <option value="Easy">🟢 Iniciante (Easy)</option>
                            <option value="Medium">🟡 Intermediário (Medium)</option>
                            <option value="Hard">🔴 Avançado (Hard)</option>
                        </select>
                    </div>

                    <div>
                        <label class="label-admin">Data de Início (Opcional)</label>
                        <input type="date" name="start_date" class="input-admin">
                        <p class="text-[10px] text-gray-500 mt-1">Deixe em branco para início imediato.</p>
                    </div>
                    <div>
                        <label class="label-admin">Data de Término (Opcional)</label>
                        <input type="date" name="end_date" class="input-admin">
                        <p class="text-[10px] text-gray-500 mt-1">Deixe em branco para permanente.</p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="label-admin">Banner do Tour (Imagem)</label>
                        <div class="flex items-center justify-center w-full">
                            <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                                    <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Clique para enviar</span> ou arraste</p>
                                    <p class="text-xs text-gray-500">JPG, PNG ou GIF (Recomendado: 1920x400px)</p>
                                </div>
                                <input id="dropzone-file" name="banner_file" type="file" class="hidden" accept="image/*" required />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold text-slate-700 border-b pb-2 mb-4">2. Briefing Operacional</h3>
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="label-admin">Descrição e Instruções</label>
                        <textarea name="description" rows="5" class="input-admin" placeholder="Instruções para os pilotos..."></textarea>
                    </div>
                    <div>
                        <label class="label-admin">Link para Cenário (Sugestão)</label>
                        <input type="url" name="scenery_link" class="input-admin" placeholder="https://flightsim.to/..." >
                        <p class="text-[10px] text-gray-500 mt-1">Link direto para download ou loja.</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 p-6 rounded-xl border border-blue-100">
                <h3 class="text-lg font-bold text-blue-900 border-b border-blue-200 pb-2 mb-4">3. Regras de Validação</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="relative group-autocomplete">
                        <label class="label-admin text-blue-800">Aeronaves (ICAO)</label>
                        <input type="text" id="aircraftInput" name="rules[allowed_aircraft]" class="input-admin border-blue-200" placeholder="B738, A320" autocomplete="off">
                        <div id="aircraftSuggestions" class="hidden absolute z-50 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto mt-1">
                            </div>
                        <p class="text-[10px] text-blue-400 mt-1">Separe por vírgula. Digite para buscar na base SimBrief.</p>
                    </div>
                    <div>
                        <label class="label-admin text-blue-800">Velocidade Máx. (< FL100)</label>
                        <input type="number" name="rules[speed_fl100]" value="250" class="input-admin border-blue-200">
                    </div>
                    <div>
                        <label class="label-admin text-blue-800">Rede</label>
                        <select name="rules[network]" class="input-admin border-blue-200">
                            <option value="BOTH">Ambas</option>
                            <option value="IVAO">IVAO</option>
                            <option value="VATSIM">VATSIM</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition">
                    Salvar e Continuar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .label-admin { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem; }
    .input-admin { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.75rem; outline: none; transition: all 0.2s; }
    .input-admin:focus { ring: 2px; border-color: #3b82f6; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('aircraftInput');
    const suggestionsBox = document.getElementById('aircraftSuggestions');
    let aircraftList = [];

    // Carregar lista de aeronaves do backend
    fetch('ajax_simbrief_aircraft.php')
        .then(response => response.json())
        .then(data => {
            aircraftList = data; // Espera formato [{icao: 'A320', name: 'Airbus A320'}, ...]
        })
        .catch(err => console.error('Erro ao carregar aeronaves:', err));

    input.addEventListener('input', function(e) {
        const val = this.value;
        const cursorPosition = this.selectionStart;
        
        // Identificar o termo atual (entre vírgulas onde está o cursor)
        const lastComma = val.lastIndexOf(',', cursorPosition - 1);
        const nextComma = val.indexOf(',', cursorPosition);
        const start = lastComma + 1;
        const end = nextComma === -1 ? val.length : nextComma;
        
        const currentTerm = val.substring(start, end).trim().toUpperCase();

        if (currentTerm.length < 1) {
            suggestionsBox.classList.add('hidden');
            return;
        }

        // Filtrar
        const matches = aircraftList.filter(ac => 
            ac.icao.startsWith(currentTerm) || ac.name.toUpperCase().includes(currentTerm)
        ).slice(0, 10); // Limite de 10 sugestões

        if (matches.length > 0) {
            suggestionsBox.innerHTML = matches.map(ac => `
                <div class="p-2 hover:bg-blue-50 cursor-pointer text-sm border-b last:border-0" 
                     onclick="selectAircraft('${ac.icao}', ${start}, ${end})">
                    <span class="font-bold text-blue-800">${ac.icao}</span> 
                    <span class="text-gray-500 text-xs">- ${ac.name}</span>
                </div>
            `).join('');
            suggestionsBox.classList.remove('hidden');
        } else {
            suggestionsBox.classList.add('hidden');
        }
    });

    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.classList.add('hidden');
        }
    });

    // Função global para ser chamada pelo onclick do HTML gerado
    window.selectAircraft = function(icao, start, end) {
        const val = input.value;
        const before = val.substring(0, start);
        const after = val.substring(end);
        
        // Adiciona espaço após a vírgula se necessário
        const prefix = before.trimEnd(); 
        const suffix = after;
        
        input.value = prefix + (prefix.endsWith(',') || prefix === '' ? '' : ', ') + icao + suffix;
        suggestionsBox.classList.add('hidden');
        input.focus();
    };
});
</script>

</body>
</html>