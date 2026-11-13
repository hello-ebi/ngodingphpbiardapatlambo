<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Validasi parameter kunci rahasia tunggal (?key=cukurukuk666)
$activeAdminKey = requireAdminKey();
$manageBaseUrl = 'manage.php?key=' . urlencode($activeAdminKey);

$idVocab = filter_var($_GET['id'] ?? $_POST['id_vocab'] ?? null, FILTER_VALIDATE_INT);
if ($idVocab === false || $idVocab <= 0) {
    header('Location: ' . $manageBaseUrl . '&msg=notfound');
    exit;
}

$categories = [];
$vocabItem = null;
$formErrors = [];

if ($pdo !== null) {
    try {
        $categories = getCategories($pdo);
        $vocabItem = getVocabById($pdo, $idVocab);
    } catch (PDOException $e) {
        $dbConnectionError = 'Gagal mengambil data kosakata dari database.';
    }
}

if ($pdo !== null && $vocabItem === null && $dbConnectionError === null) {
    header('Location: ' . $manageBaseUrl . '&msg=notfound');
    exit;
}

// Pre-fill data form dari baris database
$formData = [
    'id_kategori'        => $vocabItem['id_kategori'] ?? '',
    'english_word'       => $vocabItem['english_word'] ?? '',
    'part_of_speech'     => $vocabItem['part_of_speech'] ?? 'Noun',
    'indonesian_meaning' => $vocabItem['indonesian_meaning'] ?? '',
    'example_sentence'   => $vocabItem['example_sentence'] ?? '',
    'submitted_by'       => $vocabItem['submitted_by'] ?? 'helloebi',
    'approval_status'    => $vocabItem['approval_status'] ?? 'Approved',
    'mastery_status'     => $vocabItem['mastery_status'] ?? 'New',
];

// Proses Update Kosakata (Sub-CPMK 6) dengan pola Post/Redirect/Get (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vocab' && $pdo !== null) {
    $formData['id_kategori']        = trim((string) ($_POST['id_kategori'] ?? ''));
    $formData['english_word']       = trim((string) ($_POST['english_word'] ?? ''));
    $formData['part_of_speech']     = trim((string) ($_POST['part_of_speech'] ?? ''));
    $formData['indonesian_meaning'] = trim((string) ($_POST['indonesian_meaning'] ?? ''));
    $formData['example_sentence']   = trim((string) ($_POST['example_sentence'] ?? ''));
    $formData['submitted_by']       = trim((string) ($_POST['submitted_by'] ?? ''));
    $formData['approval_status']    = trim((string) ($_POST['approval_status'] ?? ''));
    $formData['mastery_status']     = trim((string) ($_POST['mastery_status'] ?? ''));

    $idKategoriInt = filter_var($formData['id_kategori'], FILTER_VALIDATE_INT);

    if ($idKategoriInt === false || $idKategoriInt <= 0 || !categoryExists($pdo, $idKategoriInt)) {
        $formErrors[] = 'Pilih kategori kosakata yang valid.';
    }

    if ($formData['english_word'] === '' || mb_strlen($formData['english_word']) > 100) {
        $formErrors[] = 'Kosakata bahasa Inggris wajib diisi (maksimal 100 karakter).';
    }

    if (!in_array($formData['part_of_speech'], getAllowedPartsOfSpeech(), true)) {
        $formErrors[] = 'Kelas kata (Part of Speech) tidak valid.';
    }

    if ($formData['indonesian_meaning'] === '' || mb_strlen($formData['indonesian_meaning']) > 150) {
        $formErrors[] = 'Arti bahasa Indonesia wajib diisi (maksimal 150 karakter).';
    }

    if ($formData['submitted_by'] === '' || mb_strlen($formData['submitted_by']) > 50) {
        $formErrors[] = 'Nama pengirim wajib diisi (maksimal 50 karakter).';
    }

    if (!in_array($formData['approval_status'], getAllowedApprovalStatuses(), true)) {
        $formErrors[] = 'Status persetujuan (Approval Status) tidak valid.';
    }

    if (!in_array($formData['mastery_status'], getAllowedMasteryStatuses(), true)) {
        $formErrors[] = 'Status penguasaan (Mastery Status) tidak valid.';
    }

    if (empty($formErrors)) {
        try {
            updateVocab($pdo, $idVocab, [
                'id_kategori'        => $idKategoriInt,
                'english_word'       => $formData['english_word'],
                'part_of_speech'     => $formData['part_of_speech'],
                'indonesian_meaning' => $formData['indonesian_meaning'],
                'example_sentence'   => $formData['example_sentence'],
                'submitted_by'       => $formData['submitted_by'],
                'approval_status'    => $formData['approval_status'],
                'mastery_status'     => $formData['mastery_status'],
            ]);

            header('Location: ' . $manageBaseUrl . '&msg=updated');
            exit;
        } catch (PDOException $e) {
            $formErrors[] = 'Gagal memperbarui kosakata di database.';
        }
    }
}

