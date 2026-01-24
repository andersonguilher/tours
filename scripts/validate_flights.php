<?php
// scripts/validate_flights.php
// VERSÃO: MULTI-NETWORK + SESSION PERSISTENCE
// Resolve o problema de desconexão e reinício do contador de tempo.

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';

class FlightValidator {
    private $pdoTracker;
    private $pdoPilots;
    private $settings;
    private $pilotsOnline = [];
    private $knownPilots = [];
    
    // Configurações Padrão
    private $pilotTable = 'Dados_dos_Pilotos';
    private $colIvaoId = 'ivao_id';
    private $colVatsimId = 'vatsim_id'; // Novo suporte VATSIM
    private $colMatricula = 'matricula';
    private $colPilotId = 'id_piloto';
    
    // Parâmetros de Validação
    private $arrivalChecksRequired = 2; // Reduzido para 2 mins (antes 3)
    private $landingSpeedThreshold = 10; // Aumentado para 10kts (antes 5)

    public function __construct($pdoTracker) {
        $this->pdoTracker = $pdoTracker;
        $this->loadSettings();
        $this->connectPilotDB();
        $this->loadKnownPilots(); // Prefetch para performance
    }

    private function loadKnownPilots() {
        echo "[INFO] Identificando pilotos com Tours Ativas...\n";
        $this->knownPilots = [];
        
        try {
            // 1. Busca IDs de quem está com Status 'In Progress' no sistema de Tours
            $stmtActive = $this->pdoTracker->query("SELECT DISTINCT pilot_id FROM tour_progress WHERE status = 'In Progress'");
            $activeIds = $stmtActive->fetchAll(PDO::FETCH_COLUMN);

            if (empty($activeIds)) {
                echo "[INFO] Nenhum piloto com tour ativa no momento.\n";
                return;
            }

            $idsList = implode(',', array_map('intval', $activeIds));
            
            // 2. Busca dados apenas desses pilotos no BD externo
            echo "[INFO] Carregando dados de rede para " . count($activeIds) . " pilotos ativos...\n";
            
            $sql = "SELECT *, {$this->colMatricula} as matricula FROM {$this->pilotTable} WHERE {$this->colPilotId} IN ($idsList)";
            $stmt = $this->pdoPilots->query($sql);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Indexar por IVAO
                $ivaoRaw = $row[$this->colIvaoId] ?? $row['ivao_id'] ?? null;
                if (!empty($ivaoRaw)) {
                    $this->knownPilots['IVAO:' . $ivaoRaw] = $row;
                }
                
                // Indexar por VATSIM
                $vatsimRaw = $row[$this->colVatsimId] ?? $row['vatsim_id'] ?? null;
                if (!empty($vatsimRaw)) {
                    $this->knownPilots['VATSIM:' . $vatsimRaw] = $row;
                }
            }
            echo "[INFO] Cache construído: " . count($this->knownPilots) . " identidades monitoradas (Apenas Tours Ativas).\n";
        } catch (PDOException $e) {
            $this->log("[WARN] Erro ao carregar pilotos para memória: " . $e->getMessage());
        }
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
            $this->colVatsimId = $map['columns']['vatsim_id'] ?? $this->colVatsimId;
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
        $this->pilotsOnline = [];

