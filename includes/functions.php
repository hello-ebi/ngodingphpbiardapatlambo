<?php
declare(strict_types=1);

/**
 * Helper Sanitasi XSS, Otorisasi Kunci Admin, dan Query Database (PDO Prepared Statements)
 */

/**
 * Sanitasi seluruh output string ke HTML untuk mencegah serangan XSS.
 */
function e(string|int|float|null $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Merender nama kontributor dengan aman; jika bernilai 'helloebi', bungkus menjadi anchor ke helloebi.web.id.
 */
function renderContributor(string|null $submittedBy): string
{
    $cleanName = trim((string) $submittedBy);
    if (strtolower($cleanName) === 'helloebi') {
        return '<a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">' . e($cleanName) . '</a>';
    }
    return e($cleanName);
}

/**
 * Validasi kunci rahasia admin tunggal (ADMIN_SECRET_KEY = 'cukurukuk666') dari parameter GET atau POST.
 */
function requireAdminKey(): string
{
    $providedKey = '';
    if (isset($_GET['key']) && is_string($_GET['key'])) {
        $providedKey = trim($_GET['key']);
    } elseif (isset($_POST['key']) && is_string($_POST['key'])) {
        $providedKey = trim($_POST['key']);
    }

    $isValid = defined('ADMIN_SECRET_KEY') && hash_equals(ADMIN_SECRET_KEY, $providedKey);

    if ($providedKey === '' || !$isValid) {
        http_response_code(403);
        header('Location: index.php?msg=forbidden');
        exit;
    }

    return $providedKey;
}

/**
 * Daftar nilai ENUM Part of Speech yang diizinkan sesuai skema tabel daftar_vocab.
 *
 * @return array<int, string>
 */
function getAllowedPartsOfSpeech(): array
{
    return ['Noun', 'Verb', 'Adjective', 'Adverb', 'Phrasal Verb', 'Idiom'];
}

/**
 * Daftar nilai ENUM Mastery Status yang diizinkan sesuai skema tabel daftar_vocab.
 *
 * @return array<int, string>
 */
function getAllowedMasteryStatuses(): array
{
    return ['New', 'Learning', 'Mastered'];
}

/**
 * Daftar nilai ENUM Approval Status yang diizinkan sesuai skema tabel daftar_vocab.
 *
 * @return array<int, string>
 */
function getAllowedApprovalStatuses(): array
{
    return ['Pending', 'Approved'];
}

/**
 * Mengambil seluruh daftar kategori kosakata beserta jumlah kosakata Approved di dalamnya.
 *
 * @return array<int, array<string, mixed>>
 */
function getCategories(PDO $pdo): array
{
    $sql = "SELECT k.id_kategori, k.nama_kategori,
                   COUNT(CASE WHEN v.approval_status = 'Approved' THEN 1 END) AS total_approved
            FROM kategori_vocab k
            LEFT JOIN daftar_vocab v ON k.id_kategori = v.id_kategori
            GROUP BY k.id_kategori, k.nama_kategori
            ORDER BY k.nama_kategori ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Mengambil daftar kosakata berstatus Approved menggunakan INNER JOIN (Sub-CPMK 5).
 * Mendukung filter kategori (id_kategori) dan pencarian kata kunci.
 *
 * @return array<int, array<string, mixed>>
 */
function getApprovedVocab(PDO $pdo, ?int $categoryId = null, string $searchQuery = ''): array
{
    $sql = "SELECT v.id_vocab,
                   v.id_kategori,
                   k.nama_kategori,
                   v.english_word,
                   v.part_of_speech,
                   v.indonesian_meaning,
                   v.example_sentence,
                   v.submitted_by,
                   v.approval_status,
                   v.mastery_status,
                   v.created_at
            FROM daftar_vocab v
            INNER JOIN kategori_vocab k ON v.id_kategori = k.id_kategori
            WHERE v.approval_status = :status";

    $params = [':status' => 'Approved'];

    if ($categoryId !== null && $categoryId > 0) {
        $sql .= " AND v.id_kategori = :id_kategori";
        $params[':id_kategori'] = $categoryId;
    }

    $trimmedSearch = trim($searchQuery);
    if ($trimmedSearch !== '') {
        $sql .= " AND (v.english_word LIKE :q_word OR v.indonesian_meaning LIKE :q_meaning OR v.example_sentence LIKE :q_example)";
        $likePattern = '%' . $trimmedSearch . '%';
        $params[':q_word'] = $likePattern;
        $params[':q_meaning'] = $likePattern;
        $params[':q_example'] = $likePattern;
    }

    $sql .= " ORDER BY v.created_at DESC, v.id_vocab DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mengambil daftar kosakata berdasarkan status persetujuan ('Pending' atau 'Approved') untuk halaman admin.
 *
 * @return array<int, array<string, mixed>>
 */
function getVocabByApprovalStatus(PDO $pdo, string $approvalStatus): array
{
    $sql = "SELECT v.id_vocab,
                   v.id_kategori,
                   k.nama_kategori,
                   v.english_word,
                   v.part_of_speech,
                   v.indonesian_meaning,
                   v.example_sentence,
                   v.submitted_by,
                   v.approval_status,
                   v.mastery_status,
                   v.created_at
            FROM daftar_vocab v
            INNER JOIN kategori_vocab k ON v.id_kategori = k.id_kategori
            WHERE v.approval_status = :approval_status
            ORDER BY v.created_at DESC, v.id_vocab DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':approval_status' => $approvalStatus]);
    return $stmt->fetchAll();
}

/**
 * Mengambil satu baris kosakata berdasarkan id_vocab untuk pre-filled form Edit.
 *
 * @return array<string, mixed>|null
 */
function getVocabById(PDO $pdo, int $idVocab): ?array
{
    $sql = "SELECT v.id_vocab,
                   v.id_kategori,
                   k.nama_kategori,
                   v.english_word,
                   v.part_of_speech,
                   v.indonesian_meaning,
                   v.example_sentence,
                   v.submitted_by,
                   v.approval_status,
                   v.mastery_status,
                   v.created_at
            FROM daftar_vocab v
            INNER JOIN kategori_vocab k ON v.id_kategori = k.id_kategori
            WHERE v.id_vocab = :id_vocab
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_vocab' => $idVocab]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Memeriksa apakah id_kategori terdaftar di tabel kategori_vocab.
 */
function categoryExists(PDO $pdo, int $idKategori): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM kategori_vocab WHERE id_kategori = :id LIMIT 1");
    $stmt->execute([':id' => $idKategori]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Menyimpan kosakata baru ke tabel daftar_vocab menggunakan Prepared Statement.
 */
function createVocab(PDO $pdo, array $data): bool
{
    $sql = "INSERT INTO daftar_vocab (
                id_kategori,
                english_word,
                part_of_speech,
                indonesian_meaning,
                example_sentence,
                submitted_by,
                approval_status,
                mastery_status
            ) VALUES (
                :id_kategori,
                :english_word,
                :part_of_speech,
                :indonesian_meaning,
                :example_sentence,
                :submitted_by,
                :approval_status,
                :mastery_status
            )";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':id_kategori'        => (int) $data['id_kategori'],
        ':english_word'       => $data['english_word'],
        ':part_of_speech'     => $data['part_of_speech'],
        ':indonesian_meaning' => $data['indonesian_meaning'],
        ':example_sentence'   => $data['example_sentence'] !== '' ? $data['example_sentence'] : null,
        ':submitted_by'       => $data['submitted_by'],
        ':approval_status'    => $data['approval_status'],
        ':mastery_status'     => $data['mastery_status'],
    ]);
}

