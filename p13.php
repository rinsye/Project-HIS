<?php
/*
=======================================================
  P13 — MINI PROJECT: Modul EMR (Form SOAP)
  Tujuan : Buat form rekam medis elektronik SOAP
  Hasil  : Dokter bisa mengisi dan menyimpan catatan SOAP ke database
  Buku   : Bab 5 — Modul Dokter & Rekam Medis Elektronik
=======================================================

  KERJAKAN P08 SAMPAI P12 LEBIH DULU.
  Halaman ini membaca antrian hari ini, yang dibuat di P12 (tabel
  kunjungan dari P08). Kalau antrian kosong, buka P12 dan daftarkan
  satu atau dua pasien dulu.

  P13 DIKERJAKAN DUA BAGIAN, BERURUTAN:
    Bagian 1  rekam_medis.sql, TODO 1 dan 2. Dijalankan di phpMyAdmin
              untuk membuat tabel tempat catatan SOAP. Kerjakan DULU.
    Bagian 2  berkas ini, TODO 3 sampai 6. Dibuka di browser.
  Kalau tabelnya belum ada, halaman ini berhenti dengan petunjuk.

  FITUR YANG DIBANGUN:
    1. antrian pasien menunggu, di kolom kiri
    2. klik pasien, lalu form SOAP terbuka di kanan
    3. isi tanda vital dan S, O, A, P
    4. simpan, lalu status kunjungan berubah menjadi Selesai
    5. riwayat kunjungan sebelumnya tampil di bawah form

  SOAP adalah cara dokter mencatat satu pemeriksaan:
    S  Subjektif   keluhan menurut pasien
    O  Objektif    hasil pemeriksaan dokter
    A  Assessment  diagnosis, ditulis dengan kode ICD-10
    P  Plan        terapi, pemeriksaan lanjutan, edukasi

  Kerjakan berurutan. Setelah tiap TODO, simpan (Ctrl+S), muat ulang
  halaman (F5), pilih pasien, isi form, lalu klik Simpan:
    TODO 3  muncul pesan bahwa ada ___ yang belum diganti, itu wajar
    TODO 4  catatan tersimpan, tetapi pasien masih ada di antrian
    TODO 5  pasien hilang dari antrian sesudah disimpan
    TODO 6  buka pasien yang pernah diperiksa: riwayatnya tampil di bawah form
*/


// ======================================================
// BAGIAN 1: SAMBUNGAN KE DATABASE, BACA SAJA
// ======================================================
$db_host  = 'localhost';
$db_nama  = 'db_simrs';
$db_user  = 'root';
$db_sandi = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_nama;charset=utf8mb4", $db_user, $db_sandi);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;padding:24px">'
      . '<b>Gagal menyambung ke database.</b><br>' . htmlspecialchars($e->getMessage())
      . '<br><br>Periksa dua hal: MySQL sudah menyala di XAMPP, dan P08 sudah dijalankan '
      . 'di phpMyAdmin sehingga database db_simrs ada.</p>');
}


