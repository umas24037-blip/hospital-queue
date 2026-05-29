<?php
require_once '../config.php';
set_exception_handler(fn($e) => json_response(['error' => $e->getMessage()], 500));

$phone = trim($_GET['phone'] ?? '');
if (!$phone) json_response(['error' => 'phone required'], 422);

$pdo = db();

$patient = $pdo->prepare('SELECT id, name, phone, created_at FROM patients WHERE phone = ? LIMIT 1');
$patient->execute([$phone]);
$p = $patient->fetch();

if (!$p) json_response(['error' => 'No patient found with this number'], 404);

$bookings = $pdo->prepare("
    SELECT
        q.id,
        q.priority_score,
        q.raw_symptoms,
        q.status,
        q.booked_at,
        q.estimated_wait_time,
        d.name        AS doctor_name,
        d.specialty,
        JSON_UNQUOTE(JSON_EXTRACT(q.symptoms_analysis, '$.urgency_label')) AS urgency_label,
        JSON_UNQUOTE(JSON_EXTRACT(q.symptoms_analysis, '$.recommended_specialty')) AS recommended_specialty,
        (
            SELECT COUNT(*) FROM queue_log q2
            WHERE q2.doctor_id = q.doctor_id
              AND q2.status = 'waiting'
              AND (
                    q2.priority_score > q.priority_score
                    OR (q2.priority_score = q.priority_score AND q2.booked_at < q.booked_at)
                  )
        ) + 1 AS queue_position
    FROM queue_log q
    JOIN doctors d ON d.id = q.doctor_id
    WHERE q.patient_id = ?
    ORDER BY q.booked_at DESC
");
$bookings->execute([$p['id']]);

json_response([
    'patient'  => $p,
    'bookings' => $bookings->fetchAll(),
]);
