<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$formErrors = [];
$formData = [
    'id_kategori'        => '',
    'english_word'       => '',
    'part_of_speech'     => 'Noun',
    'indonesian_meaning' => '',
    'example_sentence'   => '',
    'submitted_by'       => '',
];

// Proses pengiriman form kontribusi pengunjung (Status otomatis: Pending, PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_public_vocab') {
    $formData['id_kategori']        = trim((string) ($_POST['id_kategori'] ?? ''));
    $formData['english_word']       = trim((string) ($_POST['english_word'] ?? ''));
    $formData['part_of_speech']     = trim((string) ($_POST['part_of_speech'] ?? ''));
    $formData['indonesian_meaning'] = trim((string) ($_POST['indonesian_meaning'] ?? ''));
    $formData['example_sentence']   = trim((string) ($_POST['example_sentence'] ?? ''));
    $formData['submitted_by']       = trim((string) ($_POST['submitted_by'] ?? ''));

    $idKategoriInt = filter_var($formData['id_kategori'], FILTER_VALIDATE_INT);

    if ($pdo === null) {
        $formErrors[] = 'Koneksi database sedang bermasalah sehingga kontribusi belum dapat disimpan.';
    } else {
        if ($idKategoriInt === false || $idKategoriInt <= 0 || !categoryExists($pdo, $idKategoriInt)) {
            $formErrors[] = 'Pilih kategori kosakata yang valid dari daftar.';
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

        if (empty($formErrors)) {
            try {
                createVocab($pdo, [
                    'id_kategori'        => $idKategoriInt,
                    'english_word'       => $formData['english_word'],
                    'part_of_speech'     => $formData['part_of_speech'],
                    'indonesian_meaning' => $formData['indonesian_meaning'],
                    'example_sentence'   => $formData['example_sentence'],
                    'submitted_by'       => $formData['submitted_by'],
                    'approval_status'    => 'Pending',
                    'mastery_status'     => 'New',
                ]);

                header('Location: index.php?msg=submitted');
                exit;
            } catch (PDOException $e) {
                $formErrors[] = 'Gagal menyimpan kosakata ke database. Silakan periksa kembali isian Anda.';
            }
        }
    }
}

// Parameter Filter GET (kategori & kata kunci pencarian)
$selectedCategory = null;
if (isset($_GET['kategori']) && $_GET['kategori'] !== '') {
    $validatedCat = filter_var($_GET['kategori'], FILTER_VALIDATE_INT);
    if ($validatedCat !== false && $validatedCat > 0) {
        $selectedCategory = $validatedCat;
    }
}

$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$msgCode = isset($_GET['msg']) && is_string($_GET['msg']) ? trim($_GET['msg']) : null;
$flash = getFlashNotification($msgCode);

$categories = [];
$vocabList = [];
$totalAllApproved = 0;

