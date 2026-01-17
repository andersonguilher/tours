<?php
// pilots/passport_book.php
// VERSÃO 7.0: SOMBRAS REFORÇADAS + CAPA FINAL FECHADA
define('BASE_PATH', dirname(__DIR__));
require '../config/db.php';

// --- 1. IDENTIFICAÇÃO DO PILOTO ---
$pilot_id = $_GET['pilot_id'] ?? 0;

if ($pilot_id == 0 && function_exists('wp_get_current_user')) {
    $current_user = wp_get_current_user();
    $pilot_id = $current_user->ID;
}

// --- 2. CONEXÃO DB PILOTOS ---
$pdoPilots = null;
try {
    $host_p = defined('DB_PILOTOS_HOST') ? DB_PILOTOS_HOST : 'localhost'; 
    $user_p = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
    $pass_p = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
    $name_p = 'u378005298_hEatD'; 

    $pdoPilots = new PDO("mysql:host=$host_p;dbname=$name_p;charset=utf8mb4", $user_p, $pass_p);
    $pdoPilots->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoPilots->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// --- 3. DADOS PADRÃO ---
$pilot = [
    'nome' => 'PILOTO', 'sobrenome' => 'DESCONHECIDO',
    'foto' => '', 'matricula' => '0000', 'nacionalidade' => 'BRA',
    'ivao' => '---', 'vatsim' => '---',
    'admissao' => date('d/m/Y'), 'nascimento' => '01/01/1980',
    'nascimento_mrz' => '800101'
];

// --- 4. BUSCA DADOS REAIS ---
if ($pilot_id > 0 && $pdoPilots) {
    try {
        $stmtP = $pdoPilots->prepare("SELECT * FROM Dados_dos_Pilotos WHERE id_piloto = ? LIMIT 1");
        $stmtP->execute([$pilot_id]);
        $res = $stmtP->fetch();

        if ($res) {
            $pilot['nome'] = !empty($res['first_name']) ? strtoupper(trim($res['first_name'])) : 'PILOTO';
            $pilot['sobrenome'] = !empty($res['last_name']) ? strtoupper(trim($res['last_name'])) : '';
            $pilot['matricula'] = $res['matricula'] ?? '0000';
            $pilot['ivao'] = $res['ivao_id'] ?? '---';
            $pilot['vatsim'] = $res['vatsim_id'] ?? '---';
            if (!empty($res['foto_perfil'])) $pilot['foto'] = $res['foto_perfil'];
            if (!empty($res['post_date_gmt'])) $pilot['admissao'] = date('d/m/Y', strtotime($res['post_date_gmt']));
            if (!empty($res['data_de_nascimento'])) {
                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $res['data_de_nascimento'], $matches)) {
                    $d = sprintf("%02d", $matches[1]); $m = sprintf("%02d", $matches[2]); $y = $matches[3];
                    if (strlen($y) == 2) { $y = ($y > date('y')) ? "19$y" : "20$y"; if ($y > date('Y')) $y -= 100; }
                    $pilot['nascimento'] = "$d/$m/$y";
                    $pilot['nascimento_mrz'] = substr($y, 2, 2) . $m . $d;
                } else {
                    $pilot['nascimento'] = $res['data_de_nascimento'];
                }
            }
        }
    } catch (Exception $e) {}
}

if (empty($pilot['foto'])) {
    $pilot['foto'] = "https://ui-avatars.com/api/?name=" . urlencode($pilot['nome']) . "&background=e2e8f0&color=1e293b&size=200";
}

// --- 5. GERADOR MRZ ---
$mrz_name = str_replace(' ', '<', $pilot['sobrenome']) . "<<" . str_replace(' ', '<', $pilot['nome']);
$mrz1 = "P<BRA" . substr($mrz_name . str_repeat('<', 44), 0, 39);
$clean_mat = substr(preg_replace('/[^A-Z0-9]/', '', $pilot['matricula']), 0, 9);
$mrz2 = str_pad($clean_mat, 9, '<') . "0BRA" . $pilot['nascimento_mrz'] . "M" . date('ymd', strtotime('+10 years')) . "<<<<<<<<<<<<<<<<00";
$mrz2 = substr($mrz2, 0, 44);

