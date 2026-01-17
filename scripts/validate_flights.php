<?php
// validate_flights.php
// FINAL VERSION: Dual Database Connection (Pilots DB + Tracker DB) + Discord + Landing Rate
// Runs via Cron every 2 minutes

define('BASE_PATH', '/var/www/kafly_user/data/www/kafly.com.br');

// 1. Load Tracker Database Connection ($pdo)
require BASE_PATH . '/dash/tours/config/db.php'; 

// 2. Load Settings & Create Pilot Database Connection
$settingsPath = BASE_PATH . '/dash/settings.json';
$tb_pilotos = 'Dados_dos_Pilotos';
$col_vid    = 'ivao_id'; 
$col_matr   = 'matricula';

if (file_exists($settingsPath)) {
    $settings = json_decode(file_get_contents($settingsPath), true);
    if (isset($settings['database_mappings']['pilots_table'])) 
        $tb_pilotos = $settings['database_mappings']['pilots_table'];
    
    $cols = $settings['database_mappings']['columns'] ?? [];
    if (isset($cols['ivao_id'])) $col_vid = $cols['ivao_id'];
    if (isset($cols['matricula'])) $col_matr = $cols['matricula'];
}

try {
    $host_p = defined('DB_SERVERNAME') ? DB_SERVERNAME : 'localhost';
    $user_p = defined('DB_PILOTOS_USER') ? DB_PILOTOS_USER : 'root';
    $pass_p = defined('DB_PILOTOS_PASS') ? DB_PILOTOS_PASS : '';
    $name_p = defined('DB_PILOTOS_NAME') ? DB_PILOTOS_NAME : 'u378005298_hEatD';

    $pdoPilots = new PDO("mysql:host=$host_p;dbname=$name_p;charset=utf8mb4", $user_p, $pass_p);
    $pdoPilots->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to Pilot DB: $name_p\n";

} catch (PDOException $e) {
    die("CRITICAL ERROR: Could not connect to Pilot Database. " . $e->getMessage());
}

// 3. Load Network Data (Whazzup)
$whazzupPath = BASE_PATH . '/skymetrics/api/whazzup.json';
if (!file_exists($whazzupPath)) die("Error: whazzup.json not found.");

$data = json_decode(file_get_contents($whazzupPath), true);
$pilotsOnline = [];

if (isset($data[0]['clients']['pilots'])) {
    $pilotsOnline = $data[0]['clients']['pilots']; 
} elseif (isset($data['clients']['pilots'])) {
    $pilotsOnline = $data['clients']['pilots']; 
}

echo "Processing " . count($pilotsOnline) . " online connections...\n";

