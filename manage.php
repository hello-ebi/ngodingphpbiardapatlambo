<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Validasi parameter kunci rahasia tunggal (?key=cukurukuk666)
$activeAdminKey = requireAdminKey();
$manageBaseUrl = 'manage.php?key=' . urlencode($activeAdminKey);

$formErrors = [];
$adminFormData = [
    'id_kategori'        => '',
    'english_word'       => '',
    'part_of_speech'     => 'Noun',
    'indonesian_meaning' => '',
    'example_sentence'   => '',
    'mastery_status'     => 'New',
];

// Proses Aksi POST Admin: Create (Approved), ACC (Pending -> Approved), dan Delete (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'admin_create') {
        $adminFormData['id_kategori']        = trim((string) ($_POST['id_kategori'] ?? ''));
        $adminFormData['english_word']       = trim((string) ($_POST['english_word'] ?? ''));
        $adminFormData['part_of_speech']     = trim((string) ($_POST['part_of_speech'] ?? ''));
        $adminFormData['indonesian_meaning'] = trim((string) ($_POST['indonesian_meaning'] ?? ''));
        $adminFormData['example_sentence']   = trim((string) ($_POST['example_sentence'] ?? ''));
        $adminFormData['mastery_status']     = trim((string) ($_POST['mastery_status'] ?? 'New'));

        $idKategoriInt = filter_var($adminFormData['id_kategori'], FILTER_VALIDATE_INT);

        if ($idKategoriInt === false || $idKategoriInt <= 0 || !categoryExists($pdo, $idKategoriInt)) {
            $formErrors[] = 'Pilih kategori kosakata yang valid.';
        }

        if ($adminFormData['english_word'] === '' || mb_strlen($adminFormData['english_word']) > 100) {
            $formErrors[] = 'Kosakata bahasa Inggris wajib diisi (maksimal 100 karakter).';
        }

        if (!in_array($adminFormData['part_of_speech'], getAllowedPartsOfSpeech(), true)) {
            $formErrors[] = 'Kelas kata (Part of Speech) tidak valid.';
        }

        if ($adminFormData['indonesian_meaning'] === '' || mb_strlen($adminFormData['indonesian_meaning']) > 150) {
            $formErrors[] = 'Arti bahasa Indonesia wajib diisi (maksimal 150 karakter).';
        }

        if (!in_array($adminFormData['mastery_status'], getAllowedMasteryStatuses(), true)) {
            $formErrors[] = 'Status penguasaan (Mastery Status) tidak valid.';
        }

        if (empty($formErrors)) {
            try {
                createVocab($pdo, [
                    'id_kategori'        => $idKategoriInt,
                    'english_word'       => $adminFormData['english_word'],
                    'part_of_speech'     => $adminFormData['part_of_speech'],
                    'indonesian_meaning' => $adminFormData['indonesian_meaning'],
                    'example_sentence'   => $adminFormData['example_sentence'],
                    'submitted_by'       => 'helloebi',
                    'approval_status'    => 'Approved',
                    'mastery_status'     => $adminFormData['mastery_status'],
                ]);

                header('Location: ' . $manageBaseUrl . '&msg=added');
                exit;
            } catch (PDOException $e) {
                $formErrors[] = 'Gagal menyimpan kosakata baru ke database.';
            }
        }
    } elseif ($action === 'approve_vocab') {
        $idVocab = filter_var($_POST['id_vocab'] ?? null, FILTER_VALIDATE_INT);
        if ($idVocab !== false && $idVocab > 0) {
            approveVocabById($pdo, $idVocab);
            header('Location: ' . $manageBaseUrl . '&msg=approved');
            exit;
        }
        header('Location: ' . $manageBaseUrl . '&msg=invalid');
        exit;
    } elseif ($action === 'delete_vocab') {
        $idVocab = filter_var($_POST['id_vocab'] ?? null, FILTER_VALIDATE_INT);
        if ($idVocab !== false && $idVocab > 0) {
            deleteVocabById($pdo, $idVocab);
            header('Location: ' . $manageBaseUrl . '&msg=deleted');
            exit;
        }
        header('Location: ' . $manageBaseUrl . '&msg=invalid');
        exit;
    }
}