$editActionUrl = 'edit.php?id=' . urlencode((string) $idVocab) . '&key=' . urlencode($activeAdminKey);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kosakata #<?= e($idVocab) ?> (<?= e($formData['english_word']) ?>) - English Vocabulary Builder</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <header class="site-header">
        <div class="container header-inner">
            <div class="brand-group">
                <a class="brand-title" href="<?= e($manageBaseUrl) ?>">Panel Kurasi &amp; Moderasi</a>
                <p class="brand-subtitle">
                    Formulir Pembaruan Data Kosakata (<a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>)
                </p>
            </div>
            <nav class="site-nav" aria-label="Navigasi Edit">
                <a class="nav-link" href="index.php">Lihat Kamus Publik</a>
                <a class="nav-link" href="<?= e($manageBaseUrl) ?>">Kembali ke Moderasi</a>
            </nav>
        </div>
    </header>

    <main class="main-content" id="main-content">
        <div class="container">
            <section class="page-masthead" aria-labelledby="edit-heading">
                <div class="masthead-copy">
                    <h1 class="masthead-title" id="edit-heading">
                        Edit Kosakata: <?= e($formData['english_word']) ?>
                    </h1>
                    <p class="masthead-desc">
                        Perbarui istilah bahasa Inggris, kategori, kelas kata, arti bahasa Indonesia, contoh kalimat, hingga status moderasi dan penguasaan untuk ID <code>#<?= e($idVocab) ?></code> oleh kurator <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>.
                    </p>
                </div>
                <?php if ($vocabItem !== null): ?>
                <div class="masthead-meta" aria-label="Metadata Entri">
                    <div class="meta-item">
                        <span class="meta-label">ID Kosakata</span>
                        <span class="meta-value">#<?= e($vocabItem['id_vocab']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Waktu Dibuat</span>
                        <span class="meta-value"><?= e($vocabItem['created_at']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($dbConnectionError !== null): ?>
            <section class="alert-banner alert-error" role="alert" aria-live="assertive">
                <h2 class="alert-title">Koneksi Database Bermasalah</h2>
                <p class="alert-message"><?= e($dbConnectionError) ?></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
            <section class="alert-banner alert-error" role="alert" aria-live="assertive">
                <h2 class="alert-title">Pembaruan Data Gagal</h2>
                <ul class="alert-list">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <section class="panel" aria-labelledby="form-edit-title">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title" id="form-edit-title">Formulir Pre-filled Kosakata</h2>
                        <p class="panel-subtitle">
                            Seluruh kolom di bawah telah diisi otomatis berdasarkan data tersimpan pada <code>id_vocab = <?= e($idVocab) ?></code>.
                        </p>
                    </div>
                </div>

                <form method="post" action="<?= e($editActionUrl) ?>" class="form-grid" id="edit-vocab-form">
                    <input type="hidden" name="key" value="<?= e($activeAdminKey) ?>">
                    <input type="hidden" name="id_vocab" value="<?= e($idVocab) ?>">
                    <input type="hidden" name="action" value="update_vocab">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="english_word">
                                Kosakata Bahasa Inggris <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                id="english_word"
                                name="english_word"
                                class="form-control"
                                maxlength="100"
                                required
                                value="<?= e($formData['english_word']) ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="submitted_by">
                                Nama Kontributor / Pengirim <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                id="submitted_by"
                                name="submitted_by"
                                class="form-control"
                                maxlength="50"
                                required
                                value="<?= e($formData['submitted_by']) ?>"
                            >
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="id_kategori">
                                Kategori Kosakata <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <select id="id_kategori" name="id_kategori" class="form-control" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option
                                        value="<?= e($cat['id_kategori']) ?>"
                                        <?= (string) $formData['id_kategori'] === (string) $cat['id_kategori'] ? 'selected' : '' ?>
                                    >
                                        <?= e($cat['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="part_of_speech">
                                Kelas Kata (Part of Speech) <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <select id="part_of_speech" name="part_of_speech" class="form-control" required>
                                <?php foreach (getAllowedPartsOfSpeech() as $pos): ?>
                                    <option
                                        value="<?= e($pos) ?>"
                                        <?= $formData['part_of_speech'] === $pos ? 'selected' : '' ?>
                                    >
                                        <?= e($pos) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="indonesian_meaning">
                            Arti Bahasa Indonesia <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            id="indonesian_meaning"
                            name="indonesian_meaning"
                            class="form-control"
                            maxlength="150"
                            required
                            value="<?= e($formData['indonesian_meaning']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="example_sentence">Contoh Kalimat Penggunaan</label>
                        <textarea
                            id="example_sentence"
                            name="example_sentence"
                            class="form-control"
                        ><?= e($formData['example_sentence']) ?></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="approval_status">
                                Status Moderasi (Approval Status) <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <select id="approval_status" name="approval_status" class="form-control" required>
                                <?php foreach (getAllowedApprovalStatuses() as $status): ?>
                                    <option
                                        value="<?= e($status) ?>"
                                        <?= $formData['approval_status'] === $status ? 'selected' : '' ?>
                                    >
                                        <?= e($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="mastery_status">
                                Status Penguasaan (Mastery Status) <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <select id="mastery_status" name="mastery_status" class="form-control" required>
                                <?php foreach (getAllowedMasteryStatuses() as $mastery): ?>
                                    <option
                                        value="<?= e($mastery) ?>"
                                        <?= $formData['mastery_status'] === $mastery ? 'selected' : '' ?>
                                    >
                                        <?= e($mastery) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary" id="btn-update-submit">
                            Simpan Perubahan
                        </button>
                        <a href="<?= e($manageBaseUrl) ?>" class="btn btn-secondary">
                            Batal &amp; Kembali ke Moderasi
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span>Mode Edit Kurator: <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a> (ID #<?= e($idVocab) ?>)</span>
            <a href="<?= e($manageBaseUrl) ?>" class="nav-link">Kembali ke Panel Moderasi</a>
        </div>
    </footer>

    <script>
        (function () {
            var editForm = document.getElementById('edit-vocab-form');
            var updateBtn = document.getElementById('btn-update-submit');
            if (editForm && updateBtn) {
                editForm.addEventListener('submit', function () {
                    updateBtn.disabled = true;
                    updateBtn.setAttribute('aria-busy', 'true');
                    updateBtn.textContent = 'Menyimpan perubahan...';
                });
            }
        })();
    </script>
</body>
</html>