if ($pdo !== null) {
    try {
        $categories = getCategories($pdo);
        foreach ($categories as $cat) {
            $totalAllApproved += (int) $cat['total_approved'];
        }
        $vocabList = getApprovedVocab($pdo, $selectedCategory, $searchQuery);
    } catch (PDOException $e) {
        $dbConnectionError = 'Tabel database belum ditemukan. Pastikan file database.sql sudah diimpor ke database ' . DB_NAME . '.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>English Vocabulary Builder &amp; Community Glossary (helloebi)</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <header class="site-header">
        <div class="container header-inner">
            <div class="brand-group">
                <a class="brand-title" href="index.php">VocabIn</a>
                <p class="brand-subtitle">
                    Koleksi Vocabulary <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a> &amp; Warganet
                </p>
            </div>
            <nav class="site-nav" aria-label="Navigasi Utama">
                <a class="nav-link" href="https://github.com/hello-ebi" target="_blank" rel="noopener noreferrer">Github ebi</a>
<a class="nav-link" href="https://helloebi.web.id" target="_blank" rel="noopener noreferrer">Web ebi</a>
            </nav>
        </div>
    </header>

    <main class="main-content" id="main-content">
        <div class="container">
            <section class="page-masthead" aria-labelledby="page-heading">
                <div class="masthead-copy">
                    <h1 class="masthead-title" id="page-heading">Kamus Kosakata Bahasa Inggris Terkurasi</h1>
                    <p class="masthead-desc">
                        Menampilkan daftar kosakata yang telah diverifikasi (berstatus <code>Approved</code>).
                        Pengunjung dapat menyumbangkan istilah baru melalui formulir kontribusi untuk ditinjau oleh <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>.
                    </p>
                </div>
                <?php if ($pdo !== null && $dbConnectionError === null): ?>
                <div class="masthead-meta" aria-label="Ringkasan Data Kamus">
                    <div class="meta-item">
                        <span class="meta-label">Total Kosakata Tayang</span>
                        <span class="meta-value"><?= e($totalAllApproved) ?> istilah</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Kategori Aktif</span>
                        <span class="meta-value"><?= e(count($categories)) ?> bidang</span>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($dbConnectionError !== null): ?>
            <!-- Error State: Database Connection / Schema Missing (R-27) -->
            <section class="alert-banner alert-error" role="alert" aria-live="assertive">
                <h2 class="alert-title">Koneksi atau Tabel Database Bermasalah</h2>
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
                <h2 class="alert-title">Pengiriman Kontribusi Gagal</h2>
                <p class="alert-message">Perbaiki kesalahan berikut lalu kirim ulang formulir:</p>
                <ul class="alert-list">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <div class="workspace-grid">
                <!-- Kolom Kiri: Daftar Kosakata Publik (Approved) + Filter Kategori -->
                <section class="panel" aria-labelledby="glossary-heading">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title" id="glossary-heading">Daftar Kosakata (Approved)</h2>
                            <p class="panel-subtitle">
                                Menampilkan <?= e(count($vocabList)) ?> entri kosakata dari database.
                            </p>
                        </div>
                        <?php if ($selectedCategory !== null || $searchQuery !== ''): ?>
                            <a class="btn btn-secondary" href="index.php">Reset Filter</a>
                        <?php endif; ?>
                    </div>

                    <div class="filter-bar">
                        <form method="get" action="index.php" class="filter-form" id="filter-form">
                            <div class="form-group">
                                <label class="form-label" for="filter-q">Cari Kata atau Arti</label>
                                <input
                                    type="search"
                                    id="filter-q"
                                    name="q"
                                    class="form-control"
                                    value="<?= e($searchQuery) ?>"
                                    placeholder="Ketik kata Inggris atau arti Indonesia..."
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filter-kategori">Filter Kategori</label>
                                <select id="filter-kategori" name="kategori" class="form-control">
                                    <option value="">Semua Kategori (<?= e($totalAllApproved) ?>)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?= e($cat['id_kategori']) ?>"
                                            <?= $selectedCategory === (int) $cat['id_kategori'] ? 'selected' : '' ?>
                                        >
                                            <?= e($cat['nama_kategori']) ?> (<?= e($cat['total_approved']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary" id="btn-filter-submit">Terapkan Filter</button>
                            </div>
                        </form>

                        <?php if (!empty($categories)): ?>
                        <nav class="category-pills" aria-label="Filter cepat berdasarkan kategori">
                            <a
                                href="index.php<?= $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : '' ?>"
                                class="category-pill <?= $selectedCategory === null ? 'is-active' : '' ?>"
                            >
                                Semua <span class="pill-count"><?= e($totalAllApproved) ?></span>
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                $catId = (int) $cat['id_kategori'];
                                $pillUrl = 'index.php?kategori=' . $catId . ($searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '');
                                ?>
                                <a
                                    href="<?= e($pillUrl) ?>"
                                    class="category-pill <?= $selectedCategory === $catId ? 'is-active' : '' ?>"
                                >
                                    <?= e($cat['nama_kategori']) ?>
                                    <span class="pill-count"><?= e($cat['total_approved']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($vocabList)): ?>
                        <!-- Empty State (R-27): menjelaskan penyebab kosong dan tindakan selanjutnya -->
                        <div class="state-box" role="status">
                            <?php if ($selectedCategory !== null || $searchQuery !== ''): ?>
                                <h3 class="state-title">Tidak Ada Kosakata yang Cocok dengan Filter</h3>
                                <p class="state-desc">
                                    Pencarian untuk kata kunci atau kategori yang dipilih belum memiliki entri berstatus <code>Approved</code>.
                                    Silakan tampilkan seluruh kategori atau ajukan kosakata baru melalui formulir di samping.
                                </p>
                                <a href="index.php" class="btn btn-secondary">Tampilkan Semua Kosakata</a>
                            <?php else: ?>
                                <h3 class="state-title">Belum Ada Kosakata yang Dipublikasikan</h3>
                                <p class="state-desc">
                                    Tabel kamus saat ini masih kosong atau seluruh kiriman masih berada dalam antrean moderasi (<code>Pending</code>).
                                    Gunakan formulir kontribusi untuk mengirim istilah pertama.
                                </p>
                                <a href="#form-kontribusi" class="btn btn-primary">Tambah Kosakata Pertama</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="vocab-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Kosakata &amp; Kelas Kata</th>
                                        <th scope="col">Kategori</th>
                                        <th scope="col">Arti &amp; Contoh Kalimat</th>
                                        <th scope="col">Penguasaan</th>
                                        <th scope="col">Kontributor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vocabList as $item): ?>
                                        <?php
                                        $masteryClass = 'tag-mastery-new';
                                        if ($item['mastery_status'] === 'Learning') {
                                            $masteryClass = 'tag-mastery-learning';
                                        } elseif ($item['mastery_status'] === 'Mastered') {
                                            $masteryClass = 'tag-mastery-mastered';
                                        }
                                        ?>
                                        <tr>
                                            <td data-label="Kosakata &amp; Kelas Kata">
                                                <span class="word-term"><?= e($item['english_word']) ?></span>
                                                <span class="tag tag-pos"><?= e($item['part_of_speech']) ?></span>
                                            </td>
                                            <td data-label="Kategori">
                                                <span class="tag"><?= e($item['nama_kategori']) ?></span>
                                            </td>
                                            <td data-label="Arti &amp; Contoh Kalimat">
                                                <p class="word-meaning"><?= e($item['indonesian_meaning']) ?></p>
                                                <?php if (!empty($item['example_sentence'])): ?>
                                                    <p class="word-example">"<?= e($item['example_sentence']) ?>"</p>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Penguasaan">
                                                <span class="tag <?= e($masteryClass) ?>"><?= e($item['mastery_status']) ?></span>
                                            </td>
                                            <td data-label="Kontributor">
                                                <span class="contributor-meta">Oleh: <?= renderContributor((string) $item['submitted_by']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Kolom Kanan: Form Kontribusi Kosakata Pengunjung (Status Default: Pending) -->
                <aside class="panel" id="form-kontribusi" aria-labelledby="contrib-heading">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title" id="contrib-heading">Kontribusi Kosakata Baru</h2>
                            <p class="panel-subtitle">
                                Setiap kiriman pengunjung disimpan otomatis dengan status <code>Pending</code> hingga disetujui (ACC) oleh <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>.
                            </p>
                        </div>
                    </div>

                    <form method="post" action="index.php" class="form-grid" id="public-contrib-form">
                        <input type="hidden" name="action" value="submit_public_vocab">

                        <div class="form-group">
                            <label class="form-label" for="submitted_by">
                                Nama Pengirim <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                id="submitted_by"
                                name="submitted_by"
                                class="form-control"
                                maxlength="50"
                                required
                                value="<?= e($formData['submitted_by']) ?>"
                                placeholder="Nama atau panggilan Anda"
                            >
                            <p class="form-hint">Ditampilkan sebagai kredit kontributor setelah kosakata disetujui.</p>
                        </div>

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
                                placeholder="Contoh: resilient, iron out"
                            >
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label" for="id_kategori">
                                    Kategori <span class="required-mark" aria-hidden="true">*</span>
                                </label>
                                <select id="id_kategori" name="id_kategori" class="form-control" required>
                                    <option value="">Pilih kategori...</option>
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
                                    Kelas Kata <span class="required-mark" aria-hidden="true">*</span>
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
                                placeholder="Ringkasan arti dalam bahasa Indonesia"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="example_sentence">Contoh Kalimat Penggunaan</label>
                            <textarea
                                id="example_sentence"
                                name="example_sentence"
                                class="form-control"
                                placeholder="Tuliskan satu kalimat bahasa Inggris yang memuat kosakata tersebut..."
                            ><?= e($formData['example_sentence']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="btn-public-submit">
                            Kirim Kosakata ke Antrean Moderasi
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span>VocabIn (Dibuat oleh: <a href="https://helloebi.web.id" class="author-link" target="_blank" rel="noopener noreferrer">helloebi</a>)</span>
            <span>PHP 8 Native</span>
        </div>
    </footer>

    <script>
        (function () {
            // Loading state feedback pada pengiriman form (R-27)
            var publicForm = document.getElementById('public-contrib-form');
            var submitBtn = document.getElementById('btn-public-submit');
            if (publicForm && submitBtn) {
                publicForm.addEventListener('submit', function () {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('aria-busy', 'true');
                    submitBtn.textContent = 'Menyimpan kosakata...';
                });
            }
        })();
    </script>
</body>
</html>
