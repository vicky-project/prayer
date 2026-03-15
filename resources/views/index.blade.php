@extends('coreui::layouts.app')
@section('title', 'Jadwal Shalat')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-moon-stars me-2"></i>Jadwal Waktu Shalat</h4>
        </div>
        <div class="card-body" id="prayerApp">
          {{-- Konten akan diisi oleh JavaScript --}}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* Menggunakan tema Telegram */
  body {
    background-color: var(--tg-theme-bg-color);
    color: var(--tg-theme-text-color);
  }
  .card {
    background-color: var(--tg-theme-secondary-bg-color);
    border: none;
  }
  .card-header {
    background-color: var(--tg-theme-button-color);
    color: var(--tg-theme-button-text-color);
    border-bottom: none;
  }
  .btn-primary {
    background-color: var(--tg-theme-button-color);
    border-color: var(--tg-theme-button-color);
    color: var(--tg-theme-button-text-color);
  }
  .btn-outline-primary {
    color: var(--tg-theme-button-color);
    border-color: var(--tg-theme-button-color);
  }
  .btn-outline-primary:hover {
    background-color: var(--tg-theme-button-color);
    color: var(--tg-theme-button-text-color);
  }
  .btn-outline-secondary {
    color: var(--tg-theme-hint-color);
    border-color: var(--tg-theme-hint-color);
  }
  .btn-outline-secondary:hover {
    background-color: var(--tg-theme-hint-color);
    color: var(--tg-theme-button-text-color);
  }
  .text-muted {
    color: var(--tg-theme-hint-color) !important;
  }
  .table {
    color: var(--tg-theme-text-color);
  }
  .table-hover tbody tr:hover {
    background-color: var(--tg-theme-section-separator-color);
  }
  .table td, .table th {
    border-color: var(--tg-theme-section-separator-color);
  }
  .spinner-border {
    color: var(--tg-theme-button-color) !important;
  }
  .timeout-option {
    margin-top: 1rem;
    font-size: 0.9rem;
  }
  #dateDisplay, #coordDisplay {
    font-size: 0.9rem;
  }
</style>
@endpush

