<?php
// admin/manage_legs.php
// Interface para Gerenciar Rotas do Tour
require '../config/db.php';

$tour_id = $_GET['tour_id'] ?? 0;
if ($tour_id == 0) header("Location: index.php");

// Pega info do Tour
// ATUALIZADO: tabela tour_tours
$stmt = $pdo->prepare("SELECT * FROM tour_tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();

// Pega as Pernas
// ATUALIZADO: tabela tour_legs
$stmtLegs = $pdo->prepare("SELECT * FROM tour_legs WHERE tour_id = ? ORDER BY leg_order ASC");
$stmtLegs->execute([$tour_id]);
$legs = $stmtLegs->fetchAll();

// Sugestão inteligente para o próximo voo (Pega o destino da última perna)
$lastArr = '';
if (count($legs) > 0) {
    $lastLeg = end($legs);
    $lastArr = $lastLeg['arr_icao'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Rotas - <?php echo htmlspecialchars($tour['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 font-sans pb-20">

<div class="max-w-5xl mx-auto py-10 px-6">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="index.php" class="text-sm text-slate-500 hover:text-blue-600"><i class="fa-solid fa-arrow-left"></i> Voltar ao Dashboard</a>
            <h1 class="text-3xl font-bold mt-2">Gerenciar Rotas</h1>
            <p class="text-slate-500">Tour: <span class="font-bold text-blue-600"><?php echo htmlspecialchars($tour['title']); ?></span></p>
        </div>
        <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-bold">
            Total: <?php echo count($legs); ?> Pernas
        </div>
    </div>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8">
        <?php if(count($legs) == 0): ?>
            <div class="p-10 text-center text-slate-400">
                <i class="fa-solid fa-route text-4xl mb-3"></i>
                <p>Nenhuma rota cadastrada ainda.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold border-b">
                    <tr>
                        <th class="p-4 w-16 text-center">#</th>
                        <th class="p-4">Origem</th>
                        <th class="p-4">Destino</th>
                        <th class="p-4">Rota (String)</th>
                        <th class="p-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($legs as $leg): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4 text-center font-bold text-slate-400"><?php echo $leg['leg_order']; ?></td>
                        <td class="p-4 font-mono font-bold text-lg"><?php echo $leg['dep_icao']; ?></td>
                        <td class="p-4 font-mono font-bold text-lg"><?php echo $leg['arr_icao']; ?></td>
                        <td class="p-4 font-mono text-xs text-slate-500 truncate max-w-xs" title="<?php echo htmlspecialchars($leg['route_string']); ?>">
                            <?php echo $leg['route_string'] ? $leg['route_string'] : '<span class="italic text-gray-300">Direto</span>'; ?>
                        </td>
                        <td class="p-4 text-right">
                            <form action="process_leg.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta perna?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">
                                <input type="hidden" name="leg_id" value="<?php echo $leg['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded transition" title="Excluir Rota">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="bg-slate-800 text-white rounded-2xl p-6 shadow-2xl">
        <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i class="fa-solid fa-plus-circle text-green-400"></i> Adicionar Próxima Perna
        </h3>
        
        <form action="process_leg.php" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="tour_id" value="<?php echo $tour_id; ?>">

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">DE (ICAO)</label>
                <input type="text" name="dep_icao" value="<?php echo $lastArr; ?>" class="w-full bg-slate-700 border border-slate-600 rounded p-2 text-white font-mono uppercase focus:border-blue-500 outline-none" placeholder="SBRJ" required maxlength="4">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">PARA (ICAO)</label>
                <input type="text" name="arr_icao" class="w-full bg-slate-700 border border-slate-600 rounded p-2 text-white font-mono uppercase focus:border-blue-500 outline-none" placeholder="SBSP" required maxlength="4">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Rota (Opcional)</label>
                <input type="text" name="route" class="w-full bg-slate-700 border border-slate-600 rounded p-2 text-white text-sm focus:border-blue-500 outline-none" placeholder="UW22...">
            </div>

            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition h-[42px]">
                Adicionar
            </button>
        </form>
    </div>

</div>

</body>
</html>