// 4. Validation Loop
foreach ($pilotsOnline as $flight) {
    
    $networkId = $flight['userId'] ?? null; 
    $liveCallsign = strtoupper($flight['callsign'] ?? '');
    
    if (!$networkId) continue;

    try {
        $sql = "SELECT id_piloto, post_id, $col_matr as matricula FROM $tb_pilotos WHERE $col_vid = ? LIMIT 1";
        $stmtPilot = $pdoPilots->prepare($sql);
        $stmtPilot->execute([$networkId]);
        $pilotDB = $stmtPilot->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "SQL Error searching pilot: " . $e->getMessage() . "\n";
        continue;
    }

    if (!$pilotDB) continue; 

    $pilotId = $pilotDB['post_id'] ?? $pilotDB['id_piloto']; 
    $dbCallsign = strtoupper($pilotDB['matricula']);

    if ($liveCallsign !== $dbCallsign) {
        // echo " -> Pilot ID $pilotId flying with wrong callsign ($liveCallsign). Expected: $dbCallsign. Skipping.\n";
        continue; 
    }

    echo " -> Validating $liveCallsign (ID: $pilotId)...\n";

    $flightPlan = $flight['flightPlan'] ?? null;
    $lastTrack  = $flight['lastTrack'] ?? null;

    if (!$flightPlan || !$lastTrack) continue;

    $pilotData = [
        'callsign'      => $liveCallsign,
        'dep_icao'      => $flightPlan['departureId'] ?? '',
        'arr_icao'      => $flightPlan['arrivalId'] ?? '',
        'aircraft'      => $flightPlan['aircraft']['icaoCode'] ?? '',
        'state'         => $lastTrack['state'] ?? '',
        'on_ground'     => $lastTrack['onGround'] ?? false,
        'groundspeed'   => $lastTrack['groundSpeed'] ?? 0,
        'vertical_speed'=> $lastTrack['verticalSpeed'] ?? 0
    ];

    $stmt = $pdo->prepare("
        SELECT p.id as progress_id, p.current_leg_id, t.rules_json, t.id as tour_real_id, t.title as tour_title,
               l.dep_icao as leg_dep, l.arr_icao as leg_arr, l.id as leg_real_id
        FROM pilot_tour_progress p
        JOIN tours t ON p.tour_id = t.id
        JOIN tour_legs l ON p.current_leg_id = l.id
        WHERE p.pilot_id = ? 
        AND p.status = 'In Progress'
    ");
    $stmt->execute([$pilotId]);
    $activeLeg = $stmt->fetch();

    if ($activeLeg) {
        validateLeg($activeLeg, $pilotData, $pdo, $pilotId);
    }
}

// Logic Function
function validateLeg($legData, $pilotData, $pdo, $pilotId) {
    // 1. Route Check
    if ($pilotData['dep_icao'] != $legData['leg_dep'] || $pilotData['arr_icao'] != $legData['leg_arr']) return;

    // 2. Aircraft Check
    $rules = json_decode($legData['rules_json'], true);
    if (isset($rules['allowed_aircraft']) && !empty($rules['allowed_aircraft'])) {
        $allowedRaw = is_array($rules['allowed_aircraft']) ? $rules['allowed_aircraft'] : explode(',', $rules['allowed_aircraft']);
        $allowed = array_map('trim', $allowedRaw);
        if (!in_array($pilotData['aircraft'], $allowed)) {
            echo "    -> Invalid Aircraft ({$pilotData['aircraft']}).\n";
            return;
        }
    }

    // 3. Landing Check
    $isLanded = ($pilotData['state'] == 'Landed' || $pilotData['state'] == 'On Blocks' || ($pilotData['on_ground'] && $pilotData['groundspeed'] < 30));
    
    if ($isLanded) {
        echo "    -> LEG COMPLETED! {$pilotData['callsign']} landed at destination.\n";
        
        // Tenta pegar o Landing Rate se disponível (negativo)
        $landingRate = $pilotData['vertical_speed'];
        
        // Save Log with Landing Rate
        $stmtLog = $pdo->prepare("
            INSERT INTO pilot_leg_history (pilot_id, tour_id, leg_id, callsign, aircraft, date_flown, network, landing_rate)
            VALUES (?, (SELECT tour_id FROM pilot_tour_progress WHERE id = ?), ?, ?, ?, NOW(), 'IVAO', ?)
        ");
        $stmtLog->execute([$pilotId, $legData['progress_id'], $legData['leg_real_id'], $pilotData['callsign'], $pilotData['aircraft'], $landingRate]);

        // Discord Notification
        sendDiscordWebhook($pilotData['callsign'], $legData['tour_title'], "{$legData['leg_dep']} > {$legData['leg_arr']}");

        // Advance Leg
        $currentLegId = $legData['leg_real_id'];
        $stmtNext = $pdo->prepare("
            SELECT id FROM tour_legs 
            WHERE tour_id = (SELECT tour_id FROM pilot_tour_progress WHERE id = ?)
            AND leg_order > (SELECT leg_order FROM tour_legs WHERE id = ?)
            ORDER BY leg_order ASC LIMIT 1
        ");
        $stmtNext->execute([$legData['progress_id'], $currentLegId]);
        $nextLeg = $stmtNext->fetch();

        if ($nextLeg) {
            $stmtUpd = $pdo->prepare("UPDATE pilot_tour_progress SET current_leg_id = ?, status = 'In Progress' WHERE id = ?");
            $stmtUpd->execute([$nextLeg['id'], $legData['progress_id']]);
        } else {
            $stmtUpd = $pdo->prepare("UPDATE pilot_tour_progress SET status = 'Completed', completed_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$legData['progress_id']]);
            
            // Notification for Full Tour Completion
            sendDiscordWebhook($pilotData['callsign'], $legData['tour_title'], "🏆 TOUR FINALIZADO!");
        }
    }
}

function sendDiscordWebhook($callsign, $tourName, $details) {
    // INSIRA SUA WEBHOOK URL AQUI
    $webhookurl = "https://discord.com/api/webhooks/SEU_WEBHOOK_AQUI";

    $timestamp = date("c", strtotime("now"));
    $json_data = json_encode([
        "username" => "Kafly Tour Tracker",
        "embeds" => [
            [
                "title" => "✈️ Tour Progress Update",
                "type" => "rich",
                "color" => hexdec("3366ff"),
                "fields" => [
                    ["name" => "Pilot", "value" => $callsign, "inline" => true],
                    ["name" => "Tour", "value" => $tourName, "inline" => true],
                    ["name" => "Status", "value" => $details, "inline" => false]
                ],
                "footer" => ["text" => "Kafly Systems"],
                "timestamp" => $timestamp
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($webhookurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);
}
?>