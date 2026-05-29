<?php
require_once '../config.php';
set_exception_handler(fn($e) => json_response(['error' => $e->getMessage()], 500));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST only'], 405);

$body     = json_decode(file_get_contents('php://input'), true);
$symptoms = trim($body['symptoms'] ?? '');
if (!$symptoms) json_response(['error' => 'symptoms required'], 422);

$s = strtolower($symptoms);

$rules = [
    5 => ['chest pain','heart attack','stroke','unconscious','not breathing','severe bleeding','choking','seizure','overdose','unresponsive'],
    4 => ['difficulty breathing','shortness of breath','high fever','severe pain','broken bone','fracture','deep cut','allergic reaction','vomiting blood','coughing blood'],
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

$labels     = [1=>'Routine',2=>'Non-Urgent',3=>'Semi-Urgent',4=>'Urgent',5=>'Emergency'];
$specialties = [5=>'Emergency Medicine',4=>'Internal Medicine',3=>'General Physician',2=>'General Physician',1=>'General Physician'];

json_response([
    'priority_score' => $score,
    'analysis' => [
        'priority_score'        => $score,
        'urgency_label'         => $labels[$score],
        'recommended_specialty' => $specialties[$score],
        'possible_conditions'   => [],
        'red_flags'             => [],
    ]
]);
