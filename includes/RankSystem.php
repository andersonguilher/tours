<?php
// tours/includes/RankSystem.php
// VERSÃO DEFINITIVA: Aceita conexão externa ($pdo)

class RankSystem {
    
    /**
     * Calcula a patente baseada nos minutos de voo
     * @param int $flightMinutes Minutos totais de voo
     * @param PDO|null $externalPDO Conexão com o banco passada pelo index.php
     */
    public static function getRank($flightMinutes, $externalPDO = null) {
        // 1. Tenta usar a conexão externa (do index.php), se não, tenta global, se não, cria nova.
        $pdo = $externalPDO;
        
        if (!$pdo) {
            global $pdo; // Tenta pegar do escopo global
            if (!isset($pdo)) {
                $dbPath = __DIR__ . '/../config/db.php';
                if (file_exists($dbPath)) { require $dbPath; }
            }
        }

        // Dados Padrão (Fallback)
        $rank = ['title' => 'Aluno', 'stripes' => 1, 'has_star' => 0];

        // Se a conexão falhar aqui, retornamos o padrão
        if (!isset($pdo) || !$pdo) {
            return $rank;
        }

        $hours = floor($flightMinutes / 60);
        
        try {
            // Busca a patente correta
            $stmt = $pdo->prepare("SELECT * FROM tour_ranks WHERE min_hours <= ? ORDER BY min_hours DESC LIMIT 1");
            $stmt->execute([$hours]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $rank['title'] = $result['rank_title'];
                $rank['stripes'] = (int)$result['stripes'];
                $rank['has_star'] = (int)($result['has_star'] ?? 0);
            }
        } catch (Exception $e) {
            // Erro silencioso
        }

        return $rank;
    }

    public static function getNextRankProgress($flightMinutes, $externalPDO = null) {
        $pdo = $externalPDO;
        if (!$pdo) { global $pdo; if(!isset($pdo)) { $db = __DIR__.'/../config/db.php'; if(file_exists($db)) require $db; } }
        if (!isset($pdo) || !$pdo) return 0;

        $hours = floor($flightMinutes / 60);
        
        try {
            $stmt = $pdo->prepare("SELECT min_hours FROM tour_ranks WHERE min_hours > ? ORDER BY min_hours ASC LIMIT 1");
            $stmt->execute([$hours]);
            $next = $stmt->fetchColumn();
            
            if (!$next) return 100;
            
            $stmt2 = $pdo->prepare("SELECT min_hours FROM tour_ranks WHERE min_hours <= ? ORDER BY min_hours DESC LIMIT 1");
            $stmt2->execute([$hours]);
            $base = $stmt2->fetchColumn() ?: 0;
            
            return min(100, round((($hours - $base) / ($next - $base)) * 100));
        } catch (Exception $e) { return 0; }
    }
}
?>