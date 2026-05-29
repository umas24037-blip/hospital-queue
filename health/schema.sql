-- Smart Hospital Queue System - Database Schema
-- Run this file once to initialize the database

CREATE DATABASE IF NOT EXISTS hospital_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_queue;

-- Doctors table
CREATE TABLE doctors (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    specialty   VARCHAR(100) NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    avg_consult_minutes INT DEFAULT 10  -- used for ETA calculation
);

-- Patients table
CREATE TABLE patients (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    phone        VARCHAR(20),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Queue log — core table
CREATE TABLE queue_log (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT NOT NULL,
    doctor_id           INT NOT NULL,
    raw_symptoms        TEXT NOT NULL,                  -- original voice/text input
    symptoms_analysis   TEXT,                           -- Gemini structured analysis JSON
    priority_score      TINYINT NOT NULL DEFAULT 1,     -- 1 (low) to 5 (emergency)
    estimated_wait_time INT DEFAULT 0,                  -- in minutes, recalculated dynamically
    status              ENUM('waiting','in_progress','done','cancelled') DEFAULT 'waiting',
    booked_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    seen_at             TIMESTAMP NULL,

    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id)  REFERENCES doctors(id),

    -- Index for the sorting query (priority DESC, booked_at ASC)
    INDEX idx_queue_sort (doctor_id, status, priority_score, booked_at)
);

-- Seed: sample doctors
INSERT INTO doctors (name, specialty, avg_consult_minutes) VALUES
('Dr. Ayesha Khan',  'General Physician', 8),
('Dr. Bilal Ahmed',  'Cardiologist',      15),
('Dr. Sara Malik',   'Pediatrician',      10);