// ======================================================
// PENANGKAP KESALAHAN, BACA SAJA
// ======================================================
// Bila ada yang keliru, yang tampil pesan yang bisa dibaca. $bagian diisi
// di awal tiap langkah supaya pesannya menyebut TODO yang sedang berjalan.
$bagian = '';
set_exception_handler(function (Throwable $e) use (&$bagian) {
    $pesan = $e->getMessage();
    $isian = str_repeat('_', 3);   // tiga garis bawah, tanda isian yang belum diganti
    if (str_contains($pesan, $isian)) {
        $saran = "Masih ada $isian yang belum diganti.";
    } elseif (str_contains($pesan, 'number of bound variables') || str_contains($pesan, "near '?")) {
        $saran = 'Jumlah isi execute([...]) tidak sama dengan jumlah tanda tanya ? di query. Hitung keduanya.';
    } elseif (str_contains($pesan, 'Unknown column')) {
        $saran = 'Ada nama kolom yang salah ketik. Kolom rekam_medis: id_kunjungan, id_dokter, subjektif, '
               . 'objektif, assessment, plan, td_sistolik, td_diastolik, nadi, suhu, spo2, berat_badan.';
    } elseif (str_contains($pesan, '1064')) {
        $saran = 'Ada salah ketik di query: periksa ejaan, tanda koma, dan tanda kurung.';
    } elseif (str_contains($pesan, 'Incorrect') || str_contains($pesan, '1366') || str_contains($pesan, '1265')) {
        $saran = 'Ada nilai yang tidak cocok dengan tipe kolomnya. Biasanya urutan variabel di execute tertukar.';
    } else {
        $saran = 'Periksa baris yang terakhir Anda ubah.';
    }
    echo '<div style="font-family:sans-serif;max-width:720px;margin:32px auto;padding:20px;'
       . 'border:2px solid #f59e0b;border-radius:12px;background:#fffbeb;color:#78350f">'
       . '<b>Ada kesalahan.</b>'
       . ($bagian !== '' ? '<br>Terjadi saat: ' . htmlspecialchars($bagian) . '.' : '')
       . '<br><br>' . htmlspecialchars($saran)
       . '<br><br><span style="font-size:13px;color:#92400e">Pesan asli: '
       . htmlspecialchars($pesan) . '</span>'
       . '<br><br><a href="?" style="color:#1d4ed8">Kembali ke halaman EMR</a></div>';
});


// ======================================================
// BAGIAN 2: PERIKSA TABEL REKAM MEDIS, BACA SAJA
// ======================================================
// Tabel rekam_medis dibuat di phpMyAdmin lewat rekam_medis.sql
// (TODO 1 dan 2). Kalau belum ada, halaman berhenti dengan petunjuk.
if ($pdo->query("SHOW TABLES LIKE 'rekam_medis'")->fetchColumn() === false) {
    die('<div style="font-family:sans-serif;max-width:720px;margin:32px auto;padding:20px;'
      . 'border:2px solid #f59e0b;border-radius:12px;background:#fffbeb;color:#78350f">'
      . '<b>Tabel rekam_medis belum ada.</b><br><br>'
      . 'Kerjakan <b>rekam_medis.sql</b> lebih dulu (TODO 1 dan 2): tempel isinya di tab SQL '
      . 'phpMyAdmin pada database db_simrs, lalu klik Go. Sesudah itu muat ulang halaman ini.</div>');
}

// Kolom id_kunjungan harus UNIQUE (TODO 1f di rekam_medis.sql). Kalau belum,
// satu kunjungan bisa punya banyak rekam medis, dan menyimpan dua kali
// menambah baris baru.
$id_kunjungan_unik = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rekam_medis'
      AND COLUMN_NAME = 'id_kunjungan' AND NON_UNIQUE = 0")->fetchColumn();
// BACA SAJA. Dokter yang bertugas. Di aplikasi sungguhan, dokter adalah
// akun yang sedang login (P11). Di sini dipilih dari daftar, supaya
// latihan ini berdiri sendiri.
$daftar_dokter = [
    1 => 'dr. Ahmad Fauzi, Sp.PD',
    2 => 'dr. Sinta Dewi, Sp.A',
];


