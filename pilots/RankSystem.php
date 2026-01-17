<?php
// includes/RankSystem.php

class RankSystem {
    
    // Definição das Patentes (Min Horas => Dados)
    // Você pode substituir as URLs das imagens por caminhos locais para as "epaulets"
    private static $ranks = [
        0 =>   ['title' => 'Aluno Piloto',    'stripes' => 1, 'img' => 'rank_1.png'],
        10 =>  ['title' => 'Oficial Piloto',  'stripes' => 2, 'img' => 'rank_2.png'],
        50 =>  ['title' => 'Comandante',      'stripes' => 3, 'img' => 'rank_3.png'],
        100 => ['title' => 'Comandante Sênior', 'stripes' => 4, 'img' => 'rank_4.png'],
        500 => ['title' => 'Instrutor Master',  'stripes' => 4, 'img' => 'rank_gold.png'] // Estrela dourada?
    ];

    public static function getRank($flightMinutes) {
        $hours = floor($flightMinutes / 60);
        $currentRank = self::$ranks[0]; // Padrão

        foreach (self::$ranks as $minHours => $rankData) {
            if ($hours >= $minHours) {
                $currentRank = $rankData;
            } else {
                break; // Se as horas do piloto são menores que o requisito, para no anterior
            }
        }
        
        // Adiciona as horas formatadas ao array de retorno
        $currentRank['total_hours'] = $hours;
        return $currentRank;
    }

    // Exemplo de uso para barra de progresso para próxima patente
    public static function getNextRankProgress($flightMinutes) {
        $hours = floor($flightMinutes / 60);
        $nextGoal = 100000; // Infinito se for o último
        
        foreach (array_keys(self::$ranks) as $minHours) {
            if ($minHours > $hours) {
                $nextGoal = $minHours;
                break;
            }
        }
        
        if ($nextGoal == 100000) return 100; // Já no topo
        
        // Cálculo simples de porcentagem
        // Ex: Tem 30h, Próximo 50h. Progresso = 30/50 * 100 = 60%
        // Pode refinar para ser progresso *dentro* do nível
        return round(($hours / $nextGoal) * 100);
    }
}
?>