// --- 6. HISTÓRICO ---
$history = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tour_history WHERE pilot_id = ? ORDER BY date_flown DESC");
    $stmt->execute([$pilot_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$stamps_per_page = 8;
$history_chunks = array_chunk($history, $stamps_per_page);
if (empty($history_chunks)) $history_chunks = [[]]; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Passaporte - <?php echo htmlspecialchars($pilot['nome']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700;900&family=OCR-B&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --passport-bg: #f8fafc;
            --passport-cover: #1e293b;
            --gold: #fbbf24;
            --text-main: #334155;
            --text-light: #94a3b8;
        }

        body {
            margin: 0; padding: 0;
            background-color: var(--bg-color);
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 30px 30px;
            font-family: 'Montserrat', sans-serif;
            color: white;
            height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            overflow: hidden;
        }

        .stage {
            position: relative;
            width: 100%; max-width: 1000px;
            height: 600px;
            display: flex; justify-content: center; align-items: center;
        }

        .passport-book {
            display: flex;
            background: var(--passport-bg);
            border-radius: 12px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            position: relative;
            width: 900px; /* Aberto */
            height: 580px;
            transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1), border-radius 0.6s ease;
        }

        /* ESTADO FECHADO (Capa Frente ou Verso) */
        .passport-book.book-closed {
            width: 450px; 
            border-radius: 12px 12px 12px 12px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .page {
            flex: 1; min-width: 450px; height: 100%;
            position: relative; overflow: hidden;
            display: none; flex-direction: column;
            background: white;
            animation: fadeIn 0.5s ease;
        }
        
        .page.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- CORREÇÃO DAS SOMBRAS (LOMBADA) --- */
        /* Usando linear-gradient para uma sombra mais perceptível no centro */
        .spine-shadow-left {
            background: linear-gradient(to right, #ffffff 90%, #e2e8f0 100%);
            box-shadow: inset -20px 0 30px -10px rgba(0,0,0,0.15); 
            border-right: 1px solid #cbd5e1;
            z-index: 2;
        }
        .spine-shadow-right {
            background: linear-gradient(to left, #ffffff 90%, #e2e8f0 100%);
            box-shadow: inset 20px 0 30px -10px rgba(0,0,0,0.15);
            z-index: 2;
        }

        .page-cover {
            background: var(--passport-cover);
            color: var(--gold);
            text-align: center; justify-content: center; align-items: center;
            width: 100%; height: 100%;
        }
        .cover-icon { font-size: 5rem; margin-bottom: 2rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); }
        .cover-title { font-weight: 900; font-size: 2.5rem; letter-spacing: 4px; line-height: 1; margin: 0; }
        .cover-subtitle { font-weight: 300; font-size: 1rem; letter-spacing: 2px; opacity: 0.8; margin-top: 10px; }

        .id-grid { padding: 40px; display: grid; grid-template-columns: 140px 1fr; gap: 30px; height: 100%; align-content: start; }
        .id-header { grid-column: 1 / -1; border-bottom: 2px solid var(--text-main); padding-bottom: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-end; }
        .id-label-main { font-weight: 800; color: var(--text-main); text-transform: uppercase; font-size: 0.9rem; }
        .pilot-img { width: 140px; height: 180px; border-radius: 8px; object-fit: cover; filter: grayscale(100%); mix-blend-mode: multiply; background: #e2e8f0; }
        .data-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .field { display: flex; flex-direction: column; }
        .label { font-size: 0.6rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
        .value { font-size: 0.9rem; color: var(--text-main); font-weight: 700; text-transform: uppercase; }
        .val-lg { font-size: 1.1rem; font-weight: 900; }
        .mrz { grid-column: 1 / -1; margin-top: auto; font-family: 'OCR-B', monospace; color: #475569; letter-spacing: 2px; line-height: 1.6; font-size: 14px; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 6px; word-break: break-all; }

        .visas-grid { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(4, 1fr); gap: 15px; padding: 40px; height: 100%; }
        .visa-header { padding: 20px 40px 0; text-align: center; font-size: 0.7rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 1px; }
        .stamp { border: 1px dashed #cbd5e1; border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.2s; }
        .stamp.filled { border: 2px solid; background: white; border-style: solid; }
        .stamp-code { font-size: 1.8rem; font-weight: 900; color: #e2e8f0; }
        .stamp.filled .stamp-code { color: inherit; }
        .c-1 { border-color: #3b82f6; color: #3b82f6; } .c-2 { border-color: #10b981; color: #10b981; } .c-3 { border-color: #f59e0b; color: #f59e0b; } .c-4 { border-color: #6366f1; color: #6366f1; }

        .page-num { position: absolute; bottom: 15px; width: 100%; text-align: center; font-size: 0.7rem; color: var(--text-light); }

        .controls { position: absolute; width: 110%; top: 50%; transform: translateY(-50%); display: flex; justify-content: space-between; padding: 0 20px; pointer-events: none; }
        .btn-nav { pointer-events: auto; background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; font-size: 1.5rem; }
        .btn-nav:hover { background: var(--gold); color: #000; transform: scale(1.1); box-shadow: 0 0 20px rgba(251, 191, 36, 0.5); }
        .btn-nav.disabled { opacity: 0; pointer-events: none; }

        @media (max-width: 950px) {
            .passport-book { width: 90% !important; height: 600px; flex-direction: column; }
            .page { min-width: 100%; }
            .controls { width: 100%; padding: 0; }
        }
    </style>
</head>
<body>

<div class="stage">
    
    <div class="passport-book" id="bookContainer">
        
        <div class="page page-cover active" id="p1">
            <i class="fa-solid fa-earth-americas cover-icon"></i>
            <h1 class="cover-title">PASSPORT</h1>
            <h2 class="cover-subtitle">KAFLY VIRTUAL AIRLINE</h2>
            <div style="margin-top: 50px; border: 2px solid var(--gold); padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">OFFICIAL DOCUMENT</div>
        </div>

        <div class="page spine-shadow-left" id="p2">
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; padding: 40px; text-align: center; color: var(--text-light);">
                <div>
                    <p style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; line-height: 1.8;">
                        Este documento certifica a qualificação<br>e o histórico operacional do piloto.
                    </p>
                    <div style="width: 40px; height: 2px; background: #cbd5e1; margin: 30px auto;"></div>
                    <p style="font-size: 0.7rem; font-weight: bold;">PROPERTY OF KAFLY SYSTEMS</p>
                </div>
            </div>
        </div>

        <div class="page spine-shadow-right" id="p3">
            <div class="id-grid">
                <div class="id-header">
                    <span class="id-label-main">Identification</span>
                    <span class="id-label-main" style="color: var(--text-light)">BRA / INT</span>
                </div>
                <img src="<?php echo htmlspecialchars($pilot['foto']); ?>" class="pilot-img" onerror="this.src='https://ui-avatars.com/api/?name=Pilot&background=ddd&size=150'">
                <div class="data-fields">
                    <div class="field" style="grid-column: span 2;"><span class="label">Surname</span><span class="val-lg"><?php echo htmlspecialchars($pilot['sobrenome']); ?></span></div>
                    <div class="field" style="grid-column: span 2;"><span class="label">Given Names</span><span class="value"><?php echo htmlspecialchars($pilot['nome']); ?></span></div>
                    <div class="field"><span class="label">Nationality</span><span class="value"><?php echo htmlspecialchars($pilot['nacionalidade']); ?></span></div>
                    <div class="field"><span class="label">Date of Birth</span><span class="value"><?php echo htmlspecialchars($pilot['nascimento']); ?></span></div>
                    <div class="field"><span class="label">Callsign</span><span class="value"><?php echo htmlspecialchars($pilot['matricula']); ?></span></div>
                    <div class="field"><span class="label">Issued</span><span class="value"><?php echo htmlspecialchars($pilot['admissao']); ?></span></div>
                </div>
                <div class="mrz"><?php echo $mrz1; ?><br><?php echo $mrz2; ?></div>
            </div>
            <div class="page-num">01</div>
        </div>

        <?php 
        $page_counter = 2;
        foreach ($history_chunks as $chunk): 
            $page_counter++;
            $side_class = ($page_counter % 2 != 0) ? 'spine-shadow-right' : 'spine-shadow-left';
        ?>
            <div class="page <?php echo $side_class; ?>">
                <div class="visa-header">Visas & Entry Permits</div>
                <div class="visas-grid">
                    <?php 
                    for ($i = 0; $i < $stamps_per_page; $i++):
                        if (isset($chunk[$i])):
                            $h = $chunk[$i];
                            $date = date("d.M.Y", strtotime($h['date_flown']));
                            $colorClass = 'c-' . rand(1, 4);
                    ?>
                        <div class="stamp filled <?php echo $colorClass; ?>">
                            <span style="font-size: 0.5rem; font-weight: bold; margin-bottom: 2px;">ENTRY</span>
                            <span class="stamp-code"><?php echo $h['arr_icao']; ?></span>
                            <span style="font-size: 0.55rem; font-weight: 600; margin-top: 5px; opacity: 0.7;"><?php echo $date; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="stamp"><span class="stamp-code" style="opacity: 0.1">VOID</span></div>
                    <?php endif; endfor; ?>
                </div>
                <div class="page-num"><?php echo str_pad($page_counter, 2, '0', STR_PAD_LEFT); ?></div>
            </div>
        <?php endforeach; ?>

        <?php if (($page_counter) % 2 != 0): ?>
            <div class="page spine-shadow-right"></div>
        <?php endif; ?>

        <div class="page page-cover" id="endPage">
            <i class="fa-solid fa-plane-up cover-icon" style="font-size: 3rem;"></i>
            <h2 class="cover-subtitle" style="opacity: 1;">KAFLY SYSTEMS</h2>
        </div>

    </div>

    <div class="controls">
        <div class="btn-nav" id="prevBtn" onclick="changePage(-1)"><i class="fa-solid fa-chevron-left"></i></div>
        <div class="btn-nav" id="nextBtn" onclick="changePage(1)"><i class="fa-solid fa-chevron-right"></i></div>
    </div>
</div>

<script>
    let pages = document.querySelectorAll('.page');
    let totalPages = pages.length;
    let currentIndex = 0; 
    let bookContainer = document.getElementById('bookContainer');
    let isMobile = window.innerWidth <= 950;

    function init() {
        updateView();
    }

    function updateView() {
        pages.forEach(p => p.classList.remove('active'));

        if (isMobile) {
            pages[currentIndex].classList.add('active');
            bookContainer.classList.remove('book-closed'); 
        } else {
            // DESKTOP LOGIC
            if (currentIndex === 0) {
                // CAPA FRENTE FECHADA
                pages[0].classList.add('active');
                bookContainer.classList.add('book-closed');
            } 
            else if (currentIndex === totalPages - 1) {
                // CAPA VERSO FECHADA
                pages[totalPages - 1].classList.add('active');
                bookContainer.classList.add('book-closed');
            }
            else {
                // LIVRO ABERTO
                bookContainer.classList.remove('book-closed');
                if (pages[currentIndex]) pages[currentIndex].classList.add('active');
                if (pages[currentIndex+1]) pages[currentIndex+1].classList.add('active');
            }
        }

        // Botoes
        document.getElementById('prevBtn').classList.toggle('disabled', currentIndex === 0);
        document.getElementById('nextBtn').classList.toggle('disabled', currentIndex === totalPages - 1);
    }

    function changePage(dir) {
        let nextIndex = currentIndex;

        if (isMobile) {
            nextIndex += dir;
        } else {
            if (dir === 1) {
                // AVANÇAR
                if (currentIndex === 0) {
                    nextIndex = 1; // Abrir
                } else {
                    nextIndex += 2;
                    // Se passar do limite, vai para a capa final
                    if (nextIndex >= totalPages) nextIndex = totalPages - 1;
                    // Se cair na capa final (que deve ser fechada), garante index exato
                    if (nextIndex == totalPages - 1) nextIndex = totalPages - 1; 
                }
            } else {
                // VOLTAR
                if (currentIndex === totalPages - 1) {
                    // Estava na capa final fechada, volta para a par anterior aberta
                    // A capa final é sempre "direita" ou "sozinha". 
                    // A anterior aberta seria (Total-1) - 2 se Total for par?
                    // Vamos simplificar: A página anterior à capa final é a (Total-2).
                    // Mas como mostramos pares (1,2), (3,4)...
                    // Se Total=6 (0,1,2,3,4,5). CapaFinal=5.
                    // Anterior aberta deve mostrar 3 e 4. O index deve ser 3.
                    // 5 - 2 = 3.
                    nextIndex -= 2;
                    // Ajuste fino se necessário para garantir impar
                    if (nextIndex % 2 == 0 && nextIndex != 0) nextIndex -= 1;
                } else if (currentIndex === 1) {
                    nextIndex = 0; // Fechar capa frente
                } else {
                    nextIndex -= 2;
                }
            }
        }
        
        // Limites de Segurança
        if (nextIndex < 0) nextIndex = 0;
        if (nextIndex >= totalPages) nextIndex = totalPages - 1;

        currentIndex = nextIndex;
        updateView();
    }

    window.addEventListener('resize', () => {
        let newMobile = window.innerWidth <= 950;
        if (newMobile !== isMobile) {
            isMobile = newMobile;
            currentIndex = 0;
            updateView();
        }
    });

    init();
</script>

</body>
</html>