// ======================================================
// BAGIAN 3: SIMPAN SOAP (form dikirim dengan POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_soap'])) {
    $bagian = 'memeriksa isian';

    // BACA SAJA. Mengambil isian form.
    $id_kunjungan = (int) ($_POST['id_kunjungan'] ?? 0);
    $id_dokter    = (int) ($_POST['id_dokter'] ?? 0);
    $subjektif    = trim($_POST['subjektif'] ?? '');
    $objektif     = trim($_POST['objektif'] ?? '');
    $assessment   = trim($_POST['assessment'] ?? '');
    $plan         = trim($_POST['plan'] ?? '');

    // BACA SAJA. Kunjungannya harus ada, dokternya harus dari daftar, dan
    // diagnosis (Assessment) tidak boleh kosong.
    $cek = $pdo->prepare("SELECT COUNT(*) FROM kunjungan WHERE id = ?");
    $cek->execute([$id_kunjungan]);
    if ($cek->fetchColumn() == 0 || !isset($daftar_dokter[$id_dokter]) || $assessment === '') {
        header('Location: ?kunjungan=' . $id_kunjungan . '&gagal=' . urlencode(
            'Pilih dokter dan isi Assessment (diagnosis) sebelum menyimpan.'));
        exit;
    }

    // TODO 3: Ubah tanda vital yang kosong menjadi NULL, bukan 0.
    //
    // YANG ANDA TULIS : tiga baris, menggantikan ___
    //   3a  nadi    3b  suhu    3c  berat_badan
    //
    // FUNGSINYA:
    //   Kotak yang tidak diisi dikirim sebagai teks kosong, dan kalau
    //   langsung disimpan menjadi angka 0. Suhu 0 derajat artinya pasien
    //   membeku, sedangkan NULL artinya "belum diukur". Dua arti yang
    //   sangat berbeda di rekam medis.
    //
    //   Tiga baris di bawah sudah jadi: td_sistolik, td_diastolik, dan
    //   spo2. Tiru bentuknya, ganti nama kolom di dalam $_POST[' '] dan
    //   tipenya:
    //     (int) ($_POST['nama'] ?? 0) ?: null      untuk angka bulat
    //     (float) ($_POST['nama'] ?? 0) ?: null    untuk angka berkoma
    //
    //   Cara membacanya: ambil isian, ubah menjadi angka, lalu tanda ?:
    //   berarti "kalau hasilnya 0 atau kosong, pakai null". Tanda ?? 0
    //   mengisi 0 bila kotaknya tidak terkirim sama sekali.
    //
    //   Pakai (int) untuk nadi, karena bilangan bulat. Pakai (float) untuk
    //   suhu dan berat_badan, karena bisa berkoma (misalnya 36.8). Hapus
    //   tanda ___ dan tulis ekspresinya, tanpa tanda kutip di sekelilingnya.
    $bagian = 'TODO 3, mengubah tanda vital kosong menjadi NULL';
    $td_sistolik  = (int) ($_POST['td_sistolik'] ?? 0) ?: null;    // contoh, sudah jadi
    $td_diastolik = (int) ($_POST['td_diastolik'] ?? 0) ?: null;   // contoh, sudah jadi
    $nadi         = (int) ($_POST['nadi'] ?? 0) ?: null;   // <- 3a
    $suhu         = (float) ($_POST['suhu'] ?? 0) ?: null;   // <- 3b
    $spo2         = (int) ($_POST['spo2'] ?? 0) ?: null;           // contoh, sudah jadi
    $berat_badan  = (float) ($_POST['berat_badan'] ?? 0) ?: null;   // <- 3c
    // TODO 4: Simpan catatan SOAP ke tabel rekam_medis.
    //
    // YANG ANDA TULIS : dua isian
    //   4a  dua belas tanda tanya di dalam VALUES ( ), menggantikan ___
    //   4b  dua belas variabel di dalam execute([ ]), berurutan
    //
    // FUNGSINYA:
    //   Dua belas kolom di baris INSERT INTO, jadi dua belas tanda tanya,
    //   dipisah koma. Variabel di execute harus sama urutannya dengan
    //   kolom:
    //     id_kunjungan  id_dokter   subjektif   objektif    assessment  plan
    //     td_sistolik   td_diastolik   nadi     suhu        spo2        berat_badan
    //   Setiap variabel bernama sama dengan kolomnya, diawali tanda $,
    //   misalnya kolom nadi diisi oleh $nadi. Semuanya sudah dibuat di
    //   atas, tinggal disusun sesuai urutan kolom.
    //
    //   Bagian ON DUPLICATE KEY UPDATE sudah ditulis, dan itu penting:
    //   satu kunjungan hanya boleh punya SATU rekam medis (id_kunjungan
    //   UNIQUE). Kalau dokter menyimpan kedua kali, baris lama diperbarui,
    //   bukan bertambah satu baris lagi dan bukan error. VALUES(kolom)
    //   artinya nilai yang baru saja hendak dimasukkan.
    $bagian = 'TODO 4, menyimpan SOAP';
    $stmt = $pdo->prepare("INSERT INTO rekam_medis
            (id_kunjungan, id_dokter, subjektif, objektif, assessment, plan,
             td_sistolik, td_diastolik, nadi, suhu, spo2, berat_badan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)   -- <- 4a
        ON DUPLICATE KEY UPDATE
            id_dokter = VALUES(id_dokter), subjektif = VALUES(subjektif),
            objektif = VALUES(objektif), assessment = VALUES(assessment),
            plan = VALUES(plan), td_sistolik = VALUES(td_sistolik),
            td_diastolik = VALUES(td_diastolik), nadi = VALUES(nadi),
            suhu = VALUES(suhu), spo2 = VALUES(spo2), berat_badan = VALUES(berat_badan)");
    $stmt->execute([$id_kunjungan, $id_dokter, $subjektif, $objektif, $assessment, $plan, $td_sistolik, $td_diastolik, $nadi, $suhu, $spo2, $berat_badan]);   // <- 4b: isi di dalam [ ]

    // TODO 5: Setelah SOAP tersimpan, tutup kunjungannya.
    //
    // YANG ANDA TULIS : tiga isian
    //   5a  status baru, menggantikan ___ sesudah SET status =
    //   5b  syarat sesudah WHERE, menggantikan ___
    //   5c  satu variabel di dalam execute([ ])
    //
    // FUNGSINYA:
    //   Pemeriksaan selesai, jadi status kunjungan berubah menjadi Selesai
    //   dan pasien hilang dari antrian dokter. Itu UPDATE yang sama
    //   seperti di P10, tetapi yang diubah kolom status.
    //     5a  teks Selesai, ditulis dengan tanda kutip tunggal
    //     5b  hanya kunjungan ini: id sama dengan tanda tanya
    //     5c  $id_kunjungan
    //   Tanpa WHERE, UPDATE mengubah SEMUA kunjungan menjadi Selesai.
    $bagian = 'TODO 5, menutup kunjungan';
    $stmt = $pdo->prepare("UPDATE kunjungan SET status = 'Selesai' WHERE id = ?");   // <- 5a dan 5b
    $stmt->execute([$id_kunjungan]);                                                      // <- 5c: isi di dalam [ ]

    // BACA SAJA. Dialihkan setelah menyimpan, supaya F5 tidak mengirim ulang form.
    header('Location: ?sukses=' . urlencode('Rekam medis tersimpan. Kunjungan ditutup.'));
    exit;
}


