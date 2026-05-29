<?php
// api/book.php
// POST { "name": "Ali", "phone": "03001234567", "symptoms": "...", "doctor_id": 1 }
// Returns booking confirmation with queue position and ETA

require_once '../config.php';
set_exception_handler(fn($e) => json_response(['error' => $e->getMessage()], 500));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST only'], 405);

$body      = json_decode(file_get_contents('php://input'), true);
$name      = trim($body['name']      ?? '');
$phone     = trim($body['phone']     ?? '');
$symptoms  = trim($body['symptoms']  ?? '');
$doctor_id = (int)($body['doctor_id'] ?? 0);

if (!$name || !$symptoms || !$doctor_id) json_response(['error' => 'name, symptoms, doctor_id required'], 422);

// 1. Call Gemini triage inline (avoids loopback curl issues)
$triageResult  = callTriage($symptoms);
$priorityScore = (int)($triageResult['priority_score'] ?? 1);
$analysisJson  = json_encode($triageResult['analysis'] ?? []);

$pdo = db();

// 2. Upsert patient (match by phone if provided)
if ($phone) {
    $stmt = $pdo->prepare('SELECT id FROM patients WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    $patient = $stmt->fetch();
}

if (empty($patient)) {
    $pdo->prepare('INSERT INTO patients (name, phone) VALUES (?, ?)')->execute([$name, $phone ?: null]);
    $patientId = (int)$pdo->lastInsertId();
} else {
    $patientId = (int)$patient['id'];
}

// 3. Calculate ETA: (waiting patients ahead) × avg_consult_minutes
$etaStmt = $pdo->prepare("
    SELECT COUNT(q.id) AS ahead, d.avg_consult_minutes
    FROM queue_log q
    JOIN doctors d ON d.id = q.doctor_id
    WHERE q.doctor_id = ? AND q.status = 'waiting'
    GROUP BY d.avg_consult_minutes
");
$etaStmt->execute([$doctor_id]);
$etaRow = $etaStmt->fetch();
$ahead  = $etaRow ? (int)$etaRow['ahead'] : 0;
$avgMin = $etaRow ? (int)$etaRow['avg_consult_minutes'] : 10;
$eta    = $ahead * $avgMin;

// 4. Insert queue entry
$pdo->prepare("
    INSERT INTO queue_log (patient_id, doctor_id, raw_symptoms, symptoms_analysis, priority_score, estimated_wait_time)
    VALUES (?, ?, ?, ?, ?, ?)
")->execute([$patientId, $doctor_id, $symptoms, $analysisJson, $priorityScore, $eta]);

$queueId = (int)$pdo->lastInsertId();

// 5. Queue position (how many are ahead with higher/equal priority booked earlier)
$posStmt = $pdo->prepare("
    SELECT COUNT(*) AS position FROM queue_log
    WHERE doctor_id = ? AND status = 'waiting'
      AND (
            priority_score > ?
            OR (priority_score = ? AND booked_at < (SELECT booked_at FROM queue_log WHERE id = ?))
          )
");
$posStmt->execute([$doctor_id, $priorityScore, $priorityScore, $queueId]);
$position = (int)$posStmt->fetchColumn() + 1;

json_response([
    'queue_id'       => $queueId,
    'position'       => $position,
    'priority_score' => $priorityScore,
    'urgency_label'  => $triageResult['analysis']['urgency_label'] ?? '',
    'eta_minutes'    => $eta,
    'message'        => "You are #{$position} in queue. Estimated wait: {$eta} minutes.",
]);

// --- Rule-based triage (no external API needed) ---
function callTriage(string $symptoms): array {
    $s = strtolower($symptoms);

    $rules = [
        5 => ['chest pain','heart attack','stroke','unconscious','not breathing','severe bleeding','choking','seizure','overdose','unresponsive'],
        4 => ['difficulty breathing','shortness of breath','high fever','severe pain','broken bone','fracture','deep cut','allergic reaction','appendix','vomiting blood','coughing blood'],
        3 => ['vomiting','moderate pain','sprain','mild injury','dizziness','fainting','ear pain','eye pain','urinary pain','infection','swelling'],
        2 => ['cold','cough','mild headache','sore throat','runny nose','minor cut','bruise','rash','nausea','back pain','fatigue'],
        1 => ['checkup','prescription','refill','follow up','routine','vaccination','certificate','report'],
    ];

    $score = 1;
    foreach ($rules as $priority => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($s, $kw)) { $score = $priority; break 2; }
        }
    }

    $labels = [1=>'Routine',2=>'Non-Urgent',3=>'Semi-Urgent',4=>'Urgent',5=>'Emergency'];
    $specialties = [
        5=>'Emergency Medicine',4=>'Internal Medicine',
        3=>'General Physician',2=>'General Physician',1=>'General Physician'
    ];

    return [
        'priority_score' => $score,
        'analysis' => [
            'priority_score'         => $score,
            'urgency_label'          => $labels[$score],
            'recommended_specialty'  => $specialties[$score],
            'possible_conditions'    => [],
            'red_flags'              => [],
        ]
    ];
}
