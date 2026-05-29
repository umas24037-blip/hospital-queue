# Smart Hospital Queue System

## Stack
- **Backend**: PHP 8+ (no framework)
- **Database**: MySQL 8+
- **AI**: Google Gemini 1.5 Flash API
- **Frontend**: Vanilla HTML/CSS/JS (zero dependencies)

## Setup

### 1. Database
```sql
-- Run schema.sql in your MySQL client
mysql -u root -p < schema.sql
```

### 2. Config
Edit `config.php`:
```php
define('DB_PASS', 'your_mysql_password');
define('GEMINI_API_KEY', 'your_gemini_api_key');
```
Get a free Gemini API key at: https://aistudio.google.com/app/apikey

### 3. Web Server
Place the project in your web server root (e.g. `htdocs/health/` for XAMPP).

```
http://localhost/health/          → Patient booking (2-step UI)
http://localhost/health/queue-display.html → Live queue screen (for reception TV)
```

---

## Architecture

```
Patient (Browser)
    │
    ├─ Step 1: Name + Doctor  (< 10 sec)
    ├─ Step 2: Symptoms via Voice-to-Text  (< 15 sec)
    │
    └─ POST api/book.php
            │
            ├─ POST api/triage.php ──► Gemini API
            │       └─ Returns priority_score (1-5) + structured analysis
            │
            ├─ Upsert patient record
            ├─ Calculate ETA = (waiting patients) × avg_consult_minutes
            ├─ INSERT into queue_log
            └─ Return: position, ETA, urgency label

Reception Screen
    └─ GET api/queue.php?doctor_id=X  (auto-refresh every 15s)
            └─ ORDER BY priority_score DESC, booked_at ASC
```

---

## The Sorting Algorithm (Key Logic)

```sql
ORDER BY priority_score DESC, booked_at ASC
```

**Example scenario:**
| Patient | Booked At | Priority | Final Position |
|---------|-----------|----------|----------------|
| Ali (Cold) | 10:00 | 2 | #2 |
| Sara (Chest Pain) | 10:05 | 5 | **#1** ← AI moved her up |
| Umar (Fever) | 09:55 | 2 | #3 (same priority as Ali, but booked later) |

Wait — Umar booked at 09:55 (earlier than Ali at 10:00), so:
| Patient | Booked At | Priority | Final Position |
|---------|-----------|----------|----------------|
| Sara (Chest Pain) | 10:05 | 5 | **#1** ← Emergency |
| Umar (Fever) | 09:55 | 2 | #2 ← FCFS |
| Ali (Cold) | 10:00 | 2 | #3 ← FCFS |

---

## Priority Score Reference (Gemini-assigned)

| Score | Label | Example Symptoms |
|-------|-------|-----------------|
| 5 | 🚨 Emergency | Chest pain, stroke, unconscious |
| 4 | Urgent | High fever, severe pain, breathing difficulty |
| 3 | Semi-Urgent | Vomiting, moderate pain, mild injury |
| 2 | Non-Urgent | Cold, mild headache, minor cuts |
| 1 | Routine | Prescription refill, checkup |

---

## API Reference

### POST /api/triage.php
```json
Request:  { "symptoms": "chest pain and shortness of breath" }
Response: { "priority_score": 5, "analysis": { "urgency_label": "Emergency", ... } }
```

### POST /api/book.php
```json
Request:  { "name": "Ali", "phone": "03001234567", "symptoms": "...", "doctor_id": 1 }
Response: { "queue_id": 7, "position": 2, "priority_score": 5, "eta_minutes": 10, "message": "..." }
```

### GET /api/queue.php?doctor_id=1
```json
Response: { "total": 3, "queue": [ { "position": 1, "patient_name": "Sara", "priority_score": 5, ... } ] }
```
