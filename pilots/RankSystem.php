<?php
// includes/RankSystem.php
class RankSystem {
    
    public static function getRank($flightMinutes) {
        global $pdo; 
        // Garante conexão se não houver
        if (!isset($pdo)) { 
            $dbPath = __DIR__ . '/../config/db.php';
            if(file_exists($dbPath)) require $dbPath; 
        }

        $hours = floor($flightMinutes / 60);
        
        // Padrão (Fallback)
        $rank = [
            'title' => 'Aluno Piloto',
            'img' => 'rank_1.png', // Mantido para compatibilidade, mas não usado no CSS
            'stripes' => 1,
            'has_star' => 0,
            'total_hours' => $hours
        ];

        if (isset($pdo)) {
            try {
                // Busca a maior patente possível
                $stmt = $pdo->prepare("SELECT * FROM tour_ranks WHERE min_hours <= ? ORDER BY min_hours DESC LIMIT 1");
                $stmt->execute([$hours]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $rank['title'] = $result['rank_title'];
                    $rank['stripes'] = $result['stripes'];
                    $rank['has_star'] = $result['has_star'] ?? 0;
                }
            } catch (Exception $e) {
                // Falha silenciosa, usa o padrão
            }
        }

        return $rank;
    }

    public static function getNextRankProgress($flightMinutes) {
        global $pdo;
        if (!isset($pdo)) return 0;

        $hours = floor($flightMinutes / 60);
        
        try {
            // Próximo Rank
            $stmt = $pdo->prepare("SELECT min_hours FROM tour_ranks WHERE min_hours > ? ORDER BY min_hours ASC LIMIT 1");
            $stmt->execute([$hours]);
            $nextRankHours = $stmt->fetchColumn();
            
            if (!$nextRankHours) return 100; // Nível máximo
            
            // Rank Atual (Base)
            $stmtCurr = $pdo->prepare("SELECT min_hours FROM tour_ranks WHERE min_hours <= ? ORDER BY min_hours DESC LIMIT 1");
            $stmtCurr->execute([$hours]);
            $currentBase = $stmtCurr->fetchColumn() ?: 0;
            
            $totalNeeded = $nextRankHours - $currentBase;
            $earned = $hours - $currentBase;
            
            if ($totalNeeded <= 0) return 100;
            
            $pct = round(($earned / $totalNeeded) * 100);
            return ($pct > 100) ? 100 : $pct;

        } catch (Exception $e) {
            return 0;
        }
    }
}
?>