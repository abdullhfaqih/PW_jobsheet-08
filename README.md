# Jobsheet 8: Koneksi PostgreSQL - SIMPUS-Mini

## Ringkasan Jobsheet

Jobsheet 8 berfokus pada penyelesaian masalah persistensi data pada aplikasi SIMPUS-Mini. Sebelumnya data disimpan pada `$_SESSION` yang akan hilang ketika browser ditutup. Pada jobsheet ini, aplikasi sepenuhnya dihubungkan ke sistem _Database Management System_ (DBMS) relasional menggunakan PostgreSQL.

### Poin Utama yang Dipelajari:

1. **Skema Database & SQL:** Membangun struktur tabel `buku` dan `anggota` menggunakan perintah `CREATE TABLE`, mengatur tipe data (`VARCHAR`, `INTEGER`, `SERIAL`), dan menambahkan _constraint_ (`PRIMARY KEY`, `NOT NULL`, `UNIQUE`, `DEFAULT`).
2. **Koneksi PDO (PHP Data Objects):** Membuat jembatan koneksi antara PHP dan PostgreSQL (`koneksi.php`) menggunakan _Data Source Name_ (DSN) dan blok `try/catch` untuk penanganan error jika server database gagal dihubungi.
3. **Prepared Statements (INSERT):** Mengganti pengisian `$_SESSION` dengan query `INSERT` ke database. Proses ini menggunakan `$pdo->prepare()` dan pengikatan parameter (placeholder) untuk mencegah celah keamanan _SQL Injection_.
4. **Membaca Data (SELECT):** Mengambil data dari database menggunakan `$pdo->query()` dan `fetchAll(PDO::FETCH_ASSOC)` agar struktur datanya identik dengan array session lama, sehingga kode HTML tidak perlu banyak diubah. Query `SELECT COUNT(*)` juga digunakan untuk menghitung total baris pada _dashboard_.

---

## Jawaban Latihan (7.4)

### 1. Menangani Error UNIQUE pada Tabel Anggota

Karena kolom `no_anggota` diset `UNIQUE`, menginput nomor yang sama akan memicu fatal error (PDOException). Solusinya adalah membungkus eksekusi _prepared statement_ dengan `try/catch` di file `anggota/proses_tambah.php`.

**Kode pada `anggota/proses_tambah.php`:**

```php
try {
    $stmt->execute([
        'nama' => $nama,
        'no_anggota' => $no_anggota,
        'alamat' => $alamat,
        'no_hp' => $no_hp
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'pesan' => 'Anggota berhasil ditambahkan.'];
    header('Location: list.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'pesan' => 'No. Anggota sudah dipakai, gunakan nomor lain.'];
    header('Location: tambah.php');
    exit;
}
```

### 2. Menambah Kolom `tanggal_ditambahkan`

Menambahkan kolom otomatis pencatat waktu pada tabel buku dan menampilkannya di halaman daftar buku.

Tahap A: Modifikasi Tabel di Database (Terminal/psql)

```sql
ALTER TABLE buku ADD COLUMN tanggal_ditambahkan TIMESTAMP DEFAULT NOW();
```

Tahap B: Menampilkan di `buku/list.php`

```html
<!-- Pada bagian <thead> -->
<th>Tanggal Ditambahkan</th>

<!-- Pada bagian <tbody> di dalam foreach -->
<td><?php echo $buku['tanggal_ditambahkan']; ?></td>
```

### 3. Membuat Query Pencarian Sisi Server (Server-Side Search)
Memindahkan fungsi pencarian dari sekadar filter DOM JavaScript menjadi pencarian langsung ke database (lebih ringan untuk data besar). Menggunakan klausa `ILIKE` agar pencarian tidak membedakan huruf besar/kecil (case-insensitive).

Kode pada `buku/list.php`:
```php
require __DIR__ . '/../includes/koneksi.php';

$keyword = $_GET['keyword'] ?? '';

if ($keyword !== '') {
    $stmt = $pdo->prepare("SELECT * FROM buku WHERE judul ILIKE :keyword ORDER BY id DESC");
    $stmt->execute(['keyword' => "%$keyword%"]);
    $daftarBuku = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $daftarBuku = $pdo->query("SELECT * FROM buku ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
```

### 4. Skrip Migrasi Data JSON ke PostgreSQL
Memindahkan data buku dari file `buku.json` lama (dari Jobsheet 6) ke dalam tabel `buku` secara otomatis.

Buat file `migrasi_json.php` di root folder:
```php
<?php
require __DIR__ . '/includes/koneksi.php';

$jsonFile = __DIR__ . '/data/buku.json'; 

if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $bukuLama = json_decode($jsonData, true);

    if (is_array($bukuLama)) {
        $stmt =$pdo->prepare(
            "INSERT INTO buku (judul, pengarang, tahun, isbn, stok, kategori)
             VALUES (:judul, :pengarang, :tahun, :isbn, :stok, :kategori)"
        );

        $sukses = 0;
        foreach ($bukuLama as $buku) {$stmt->execute([
                'judul' => $buku['judul'],
                'pengarang' => $buku['pengarang'],
                'tahun' => (int) $buku['tahun'],
                'isbn' => $buku['isbn'] ?? null,
                'stok' => (int) ($buku['stok'] ?? 0),
                'kategori' =>$buku['kategori'] ?? null
            ]);
            $sukses++;
        }
        echo "Migrasi selesai! $sukses buku berhasil dipindahkan.";
    }
} else {
    echo "File buku.json tidak ditemukan.";
}
```