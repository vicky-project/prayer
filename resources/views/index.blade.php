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
  #prayerApp .table th {
    font-weight: 500;
  }
  #prayerApp .table td {
    font-family: monospace;
    font-size: 1.2rem;
  }
  .btn:focus {
    box-shadow: none;
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

  // Fungsi untuk membangun UI berdasarkan state
  function buildUI() {
    let html = '';
    if (currentState === 'loading') {
      html = `
      <div class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Memuat...</span>
      </div>
      <p class="mt-3">Meminta akses lokasi...</p>
      </div>
      `;
    } else if (currentState === 'denied') {
      html = `
      <div class="text-center py-4">
      <i class="bi bi-geo-alt-fill text-danger" style="font-size: 3rem;"></i>
      <h5 class="mt-3">Akses Lokasi Ditolak</h5>
      <p style="color: var(--tg-theme-hint-color);">Untuk menampilkan jadwal shalat yang akurat, kami memerlukan akses lokasi Anda.</p>
      <button class="btn btn-primary" onclick="openLocationSettings()">
      <i class="bi bi-gear me-2"></i>Buka Pengaturan
      </button>
      <button class="btn btn-outline-secondary mt-2" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Coba Lagi
      </button>
      <hr class="my-4">
      <p style="color: var(--tg-theme-hint-color);">Atau masukkan lokasi manual:</p>
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
      <p style="color: var(--tg-theme-hint-color);">Fitur lokasi tidak didukung di perangkat ini. Silakan masukkan lokasi secara manual.</p>
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
      <input type="number" step="any" class="form-control" id="latitude" placeholder="Contoh: -6.2088">
      </div>
      <div class="col-md-6 mb-3">
      <label for="longitude" class="form-label">Longitude</label>
      <input type="number" step="any" class="form-control" id="longitude" placeholder="Contoh: 106.8456">
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
      <p style="color: var(--tg-theme-hint-color);">${errorMessage}</p>
      <button class="btn btn-primary" onclick="requestLocation()">
      <i class="bi bi-arrow-repeat me-2"></i>Coba Lagi
      </button>
      </div>
      `;
    } else if (currentState === 'loaded') {
      html = `
      <div>
      <div class="text-center mb-4">
      <i class="bi bi-geo-alt-fill text-primary"></i>
      <span class="ms-2" id="locationDisplay">${locationName || 'Lokasi Anda'}</span>
      </div>
      <div class="text-center mb-2 small" style="color: var(--tg-theme-hint-color);" id="dateDisplay"></div>
      <table class="table" style="background-color: var(--tg-theme-section-bg-color);">
      <tbody>
      <tr><th scope="row">Imsak</th><td class="text-end" id="imsak">-</td></tr>
      <tr><th scope="row">Subuh</th><td class="text-end" id="shubuh">-</td></tr>
      <tr><th scope="row">Dhuhur</th><td class="text-end" id="dhuhur">-</td></tr>
      <tr><th scope="row">Ashar</th><td class="text-end" id="ashar">-</td></tr>
      <tr><th scope="row">Maghrib</th><td class="text-end" id="maghrib">-</td></tr>
      <tr><th scope="row">Isya</th><td class="text-end" id="isya">-</td></tr>
      </tbody>
      </table>
      <div class="small text-center" style="color: var(--tg-theme-hint-color);" id="metodeDisplay"></div>
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

  // Meminta lokasi melalui Telegram Location Manager atau fallback browser
  window.requestLocation = function() {
    currentState = 'loading';
    buildUI();

    clearLocationTimeout();
    locationTimeout = setTimeout(() => {
    if(currentState === 'loading') {
    console.warn("Location request timeout - Switching to unavailable");
    currentState = 'unavailable';
    buildUI();
    }
    }, 10000)

    const tg = window.Telegram?.WebApp;
    if (!tg) {
      // Fallback ke browser geolocation jika Telegram WebApp tidak tersedia
      useBrowserGeolocation();
      return;
    }

    if (tg.LocationManager && typeof tg.LocationManager.getLocation === 'function') {
      try {
        tg.LocationManager.getLocation((locationData) => {
        if (locationData) {
        // Izin diberikan
        sendLocationToBackend(locationData.latitude, locationData.longitude);
        } else {
        // Izin ditolak
        currentState = 'denied';
        buildUI();
        }
        });
      } catch(error) {
        console.error("Telegram LocationManager error:", error);
        clearLocationTimeout();
        useBrowserGeolocation();
      }
    } else {
      // LocationManager tidak tersedia, fallback ke browser
      useBrowserGeolocation();
    }
  };

  // Fallback geolocation browser
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
      if (error.code === error.PERMISSION_DENIED) {
      currentState = 'denied';
      } else {
      currentState = 'unavailable';
      }
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

  // Buka pengaturan lokasi Telegram
  window.openLocationSettings = function() {
    const tg = window.Telegram?.WebApp;
    if (tg?.LocationManager) {
      tg.LocationManager.openSettings();
    } else {
      alert('Silakan buka pengaturan Telegram dan izinkan akses lokasi untuk aplikasi ini.');
    }
  };

  // Tampilkan form input manual
  window.showManualInput = function() {
    clearLocationTimeout();
    currentState = 'manual';
    buildUI();
  };

  // Kembali ke state awal (loading dan minta lokasi ulang)
  window.resetToInitial = function() {
    currentState = 'loading';
    buildUI();
    requestLocation();
  };

  // Ambil lokasi dari input manual
  window.getManualLocation = function() {
    const lat = document.getElementById('latitude')?.value;
    const lon = document.getElementById('longitude')?.value;
    const city = document.getElementById('city')?.value;

    if (lat && lon) {
      sendLocationToBackend(parseFloat(lat), parseFloat(lon), city || 'Manual');
    } else if (city) {
      geocodeCity(city);
    } else {
      alert('Masukkan kota atau koordinat yang valid.');
    }
  };

  // Geocoding sederhana menggunakan Nominatim (OpenStreetMap)
  function geocodeCity(city) {
    currentState = 'loading';
    buildUI();

    fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(city)}&format=json&limit=1`)
    .then(res => res.json())
    .then(data => {
    alert(JSON.stringify(data));
    if (data.length > 0) {
    const lat = parseFloat(data[0].lat);
    const lon = parseFloat(data[0].lon);
    sendLocationToBackend(lat, lon, city);
    } else {
    alert('Kota tidak ditemukan. Silakan masukkan koordinat manual.');
    currentState = 'manual';
    buildUI();
    }
    })
    .catch(err => {
    console.error('Geocoding error:', err);
    alert('Gagal mendapatkan koordinat. Coba masukkan manual.');
    currentState = 'manual';
    buildUI();
    });
  }

  function renderPrayerTimes(data, lat, lon, cityName) {
    currentState = 'loaded';
    locationName = cityName || `Lat: ${lat.toFixed(4)}, Lon: ${lon.toFixed(4)}`;
    buildUI(); // Render tabel kosong

    // Isi data ke tabel
    document.getElementById('shubuh').innerText = data.jadwal.shubuh;
    document.getElementById('dhuhur').innerText = data.jadwal.dhuhur;
    document.getElementById('ashar').innerText = data.jadwal.ashar;
    document.getElementById('maghrib').innerText = data.jadwal.maghrib;
    document.getElementById('isya').innerText = data.jadwal["isya'"];
    if (cityName) document.getElementById('locationDisplay').innerText = cityName;
  }

  // Kirim koordinat ke backend
  function sendLocationToBackend(lat, lon, cityName = '') {
    currentState = 'loading';
    buildUI();

    const initData = window.Telegram?.WebApp?.initData || @json(request()->get("initData"));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('{{ secure_url(config("app.url")) }}/api/prayer/times', {
      // Sesuaikan endpoint
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({
      latitude: lat,
      longitude: lon,
      initData: initData
      })
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    renderPrayerTimes(data.data, lat, lon, cityName);
    } else {
    currentState = 'error';
    errorMessage = data.message || 'Gagal memuat jadwal shalat.';
    buildUI();
    }
    })
    .catch(err => {
    console.error('Fetch error:', err);
    currentState = 'error';
    errorMessage = 'Koneksi ke server gagal. '+ err.message;
    buildUI();
    });
  }

  // Inisialisasi saat halaman dimuat
  document.addEventListener('DOMContentLoaded', function() {
  requestLocation();
  });
</script>
@endpush