// ======================================================
// BAGIAN 4: DATA UNTUK TAMPILAN, BACA SAJA
// ======================================================
$bagian = 'menampilkan antrian';
$id_kunjungan_aktif = (int) ($_GET['kunjungan'] ?? 0);

// Antrian dokter: kunjungan hari ini yang belum selesai.
$antrian = $pdo->query("
    SELECT k.id, k.no_antrian, p.nama_lengkap, p.nomor_rm, k.poliklinik, k.status
    FROM kunjungan k JOIN pasien p ON k.id_pasien = p.id
    WHERE k.tanggal = CURDATE() AND k.status IN ('Menunggu','Dipanggil')
    ORDER BY k.id
")->fetchAll();

// Kunjungan yang sedang diperiksa, lengkap dengan isi rekam medisnya
// bila sudah pernah disimpan (LEFT JOIN: tetap tampil walau belum ada).
$kunjungan_aktif = null;
$rm_sebelumnya   = [];
if ($id_kunjungan_aktif > 0) {
    $s = $pdo->prepare("
        SELECT k.id, k.id_pasien, k.no_antrian, k.poliklinik, k.status,
               p.nama_lengkap, p.nomor_rm, p.tanggal_lahir, p.jenis_kelamin, p.alergi,
               rm.id_dokter, rm.subjektif, rm.objektif, rm.assessment, rm.plan,
               rm.td_sistolik, rm.td_diastolik, rm.nadi, rm.suhu, rm.spo2, rm.berat_badan
        FROM kunjungan k JOIN pasien p ON k.id_pasien = p.id
        LEFT JOIN rekam_medis rm ON rm.id_kunjungan = k.id
        WHERE k.id = ?
    ");
    $s->execute([$id_kunjungan_aktif]);
    $kunjungan_aktif = $s->fetch();

    if ($kunjungan_aktif) {

        // TODO 6: Tampilkan riwayat rekam medis pasien ini dari kunjungan sebelumnya.
        //
        // YANG ANDA TULIS : dua isian
        //   6a  syarat sesudah WHERE, menggantikan 1 = 0
        //   6b  isi execute([ ]), dua nilai berurutan
        //
        // FUNGSINYA:
        //   JOIN sudah ditulis: rekam_medis digabung dengan kunjungan lewat
        //   rm.id_kunjungan = k.id, sehingga setiap catatan SOAP punya
        //   tanggal dan poliklinik dari kunjungannya.
        //
        //   Syaratnya ada dua, dihubungkan dengan AND (artinya DAN):
        //     - kunjungan milik pasien yang sama  : k.id_pasien = ?
        //     - bukan kunjungan yang sedang dibuka: k.id != ?
        //   Tanda != artinya TIDAK SAMA DENGAN. Dua tanda tanya, jadi
        //   execute diisi dua nilai, berurutan dari kiri:
        //     $kunjungan_aktif['id_pasien']  lalu  $id_kunjungan_aktif
        //
        //   Riwayat penting: dokter perlu tahu diagnosis dan terapi
        //   sebelumnya sebelum memutuskan terapi hari ini.
        $bagian = 'TODO 6, mengambil riwayat';
        $s2 = $pdo->prepare("
            SELECT rm.subjektif, rm.assessment, rm.plan, k.tanggal, k.poliklinik
            FROM rekam_medis rm JOIN kunjungan k ON rm.id_kunjungan = k.id
            WHERE k.id_pasien = ? AND k.id != ?   -- <- 6a
            ORDER BY k.tanggal DESC LIMIT 5
        ");
        $s2->execute([$kunjungan_aktif['id_pasien'], $id_kunjungan_aktif]);   // <- 6b: isi di dalam [ ]
        $rm_sebelumnya = $s2->fetchAll();
    }
}

// BACA SAJA. Daftar kode ICD-10 untuk tombol rujukan cepat.
$icd10_list = [
    ['A09','Diare dan gastroenteritis'],['B34.9','Infeksi virus, tidak spesifik'],
    ['E11','Diabetes mellitus tipe 2'],['I10','Hipertensi esensial (primer)'],
    ['I21','Infark miokard akut'],['J06','Infeksi saluran napas atas akut'],
    ['J18','Pneumonia, tidak spesifik'],['K29','Gastritis dan duodenitis'],
    ['M54.5','Nyeri punggung bawah (LBP)'],['N39','Infeksi saluran kemih'],
    ['R50','Demam tanpa penyebab jelas'],['R51','Nyeri kepala'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P13 — Modul EMR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-blue-700 text-white px-6 py-3 flex justify-between items-center shadow sticky top-0 z-10">
    <div class="font-bold flex items-center gap-2">📝 SIMRS — Modul EMR (P13)</div>
    <span class="text-sm">Ruang Periksa Dokter</span>
</header>

<div class="flex max-w-7xl mx-auto p-4 gap-4">

    <!-- SIDEBAR: antrian -->
    <div class="w-64 flex-shrink-0">
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden sticky top-16">
            <div class="px-4 py-3 bg-blue-50 border-b">
                <h2 class="font-semibold text-blue-800 text-sm">📋 Antrian Hari Ini</h2>
                <p class="text-xs text-blue-600"><?= count($antrian) ?> pasien menunggu</p>
            </div>
            <?php if (empty($antrian)): ?>
            <p class="p-4 text-xs text-gray-400 text-center">Tidak ada antrian.<br>Daftarkan pasien di P12 dulu.</p>
            <?php else: foreach ($antrian as $a): ?>
            <a href="?kunjungan=<?= (int) $a['id'] ?>"
               class="flex items-center gap-3 px-4 py-3 border-b hover:bg-blue-50 transition
                      <?= ($id_kunjungan_aktif === (int) $a['id']) ? 'bg-blue-100 border-l-4 border-l-blue-600' : '' ?>">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($a['nama_lengkap']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($a['no_antrian']) ?> · <?= htmlspecialchars($a['poliklinik']) ?></p>
                </div>
                <span class="text-xs <?= $a['status'] === 'Dipanggil' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?> px-2 py-0.5 rounded-full whitespace-nowrap">
                    <?= htmlspecialchars($a['status']) ?>
                </span>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- AREA UTAMA: form SOAP -->
    <div class="flex-1">

        <?php if (isset($_GET['sukses'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4 text-sm">
            ✅ <?= htmlspecialchars($_GET['sukses']) ?>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['gagal'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= htmlspecialchars($_GET['gagal']) ?>
        </div>
        <?php endif; ?>
        <?php if (!$id_kunjungan_unik): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4 text-sm">
            Kolom <b>id_kunjungan</b> di tabel rekam_medis belum UNIQUE (TODO 1f di rekam_medis.sql).
            Selama itu belum benar, menyimpan dua kali menambah baris baru, bukan memperbarui.
        </div>
        <?php endif; ?>

        <?php if (!$kunjungan_aktif): ?>
        <div class="bg-white rounded-xl border shadow-sm p-12 text-center">
            <div class="text-5xl mb-4">👈</div>
            <h2 class="text-xl font-bold text-gray-700 mb-2">Pilih Pasien dari Antrian</h2>
            <p class="text-gray-400">Klik nama pasien di sebelah kiri untuk memulai pemeriksaan dan mengisi rekam medis.</p>
        </div>

        <?php else: ?>

        <!-- Banner alergi -->
        <?php if (!empty($kunjungan_aktif['alergi'])): ?>
        <div class="bg-red-600 text-white rounded-xl px-5 py-3 mb-4 flex items-center gap-3 font-semibold">
            <span class="text-2xl">⚠️</span>
            <span>ALERGI: <?= htmlspecialchars($kunjungan_aktif['alergi']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Info pasien -->
        <div class="bg-white rounded-xl border shadow-sm p-4 mb-4 flex flex-wrap gap-6 text-sm">
            <div><span class="text-gray-400">Nama</span><br><strong><?= htmlspecialchars($kunjungan_aktif['nama_lengkap']) ?></strong></div>
            <div><span class="text-gray-400">No. RM</span><br><strong class="font-mono text-blue-700"><?= htmlspecialchars($kunjungan_aktif['nomor_rm']) ?></strong></div>
            <div><span class="text-gray-400">No. Antrian</span><br><strong class="text-blue-700"><?= htmlspecialchars($kunjungan_aktif['no_antrian']) ?></strong></div>
            <div><span class="text-gray-400">Poliklinik</span><br><strong><?= htmlspecialchars($kunjungan_aktif['poliklinik']) ?></strong></div>
            <?php
            $lahir = new DateTime($kunjungan_aktif['tanggal_lahir']);
            $umur  = (int) $lahir->diff(new DateTime())->y;
            ?>
            <div><span class="text-gray-400">Usia</span><br><strong><?= $umur ?> tahun</strong></div>
            <div><span class="text-gray-400">Jenis Kelamin</span><br><strong><?= $kunjungan_aktif['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></strong></div>
        </div>

        <!-- Form SOAP -->
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="id_kunjungan" value="<?= $id_kunjungan_aktif ?>">

            <div class="bg-white rounded-xl border shadow-sm p-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Dokter Jaga *</label>
                <select name="id_dokter" class="w-full md:w-80 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">-- Pilih dokter --</option>
                    <?php foreach ($daftar_dokter as $id_d => $nama_d): ?>
                    <option value="<?= $id_d ?>" <?= (int) ($kunjungan_aktif['id_dokter'] ?? 0) === $id_d ? 'selected' : '' ?>><?= htmlspecialchars($nama_d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tanda vital -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="font-semibold text-gray-700 mb-4">🩺 Tanda-Tanda Vital</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php
                    $ttv = [
                        ['td_sistolik',  'TD Sistolik',  'mmHg'],
                        ['td_diastolik', 'TD Diastolik', 'mmHg'],
                        ['nadi',         'Nadi',         'x/mnt'],
                        ['suhu',         'Suhu',         '°C'],
                        ['spo2',         'SpO2',         '%'],
                        ['berat_badan',  'Berat Badan',  'kg'],
                    ];
                    foreach ($ttv as [$name, $label, $unit]):
                    ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1"><?= $label ?> <span class="font-normal text-gray-400">(<?= $unit ?>)</span></label>
                        <input type="number" name="<?= $name ?>" step="0.1"
                               value="<?= htmlspecialchars($kunjungan_aktif[$name] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SOAP -->
            <?php
            $soap = [
                ['subjektif',  'S — Subjektif', 'Keluhan utama, onset, riwayat penyakit, alergi...', 3],
                ['objektif',   'O — Objektif',  'Pemeriksaan fisik: kesadaran, status generalis, lokalis...', 3],
                ['assessment', 'A — Assessment (Diagnosis) *', 'Diagnosis kerja + kode ICD-10. Contoh: I10 - Hipertensi esensial', 2],
                ['plan',       'P — Plan (Rencana)', 'Resep obat, order lab/radiologi, edukasi pasien, kontrol ulang...', 3],
            ];
            ?>
            <div class="bg-white rounded-xl border shadow-sm p-5 space-y-4">
                <h3 class="font-semibold text-gray-700">📋 Rekam Medis SOAP</h3>
                <?php foreach ($soap as [$name, $label, $placeholder, $rows]): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5"><?= $label ?></label>
                    <textarea name="<?= $name ?>" rows="<?= $rows ?>" placeholder="<?= htmlspecialchars($placeholder) ?>"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"><?= htmlspecialchars($kunjungan_aktif[$name] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>

                <!-- BACA SAJA. Tombol rujukan cepat ICD-10: klik untuk menambahkan
                     kodenya ke kotak Assessment. -->
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-2">💡 Rujukan Cepat ICD-10:</p>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($icd10_list as [$kode, $nama_icd]): ?>
                        <button type="button"
                                onclick="document.querySelector('[name=assessment]').value += '<?= $kode ?> - <?= $nama_icd ?>\n'"
                                class="text-xs bg-gray-100 hover:bg-blue-100 hover:text-blue-700 px-2 py-1 rounded border border-gray-200 transition">
                            <?= $kode ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <button type="submit" name="simpan_soap"
                    class="w-full py-3 bg-blue-700 hover:bg-blue-800 text-white font-bold rounded-xl transition text-sm">
                💾 Simpan Rekam Medis &amp; Tutup Kunjungan
            </button>
        </form>

        <!-- Riwayat -->
        <?php if (!empty($rm_sebelumnya)): ?>
        <div class="mt-4 bg-white rounded-xl border shadow-sm p-5">
            <h3 class="font-semibold text-gray-700 mb-3">🕐 Riwayat Kunjungan Sebelumnya</h3>
            <?php foreach ($rm_sebelumnya as $r): ?>
            <details class="border border-gray-200 rounded-lg mb-2">
                <summary class="px-4 py-2 text-sm cursor-pointer hover:bg-gray-50 font-medium">
                    <?= htmlspecialchars($r['tanggal']) ?> — <?= htmlspecialchars($r['poliklinik']) ?>
                </summary>
                <div class="px-4 py-3 text-sm space-y-1 bg-gray-50">
                    <p><strong>S:</strong> <?= htmlspecialchars($r['subjektif'] ?? '-') ?></p>
                    <p><strong>A:</strong> <?= htmlspecialchars($r['assessment'] ?? '-') ?></p>
                    <p><strong>P:</strong> <?= htmlspecialchars($r['plan'] ?? '-') ?></p>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
