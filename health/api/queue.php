<?php
// api/queue.php
// GET ?doctor_id=1  → returns sorted live queue with recalculated ETAs

require_once '../config.php';
set_exception_handler(fn($e) => json_response(['error' => $e->getMessage()], 500));

$doctorId = (int)($_GET['doctor_id'] ?? 0);
if (!$doctorId) json_response(['error' => 'doctor_id required'], 422);

$pdo = db();

// ─── THE SORTING ALGORITHM ───────────────────────────────────────────────────
// Rule 1: Higher priority_score comes first (Emergency=5 at top)
// Rule 2: For equal priority_score, earlier booked_at comes first (FCFS)
// This single ORDER BY clause enforces both rules simultaneously.
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        q.id,
        p.name            AS patient_name,
        q.priority_score,
        q.raw_symptoms,
        q.symptoms_analysis,
        q.booked_at,
        q.estimated_wait_time,
        JSON_UNQUOTE(JSON_EXTRACT(q.symptoms_analysis, '$.urgency_label')) AS urgency_label
    FROM queue_log q
    JOIN patients p ON p.id = q.patient_id
    WHERE q.doctor_id = ? AND q.status = 'waiting'
    ORDER BY q.priority_score DESC, q.booked_at ASC
");
$stmt->execute([$doctorId]);
$queue = $stmt->fetchAll();

// Recalculate ETA for each position dynamically
$avgMin = (int)($pdo->query("SELECT avg_consult_minutes FROM doctors WHERE id = $doctorId")->fetchColumn() ?: 10);
$cumulativeWait = 0;

foreach ($queue as $i => &$row) {
    $row['position']       = $i + 1;
    $row['eta_minutes']    = $cumulativeWait;
    $cumulativeWait       += $avgMin;

    // Update DB with fresh ETA
    $pdo->prepare("UPDATE queue_log SET estimated_wait_time = ? WHERE id = ?")
        ->execute([$row['eta_minutes'], $row['id']]);
}

json_response(['queue' => $queue, 'total' => count($queue)]);
