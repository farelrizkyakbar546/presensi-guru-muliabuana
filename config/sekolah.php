<?php
/**
 * KONFIGURASI PROFIL SEKOLAH & IDENTITAS KOP LAPORAN
 * SMKS Mulia Buana - Parungpanjang, Bogor
 * 
 * Panduan untuk Staf IT / Staf TU:
 * File ini digunakan untuk mengatur data profil lembaga yang tampil pada:
 * 1. Lembar Kop Surat Cetak Rekapitulasi Presensi (Cetak Fisik / Simpan PDF)
 * 2. Lembar Pengesahan Tanda Tangan (Kepala Sekolah & Admin Tata Usaha)
 * 
 * Silakan sesuaikan teks di dalam tanda kutip tunggal ('...') di bawah ini
 * sesuai data resmi sekolah yang berlaku tanpa perlu mengubah kodingan sistem lainnya.
 */

return [
    // --- Identitas Lembaga & Kop Surat ---
    'nama_yayasan'     => 'Yayasan Pendidikan Mulia Buana',
    'nama_sekolah'     => 'SMK SWASTA MULIA BUANA',
    'akreditasi'       => 'Terakreditasi B',
    'npsn'             => '20231422',
    'nss'              => '402020228090',
    'alamat'           => 'Jl. Raya Parungpanjang - Dago No. 1, Parungpanjang, Kec. Parungpanjang, Kab. Bogor, Jawa Barat',
    'email'            => 'smks.muliabuana@gmail.com',
    'website'          => 'smksmuliabuana.sch.id',

    // --- Pejabat Penandatangan Laporan ---
    'kota_surat'       => 'Bogor',
    
    // Kepala Sekolah
    'jabatan_kepsek'   => 'Kepala SMKS Mulia Buana',
    'nama_kepsek'      => 'Ahmad Suherman, S.Pd.', // Ganti dengan nama Kepala Sekolah yang menjabat
    'nip_kepsek'       => '-',                     // Ganti dengan NIP Kepala Sekolah jika ada

    // Admin Tata Usaha / Petugas Rekap
    'jabatan_tu'       => 'Admin Tata Usaha',
    'nama_tu'          => 'Staf Administrasi TU',  // Ganti dengan nama penanggung jawab TU
    'nip_tu'           => '-',                     // Ganti dengan NIP penanggung jawab TU jika ada
];
