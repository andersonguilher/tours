<?php
require_once '../includes/RankSystem.php';

// ... query SQL que soma o tempo total de voo ...
// SELECT SUM(flight_time_minutes) as total_min FROM tour_history WHERE pilot_id = ?
$total_minutes = 3200; // Exemplo: vindo do banco

$rank = RankSystem::getRank($total_minutes);
?>

<div class="pilot-card">
    <img src="assets/ranks/<?= $rank['img'] ?>" alt="Epaulet">
    <h3><?= $rank['title'] ?></h3>
    <p>Horas Totais: <?= $rank['total_hours'] ?>h</p>
    
    <div style="background:#ddd; height:10px; width:200px; border-radius:5px;">
        <div style="background:blue; height:100%; width:<?= RankSystem::getNextRankProgress($total_minutes) ?>%"></div>
    </div>
    <small>Próxima promoção em breve...</small>
</div>