        // 1. VATSIM V3 Data
        echo "[INFO] Baixando dados da VATSIM (v3)...\n";
        $vatsimJson = @file_get_contents('https://data.vatsim.net/v3/vatsim-data.json');
        if ($vatsimJson) {
            $vatsimData = json_decode($vatsimJson, true);
            if (isset($vatsimData['pilots']) && is_array($vatsimData['pilots'])) {
                foreach ($vatsimData['pilots'] as $p) {
                    $this->pilotsOnline[] = [
                        'network' => 'VATSIM',
                        'id' => $p['cid'],
                        'callsign' => $p['callsign'],
                        'flightPlan' => [
                            'departureId' => $p['flight_plan']['departure'] ?? '',
                            'arrivalId' => $p['flight_plan']['arrival'] ?? '',
                            'aircraft' => ['icaoCode' => $p['flight_plan']['aircraft_short'] ?? '']
                        ],
                        'lastTrack' => [
                            'latitude' => $p['latitude'],
                            'longitude' => $p['longitude'],
                            'altitude' => $p['altitude'],
                            'groundSpeed' => $p['groundspeed'],
                            'heading' => $p['heading'],
                            'onGround' => ($p['groundspeed'] < 40), // Inferido
                            'state' => ($p['groundspeed'] < 40) ? 'Boarding' : 'En Route'
                        ]
                    ];
                }
                echo "    -> " . count($vatsimData['pilots']) . " pilotos VATSIM processados.\n";
            }
        } else {
            $this->log("[WARN] Falha ao baixar feed da VATSIM.");
        }

        // 2. IVAO V2 Tracker Data
        echo "[INFO] Baixando dados da IVAO (v2 Tracker)...\n";
        $ivaoJson = @file_get_contents('https://api.ivao.aero/v2/tracker/whazzup');
        if ($ivaoJson) {
            $ivaoData = json_decode($ivaoJson, true);
            // Verifica se retornou lista direta ou objeto
            $clients = (isset($ivaoData['clients']['pilots'])) ? $ivaoData['clients']['pilots'] : (is_array($ivaoData) ? $ivaoData : []);
            
            $countIvao = 0;
            foreach ($clients as $p) {
                // Filtra apenas pilotos, caso venha misturado (ATC/Observer)
                // Na API whazzup v2 usually vem tudo junto ou separado por endpoint. 
                // Assumindo lista de clientes padrão whazzup
                if (isset($p['pilotSession']) || (isset($p['type']) && $p['type'] == 'pilot')) {
                    // Normalização mínima, pois a estrutura já é a esperada pelo script
                    $p['network'] = 'IVAO';
                    $this->pilotsOnline[] = $p;
                    $countIvao++;
                }
            }
            echo "    -> " . $countIvao . " pilotos IVAO processados.\n";
        } else {
            $this->log("[WARN] Falha ao baixar feed da IVAO.");
        }