/**
 * Memperbarui seluruh kolom kosakata berdasarkan id_vocab.
 */
function updateVocab(PDO $pdo, int $idVocab, array $data): bool
{
    $sql = "UPDATE daftar_vocab
            SET id_kategori = :id_kategori,
                english_word = :english_word,
                part_of_speech = :part_of_speech,
                indonesian_meaning = :indonesian_meaning,
                example_sentence = :example_sentence,
                submitted_by = :submitted_by,
                approval_status = :approval_status,
                mastery_status = :mastery_status
            WHERE id_vocab = :id_vocab";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':id_kategori'        => (int) $data['id_kategori'],
        ':english_word'       => $data['english_word'],
        ':part_of_speech'     => $data['part_of_speech'],
        ':indonesian_meaning' => $data['indonesian_meaning'],
        ':example_sentence'   => $data['example_sentence'] !== '' ? $data['example_sentence'] : null,
        ':submitted_by'       => $data['submitted_by'],
        ':approval_status'    => $data['approval_status'],
        ':mastery_status'     => $data['mastery_status'],
        ':id_vocab'           => $idVocab,
    ]);
}

/**
 * Mengubah status kosakata menjadi Approved (fitur ACC admin).
 */
function approveVocabById(PDO $pdo, int $idVocab): bool
{
    $stmt = $pdo->prepare("UPDATE daftar_vocab SET approval_status = 'Approved' WHERE id_vocab = :id_vocab");
    return $stmt->execute([':id_vocab' => $idVocab]);
}

