@extends('coreui::layouts.mini-app')
@section('title', 'Jadwal Shalat')

@section('content')
<div class="container py-3">
  <div class="row justify-content-center mb-3">
    <div class="col-md-12">
      <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('telegram.home') }}" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
        @if($telegramUser)
        <a href="{{ route('apps.prayer.settings') }}" class="btn btn-outline-secondary">
          <i class="bi bi-gear-fill fs-5"></i>
        </a>
        @endif
      </div>
    </div>
  </div>
  <div class="row justify-content-center mt-3">
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
  /* Tema Telegram */
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
    color: var(--tg-theme-text-color) !important;
  }
  .table {
    color: var(--tg-theme-text-color);
  }
  .table-hover tbody tr:hover {
    background-color: text-white;
  }
  .table td, .table th {
    border-color: var(--tg-theme-section-separator-color);
  }
  .table-active {
    background-color: rgba(75, 255, 100, 0.2) !important;
  }
  .spinner-border {
    color: var(--tg-theme-button-color) !important;
  }
  .timeout-option {
    margin-top: 1rem;
    font-size: 0.9rem;
  }
  /* Countdown styling */
  #countdown {
    font-family: monospace;
    text-align: center;
    background: var(--tg-theme-button-color);
    color: var(--tg-theme-hint-color);
    padding: 0.75rem;
    border-radius: 0.75rem;
    margin: 1rem 0;
  }
  #countdown .next-label {
    font-size: 0.85rem;
    opacity: 0.9;
  }
  #countdown .timer {
    font-size: 2rem;
    font-weight: bold;
    letter-spacing: 2px;
    line-height: 1.2;
  }
  @media (max-width: 576px) {
    #countdown .timer {
      font-size: 1.5rem;
    }
  }
</style>
@endpush

