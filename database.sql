-- Skema Database: English Vocabulary Builder & Community Glossary
-- Target: MySQL / MariaDB (XAMPP, Laragon, InfinityFree)

CREATE DATABASE IF NOT EXISTS `24424020`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `24424020`;

DROP TABLE IF EXISTS daftar_vocab;
DROP TABLE IF EXISTS kategori_vocab;

-- Tabel 1: kategori_vocab (Tabel Induk)
CREATE TABLE kategori_vocab (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel 2: daftar_vocab (Tabel Anak, relasi 1-to-Many ke kategori_vocab)
CREATE TABLE daftar_vocab (
    id_vocab INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT NOT NULL,
    english_word VARCHAR(100) NOT NULL,
    part_of_speech ENUM('Noun', 'Verb', 'Adjective', 'Adverb', 'Phrasal Verb', 'Idiom') NOT NULL,
    indonesian_meaning VARCHAR(150) NOT NULL,
    example_sentence TEXT,
    submitted_by VARCHAR(50) NOT NULL DEFAULT 'helloebi',
    approval_status ENUM('Pending', 'Approved') NOT NULL DEFAULT 'Pending',
    mastery_status ENUM('New', 'Learning', 'Mastered') NOT NULL DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_daftar_vocab_kategori
        FOREIGN KEY (id_kategori) REFERENCES kategori_vocab(id_kategori)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_vocab_approval (approval_status),
    INDEX idx_vocab_kategori (id_kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Awal Kategori (Master Data)
INSERT INTO kategori_vocab (id_kategori, nama_kategori) VALUES
(1, 'Software Engineering'),
(2, 'Academic & Research'),
(3, 'Daily Conversation'),
(4, 'Business & Workplace'),
(5, 'Idioms & Phrasal Verbs');

-- Data Awal Kosakata (Status: Approved & Pending)
INSERT INTO daftar_vocab (
    id_kategori,
    english_word,
    part_of_speech,
    indonesian_meaning,
    example_sentence,
    submitted_by,
    approval_status,
    mastery_status
) VALUES
(1, 'idempotent', 'Adjective', 'Menghasilkan hasil yang sama meskipun operasi dijalankan berulang kali', 'An HTTP PUT request should be idempotent so retrying it does not duplicate records.', 'helloebi', 'Approved', 'Mastered'),
(1, 'deprecate', 'Verb', 'Menandai fitur lama agar tidak lagi digunakan karena akan dihapus di versi mendatang', 'We plan to deprecate the legacy authentication endpoint in version 2.4.', 'helloebi', 'Approved', 'Mastered'),
(2, 'corroborate', 'Verb', 'Memperkuat atau membuktikan kebenaran suatu pernyataan dengan bukti tambahan', 'Field measurements corroborate the findings presented in the initial study.', 'helloebi', 'Approved', 'Learning'),
(3, 'ubiquitous', 'Adjective', 'Hadir atau ditemukan di mana-mana pada waktu yang bersamaan', 'Smartphones have become ubiquitous in daily commuting life.', 'helloebi', 'Approved', 'Mastered'),
(4, 'bottleneck', 'Noun', 'Titik hambatan yang memperlambat keseluruhan alur kerja atau proses', 'Manual code review became the main bottleneck before our weekly release.', 'raka_dev', 'Approved', 'Learning'),
(5, 'call it a day', 'Idiom', 'Berhenti bekerja untuk hari ini karena tugas dirasa sudah cukup', 'All unit tests are passing now, so let us call it a day.', 'helloebi', 'Approved', 'Mastered'),
(5, 'iron out', 'Phrasal Verb', 'Menyelesaikan rincian masalah atau perbedaan kecil hingga tuntas', 'The team met on Monday morning to iron out the remaining deployment issues.', 'nadia_putri', 'Approved', 'New'),
(1, 'throughput', 'Noun', 'Jumlah data atau pekerjaan yang berhasil diproses dalam rentang waktu tertentu', 'Adding a read replica improved our database query throughput during peak hours.', 'dimas_pratama', 'Pending', 'New'),
(2, 'empirical', 'Adjective', 'Berdasarkan pengamatan langsung atau eksperimen nyata, bukan sekadar teori', 'The authors provided empirical evidence gathered from twelve hospital clinics.', 'siti_rahma', 'Pending', 'New'),
(4, 'follow up', 'Phrasal Verb', 'Menindaklanjuti komunikasi atau tugas yang sudah dibahas sebelumnya', 'I will follow up with the vendor tomorrow regarding the server delivery schedule.', 'bagas_k', 'Pending', 'New');