@push('scripts')
<script>
  // State management
  let currentState = 'loading'; // 'loading', 'denied', 'unavailable', 'manual', 'error', 'loaded'
  let errorMessage = '';
  let locationName = '';
  let locationTimeout;

  const appElement = document.getElementById('prayerApp');

  function buildUI() {
    let html = '';
    if (currentState === 'loading') {
      html = `
      <div class="text-center py-5">
      <div class="spinner-border" role="status">
      <span class="visually-hidden">Memuat...</span>
      </div>
      <p class="mt-3">Meminta akses lokasi...</p>
      <div class="timeout-option">
      <button class="btn btn-sm btn-outline-secondary" onclick="showManualInput()">
      <i class="bi bi-pencil me-1"></i>Input Manual
      </button>
      </div>
      </div>
      `;
    } else if (currentState === 'denied') {
      html = `
      <div class="text-center py-4">
      <i class="bi bi-geo-alt-fill text-danger" style="font-size: 3rem;"></i>
      <h5 class="mt-3">Akses Lokasi Ditolak</h5>
      <p class="text-muted">Untuk menampilkan jadwal shalat yang akurat, kami memerlukan akses lokasi Anda.</p>
      <button class="btn btn-primary" onclick="openLocationSettings()">
      <i class="bi bi-gear me-2"></i>Buka Pengaturan
      </button>
      <button class="btn btn-outline-secondary mt-2" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Coba Lagi
      </button>
      <hr class="my-4">
      <p class="text-muted">Atau masukkan lokasi manual:</p>
      <button class="btn btn-outline-primary" onclick="showManualInput()">
      <i class="bi bi-pencil me-2"></i>Input Manual
      </button>
      </div>
      `;
    } else if (currentState === 'unavailable') {
      html = `
      <div class="text-center py-4">
      <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
      <h5 class="mt-3">Lokasi Tidak Tersedia</h5>
      <p class="text-muted">Fitur lokasi tidak didukung atau waktu permintaan habis. Silakan masukkan lokasi secara manual.</p>
      <button class="btn btn-primary" onclick="showManualInput()">
      <i class="bi bi-pencil me-2"></i>Input Manual
      </button>
      </div>
      `;
    } else if (currentState === 'manual') {
      html = `
      <div>
      <h5 class="mb-3">Masukkan Lokasi Manual</h5>
      <div class="mb-3">
      <label for="city" class="form-label">Nama Kota</label>
      <input type="text" class="form-control" id="city" placeholder="Contoh: Jakarta">
      </div>
      <div class="row">
      <div class="col-md-6 mb-3">
      <label for="latitude" class="form-label">Latitude</label>
      <input type="number" step="any" class="form-control" id="latitude" placeholder="-6.2088">
      </div>
      <div class="col-md-6 mb-3">
      <label for="longitude" class="form-label">Longitude</label>
      <input type="number" step="any" class="form-control" id="longitude" placeholder="106.8456">
      </div>
      </div>
      <button class="btn btn-success w-100" onclick="getManualLocation()">
      <i class="bi bi-search me-2"></i>Tampilkan Jadwal
      </button>
      <button class="btn btn-link mt-2" onclick="resetToInitial()">
      Kembali
      </button>
      </div>
      `;
    } else if (currentState === 'error') {
      html = `
      <div class="text-center py-4">
      <i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 3rem;"></i>
      <h5 class="mt-3">Terjadi Kesalahan</h5>
      <p class="text-muted">${errorMessage}</p>
      <button class="btn btn-primary" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Coba Lagi
      </button>
      <button class="btn btn-outline-secondary mt-2" onclick="showManualInput()">
      <i class="bi bi-pencil me-2"></i>Input Manual
      </button>
      </div>
      `;
    } else if (currentState === 'loaded') {
      html = `
      <div>
      <div class="text-center mb-3">
      <i class="bi bi-geo-alt-fill text-primary"></i>
      <span class="ms-2" id="locationDisplay">${locationName}</span>
      </div>
      <div class="text-center mb-2 small text-muted" id="dateDisplay"></div>
      <div class="text-center mb-3 small text-muted" id="coordDisplay"></div>
      <table class="table table-hover">
      <tbody>
      <tr><th scope="row">Imsak</th><td class="text-end" id="imsak">-</td></tr>
      <tr><th scope="row">Subuh</th><td class="text-end" id="subuh">-</td></tr>
      <tr><th scope="row">Terbit</th><td class="text-end" id="terbit">-</td></tr>
      <tr><th scope="row">Dhuha</th><td class="text-end" id="dhuha">-</td></tr>
      <tr><th scope="row">Dzuhur</th><td class="text-end" id="dzuhur">-</td></tr>
      <tr><th scope="row">Ashar</th><td class="text-end" id="ashar">-</td></tr>
      <tr><th scope="row">Maghrib</th><td class="text-end" id="maghrib">-</td></tr>
      <tr><th scope="row">Isya</th><td class="text-end" id="isya">-</td></tr>
      </tbody>
      </table>
      <div class="text-muted small text-center">
      <i class="bi bi-info-circle me-1"></i>Waktu berdasarkan lokasi terdekat
      </div>
      <button class="btn btn-outline-primary w-100 mt-3" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Perbarui Lokasi
      </button>
      </div>
      `;
    }
    appElement.innerHTML = html;
  }

  function clearLocationTimeout() {
    if (locationTimeout) {
      clearTimeout(locationTimeout);
      locationTimeout = null;
    }
  }

  window.requestLocation = function() {
    currentState = 'loading';
    buildUI();

    clearLocationTimeout();
    locationTimeout = setTimeout(() => {
    if (currentState === 'loading') {
    currentState = 'unavailable';
    buildUI();
    }
    }, 10000);

    const tg = window.Telegram?.WebApp;
    if (!tg) {
      useBrowserGeolocation();
      return;
    }

    if (tg.LocationManager && typeof tg.LocationManager.getLocation === 'function') {
      try {
        tg.LocationManager.getLocation((locationData) => {
        clearLocationTimeout();
        if (locationData) {
        sendLocationToBackend(locationData.latitude, locationData.longitude);
        } else {
        currentState = 'denied';
        buildUI();
        }
        });
      } catch (e) {
        console.error('Telegram LocationManager error:', e);
        clearLocationTimeout();
        useBrowserGeolocation();
      }
    } else {
      useBrowserGeolocation();
    }
  };

  function useBrowserGeolocation() {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
      (position) => {
      clearLocationTimeout();
      sendLocationToBackend(position.coords.latitude, position.coords.longitude);
      },
      (error) => {
      clearLocationTimeout();
      console.error('Geolocation error:', error);
      currentState = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
      buildUI();
      },
      { enableHighAccuracy: true, timeout: 8000 }
      );
    } else {
      clearLocationTimeout();
      currentState = 'unavailable';
      buildUI();
    }
  }

  window.openLocationSettings = function() {
    const tg = window.Telegram?.WebApp;
    if (tg?.LocationManager) {
      tg.LocationManager.openSettings();
    } else {
      alert('Silakan buka pengaturan Telegram dan izinkan akses lokasi.');
    }
  };

  window.showManualInput = function() {
    clearLocationTimeout();
    currentState = 'manual';
    buildUI();
  };

  window.resetToInitial = function() {
    requestLocation();
  };

  window.getManualLocation = function() {
    const lat = document.getElementById('latitude')?.value;
    const lon = document.getElementById('longitude')?.value;
    const city = document.getElementById('city')?.value;

    if (lat && lon) {
      sendLocationToBackend(parseFloat(lat), parseFloat(lon), city || '');
    } else if (city) {
      sendLocationToBackend(null, null, city);
    } else {
      alert('Masukkan kota atau koordinat yang valid.');
    }
  };

  function sendLocationToBackend(lat, lon, cityName = '') {
    currentState = 'loading';
    buildUI();

    const initData = window.Telegram?.WebApp?.initData || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let body = {
      initData
    };
    if (cityName && (lat === null || lon === null)) {
      body.city = cityName;
    } else {
      body.lat = lat;
      body.lon = lon;
      if (cityName) body.city = cityName;
    }

    fetch('{{ secure_url(config("app.url")) }}/api/prayer/times', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    currentState = 'loaded';
    locationName = cityName || `Lat: ${data.data.latitude.toFixed(4)}, Lon: ${data.data.longitude.toFixed(4)}`;
    buildUI();

    // Isi data ke tabel
    document.getElementById('imsak').innerText = data.data.jadwal.imsak || '-';
    document.getElementById('subuh').innerText = data.data.jadwal.subuh || '-';
    document.getElementById('terbit').innerText = data.data.jadwal.terbit || '-';
    document.getElementById('dhuha').innerText = data.data.jadwal.dhuha || '-';
    document.getElementById('dzuhur').innerText = data.data.jadwal.dzuhur || '-';
    document.getElementById('ashar').innerText = data.data.jadwal.ashar || '-';
    document.getElementById('maghrib').innerText = data.data.jadwal.maghrib || '-';
    document.getElementById('isya').innerText = data.data.jadwal.isya || '-';

    // Tampilkan tanggal dan koordinat dari respons server
    document.getElementById('dateDisplay').innerText = `📅 ${data.data.date}`;
    document.getElementById('coordDisplay').innerText = `📍 ${data.data.latitude}, ${data.data.longitude}`;

    if (cityName) document.getElementById('locationDisplay').innerText = cityName;
    } else {
    currentState = 'error';
    errorMessage = data.message || 'Gagal memuat jadwal shalat.';
    buildUI();
    }
    })
    .catch(err => {
    console.error('Fetch error:', err);
    currentState = 'error';
    errorMessage = 'Koneksi ke server gagal. Error: ' + err.message;
    buildUI();
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
  requestLocation();
  });
</script>
@endpush