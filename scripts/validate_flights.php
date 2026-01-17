<?php
// scripts/validate_flights.php
// VERSÃO: MULTI-NETWORK (IVAO/VATSIM)
// Este script valida se o piloto cumpriu a perna baseada nos dados recebidos do wzp.json

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
        // Caminho absoluto conforme solicitado
        $localPath = '/var/www/kafly_user/data/www/kafly.com.br/mapa/3d/data/wzp.json';

        if (file_exists($localPath)) {
            $jsonData = file_get_contents($localPath);
            $data = json_decode($jsonData, true);
            
            // O wzp.json costuma ser um array raiz de objetos. 
            // Verificamos se é um array direto ou se está aninhado.
            if (is_array($data)) {
                if (isset($data[0]['callsign']) || isset($data[0]['id'])) {
                    // Formato array direto (padrão esperado para o ficheiro wzp.json combinado)
                    $this->pilotsOnline = $data;
                } elseif (isset($data['clients']['pilots'])) {
                    // Fallback para formato whazzup padrão
                    $this->pilotsOnline = $data['clients']['pilots'];
                }
            }
            
            echo "[INFO] Processando " . count($this->pilotsOnline) . " conexões (Fonte: wzp.json)...\n";
        } else {
            $this->log("[WARN] Arquivo wzp.json não encontrado em: $localPath");
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
        // Detecção da Rede e Seleção do ID correto
        $network = isset($flight['network']) ? strtoupper($flight['network']) : 'IVAO';
        
        if ($network === 'VATSIM') {
            // Na VATSIM usamos o campo 'id'
            $networkId = $flight['id'] ?? null;
        } else {
            // Na IVAO usamos o campo 'userId'
            $networkId = $flight['userId'] ?? null;
        }

        $liveCallsign = strtoupper($flight['callsign'] ?? '');

        if (!$networkId) return;

        // Busca o piloto no banco usando o ID da rede
        $pilotData = $this->getPilotFromDB($networkId);
        if (!$pilotData) return;

        $dbCallsign = strtoupper($pilotData[$this->colMatricula]);
        
        // Verifica se o callsign voado é igual à matrícula do piloto
        if ($liveCallsign !== $dbCallsign) return;

        $pilotId = $pilotData['post_id'] ?? $pilotData[$this->colPilotId] ?? null;
        if (!$pilotId) return;

        echo " -> Analisando {$liveCallsign} (Rede: {$network} | ID: {$networkId})...\n";

        $flightPlan = $flight['flightPlan'] ?? null;
        $lastTrack = $flight['lastTrack'] ?? null;

        if (!$flightPlan || !$lastTrack) return;

        // Monta a telemetria unificada
        $telemetry = [
            'callsign'       => $liveCallsign,
            'dep_icao'       => $flightPlan['departureId'] ?? '',
            'arr_icao'       => $flightPlan['arrivalId'] ?? '',
            'aircraft'       => $flightPlan['aircraft']['icaoCode'] ?? ($flightPlan['aircraftId'] ?? ''),
            'state'          => $lastTrack['state'] ?? '', 
            'on_ground'      => $lastTrack['onGround'] ?? false,
            'groundspeed'    => $lastTrack['groundSpeed'] ?? 0,
            'network'        => $network
        ];

        $activeLeg = $this->getActiveLeg($pilotId);
        if ($activeLeg) {
            $this->validateLegRules($activeLeg, $telemetry, $pilotId);
        }
    }

    private function getPilotFromDB($networkId) {
        try {
            // Busca pelo ID da rede (IVAO ID ou VATSIM ID)
            $sql = "SELECT *, {$this->colMatricula} as matricula FROM {$this->pilotTable} WHERE {$this->colIvaoId} = ? LIMIT 1";
            $stmt = $this->pdoPilots->prepare($sql);
            $stmt->execute([$networkId]);
            return $stmt->fetch();
        } catch (PDOException $e) { return null; }
    }

    private function getActiveLeg($pilotId) {
        $sql = "
            SELECT p.id as progress_id, p.current_leg_id, t.rules_json, t.id as tour_real_id, t.title as tour_title,
                   l.dep_icao as leg_dep, l.arr_icao as leg_arr, l.id as leg_real_id, l.leg_order
            FROM tour_progress p
            JOIN tour_tours t ON p.tour_id = t.id
            JOIN tour_legs l ON p.current_leg_id = l.id
            WHERE p.pilot_id = ? AND p.status = 'In Progress'
        ";
        $stmt = $this->pdoTracker->prepare($sql);
        $stmt->execute([$pilotId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validateLegRules($legData, $telemetry, $pilotId) {
        // Verifica aeroportos de saída e chegada
        if ($telemetry['dep_icao'] != $legData['leg_dep'] || $telemetry['arr_icao'] != $legData['leg_arr']) return;

        // Verifica regras de aeronave
        $rules = json_decode($legData['rules_json'], true);
        if (isset($rules['allowed_aircraft']) && !empty($rules['allowed_aircraft'])) {
            $allowedRaw = is_array($rules['allowed_aircraft']) ? $rules['allowed_aircraft'] : explode(',', $rules['allowed_aircraft']);
            $allowed = array_map('trim', $allowedRaw);
            if (!in_array($telemetry['aircraft'], $allowed)) {
                echo "    -> Aeronave Incorreta ({$telemetry['aircraft']}).\n";
                return;
            }
        }

        // Lógica de aterragem completa
        // Considera landed se status for Landed/On Blocks ou se estiver no chão com baixa velocidade
        $isLanded = ($telemetry['state'] == 'Landed' || $telemetry['state'] == 'On Blocks' || ($telemetry['on_ground'] && $telemetry['groundspeed'] < 30));

        if ($isLanded) {
            $this->completeLeg($legData, $telemetry, $pilotId);
        }
    }

    private function completeLeg($legData, $telemetry, $pilotId) {
        echo "    -> [SUCESSO] {$telemetry['callsign']} completou a perna na rede {$telemetry['network']}.\n";

        $landingRate = NULL; // Não disponível no wzp.json padrão

        try {
            $this->pdoTracker->beginTransaction();

            // Insere no histórico com a rede correta
            $stmtLog = $this->pdoTracker->prepare("
                INSERT INTO tour_history (pilot_id, tour_id, leg_id, callsign, aircraft, date_flown, network, landing_rate)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmtLog->execute([
                $pilotId, 
                $legData['tour_real_id'], 
                $legData['leg_real_id'], 
                $telemetry['callsign'], 
                $telemetry['aircraft'], 
                $telemetry['network'], // IVAO ou VATSIM
                $landingRate 
            ]);

            // Verifica se há próxima perna
            $stmtNext = $this->pdoTracker->prepare("SELECT id FROM tour_legs WHERE tour_id = ? AND leg_order > ? ORDER BY leg_order ASC LIMIT 1");
            $stmtNext->execute([$legData['tour_real_id'], $legData['leg_order']]);
            $nextLeg = $stmtNext->fetch(PDO::FETCH_ASSOC);

            if ($nextLeg) {
                // Atualiza para a próxima perna
                $stmtUpd = $this->pdoTracker->prepare("UPDATE tour_progress SET current_leg_id = ?, status = 'In Progress', last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$nextLeg['id'], $legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "✅ Perna Concluída: {$legData['leg_dep']} -> {$legData['leg_arr']} ({$telemetry['network']})");
            } else {
                // Finaliza o tour
                $stmtUpd = $this->pdoTracker->prepare("UPDATE tour_progress SET status = 'Completed', completed_at = NOW(), last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "🏆 TOUR FINALIZADO! Parabéns comandante.");
                
                // Tenta atribuir medalha se houver
                $this->assignBadge($pilotId, $legData['tour_real_id'], $legData['tour_title'], $telemetry['callsign']);
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
        $stmt = $this->pdoTracker->prepare("SELECT badge_id FROM tour_tours WHERE id = ?");
        $stmt->execute([$tourId]);
        $tourData = $stmt->fetch();
        
        if ($tourData && !empty($tourData['badge_id'])) {
            $badgeId = $tourData['badge_id'];
            
            $check = $this->pdoTracker->prepare("SELECT id FROM tour_pilot_badges WHERE pilot_id = ? AND badge_id = ?");
            $check->execute([$pilotId, $badgeId]);
            
            if (!$check->fetch()) {
                $insert = $this->pdoTracker->prepare("INSERT INTO tour_pilot_badges (pilot_id, badge_id, awarded_at) VALUES (?, ?, NOW())");
                $insert->execute([$pilotId, $badgeId]);
                
                echo "    -> [MEDALHA] Entregue medalha ID $badgeId para o piloto.\n";
                $this->sendDiscordWebhook($callsign, $tourTitle, "🎖️ Medalha Conquistada Automaticamente!");
            }
        }
    }
}

if (php_sapi_name() !== 'cli') die("Acesso via linha de comando apenas.");

if (isset($pdo)) {
    // Nota: O arquivo config/db.php deve criar a variável $pdo. 
    // Certifique-se que ele conecta ao banco do tracker (tour_progress, etc).
    $validator = new FlightValidator($pdo);
    $validator->fetchNetworkData();
    $validator->runValidation();
}
?>