        echo "[INFO] Total no Radar Combinado: " . count($this->pilotsOnline) . " aeronaves.\n";
    }

    public function runValidation() {
        if (empty($this->pilotsOnline)) return;
        foreach ($this->pilotsOnline as $flight) {
            $this->processFlight($flight);
        }
    }

    private function processFlight($flight) {
        $network = isset($flight['network']) ? strtoupper($flight['network']) : 'IVAO';
        
        // Seleção dinâmica do ID
        if ($network === 'VATSIM') {
            $networkId = $flight['id'] ?? null;
        } else {
            $networkId = $flight['userId'] ?? null;
        }

        $liveCallsign = strtoupper($flight['callsign'] ?? '');
        if (!$networkId) return;

        // VERIFICAÇÃO EM MEMÓRIA (Ultra Rápida)
        $cacheKey = $network . ':' . $networkId;
        if (!isset($this->knownPilots[$cacheKey])) {
            return; // Não é piloto da companhia
        }
        
        $pilotData = $this->knownPilots[$cacheKey];

        // Valida Callsign
        $dbCallsign = strtoupper($pilotData[$this->colMatricula] ?? $pilotData['matricula'] ?? '');
        if ($liveCallsign !== $dbCallsign) {
             echo "Callsign mismatch: Live [$liveCallsign] vs DB [$dbCallsign] (NetworkID: $networkId)\n";
             return;
        }

        $pilotId = $pilotData['post_id'] ?? $pilotData[$this->colPilotId] ?? null;
        if (!$pilotId) return;

        // Dados de Telemetria
        $flightPlan = $flight['flightPlan'] ?? null;
        $lastTrack = $flight['lastTrack'] ?? null;

        if (!$flightPlan || !$lastTrack) return;

        $telemetry = [
            'callsign'       => $liveCallsign,
            'dep_icao'       => $flightPlan['departureId'] ?? '',
            'arr_icao'       => $flightPlan['arrivalId'] ?? '',
            'aircraft'       => $flightPlan['aircraft']['icaoCode'] ?? ($flightPlan['aircraftId'] ?? ''),
            'state'          => $lastTrack['state'] ?? '', 
            'groundspeed'    => $lastTrack['groundSpeed'] ?? 0,
            'network'        => $network
        ];

        // Busca Perna Ativa (Esta query é leve pois só roda se o piloto for identificado)
        $activeLeg = $this->getActiveLeg($pilotId);
        if ($activeLeg) {
            echo " -> [Rastreando] {$liveCallsign} (ID: $pilotId) na perna {$activeLeg['leg_dep']} -> {$activeLeg['leg_arr']}\n";
            $this->manageFlightSession($pilotId, $activeLeg, $telemetry);
        }
    }

    // --- NOVA LÓGICA DE SESSÃO ---

    private function manageFlightSession($pilotId, $legData, $telemetry) {
        // Validação básica de Rota e Aeronave antes de processar sessão
        if ($telemetry['dep_icao'] != $legData['leg_dep'] || $telemetry['arr_icao'] != $legData['leg_arr']) {
            return; // Voo não corresponde à perna ativa
        }

        // Validação de Aeronave
        $rules = json_decode($legData['rules_json'], true);
        if (isset($rules['allowed_aircraft']) && !empty($rules['allowed_aircraft'])) {
            $allowedRaw = is_array($rules['allowed_aircraft']) ? $rules['allowed_aircraft'] : explode(',', $rules['allowed_aircraft']);
            $allowed = array_map('trim', $allowedRaw);
            if (!in_array($telemetry['aircraft'], $allowed)) {
                echo "    -> Aeronave Incorreta ({$telemetry['aircraft']}). Ignorando.\n";
                return;
            }
        }

        // Busca Sessão Ativa na Memória (DB)
        $session = $this->getSession($pilotId, $legData['tour_real_id'], $legData['leg_real_id']);

        if (!$session) {
            // INICIAR NOVA SESSÃO
            // Cria apenas se estiver voando ou no solo do aeroporto de saída
            $this->createSession($pilotId, $legData['tour_real_id'], $legData['leg_real_id']);
            echo "    -> Sessão iniciada. Monitorando voo.\n";
        } else {
            // ATUALIZAR SESSÃO EXISTENTE
            $this->updateSessionHeartbeat($pilotId);

            // VERIFICAÇÃO DE CHEGADA (Debounce)
            // Se estiver no destino E velocidade < 5kts (parado)
            if ($telemetry['arr_icao'] == $legData['leg_arr'] && $telemetry['groundspeed'] < $this->landingSpeedThreshold) {
                
                $checks = $session['arrival_checks'] + 1;
                $this->updateArrivalChecks($pilotId, $checks);
                echo "    -> Validando chegada ({$checks}/{$this->arrivalChecksRequired})...\n";

                if ($checks >= $this->arrivalChecksRequired) {
                    // CALCULAR TEMPO REAL DE VOO
                    $startTime = strtotime($session['start_time']);
                    $durationMinutes = round((time() - $startTime) / 60);

                    // COMPLETAR PERNA
                    $this->completeLeg($legData, $telemetry, $pilotId, $durationMinutes);
                    
                    // LIMPAR SESSÃO
                    $this->deleteSession($pilotId);
                }

            } else {
                // Se o piloto voltar a mover-se (taxi) ou decolar de novo, reseta o contador de chegada
                if ($session['arrival_checks'] > 0) {
                    $this->updateArrivalChecks($pilotId, 0);
                    echo "    -> Movimento detectado. Resetando validação de chegada.\n";
                }
            }
        }
    }

    // --- MÉTODOS DE BANCO DE DADOS (Helpers) ---

    private function getPilotFromDB($networkId, $network) {
        $targetCol = ($network === 'VATSIM') ? $this->colVatsimId : $this->colIvaoId;
        try {
            $sql = "SELECT *, {$this->colMatricula} as matricula FROM {$this->pilotTable} WHERE {$targetCol} = ? LIMIT 1";
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

    // --- MÉTODOS DE SESSÃO (Persistência) ---

    private function getSession($pilotId, $tourId, $legId) {
        try {
            $stmt = $this->pdoTracker->prepare("SELECT * FROM tour_live_sessions WHERE pilot_id = ? AND tour_id = ? AND leg_id = ?");
            $stmt->execute([$pilotId, $tourId, $legId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }

    private function createSession($pilotId, $tourId, $legId) {
        try {
            // Tenta inserir, se já existir (devido a race condition), ignora
            $stmt = $this->pdoTracker->prepare("
                INSERT INTO tour_live_sessions (pilot_id, tour_id, leg_id, start_time, last_seen, arrival_checks)
                VALUES (?, ?, ?, NOW(), NOW(), 0)
                ON DUPLICATE KEY UPDATE last_seen = NOW()
            ");
            $stmt->execute([$pilotId, $tourId, $legId]);
        } catch (PDOException $e) { $this->log("Erro ao criar sessão: " . $e->getMessage()); }
    }

    private function updateSessionHeartbeat($pilotId) {
        $stmt = $this->pdoTracker->prepare("UPDATE tour_live_sessions SET last_seen = NOW() WHERE pilot_id = ?");
        $stmt->execute([$pilotId]);
    }

    private function updateArrivalChecks($pilotId, $count) {
        $stmt = $this->pdoTracker->prepare("UPDATE tour_live_sessions SET arrival_checks = ? WHERE pilot_id = ?");
        $stmt->execute([$count, $pilotId]);
    }

    private function deleteSession($pilotId) {
        $stmt = $this->pdoTracker->prepare("DELETE FROM tour_live_sessions WHERE pilot_id = ?");
        $stmt->execute([$pilotId]);
    }

    // --- FINALIZAÇÃO ---

    private function completeLeg($legData, $telemetry, $pilotId, $duration) {
        echo "    -> [SUCESSO] {$telemetry['callsign']} completou a perna (Tempo: {$duration} min).\n";

        try {
            $this->pdoTracker->beginTransaction();

            // Histórico com Duração Calculada
            // Nota: Se a coluna flight_time_minutes não existir ainda, remova-a da query ou adicione no DB.
            $stmtLog = $this->pdoTracker->prepare("
                INSERT INTO tour_history (pilot_id, tour_id, leg_id, callsign, aircraft, date_flown, network, landing_rate, flight_time_minutes)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, NULL, ?)
            ");
            $stmtLog->execute([
                $pilotId, 
                $legData['tour_real_id'], 
                $legData['leg_real_id'], 
                $telemetry['callsign'], 
                $telemetry['aircraft'], 
                $telemetry['network'],
                $duration
            ]);

            // Atualiza Próxima Perna
            $stmtNext = $this->pdoTracker->prepare("SELECT id FROM tour_legs WHERE tour_id = ? AND leg_order > ? ORDER BY leg_order ASC LIMIT 1");
            $stmtNext->execute([$legData['tour_real_id'], $legData['leg_order']]);
            $nextLeg = $stmtNext->fetch(PDO::FETCH_ASSOC);

            if ($nextLeg) {
                $stmtUpd = $this->pdoTracker->prepare("UPDATE tour_progress SET current_leg_id = ?, status = 'In Progress', last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$nextLeg['id'], $legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "✅ Perna Concluída: {$legData['leg_dep']} -> {$legData['leg_arr']} ({$duration} min)");
            } else {
                $stmtUpd = $this->pdoTracker->prepare("UPDATE tour_progress SET status = 'Completed', completed_at = NOW(), last_update = NOW() WHERE id = ?");
                $stmtUpd->execute([$legData['progress_id']]);
                $this->sendDiscordWebhook($telemetry['callsign'], $legData['tour_title'], "🏆 TOUR FINALIZADO! Parabéns comandante.");
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
    $validator = new FlightValidator($pdo);
    $validator->fetchNetworkData();
    $validator->runValidation();
}
?>