# English Vocabulary Glossary

Aplikasi kamus kosakata bahasa Inggris berbasis PHP 8 Native (PDO) dan MySQL. Proyek ini dibuat sebagai catatan kosakata pribadi milik [helloebi](https://helloebi.web.id) sekaligus menyediakan formulir bagi pengunjung yang ingin ikut menyumbangkan istilah baru.

Setiap kosakata yang dikirim dari halaman publik tidak langsung tampil, melainkan masuk ke antrean `Pending` terlebih dahulu. Pemilik dapat menyetujui (ACC), mengedit, atau menghapus kiriman tersebut melalui halaman manajemen yang dilindungi parameter kunci pada URL.

## Fitur Utama

1. **Halaman Publik (`index.php`)**
   - Menampilkan daftar kosakata berstatus `Approved` dari hasil `INNER JOIN` antara tabel `daftar_vocab` dan `kategori_vocab`.
   - Filter berdasarkan kategori dan pencarian kata kunci menggunakan metode `GET`.
   - Formulir kontribusi pengunjung (wajib mengisi nama pengirim) yang menyimpan data baru sebagai `Pending`.

2. **Halaman Manajemen & Moderasi (`manage.php`)**
   - Diakses menggunakan parameter URL `?key=cukurukuk666`.
   - Formulir tambah kosakata khusus pemilik yang langsung tersimpan dengan status `Approved` atas nama `helloebi`.
   - Tabel antrean `Pending` dengan tombol **ACC** (mengubah status menjadi `Approved`), **Edit**, dan **Tolak / Hapus**.
   - Tabel daftar kosakata `Approved` dengan tombol **Edit** dan **Hapus**.

3. **Halaman Edit Kosakata (`edit.php`)**
   - Menampilkan *pre-filled form* berdasarkan `id_vocab` untuk memperbarui kata, kategori, kelas kata, arti, contoh kalimat, nama pengirim, status moderasi, serta tingkat penguasaan (`New`, `Learning`, `Mastered`).

## Struktur Direktori

```text
.
├── assets/
│   └── style.css          # Stylesheet CSS murni tanpa framework eksternal
├── config/
│   └── database.php       # Koneksi PDO (localhost & hosting) dan ADMIN_SECRET_KEY
├── includes/
│   └── functions.php      # Fungsi sanitasi XSS e(), validasi key, dan query PDO
├── database.sql           # Skema database 24424020 dan data awal
├── index.php              # Halaman kamus publik dan form kontribusi
├── manage.php             # Halaman moderasi dan CRUD admin
└── edit.php               # Halaman edit kosakata
```

## Skema Database (`24424020`)

Database terdiri dari dua tabel yang berelasi *One-to-Many*:

- **`kategori_vocab` (Tabel Induk):** menyimpan `id_kategori` dan `nama_kategori`.
- **`daftar_vocab` (Tabel Anak):** menyimpan `id_vocab`, `id_kategori` (*Foreign Key* `ON DELETE CASCADE`), `english_word`, `part_of_speech`, `indonesian_meaning`, `example_sentence`, `submitted_by`, `approval_status`, `mastery_status`, dan `created_at`.

## Cara Menjalankan di Lokal (XAMPP / Laragon)

1. Pastikan modul **Apache** dan **MySQL** sudah berjalan di XAMPP atau Laragon.
2. Simpan folder proyek ini di dalam direktori `htdocs` (XAMPP) atau `www` (Laragon).
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, lalu impor file `database.sql`. File ini otomatis membuat database `24424020` beserta kedua tabel dan data awalnya.
4. File `config/database.php` sudah dikonfigurasi untuk mendeteksi `localhost` secara otomatis dengan pengaturan:
   - Host: `localhost`
   - Database: `24424020`
   - User: `root`
   - Password: *(kosong)*
5. Buka aplikasi melalui browser:
   - Halaman Publik: `http://localhost/ngodingphpbiardapatlambo/index.php`
   - Halaman Admin: `http://localhost/ngodingphpbiardapatlambo/manage.php?key=cukurukuk666`

## Catatan Keamanan & Teknis

- Seluruh query yang menerima input pengguna atau parameter URL menggunakan **PDO Prepared Statements** (`prepare()` dan `execute()`).
- Seluruh output variabel pada HTML dibungkus fungsi `e()` (`htmlspecialchars` dengan `ENT_QUOTES` dan `UTF-8`).
- Setiap aksi `POST` (Create, Update, ACC, Delete) menerapkan pola **Post/Redirect/Get (PRG)** agar tidak terjadi duplikasi pengiriman data saat halaman dimuat ulang.