@push('scripts')
<script>
  // ========================
  // State management
  // ========================
  let currentState = 'loading'; // loading, denied, unavailable, manual, error, loaded
  let errorMessage = '';
  let locationName = '';
  let locationTimeout;

  const hasDefaultLocation = @json($telegramUser && isset($telegramUser->data["default_location"]) && !empty($telegramUser->data["default_location"]));
  let usingDefault = hasDefaultLocation;
  const defaultLocation = @json($telegramUser->data["default_location"] ?? null);

  const appElement = document.getElementById('prayerApp');

  // ========================
  // Countdown variables
  // ========================
  let countdownInterval = null;
  let currentPrayerTimes = null;
  let cityTimezoneOffset = null; // offset dalam menit dari UTC, diisi dari server
  let isRamadhan = false;

  // ========================
  // Helper functions
  // ========================
  function stopCountdown() {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
  }

  // Mendapatkan waktu saat ini di zona waktu kota (berdasarkan offset dari server)
  function getCurrentCityTime() {
    if (cityTimezoneOffset !== null) {
      const nowUTC = new Date();
      const utcTime = nowUTC.getTime() + (nowUTC.getTimezoneOffset() * 60 * 1000); // konversi ke UTC
      const cityTime = new Date(utcTime + (cityTimezoneOffset * 60 * 1000));
      return cityTime;
    } else {
      return new Date();
    }
  }

  // Konversi string waktu "HH:MM" ke menit sejak tengah malam
  function timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    const parts = timeStr.split(':');
    if (parts.length < 2) return 0;
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
  }

  // Nama shalat dalam bahasa Indonesia
  function getPrayerName(key) {
    const names = {
      'imsak': 'Imsak',
      'subuh': 'Subuh',
      'terbit': 'Terbit',
      'dhuha': 'Dhuha',
      'dzuhur': 'Dzuhur',
      'ashar': 'Ashar',
      'maghrib': 'Maghrib',
      'isya': 'Isya'
    };
    return names[key] || key;
  }

  // Memulai countdown menuju waktu shalat berikutnya
  function startCountdown(prayerTimes) {
    stopCountdown();
    if (!prayerTimes) return;

    const order = ['imsak',
      'subuh',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const now = getCurrentCityTime();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    let nextPrayer = null;
    let nextMinutes = null;
    for (let name of order) {
      if (prayerTimes[name]) {
        const minutes = timeToMinutes(prayerTimes[name]);
        if (minutes > nowMinutes) {
          nextPrayer = name;
          nextMinutes = minutes;
          break;
        }
      }
    }

    const countdownDiv = document.getElementById('countdown');
    if (!countdownDiv) return;

    // Jika tidak ada shalat setelah waktu sekarang (sudah lewat Isya)
    if (nextPrayer === null) {
      countdownDiv.innerHTML = `<div class="text-center text-white">✨ Shalat hari ini sudah selesai ✨</div>`;
      return;
    }

    // Hitung selisih detik
    let diffSeconds = (nextMinutes - nowMinutes) * 60 - now.getSeconds();
    if (diffSeconds < 0) diffSeconds += 24 * 60 * 60; // aman

    function updateDisplay() {
      const hours = Math.floor(diffSeconds / 3600);
      const minutes = Math.floor((diffSeconds % 3600) / 60);
      const seconds = diffSeconds % 60;
      countdownDiv.innerHTML = `
      <div class="text-center">
      <div class="next-label text-white">Menuju ${getPrayerName(nextPrayer)}</div>
      <div class="timer text-white">${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}</div>
      </div>
      `;
      if (diffSeconds <= 0) {
        // Waktu telah tercapai, hitung ulang untuk shalat berikutnya
        stopCountdown();
        startCountdown(prayerTimes);
      }
      diffSeconds--;
    }
    updateDisplay();
    countdownInterval = setInterval(updateDisplay, 1000);
  }

  // ========== RINGKASAN LISAN SEDERHANA ==========
  function getSummaryText(prayerTimes, currentPrayer) {
    if (!prayerTimes || !currentPrayer) return '';
    const order = ['imsak',
      'subuh',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const now = getCurrentCityTime();
    let nextPrayer = null;
    let found = false;
    for (let name of order) {
      if (found) {
        nextPrayer = name;
        break;
      }
      if (name === currentPrayer) found = true;
    }
    if (!nextPrayer) nextPrayer = order[0]; // kembali ke imsak besok
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    const targetMinutes = timeToMinutes(prayerTimes[nextPrayer]);
    let diffMinutes = targetMinutes - nowMinutes;
    if (diffMinutes < 0) diffMinutes += 24 * 60;
    const hours = Math.floor(diffMinutes / 60);
    const minutes = diffMinutes % 60;
    let timeStr = '';
    if (hours > 0) timeStr += `${hours} jam `;
    if (minutes > 0) timeStr += `${minutes} menit`;
    if (timeStr === '') timeStr = 'beberapa saat lagi';
    return `🕌 Waktu ${getPrayerName(nextPrayer)} akan tiba dalam ${timeStr}.`;
  }

  // Mendapatkan nama waktu shalat yang sedang berlangsung atau yang akan datang terdekat
  function getCurrentPrayer(prayerTimes) {
    const order = ['imsak',
      'subuh',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const now = getCurrentCityTime();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    let current = null;
    let next = null;

    for (let i = 0; i < order.length; i++) {
      const name = order[i];
      if (prayerTimes[name]) {
        const minutes = timeToMinutes(prayerTimes[name]);
        if (minutes <= nowMinutes) {
          current = name;
        } else {
          next = name;
          break;
        }
      }
    }

    // Jika sekarang setelah Isya, maka yang sedang berlangsung adalah Isya atau tidak ada
    if (!next && current) return current;
    // Jika sebelum Subuh, tidak ada yang sedang berlangsung
    if (!current && next) return null;
    return current;
  }

  // ========================
  // UI building
  // ========================
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
      const currentPrayer = getCurrentPrayer(currentPrayerTimes);

      // Ringkasan lisan
      const summaryText = getSummaryText(currentPrayerTimes, currentPrayer);
      const summaryHtml = summaryText ? `<div class="text-center small mt-2 text-muted"><i class="bi bi-chat-dots"></i> ${summaryText}</div>`: '';

      const prayerOrder = [
        'imsak',
        'subuh',
        'terbit',
        'dhuha',
        'dzuhur',
        'ashar',
        'maghrib',
        'isya'
      ];
      let rows = '';
      for (let name of prayerOrder) {
        const time = currentPrayerTimes[name] || '-';
        const isCurrent = (name === currentPrayer);
        const rowClass = isCurrent ? 'table-active': '';
        rows += `<tr class="${rowClass}"><th scope="row">${getPrayerName(name)}</th><td class="text-end">${time}</td></tr>`;
      }

      let extraButton = '';
      if (hasDefaultLocation && !usingDefault) {
        extraButton = `<button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="useDefaultLocation();"><i class="bi bi-arrow-repeat me-2"></i>Kembali ke Lokasi Default</button>`;
      }
      html = `
      <div>
      <div class="text-center mb-3">
      <i class="bi bi-geo-alt-fill text-primary"></i>
      <span class="ms-2" id="locationDisplay">${locationName}</span>
      </div>
      <div class="text-center mb-2 small text-muted" id="dateDisplay"></div>
      <div id="countdown"></div>
      <table class="table table-hover">
      <tbody>
      ${rows}
      </tbody>
      </table>
      ${summaryHtml}
      <div class="text-center mb-2 small text-muted" id="coordDisplay"></div>
      <div class="text-muted small text-center">
      <i class="bi bi-info-circle me-1"></i>Waktu berdasarkan lokasi terdekat
      </div>
      <button class="btn btn-outline-primary w-100 mt-3" onclick="requestLocation(true)">
      <i class="bi bi-arrow-repeat me-2"></i>Perbarui Lokasi
      </button>
      ${extraButton}
      </div>
      `;
    }
    appElement.innerHTML = html;
  }

  // ========================
  // Location & API functions
  // ========================
  function clearLocationTimeout() {
    if (locationTimeout) {
      clearTimeout(locationTimeout);
      locationTimeout = null;
    }
  }

  window.requestLocation = function(forceNoDefault = false) {
    if (!forceNoDefault && hasDefaultLocation && defaultLocation) {
      usingDefault = true;
      currentState = 'loading';
      buildUI();
      if (defaultLocation.city) {
        sendLocationToBackend(null, null, defaultLocation.city);
      } else if (defaultLocation.latitude && defaultLocation.longitude) {
        sendLocationToBackend(defaultLocation.latitude, defaultLocation.longitude, '');
      }
      return;
    }

    usingDefault = false;
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

  window.useDefaultLocation = function() {
    requestLocation(false);
  };

  window.getManualLocation = function() {
    usingDefault = false;
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
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    // Simpan data untuk countdown
    currentPrayerTimes = data.data.jadwal;
    // Jika server mengirim timezone_offset (menit dari UTC), gunakan itu
    cityTimezoneOffset = data.data.timezone_offset || null;
    isRamadhan = data.data.is_ramadhan || false;
    currentState = 'loaded';
    locationName = cityName || `Lat: ${data.data.latitude.toFixed(4)}, Lon: ${data.data.longitude.toFixed(4)}`;
    buildUI();

    // Tampilkan tanggal dan koordinat
    document.getElementById('dateDisplay').innerText = `📅 ${data.data.date}\n${data.data.hijri}`;
    document.getElementById('coordDisplay').innerText = `📍 ${data.data.latitude}, ${data.data.longitude}`;

    if (cityName) document.getElementById('locationDisplay').innerText = cityName;

    // Mulai countdown
    startCountdown(currentPrayerTimes);
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

  // Inisialisasi
  document.addEventListener('DOMContentLoaded', function() {
  requestLocation();
  });
</script>
@endpush