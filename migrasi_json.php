<?php
require __DIR__ . '/includes/koneksi.php';

// Pastikan lokasi file json sudah benar
$jsonFile = __DIR__ . '/data/buku.json';

if (file_exists($jsonFile)) {
  $jsonData = file_get_contents($jsonFile);
  $bukuLama = json_decode($jsonData, true);

  if (is_array($bukuLama)) {
    // Siapkan prepared statement di luar loop untuk efisiensi
    $stmt = $pdo->prepare(
      "INSERT INTO buku (judul, pengarang, tahun, isbn, stok, kategori)
             VALUES (:judul, :pengarang, :tahun, :isbn, :stok, :kategori)"
    );

    $sukses = 0;
    foreach ($bukuLama as $buku) {
      $stmt->execute([
        'judul' => $buku['judul'],
        'pengarang' => $buku['pengarang'],
        'tahun' => (int) $buku['tahun'],
        'isbn' => $buku['isbn'] ?? null,
        'stok' => (int) ($buku['stok'] ?? 0),
        'kategori' => $buku['kategori'] ?? null
      ]);
      $sukses++;
    }
    echo "Migrasi selesai! $sukses buku berhasil dipindahkan ke database PostgreSQL.";
  }
} else {
  echo "File buku.json tidak ditemukan.";
}
