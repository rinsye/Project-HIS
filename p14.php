<?php
/*
=======================================================
  P14 — MINI PROJECT: Modul Farmasi
  Tujuan : Kelola antrian resep, dispensing, dan stok obat
  Hasil  : Apoteker bisa menyelesaikan atau membatalkan resep dan memantau stok
  Buku   : Bab 5 — Modul Farmasi & Manajemen Stok
=======================================================

  KERJAKAN P08 SAMPAI P13 LEBIH DULU.

  P14 DIKERJAKAN DUA BAGIAN, BERURUTAN:
    Bagian 1  farmasi.sql, TODO 1 dan 2. Dijalankan di phpMyAdmin untuk
              membuat tabel obat, resep, dan resep_item, lengkap dengan
              tiga resep contoh. Kerjakan DULU.
    Bagian 2  berkas ini, TODO 3 sampai 6. Dibuka di browser.
  Kalau tabelnya belum ada, halaman ini berhenti dengan petunjuk.

  ALUR KERJA FARMASI:
    1. dokter menulis resep, masuk ke antrian farmasi
    2. apoteker melihat daftar resep yang menunggu
    3. klik Selesaikan: stok obat berkurang, status resep menjadi Selesai
    4. klik Batalkan: status resep menjadi Dibatalkan, stok tidak berubah
    5. stok yang tinggal sedikit (kurang dari 10) diberi peringatan

  KONSEP BARU: TRANSAKSI
  Saat resep diselesaikan ada dua perubahan: stok berkurang, dan status
  resep berubah. Keduanya harus berhasil BERSAMA, atau batal bersama.
  Kalau stok sudah berkurang tetapi status gagal berubah, obat hilang dari
  gudang tanpa tercatat ke pasien manapun. Transaksi mencegah itu:
    beginTransaction()  mulai, perubahan ditahan dulu
    commit()            sahkan semua perubahan sekaligus
    rollBack()          batalkan semua perubahan

  Kerjakan berurutan. Setelah tiap TODO, simpan (Ctrl+S), muat ulang
  halaman (F5), lalu klik Selesaikan pada resep pertama:
    TODO 3  tetap muncul pesan ___ yang belum diganti, kini menyebut TODO 4
    TODO 4  tetap muncul pesan ___ yang belum diganti, kini menyebut TODO 5
    TODO 5  resep berhasil diselesaikan. Stok Paracetamol berkurang 10
    TODO 6  klik Batalkan pada resep lain. Resep hilang dari antrian,
            stok tidak berubah

  Untuk mengulang dari awal, jalankan lagi farmasi.sql di phpMyAdmin.
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
// Mengubah pesan error menjadi petunjuk yang bisa dibaca. $bagian diisi di
// awal tiap langkah supaya pesannya menyebut TODO yang sedang berjalan.
$bagian = '';
function petunjuk_error(string $pesan): string {
    $isian = str_repeat('_', 3);   // tiga garis bawah, tanda isian yang belum diganti
    if (str_contains($pesan, $isian)) {
        return "Masih ada $isian yang belum diganti.";
    } elseif (str_contains($pesan, 'number of bound variables') || str_contains($pesan, "near '?")) {
        return 'Jumlah isi execute([...]) tidak sama dengan jumlah tanda tanya ? di query. Hitung keduanya.';
    } elseif (str_contains($pesan, 'Unknown column')) {
        return 'Ada nama kolom yang salah ketik. Cocokkan dengan tabel obat, resep, dan resep_item di farmasi.sql.';
    } elseif (str_contains($pesan, '1064')) {
        return 'Ada salah ketik di query: periksa ejaan, tanda koma, tanda kutip, dan tanda kurung.';
    } elseif (str_contains($pesan, 'Data truncated') || str_contains($pesan, '1265')) {
        return 'Nilai status tidak cocok dengan pilihan ENUM di tabel resep. Periksa ejaan dan tanda kutipnya.';
    }
    return 'Periksa baris yang terakhir Anda ubah.';
}
set_exception_handler(function (Throwable $e) use (&$bagian) {
    echo '<div style="font-family:sans-serif;max-width:720px;margin:32px auto;padding:20px;'
       . 'border:2px solid #f59e0b;border-radius:12px;background:#fffbeb;color:#78350f">'
       . '<b>Ada kesalahan.</b>'
       . ($bagian !== '' ? '<br>Terjadi saat: ' . htmlspecialchars($bagian) . '.' : '')
       . '<br><br>' . htmlspecialchars(petunjuk_error($e->getMessage()))
       . '<br><br><span style="font-size:13px;color:#92400e">Pesan asli: '
       . htmlspecialchars($e->getMessage()) . '</span>'
       . '<br><br><a href="?" style="color:#1d4ed8">Kembali ke halaman farmasi</a></div>';
});


// ======================================================
// BAGIAN 2: PERIKSA TABEL, BACA SAJA
// ======================================================
// Tabel dibuat di phpMyAdmin lewat farmasi.sql (TODO 1 dan 2). Kalau
// belum ada, halaman berhenti dengan petunjuk.
foreach (['obat', 'resep', 'resep_item'] as $nama_tabel) {
    if ($pdo->query("SHOW TABLES LIKE '$nama_tabel'")->fetchColumn() === false) {
        die('<div style="font-family:sans-serif;max-width:720px;margin:32px auto;padding:20px;'
          . 'border:2px solid #f59e0b;border-radius:12px;background:#fffbeb;color:#78350f">'
          . "<b>Tabel $nama_tabel belum ada.</b><br><br>"
          . 'Kerjakan <b>farmasi.sql</b> lebih dulu (TODO 1 dan 2): tempel isinya di tab SQL '
          . 'phpMyAdmin pada database db_simrs, lalu klik Go. Sesudah itu muat ulang halaman ini.</div>');
    }
}

// BACA SAJA. Daftar dokter dan apoteker. Di aplikasi sungguhan, keduanya
// adalah akun yang sedang login (P11). Di sini dipilih dari daftar, supaya
// latihan ini berdiri sendiri.
$daftar_dokter   = [1 => 'dr. Ahmad Fauzi, Sp.PD', 2 => 'dr. Sinta Dewi, Sp.A'];
$daftar_apoteker = [1 => 'apt. Sari Dewi, S.Farm.', 2 => 'apt. Rudi Hartono, S.Farm.'];


// ======================================================
// BAGIAN 3: SELESAIKAN RESEP (form dikirim dengan POST)
// ======================================================
$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selesaikan_resep'])) {
    $bagian      = 'menyiapkan transaksi';
    $id_resep    = (int) ($_POST['id_resep'] ?? 0);
    $id_apoteker = (int) ($_POST['id_apoteker'] ?? 0);

    try {
        if (!isset($daftar_apoteker[$id_apoteker])) {
            throw new Exception('Pilih apoteker yang bertugas lebih dulu.');
        }

        // BACA SAJA. Transaksi dimulai: perubahan di bawah ditahan dulu
        // sampai commit(), atau dibatalkan semua oleh rollBack().
        $pdo->beginTransaction();

        // BACA SAJA. Kunci baris resep ini dan pastikan masih menunggu.
        // FOR UPDATE membuat apoteker lain yang membuka resep yang sama
        // menunggu sampai transaksi ini selesai, sehingga resep tidak
        // bisa diproses dua kali.
        $cek = $pdo->prepare("SELECT status FROM resep WHERE id = ? FOR UPDATE");
        $cek->execute([$id_resep]);
        $status_resep = $cek->fetchColumn();
        if (!in_array($status_resep, ['Menunggu', 'Disiapkan'], true)) {
            throw new Exception('Resep ini tidak bisa diselesaikan, mungkin sudah selesai atau dibatalkan.');
        }

        // BACA SAJA. Ambil semua item resep, lalu pastikan stok cukup untuk
        // semuanya SEBELUM ada yang dikurangi.
        $items = $pdo->prepare("
            SELECT ri.id_obat, ri.jumlah, o.nama_obat, o.stok
            FROM resep_item ri JOIN obat o ON ri.id_obat = o.id
            WHERE ri.id_resep = ?
        ");
        $items->execute([$id_resep]);
        $daftar = $items->fetchAll();
        if (!$daftar) {
            throw new Exception('Resep ini tidak punya item obat.');
        }
        foreach ($daftar as $item) {
            if ($item['stok'] < $item['jumlah']) {
                throw new Exception("Stok {$item['nama_obat']} tidak mencukupi (stok: {$item['stok']}, diminta: {$item['jumlah']}). Resep tidak diproses, stok tidak berubah.");
            }
        }

        // TODO 3: Kurangi stok setiap obat dalam resep ini.
        //
        // YANG ANDA TULIS : tiga isian
        //   3a  nilai baru stok, menggantikan ___ sesudah SET stok =
        //   3b  syarat sesudah WHERE, menggantikan ___
        //   3c  dua variabel di dalam execute([ ]), berurutan
        //
        // FUNGSINYA:
        //   $daftar berisi item resep. Tiap item punya 'id_obat' dan 'jumlah'.
        //   foreach mengulang query ini untuk tiap item, seperti di P07.
        //
        //     3a  stok yang baru adalah stok yang lama dikurangi jumlah:
        //         tulis nama kolomnya, tanda minus, lalu tanda tanya
        //     3b  hanya obat yang ini: id sama dengan tanda tanya
        //     3c  dua tanda tanya, jadi dua nilai berurutan dari kiri:
        //         $item['jumlah'] lalu $item['id_obat']
        //
        //   Kenapa pengurangan ditulis relatif terhadap stok yang lama, dan
        //   bukan menghitung dulu di PHP lalu menuliskan angkanya? Karena bisa
        //   ada apoteker lain yang menyerahkan obat yang sama pada detik yang
        //   sama. Kalau database sendiri yang mengurangi, ia memakai angka
        //   yang paling baru, sehingga tidak ada pengurangan yang hilang.
        $bagian = 'TODO 3, mengurangi stok';
        foreach ($daftar as $item) {
            $stmt = $pdo->prepare("UPDATE obat SET stok = stok - ? WHERE id = ?");
            $stmt->execute([$item['jumlah'], $item['id_obat']]);
        }

        // TODO 4: Tandai resep ini selesai.
        //
        // YANG ANDA TULIS : tiga isian
        //   4a  status baru, menggantikan ___ sesudah status =
        //   4b  waktu sekarang, menggantikan ___ sesudah waktu_selesai =
        //   4c  dua variabel di dalam execute([ ]), berurutan
        //
        // FUNGSINYA:
        //   Setelah stok berkurang, catat bahwa resep selesai, siapa yang
        //   menyerahkan, dan kapan.
        //     4a  teks Selesai, ditulis dengan tanda kutip tunggal
        //     4b  fungsi MySQL untuk tanggal dan jam saat ini: NOW()
        //         (di P08 Anda memakai CURDATE() untuk tanggal saja)
        //     4c  ada dua tanda tanya: pertama untuk diselesaikan_oleh,
        //         kedua untuk id. Isi: $id_apoteker lalu $id_resep
        //
        //   Selesai dan NOW() ditulis langsung di query, bukan lewat tanda
        //   tanya, karena nilainya sudah pasti dan tidak berasal dari
        //   pengguna.
        $bagian = 'TODO 4, menandai resep selesai';
        $stmt = $pdo->prepare("UPDATE resep SET status = 'Selesai', diselesaikan_oleh = ?, waktu_selesai = NOW() WHERE id = ?");
        $stmt->execute([$id_apoteker, $id_resep]);

        // TODO 5: Sahkan transaksinya.
        //
        // YANG ANDA TULIS : satu isian, menggantikan ___ pada $pdo->___();
        //   5a  nama perintah untuk mengesahkan transaksi
        //
        // FUNGSINYA:
        //   Dua perubahan di atas, stok dan status, masih ditahan. Perintah
        //   ini mengesahkan keduanya sekaligus. Tanpa baris ini, semua
        //   perubahan dibatalkan otomatis saat halaman selesai: itulah
        //   gunanya transaksi, dua-duanya jadi, atau dua-duanya batal.
        //   Namanya sudah disebut di bagian atas berkas ini.
        $bagian = 'TODO 5, mengesahkan transaksi';
        $pdo->commit();

        // BACA SAJA. Pastikan benar-benar sah: transaksi sudah tertutup, dan
        // status resep di database sekarang Selesai. Kalau perintah di TODO 5
        // keliru (misalnya membatalkan, bukan mengesahkan), pesan berhasil
        // tidak boleh tampil.
        $cek = $pdo->prepare("SELECT status FROM resep WHERE id = ?");
        $cek->execute([$id_resep]);
        if ($pdo->inTransaction() || $cek->fetchColumn() !== 'Selesai') {
            throw new Exception('Perubahan belum tersimpan: resep belum berstatus Selesai. Periksa TODO 4 dan TODO 5.');
        }
        $pesan = 'Resep berhasil diproses dan obat telah dikeluarkan.';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();   // batalkan semua perubahan
        // Pesan dari kita sendiri (stok kurang, apoteker belum dipilih) apa adanya.
        // Pesan dari database (PDOException) diberi petunjuk TODO.
        $error = $e instanceof PDOException
            ? 'Gagal' . ($bagian !== '' ? ' saat ' . $bagian : '') . '. ' . petunjuk_error($e->getMessage()) . ' Pesan asli: ' . $e->getMessage()
            : 'Gagal: ' . $e->getMessage();
    }
    header('Location: ?' . ($pesan ? 'sukses=' . urlencode($pesan) : 'error=' . urlencode($error)));
    exit;
}


// ======================================================
// BAGIAN 4: BATALKAN RESEP (form dikirim dengan POST)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batalkan_resep'])) {
    $bagian   = 'TODO 6, membatalkan resep';
    $id_resep = (int) ($_POST['id_resep'] ?? 0);

    // TODO 6: Batalkan resep, tanpa menghapusnya.
    //
    // YANG ANDA TULIS : tiga isian
    //   6a  status baru, menggantikan ___ sesudah status =
    //   6b  syarat sesudah WHERE, menggantikan ___
    //   6c  satu variabel di dalam execute([ ])
    //
    // FUNGSINYA:
    //   Resep yang salah tidak dihapus dengan DELETE. Rekam resep harus
    //   tetap ada untuk jejak audit: siapa yang meresepkan, kapan, dan
    //   kenapa dibatalkan. Karena itu statusnya diubah menjadi Dibatalkan.
    //   Cara ini disebut soft delete: datanya masih ada, hanya ditandai.
    //     6a  teks Dibatalkan, dengan tanda kutip tunggal
    //     6b  dua syarat dengan AND: id sama dengan tanda tanya, DAN status
    //         masih salah satu dari Menunggu atau Disiapkan. Untuk syarat
    //         kedua pakai IN ('Menunggu','Disiapkan')
    //     6c  $id_resep
    //
    //   Syarat status itu penting: resep yang obatnya sudah diserahkan
    //   (Selesai) tidak boleh dibatalkan, karena stoknya sudah berkurang.
    $stmt = $pdo->prepare("UPDATE resep SET status = 'Dibatalkan' WHERE id = ? AND status IN ('Menunggu', 'Disiapkan')");
    $stmt->execute([$id_resep]);

    // BACA SAJA. rowCount() menghitung berapa baris yang berubah. Nol berarti
    // resepnya tidak ditemukan atau sudah tidak bisa dibatalkan.
    if ($stmt->rowCount() === 0) {
        header('Location: ?error=' . urlencode('Resep tidak bisa dibatalkan: tidak ditemukan, sudah selesai, atau TODO 6 belum benar.'));
    } else {
        header('Location: ?sukses=' . urlencode('Resep dibatalkan. Datanya tetap tersimpan, stok tidak berubah.'));
    }
    exit;
}


// ======================================================
// BAGIAN 5: TAMBAH STOK OBAT, BACA SAJA
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_stok'])) {
    $id_obat = (int) ($_POST['id_obat'] ?? 0);
    $tambah  = (int) ($_POST['tambah'] ?? 0);
    if ($id_obat > 0 && $tambah > 0) {
        $pdo->prepare("UPDATE obat SET stok = stok + ? WHERE id = ?")->execute([$tambah, $id_obat]);
        header('Location: ?sukses=Stok+berhasil+ditambahkan.');
    } else {
        header('Location: ?error=' . urlencode('Pilih obat dan isi jumlah lebih dari 0.'));
    }
    exit;
}


// ======================================================
// BAGIAN 6: DATA UNTUK TAMPILAN, BACA SAJA
// ======================================================
$bagian = 'menampilkan data';

// Antrian resep yang menunggu. Beberapa tabel digabung dengan JOIN, dan
// GROUP_CONCAT menyatukan semua obat satu resep menjadi satu teks.
$antrian_resep = $pdo->query("
    SELECT r.id, r.tanggal, r.status, r.catatan_dokter, r.id_dokter,
           p.nama_lengkap AS nama_pasien, p.nomor_rm,
           GROUP_CONCAT(CONCAT(o.nama_obat, ' (', ri.jumlah, ' ', o.satuan, ')') SEPARATOR ', ') AS daftar_obat
    FROM resep r
    JOIN kunjungan k ON r.id_kunjungan = k.id
    JOIN pasien p ON k.id_pasien = p.id
    LEFT JOIN resep_item ri ON ri.id_resep = r.id
    LEFT JOIN obat o ON ri.id_obat = o.id
    WHERE r.status IN ('Menunggu', 'Disiapkan')
    GROUP BY r.id, r.tanggal, r.status, r.catatan_dokter, r.id_dokter, p.nama_lengkap, p.nomor_rm
    ORDER BY r.id
")->fetchAll();

// Resep yang sudah diproses, supaya hasil Selesaikan dan Batalkan terlihat.
$riwayat = $pdo->query("
    SELECT r.id, r.status, r.waktu_selesai, p.nama_lengkap AS nama_pasien
    FROM resep r
    JOIN kunjungan k ON r.id_kunjungan = k.id
    JOIN pasien p ON k.id_pasien = p.id
    WHERE r.status IN ('Selesai', 'Dibatalkan')
    ORDER BY r.id DESC LIMIT 5
")->fetchAll();

$stok_obat    = $pdo->query("SELECT id, nama_obat, stok, satuan, harga_satuan FROM obat ORDER BY nama_obat")->fetchAll();
$stok_menipis = array_filter($stok_obat, fn($o) => $o['stok'] < 10);

$selesai_hari_ini = $pdo->query("
    SELECT COUNT(*) FROM resep WHERE DATE(waktu_selesai) = CURDATE() AND status = 'Selesai'
")->fetchColumn();
$total_resep = (int) $pdo->query("SELECT COUNT(*) FROM resep")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P14 — Modul Farmasi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-green-700 text-white px-6 py-3 flex justify-between items-center shadow sticky top-0 z-10">
    <div class="font-bold flex items-center gap-2">💊 SIMRS — Modul Farmasi (P14)</div>
    <span class="text-sm">Instalasi Farmasi</span>
</header>

<main class="p-6 max-w-7xl mx-auto">

    <?php if (isset($_GET['sukses'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4 text-sm">
        ✅ <?= htmlspecialchars($_GET['sukses']) ?>
    </div>
    <?php elseif (isset($_GET['error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <p class="text-gray-500 text-sm">Antrian Resep Aktif</p>
            <p class="text-3xl font-bold text-green-700 mt-1"><?= count($antrian_resep) ?></p>
        </div>
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <p class="text-gray-500 text-sm">Selesai Hari Ini</p>
            <p class="text-3xl font-bold text-blue-700 mt-1"><?= $selesai_hari_ini ?></p>
        </div>
        <div class="bg-white rounded-xl border p-5 shadow-sm">
            <p class="text-gray-500 text-sm">Obat Hampir Habis</p>
            <p class="text-3xl font-bold text-red-600 mt-1"><?= count($stok_menipis) ?></p>
        </div>
    </div>

    <?php if (!empty($stok_menipis)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
        <p class="font-semibold text-red-700 text-sm mb-2">⚠️ Stok Menipis (kurang dari 10 unit):</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($stok_menipis as $s): ?>
            <span class="bg-red-100 text-red-800 text-xs px-3 py-1 rounded-full font-medium">
                <?= htmlspecialchars($s['nama_obat']) ?>: <?= (int) $s['stok'] ?> <?= htmlspecialchars($s['satuan']) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ANTRIAN RESEP -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b">
                    <h2 class="font-semibold text-gray-700">📋 Antrian Resep Masuk</h2>
                </div>
                <?php if (empty($antrian_resep)): ?>
                <div class="p-10 text-center text-gray-400">
                    <div class="text-4xl mb-2">✅</div>
                    <p>Tidak ada antrian resep saat ini.</p>
                    <?php if ($total_resep === 0): ?>
                    <p class="text-xs mt-1">Belum ada resep sama sekali. Jalankan farmasi.sql untuk membuat resep contoh.</p>
                    <?php else: ?>
                    <p class="text-xs mt-1">Semua resep sudah diproses. Jalankan farmasi.sql lagi untuk mengulang.</p>
                    <?php endif; ?>
                </div>
                <?php else: foreach ($antrian_resep as $r): ?>
                <div class="border-b px-5 py-4 hover:bg-gray-50">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-1">
                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($r['nama_pasien']) ?></span>
                                <span class="font-mono text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded"><?= htmlspecialchars($r['nomor_rm']) ?></span>
                                <span class="text-xs <?= $r['status'] === 'Disiapkan' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?> px-2 py-0.5 rounded-full font-medium">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">Dokter: <?= htmlspecialchars($daftar_dokter[$r['id_dokter']] ?? 'Dokter') ?> · <?= htmlspecialchars($r['tanggal']) ?></p>
                            <p class="text-sm text-gray-600 mt-1">💊 <?= htmlspecialchars($r['daftar_obat'] ?? 'Tidak ada item obat') ?></p>
                            <?php if ($r['catatan_dokter']): ?>
                            <p class="text-xs text-orange-600 mt-1">📝 <?= htmlspecialchars($r['catatan_dokter']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col gap-2 items-stretch">
                            <!-- BACA SAJA. Dua tombol, dua form POST, karena keduanya mengubah data. -->
                            <form method="POST" action="" class="flex gap-2 items-center"
                                  onsubmit="return confirm('Proses resep dan kurangi stok obat?')">
                                <input type="hidden" name="id_resep" value="<?= (int) $r['id'] ?>">
                                <select name="id_apoteker" class="px-2 py-2 border border-gray-300 rounded-lg text-xs">
                                    <option value="">Apoteker...</option>
                                    <?php foreach ($daftar_apoteker as $id_a => $nama_a): ?>
                                    <option value="<?= $id_a ?>"><?= htmlspecialchars($nama_a) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="selesaikan_resep"
                                        class="px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-xs font-semibold rounded-lg whitespace-nowrap">
                                    ✓ Selesaikan
                                </button>
                            </form>
                            <form method="POST" action="" onsubmit="return confirm('Batalkan resep ini?')">
                                <input type="hidden" name="id_resep" value="<?= (int) $r['id'] ?>">
                                <button type="submit" name="batalkan_resep"
                                        class="w-full px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 text-xs font-semibold rounded-lg">
                                    ✕ Batalkan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- RIWAYAT -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b">
                    <h2 class="font-semibold text-gray-700 text-sm">🕐 Resep yang Sudah Diproses</h2>
                </div>
                <?php if (empty($riwayat)): ?>
                <p class="p-5 text-xs text-gray-400">Belum ada. Resep yang diselesaikan atau dibatalkan muncul di sini.</p>
                <?php else: ?>
                <table class="w-full text-sm">
                    <tbody>
                    <?php foreach ($riwayat as $h): ?>
                    <tr class="border-t border-gray-100">
                        <td class="px-5 py-2 text-gray-800"><?= htmlspecialchars($h['nama_pasien']) ?></td>
                        <td class="px-3 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $h['status'] === 'Selesai' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                <?= htmlspecialchars($h['status']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($h['waktu_selesai'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- STOK OBAT -->
        <div>
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b">
                    <h2 class="font-semibold text-gray-700 text-sm">📦 Manajemen Stok Obat</h2>
                </div>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <?php foreach ($stok_obat as $o): ?>
                    <div class="px-4 py-3 flex justify-between items-center hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($o['nama_obat']) ?></p>
                            <p class="text-xs text-gray-500">Rp <?= number_format($o['harga_satuan'], 0, ',', '.') ?> / <?= htmlspecialchars($o['satuan']) ?></p>
                        </div>
                        <div class="ml-2 text-right">
                            <span class="text-sm font-bold <?= $o['stok'] < 10 ? 'text-red-600' : 'text-gray-700' ?>"><?= (int) $o['stok'] ?></span>
                            <span class="text-xs text-gray-400"> <?= htmlspecialchars($o['satuan']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <form method="POST" action="" class="p-4 border-t bg-gray-50">
                    <p class="text-xs font-semibold text-gray-600 mb-3">+ Tambah Stok</p>
                    <select name="id_obat" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs mb-2">
                        <?php foreach ($stok_obat as $o): ?>
                        <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars($o['nama_obat']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="flex gap-2">
                        <input type="number" name="tambah" min="1" placeholder="Jumlah"
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-xs">
                        <button type="submit" name="tambah_stok"
                                class="px-3 py-2 bg-green-700 hover:bg-green-800 text-white text-xs rounded-lg font-semibold whitespace-nowrap">
                            Tambah
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>
