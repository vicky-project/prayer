@extends('coreui::layouts.mini-app')
@section('title', 'Jadwal Shalat')
@section('content')
<div class="container-custom">
  <div class="page-header">
    <a href="{{ route('telegram.home') }}" class="home-button" title="Kembali ke Beranda">
      <i class="bi bi-house-door fs-1"></i>
    </a>
    <h2>Jadwal Shalat</h2>
  </div>

  <div id="location-status" class="alert alert-info">
    <i class="bi bi-geo-alt-fill me-2"></i> Meminta lokasi...
  </div>

  <div id="prayer-times" style="display: none;">
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Waktu</th>
              <th class="text-end">Jam</th>
            </tr>
          </thead>
          <tbody id="prayer-tbody"></tbody>
        </table>
      </div>
    </div>
    <div class="text-center text-muted mt-3 small" id="prayer-date"></div>
  </div>

  <div id="error-message" class="alert alert-danger" style="display: none;"></div>
</div>
@endsection

@push('scripts')
{{-- Penting: Pastikan untuk memuat library Telegram Web App --}}
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
  // Inisialisasi Telegram Web App
  const Telegram = window.Telegram.WebApp;
  Telegram.ready();


  // Fungsi untuk menangani berbagai skenario error lokasi
  function handleLocationError(error) {
    const statusEl = document.getElementById('location-status');
    const errorEl = document.getElementById('error-message');
    const settingsBtn = document.getElementById('open-settings-btn');

    statusEl.style.display = 'none';
    errorEl.style.display = 'block';

    // Pesan default
    let errorMessage = 'Tidak dapat mengakses lokasi.';

    // Analisis pesan error dari Telegram (bersifat umum, karena tidak ada kode error spesifik)
    if (error && error.message) {
      if (error.message.includes('denied') || error.message.includes('ditolak')) {
        errorMessage = `
        <i class="bi bi-shield-lock-fill me-2"></i>
        <strong>Akses Lokasi Ditolak.</strong><br>
        Anda sebelumnya telah menolak izin lokasi untuk mini app ini.
        `;
        // Sembunyikan tombol buka pengaturan jika tidak ada
      } else if (error.message.includes('unavailable')) {
        errorMessage = `
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Layanan Lokasi Tidak Tersedia.</strong><br>
        Pastikan fitur lokasi ponsel Anda aktif.
        `;
      } else {
        errorMessage = `
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error Tidak Dikenal:</strong> ${error.message}
        `;
      }
    } else {
      // Fallback jika error tidak memberikan pesan
      errorMessage = `
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Tidak Dapat Mengakses Lokasi.</strong><br>
      Kemungkinan Anda belum memberikan izin.
      `;
    }
  }

  // Fungsi untuk membuka pengaturan lokasi Telegram
  function openLocationSettings() {
    if (Telegram.LocationManager && Telegram.LocationManager.openSettings) {
      // Catatan: openSettings harus dipanggil sebagai respons dari klik tombol
      Telegram.LocationManager.openSettings();
    } else {
      alert('Fitur buka pengaturan tidak didukung. Silakan buka pengaturan Telegram secara manual.');
    }
  }

  // Fungsi untuk mengambil jadwal shalat dari server (sama seperti sebelumnya)
  function fetchPrayerTimes(lat, lon) {
    fetch('{{ secure_url(config("app.url")) }}/api/prayer/times', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ latitude: lat, longitude: lon })
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    displayPrayerTimes(data.timings, data.date);
    } else {
    throw new Error(data.message || 'Gagal memuat data');
    }
    })
    .catch(error => {
    document.getElementById('location-status').style.display = 'none';
    const errorDiv = document.getElementById('error-message');
    errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${error.message}`;
    errorDiv.style.display = 'block';
    });
  }

  // Fungsi untuk menampilkan jadwal (sama seperti sebelumnya)
  function displayPrayerTimes(timings, date) {
    const tbody = document.getElementById('prayer-tbody');
    tbody.innerHTML = '';

    const prayerNames = {
      'Fajr': 'Subuh',
      'Sunrise': 'Terbit',
      'Dhuhr': 'Dzuhur',
      'Asr': 'Ashar',
      'Maghrib': 'Maghrib',
      'Isha': 'Isya'
    };
    const order = ['Fajr',
      'Sunrise',
      'Dhuhr',
      'Asr',
      'Maghrib',
      'Isha'];

    order.forEach(key => {
    if (timings[key]) {
    const row = `<tr>
    <td>${prayerNames[key] || key}</td>
    <td class="text-end"><strong>${timings[key]}</strong></td>
    </tr>`;
    tbody.innerHTML += row;
    }
    });

    if (date) {
      document.getElementById('prayer-date').innerHTML = `<i class="bi bi-calendar me-1"></i> ${date}`;
    }

    document.getElementById('location-status').style.display = 'none';
    document.getElementById('prayer-times').style.display = 'block';
  }

  // Fungsi untuk fallback jika Location Manager tidak tersedia
  function showManualError(message) {
    const statusEl = document.getElementById('location-status');
    const errorEl = document.getElementById('error-message');
    statusEl.style.display = 'none';
    errorEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}`;
    errorEl.style.display = 'block';
  }

  // Fungsi utama untuk meminta lokasi
  function requestLocation() {
    const statusEl = document.getElementById('location-status');
    const errorEl = document.getElementById('error-message');
    const timesEl = document.getElementById('prayer-times');

    // Tampilkan status memuat
    statusEl.style.display = 'block';
    statusEl.innerHTML = `<i class="bi bi-geo-alt-fill me-2"></i> Meminta izin lokasi...`;
    errorEl.style.display = 'none';
    timesEl.style.display = 'none';

    // Cek apakah Location Manager tersedia
    if (!Telegram.LocationManager) {
      showManualError('Fitur lokasi tidak didukung di versi Telegram ini. Silakan perbarui aplikasi Telegram Anda.');
      return;
    }

    try {
      // *** LANGSUNG MINTA LOKASI ***
      // Catatan: Method yang benar adalah requestLocation, bukan getLocation.
      // Method ini akan memicu prompt izin jika perlu.
      Telegram.LocationManager.getLocation((location) => {
      if (!location) {
      // Tangani error, termasuk izin ditolak
      alert("location error");
      // Pesan default
      let errorMessage = 'Tidak dapat mengakses lokasi.';
      // Tambahkan tombol untuk membuka pengaturan, gunakan HTML yang akan dimasukkan ke errorEl
      const settingsButtonHtml = `
      <div class="mt-3 d-grid gap-2">
      <button id="open-settings-btn" class="btn btn-outline-primary" onclick="openLocationSettings()">
      <i class="bi bi-gear-fill me-2"></i>Buka Pengaturan Lokasi
      </button>
      <button class="btn btn-light" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Coba Lagi
      </button>
      </div>
      `;
      errorEl.innerHTML = errorMessage + settingsButtonHtml;
      handleLocationError("Error mendapatkan lokasi");
      } else if (location) {
      // Lokasi berhasil didapatkan
      statusEl.innerHTML = `<i class="bi bi-check-circle-fill me-2 text-success"></i> Lokasi diperoleh, mengambil data jadwal...`;
      //fetchPrayerTimes(location.latitude, location.longitude);
      }
      });
    } catch(error) {
      alert(error.message || "Gagal meminta lokasi");
    }
  }

  // Mulai proses request lokasi saat halaman dimuat
  document.addEventListener('DOMContentLoaded', function() {
  requestLocation();
  });

</script>
@endpush

@push('styles')
<style>
  .container-custom {
    max-width: 500px;
    margin: 0 auto;
    padding: 1rem;
  }
  .page-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  .page-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--tg-theme-text-color, #000);
    }
    .home-button {
    color: var(--tg-theme-button-color, #40a7e3);
    font-size: 2rem;
    line-height: 1;
    text-decoration: none;
    }
    .alert {
    border-radius: 12px;
    padding: 1rem;
    border: none;
    }
    .alert-info {
    background-color: var(--tg-theme-bg-color, #e7f3ff);
    color: var(--tg-theme-text-color, #000);
    }
    .alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    }
    .card {
    background-color: var(--tg-theme-bg-color, #fff);
    border: 1px solid var(--tg-theme-hint-color, #ddd);
    border-radius: 16px;
    overflow: hidden;
    }
    .table {
    margin: 0;
    color: var(--tg-theme-text-color, #000);
    }
    .table td, .table th {
    padding: 1rem;
    border-top: 1px solid var(--tg-theme-hint-color, #eee);
    }
    .table tr:first-child td {
    border-top: none;
    }
    .text-end {
    text-align: right;
    }
    .small {
    font-size: 0.85rem;
    }
    </style>
    @endpush