$msgCode = isset($_GET['msg']) && is_string($_GET['msg']) ? trim($_GET['msg']) : null;
$flash = getFlashNotification($msgCode);

$categories = [];
$pendingList = [];
$approvedList = [];

if ($pdo !== null) {
    try {
        $categories = getCategories($pdo);
        $pendingList = getVocabByApprovalStatus($pdo, 'Pending');
        $approvedList = getVocabByApprovalStatus($pdo, 'Approved');
    } catch (PDOException $e) {
        $dbConnectionError = 'Gagal memuat tabel kosakata. Pastikan database.sql sudah diimpor.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen &amp; Moderasi Kosakata (helloebi) - English Vocabulary Builder</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <header class="site-header">
        <div class="container header-inner">
            <div class="brand-group">
                <a class="brand-title" href="<?= e($manageBaseUrl) ?>">Panel Kurasi &amp; Moderasi</a>
                <p class="brand-subtitle">
                    Akses Pemilik Terverifikasi: <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>
                </p>
            </div>
            <nav class="site-nav" aria-label="Navigasi Manajemen">
                <a class="nav-link" href="index.php">Lihat Kamus Publik</a>
                <a class="nav-link" href="<?= e($manageBaseUrl) ?>" aria-current="page">Moderasi &amp; CRUD</a>
                <a class="nav-link" href="#antrean-pending">Antrean Pending (<?= e(count($pendingList)) ?>)</a>
                <a class="nav-link" href="#daftar-approved">Data Approved (<?= e(count($approvedList)) ?>)</a>
            </nav>
        </div>
    </header>

    <main class="main-content" id="main-content">
        <div class="container">
            <section class="page-masthead" aria-labelledby="admin-heading">
                <div class="masthead-copy">
                    <h1 class="masthead-title" id="admin-heading">Manajemen Kosakata &amp; Moderasi Komunitas</h1>
                    <p class="masthead-desc">
                        Kelola kiriman pengunjung yang berstatus <code>Pending</code> (setujui dengan tombol <strong>ACC</strong> atau hapus jika tidak relevan),
                        tambahkan kosakata baru yang langsung berstatus <code>Approved</code> atas nama <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>, atau perbarui data yang sudah tayang.
                    </p>
                </div>
                <div class="masthead-meta" aria-label="Statistik Antrean Moderasi">
                    <div class="meta-item">
                        <span class="meta-label">Menunggu ACC (Pending)</span>
                        <span class="meta-value"><?= e(count($pendingList)) ?> kiriman</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Telah Tayang (Approved)</span>
                        <span class="meta-value"><?= e(count($approvedList)) ?> istilah</span>
                    </div>
                </div>
            </section>

            <?php if ($dbConnectionError !== null): ?>
            <section class="alert-banner alert-error" role="alert" aria-live="assertive">
                <h2 class="alert-title">Koneksi Database Bermasalah</h2>
                <p class="alert-message"><?= e($dbConnectionError) ?></p>
            </section>
            <?php endif; ?>

            <?php if ($flash !== null): ?>
            <section class="alert-banner alert-<?= e($flash['type']) ?>" role="status" aria-live="polite">
                <h2 class="alert-title"><?= e($flash['title']) ?></h2>
                <p class="alert-message"><?= e($flash['message']) ?></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
            <section class="alert-banner alert-error" role="alert" aria-live="assertive">
                <h2 class="alert-title">Gagal Menyimpan Kosakata</h2>
                <ul class="alert-list">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <div class="workspace-grid">
                <!-- Kolom Kiri: Tabel Antrean Moderasi Pending & Tabel Data Approved -->
                <div>
                    <!-- 1. Tabel Antrean Moderasi (Pending) -->
                    <section class="panel" id="antrean-pending" aria-labelledby="pending-heading">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title" id="pending-heading">
                                    Antrean Moderasi Pengunjung (<span class="tag tag-pending">Pending: <?= e(count($pendingList)) ?></span>)
                                </h2>
                                <p class="panel-subtitle">
                                    Tinjau kontribusi pengunjung. Klik <strong>ACC</strong> untuk mempublikasikan ke halaman utama atau <strong>Tolak / Hapus</strong> untuk menghapus permanen.
                                </p>
                            </div>
                        </div>

                        <?php if (empty($pendingList)): ?>
                            <div class="state-box" role="status">
                                <h3 class="state-title">Antrean Moderasi Kosong</h3>
                                <p class="state-desc">
                                    Seluruh kiriman pengunjung telah selesai ditinjau. Kiriman baru dari formulir publik akan otomatis muncul di daftar ini.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrapper">
                                <table class="vocab-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Kosakata &amp; Kelas Kata</th>
                                            <th scope="col">Kategori</th>
                                            <th scope="col">Arti &amp; Contoh Kalimat</th>
                                            <th scope="col">Pengirim</th>
                                            <th scope="col">Tindakan Moderasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingList as $row): ?>
                                            <tr>
                                                <td data-label="Kosakata &amp; Kelas Kata">
                                                    <span class="word-term"><?= e($row['english_word']) ?></span>
                                                    <span class="tag tag-pos"><?= e($row['part_of_speech']) ?></span>
                                                </td>
                                                <td data-label="Kategori">
                                                    <span class="tag"><?= e($row['nama_kategori']) ?></span>
                                                </td>
                                                <td data-label="Arti &amp; Contoh Kalimat">
                                                    <p class="word-meaning"><?= e($row['indonesian_meaning']) ?></p>
                                                    <?php if (!empty($row['example_sentence'])): ?>
                                                        <p class="word-example">"<?= e($row['example_sentence']) ?>"</p>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Pengirim">
                                                    <span class="contributor-meta"><?= renderContributor((string) $row['submitted_by']) ?></span>
                                                </td>
                                                <td data-label="Tindakan Moderasi">
                                                    <div class="btn-row">
                                                        <form method="post" action="<?= e($manageBaseUrl) ?>">
                                                            <input type="hidden" name="key" value="<?= e($activeAdminKey) ?>">
                                                            <input type="hidden" name="action" value="approve_vocab">
                                                            <input type="hidden" name="id_vocab" value="<?= e($row['id_vocab']) ?>">
                                                            <button
                                                                type="submit"
                                                                class="btn btn-success"
                                                                aria-label="Setujui (ACC) kosakata <?= e($row['english_word']) ?>"
                                                            >
                                                                ACC
                                                            </button>
                                                        </form>

                                                        <a
                                                            href="edit.php?id=<?= e($row['id_vocab']) ?>&amp;key=<?= e(urlencode($activeAdminKey)) ?>"
                                                            class="btn btn-secondary"
                                                            aria-label="Edit kosakata <?= e($row['english_word']) ?> sebelum disetujui"
                                                        >
                                                            Edit
                                                        </a>

                                                        <form
                                                            method="post"
                                                            action="<?= e($manageBaseUrl) ?>"
                                                            class="js-delete-form"
                                                            data-word="<?= e($row['english_word']) ?>"
                                                        >
                                                            <input type="hidden" name="key" value="<?= e($activeAdminKey) ?>">
                                                            <input type="hidden" name="action" value="delete_vocab">
                                                            <input type="hidden" name="id_vocab" value="<?= e($row['id_vocab']) ?>">
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger"
                                                                aria-label="Tolak dan hapus kosakata <?= e($row['english_word']) ?>"
                                                            >
                                                                Tolak / Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- 2. Tabel Daftar Kosakata Approved -->
                    <section class="panel" id="daftar-approved" aria-labelledby="approved-heading">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title" id="approved-heading">
                                    Daftar Kosakata Tayang (<span class="tag tag-approved">Approved: <?= e(count($approvedList)) ?></span>)
                                </h2>
                                <p class="panel-subtitle">
                                    Seluruh kosakata di bawah ini tampil di halaman publik. Gunakan tombol <strong>Edit</strong> untuk mengubah isi atau tingkat penguasaan.
                                </p>
                            </div>
                        </div>

                        <?php if (empty($approvedList)): ?>
                            <div class="state-box" role="status">
                                <h3 class="state-title">Belum Ada Kosakata Berstatus Approved</h3>
                                <p class="state-desc">
                                    Gunakan formulir tambah kosakata di kolom samping atau setujui kiriman dari antrean <code>Pending</code> di atas.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrapper">
                                <table class="vocab-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Kosakata &amp; Kelas Kata</th>
                                            <th scope="col">Kategori</th>
                                            <th scope="col">Arti &amp; Contoh Kalimat</th>
                                            <th scope="col">Penguasaan &amp; Pengirim</th>
                                            <th scope="col">Aksi CRUD</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approvedList as $row): ?>
                                            <?php
                                            $masteryClass = 'tag-mastery-new';
                                            if ($row['mastery_status'] === 'Learning') {
                                                $masteryClass = 'tag-mastery-learning';
                                            } elseif ($row['mastery_status'] === 'Mastered') {
                                                $masteryClass = 'tag-mastery-mastered';
                                            }
                                            ?>
                                            <tr>
                                                <td data-label="Kosakata &amp; Kelas Kata">
                                                    <span class="word-term"><?= e($row['english_word']) ?></span>
                                                    <span class="tag tag-pos"><?= e($row['part_of_speech']) ?></span>
                                                </td>
                                                <td data-label="Kategori">
                                                    <span class="tag"><?= e($row['nama_kategori']) ?></span>
                                                </td>
                                                <td data-label="Arti &amp; Contoh Kalimat">
                                                    <p class="word-meaning"><?= e($row['indonesian_meaning']) ?></p>
                                                    <?php if (!empty($row['example_sentence'])): ?>
                                                        <p class="word-example">"<?= e($row['example_sentence']) ?>"</p>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Penguasaan &amp; Pengirim">
                                                    <div><span class="tag <?= e($masteryClass) ?>"><?= e($row['mastery_status']) ?></span></div>
                                                    <span class="contributor-meta">Oleh: <?= renderContributor((string) $row['submitted_by']) ?></span>
                                                </td>
                                                <td data-label="Aksi CRUD">
                                                    <div class="btn-row">
                                                        <a
                                                            href="edit.php?id=<?= e($row['id_vocab']) ?>&amp;key=<?= e(urlencode($activeAdminKey)) ?>"
                                                            class="btn btn-secondary"
                                                            aria-label="Edit kosakata <?= e($row['english_word']) ?>"
                                                        >
                                                            Edit
                                                        </a>

                                                        <form
                                                            method="post"
                                                            action="<?= e($manageBaseUrl) ?>"
                                                            class="js-delete-form"
                                                            data-word="<?= e($row['english_word']) ?>"
                                                        >
                                                            <input type="hidden" name="key" value="<?= e($activeAdminKey) ?>">
                                                            <input type="hidden" name="action" value="delete_vocab">
                                                            <input type="hidden" name="id_vocab" value="<?= e($row['id_vocab']) ?>">
                                                            <button
                                                                type="submit"
                                                                class="btn btn-danger"
                                                                aria-label="Hapus kosakata <?= e($row['english_word']) ?>"
                                                            >
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Kolom Kanan: Form Tambah Kosakata Admin helloebi (Langsung Approved) -->
                <aside class="panel" aria-labelledby="admin-create-heading">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title" id="admin-create-heading">
                                Tambah Kosakata (Admin <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>)
                            </h2>
                            <p class="panel-subtitle">
                                Kosakata yang ditambahkan melalui formulir ini langsung disimpan dengan status <code>Approved</code> atas nama <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>.
                            </p>
                        </div>
                    </div>

                    <form method="post" action="<?= e($manageBaseUrl) ?>" class="form-grid" id="admin-create-form">
                        <input type="hidden" name="key" value="<?= e($activeAdminKey) ?>">
                        <input type="hidden" name="action" value="admin_create">

                        <div class="form-group">
                            <label class="form-label" for="admin_english_word">
                                Kosakata Bahasa Inggris <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                id="admin_english_word"
                                name="english_word"
                                class="form-control"
                                maxlength="100"
                                required
                                value="<?= e($adminFormData['english_word']) ?>"
                                placeholder="Contoh: ephemeral, bottleneck"
                            >
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label" for="admin_id_kategori">
                                    Kategori <span class="required-mark" aria-hidden="true">*</span>
                                </label>
                                <select id="admin_id_kategori" name="id_kategori" class="form-control" required>
                                    <option value="">Pilih kategori...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?= e($cat['id_kategori']) ?>"
                                            <?= (string) $adminFormData['id_kategori'] === (string) $cat['id_kategori'] ? 'selected' : '' ?>
                                        >
                                            <?= e($cat['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="admin_part_of_speech">
                                    Kelas Kata <span class="required-mark" aria-hidden="true">*</span>
                                </label>
                                <select id="admin_part_of_speech" name="part_of_speech" class="form-control" required>
                                    <?php foreach (getAllowedPartsOfSpeech() as $pos): ?>
                                        <option
                                            value="<?= e($pos) ?>"
                                            <?= $adminFormData['part_of_speech'] === $pos ? 'selected' : '' ?>
                                        >
                                            <?= e($pos) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admin_indonesian_meaning">
                                Arti Bahasa Indonesia <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                id="admin_indonesian_meaning"
                                name="indonesian_meaning"
                                class="form-control"
                                maxlength="150"
                                required
                                value="<?= e($adminFormData['indonesian_meaning']) ?>"
                                placeholder="Arti ringkas dan jelas dalam bahasa Indonesia"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admin_example_sentence">Contoh Kalimat Penggunaan</label>
                            <textarea
                                id="admin_example_sentence"
                                name="example_sentence"
                                class="form-control"
                                placeholder="Contoh kalimat kontekstual..."
                            ><?= e($adminFormData['example_sentence']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admin_mastery_status">
                                Status Penguasaan (Mastery Status) <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <select id="admin_mastery_status" name="mastery_status" class="form-control" required>
                                <?php foreach (getAllowedMasteryStatuses() as $mastery): ?>
                                    <option
                                        value="<?= e($mastery) ?>"
                                        <?= $adminFormData['mastery_status'] === $mastery ? 'selected' : '' ?>
                                    >
                                        <?= e($mastery) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="btn-admin-create">
                            Simpan &amp; Publikasikan (Approved)
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span>Mode Kurator Aktif: <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a></span>
            <a href="index.php" class="nav-link">Kembali ke Halaman Publik</a>
        </div>
    </footer>

    <script>
        (function () {
            // Konfirmasi penghapusan permanen menggunakan Vanilla JS confirm() (Sub-CPMK 7)
            var deleteForms = document.querySelectorAll('.js-delete-form');
            for (var i = 0; i < deleteForms.length; i++) {
                deleteForms[i].addEventListener('submit', function (event) {
                    var word = this.getAttribute('data-word') || 'ini';
                    var confirmed = window.confirm('Apakah Anda yakin ingin menghapus kosakata "' + word + '" secara permanen dari database?');
                    if (!confirmed) {
                        event.preventDefault();
                    }
                });
            }

            // Loading state pada form tambah kosakata admin
            var createForm = document.getElementById('admin-create-form');
            var createBtn = document.getElementById('btn-admin-create');
            if (createForm && createBtn) {
                createForm.addEventListener('submit', function () {
                    createBtn.disabled = true;
                    createBtn.setAttribute('aria-busy', 'true');
                    createBtn.textContent = 'Menyimpan data...';
                });
            }
        })();
    </script>
</body>
</html>
