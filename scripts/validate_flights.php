<?php
// scripts/validate_flights.php
// VERSÃO N8N COMPATIBLE: Validação de Rotas (Sem Landing Rate)
// Este script valida se o piloto cumpriu a perna baseada nos dados recebidos

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';

class FlightValidator {
    private $pdoTracker;
    private $pdoPilots;
    private $settings;
    private $pilotsOnline = [];

    // Configurações Padrão
    private $pilotTable = 'Dados_dos_Pilotos';
    private $colIvaoId = 'ivao_id';
    private $colMatricula = 'matricula';
    private $colPilotId = 'id_piloto';

    public function __construct($pdoTracker) {
        $this->pdoTracker = $pdoTracker;
        $this->loadSettings();
        $this->connectPilotDB();
    }

    private function loadSettings() {
        $possiblePaths = [
            BASE_PATH . '/settings.json',
            BASE_PATH . '/../settings.json',
            dirname(BASE_PATH) . '/settings.json'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $this->settings = json_decode(file_get_contents($path), true);
                break;
            }
        }

        if (isset($this->settings['database_mappings'])) {
            $map = $this->settings['database_mappings'];
            $this->pilotTable = $map['pilots_table'] ?? $this->pilotTable;
            $this->colIvaoId = $map['columns']['ivao_id'] ?? $this->colIvaoId;
            $this->colMatricula = $map['columns']['matricula'] ?? $this->colMatricula;
        }
    }

    private function connectPilotDB() {
        try {
            $host = defined('DB_SERVERNAME') ? DB_SERVERNAME : 'localhost';
            $user = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
            $pass = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
            $name = defined('DB_PILOTOS_NAME') ? DB_PILOTOS_NAME : 'u378005298_hEatD';

            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $this->pdoPilots = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            echo "[INFO] Conectado ao Banco de Pilotos: {$name}\n";
        } catch (PDOException $e) {
            $this->log("CRITICAL ERROR: Falha ao conectar no DB Pilotos. " . $e->getMessage());
            exit(1); 
        }
    }

    public function fetchNetworkData() {
        // Se o N8N estiver populando um arquivo local, leia-o aqui.
        // Se o N8N estiver chamando este script, esta função pode ser adaptada.
        // Mantendo compatibilidade com whazzup json padrão por enquanto.
        
        $localPath = dirname(BASE_PATH) . '/skymetrics/api/whazzup.json';

        if (file_exists($localPath)) {
            $jsonData = file_get_contents($localPath);
            $data = json_decode($jsonData, true);
            
            if (isset($data['clients']['pilots'])) {
                $this->pilotsOnline = $data['clients']['pilots'];
            } elseif (isset($data[0]['clients']['pilots'])) {
                $this->pilotsOnline = $data[0]['clients']['pilots'];
            }
            echo "[INFO] Processando " . count($this->pilotsOnline) . " conexões (Fonte: N8N/JSON)...\n";
        } else {
            $this->log("[WARN] Arquivo whazzup.json não encontrado. Aguardando atualização do N8N.");
            return;
        }
    }

    public function runValidation() {
        if (empty($this->pilotsOnline)) return;
        foreach ($this->pilotsOnline as $flight) {
            $this->processFlight($flight);
        }
    }

    private function processFlight($flight) {
        $networkId = $flight['userId'] ?? null;
        $liveCallsign = strtoupper($flight['callsign'] ?? '');

        if (!$networkId) return;

        $pilotData = $this->getPilotFromDB($networkId);
        if (!$pilotData) return;

        $dbCallsign = strtoupper($pilotData[$this->colMatricula]);
        if ($liveCallsign !== $dbCallsign) return;

        $pilotId = $pilotData['post_id'] ?? $pilotData[$this->colPilotId] ?? null;
        if (!$pilotId) return;

        echo " -> Analisando {$liveCallsign} (ID: {$pilotId})...\n";

        $flightPlan = $flight['flightPlan'] ?? null;
        $lastTrack = $flight['lastTrack'] ?? null;

        if (!$flightPlan || !$lastTrack) return;

        $telemetry = [
            'callsign'       => $liveCallsign,
            'dep_icao'       => $flightPlan['departureId'] ?? '',
            'arr_icao'       => $flightPlan['arrivalId'] ?? '',
            'aircraft'       => $flightPlan['aircraft']['icaoCode'] ?? '',
            'state'          => $lastTrack['state'] ?? '', 
            'on_ground'      => $lastTrack['onGround'] ?? false,
            'groundspeed'    => $lastTrack['groundSpeed'] ?? 0
        ];

        $activeLeg = $this->getActiveLeg($pilotId);
        if ($activeLeg) {
            $this->validateLegRules($activeLeg, $telemetry, $pilotId);
        }
    }

    private function getPilotFromDB($ivaoId) {
        try {
            $sql = "SELECT *, {$this->colMatricula} as matricula FROM {$this->pilotTable} WHERE {$this->colIvaoId} = ? LIMIT 1";
            $stmt = $this->pdoPilots->prepare($sql);
            $stmt->execute([$ivaoId]);
            return $stmt->fetch();
        } catch (PDOException $e) { return null; }
    }

    private function getActiveLeg($pilotId) {
        $sql = "
            SELECT p.id as progress_id, p.current_leg_id, t.rules_json, t.id as tour_real_id, t.title as tour_title,
                   l.dep_icao as leg_dep, l.arr_icao as leg_arr, l.id as leg_real_id, l.leg_order
            FROM pilot_tour_progress p
            JOIN tours t ON p.tour_id = t.id
            JOIN tour_legs l ON p.current_leg_id = l.id
            WHERE p.pilot_id = ? AND p.status = 'In Progress'
        ";
        $stmt = $this->pdoTracker->prepare($sql);
        $stmt->execute([$pilotId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validateLegRules($legData, $telemetry, $pilotId) {
        if ($telemetry['dep_icao'] != $legData['leg_dep'] || $telemetry['arr_icao'] != $legData['leg_arr']) return;

        $rules = json_decode($legData['rules_json'], true);
        if (isset($rules['allowed_aircraft']) && !empty($rules['allowed_aircraft'])) {
            $allowedRaw = is_array($rules['allowed_aircraft']) ? $rules['allowed_aircraft'] : explode(',', $rules['allowed_aircraft']);
            $allowed = array_map('trim', $allowedRaw);
            if (!in_array($telemetry['aircraft'], $allowed)) {
                echo "    -> Aeronave Incorreta ({$telemetry['aircraft']}).\n";
                return;
            }
        }

        $isLanded = ($telemetry['state'] == 'Landed' || $telemetry['state'] == 'On Blocks' || ($telemetry['on_ground'] && $telemetry['groundspeed'] < 30));

        if ($isLanded) {
            $this->completeLeg($legData, $telemetry, $pilotId);
        }
    }

    private function completeLeg($legData, $telemetry, $pilotId) {
        echo "    -> [SUCESSO] {$telemetry['callsign']} completou a perna.\n";

        // LANDING RATE É NULL POIS NÃO TEMOS COMO VERIFICAR VIA N8N ATUALMENTE
        $landingRate = NULL;

        try {
            $this->pdoTracker->beginTransaction();

            $stmtLog = $this->pdoTracker->prepare("
                INSERT INTO pilot_leg_history (pilot_id, tour_id, leg_id, callsign, aircraft, date_flown, network, landing_rate)
                VALUES (?, ?, ?, ?, ?, NOW(), 'IVAO', ?)
            ");
            $stmtLog->execute([
                $pilotId, 
                $legData['tour_real_id'], 
                $legData['leg_real_id'], 
                $telemetry['callsign'], 
                $telemetry['aircraft'], 
                $landingRate // Salvando NULL
            ]);

            $stmtNext = $this->pdoTracker->prepare("SELECT id FROM tour_legs WHERE tour_id = ? AND leg_order > ? ORDER BY leg_order ASC LIMIT 1");
            $stmtNext->execute([$legData['tour_real_id'], $legData['leg_order']]);
            $nextLeg = $stmtNext->fetch(PDO::FETCH_ASSOC);

            if ($nextLeg) {
                $stmtUpd = $this->pdoTracker->prepare("UPDATE pilot_tour_progress SET current_leg_id = ?, status = 'In Progress', last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$nextLeg['id'], $legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "✅ Perna Concluída: {$legData['leg_dep']} -> {$legData['leg_arr']}");
            } else {
                $stmtUpd = $this->pdoTracker->prepare("UPDATE pilot_tour_progress SET status = 'Completed', completed_at = NOW(), last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "🏆 TOUR FINALIZADO! Parabéns comandante.");
            }

            $this->pdoTracker->commit();

        } catch (PDOException $e) {
            $this->pdoTracker->rollBack();
            $this->log("[DB ERROR] Erro ao salvar progresso: " . $e->getMessage());
        }
    }

    private function sendDiscordWebhook($callsign, $tourName, $details) {
        $webhookurl = $this->settings['discord_webhook_url'] ?? "";
        if (strpos($webhookurl, 'http') === false) return;

        $json_data = json_encode([
            "username" => "Kafly Tracker",
            "embeds" => [[
                "title" => "✈️ Atualização de Tour",
                "color" => hexdec("22c55e"),
                "fields" => [
                    ["name" => "Piloto", "value" => $callsign, "inline" => true],
                    ["name" => "Tour", "value" => $tourName, "inline" => true],
                    ["name" => "Status", "value" => $details, "inline" => false]
                ],
                "footer" => ["text" => "Kafly Systems"],
                "timestamp" => date("c")
            ]]
        ]);

        $ch = curl_init($webhookurl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }

    private function log($msg) {
        echo date("[Y-m-d H:i:s] ") . $msg . "\n";
    }

    private function assignBadge($pilotId, $tourId, $tourTitle, $callsign) {
        // Busca qual medalha está vinculada a este tour
        $stmt = $this->pdoTracker->prepare("SELECT badge_id FROM tours WHERE id = ?");
        $stmt->execute([$tourId]);
        $tourData = $stmt->fetch();
        
        if ($tourData && !empty($tourData['badge_id'])) {
            $badgeId = $tourData['badge_id'];
            
            // Verifica se o piloto já tem essa medalha para não duplicar
            $check = $this->pdoTracker->prepare("SELECT id FROM pilot_badges WHERE pilot_id = ? AND badge_id = ?");
            $check->execute([$pilotId, $badgeId]);
            
            if (!$check->fetch()) {
                // Entrega a medalha
                $insert = $this->pdoTracker->prepare("INSERT INTO pilot_badges (pilot_id, badge_id, awarded_at) VALUES (?, ?, NOW())");
                $insert->execute([$pilotId, $badgeId]);
                
                // Avisa no console e no Discord
                echo "    -> [MEDALHA] Entregue medalha ID $badgeId para o piloto.\n";
                $this->sendDiscordWebhook($callsign, $tourTitle, "🎖️ Medalha Conquistada Automaticamente!");
            }
        }
    }
}

if (php_sapi_name() !== 'cli') die("Acesso via linha de comando apenas.");

if (isset($pdo)) {
    $validator = new FlightValidator($pdo);
    $validator->fetchNetworkData();
    $validator->runValidation();
}
?>