/**
 * Menghapus kosakata secara permanen berdasarkan id_vocab.
 */
function deleteVocabById(PDO $pdo, int $idVocab): bool
{
    $stmt = $pdo->prepare("DELETE FROM daftar_vocab WHERE id_vocab = :id_vocab");
    return $stmt->execute([':id_vocab' => $idVocab]);
}

/**
 * Mengembalikan pesan notifikasi status untuk pola Post/Redirect/Get (PRG).
 *
 * @return array{type: string, title: string, message: string}|null
 */
function getFlashNotification(?string $msgCode): ?array
{
    if ($msgCode === null || $msgCode === '') {
        return null;
    }

    $messages = [
        'submitted' => [
            'type'    => 'success',
            'title'   => 'Kontribusi Terkirim ke Antrean Moderasi',
            'message' => 'Kosakata Anda telah disimpan dengan status Pending. Data akan tampil di halaman publik setelah disetujui oleh kurator.',
        ],
        'added' => [
            'type'    => 'success',
            'title'   => 'Kosakata Baru Ditambahkan',
            'message' => 'Entri kosakata berhasil disimpan dengan status Approved dan langsung tayang di kamus publik.',
        ],
        'approved' => [
            'type'    => 'success',
            'title'   => 'Kiriman Pengunjung Disetujui (ACC)',
            'message' => 'Status kosakata telah diubah dari Pending menjadi Approved dan kini tampil di daftar publik.',
        ],
        'updated' => [
            'type'    => 'success',
            'title'   => 'Perubahan Kosakata Tersimpan',
            'message' => 'Data kosakata berhasil diperbarui di database.',
        ],
        'deleted' => [
            'type'    => 'notice',
            'title'   => 'Entri Kosakata Dihapus',
            'message' => 'Data kosakata terpilih telah dihapus permanen dari database.',
        ],
        'forbidden' => [
            'type'    => 'error',
            'title'   => 'Akses Manajemen Ditolak (403)',
            'message' => 'Halaman manajemen khusus pemilik memerlukan parameter kunci rahasia yang valid pada URL.',
        ],
        'notfound' => [
            'type'    => 'error',
            'title'   => 'Data Kosakata Tidak Ditemukan',
            'message' => 'ID kosakata yang diminta tidak tersedia atau sudah dihapus dari database.',
        ],
        'invalid' => [
            'type'    => 'error',
            'title'   => 'Permintaan Tidak Valid',
            'message' => 'Pastikan seluruh kolom wajib telah diisi sesuai format sebelum mengirim formulir.',
        ],
    ];

    return $messages[$msgCode] ?? null;
}
