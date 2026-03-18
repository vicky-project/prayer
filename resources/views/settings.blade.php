@extends('coreui::layouts.mini-app')
@section('title', 'Pengaturan Jadwal Shalat')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center mb-3">
    <div class="col-md-12">
      <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('apps.prayer') }}" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-2"></i>
          Kembali
        </a>
      </div>
    </div>
  </div>
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Pengaturan Prayer</h4>
        </div>
        <div class="card-body">

          <form id="settingsForm">
            @csrf
            <h5>Lokasi Default</h5>
            <p class="text-muted small">
              Jika diisi, lokasi ini akan digunakan saat membuka jadwal shalat.
            </p>

            <div class="mb-3">
              <label for="city" class="form-label">Nama Kota</label>
              <input type="text" class="form-control" id="city" name="city"
              value="{{ old('city', $telegramUser->data['default_location']['city'] ?? '') }}" placeholder="Nama Kota/kabupaten">
              <div class="form-text">
                Atau isi koordinat di bawah jika ingin lebih spesifik.
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="latitude" class="form-label">Latitude</label>
                <input type="number" step="any" class="form-control" id="latitude" name="latitude"
                value="{{ old('latitude', $telegramUser->data['default_location']['latitude'] ?? '') }}" placeholder="Contoh: -6.2088">
              </div>
              <div class="col-md-6 mb-3">
                <label for="longitude" class="form-label">Longitude</label>
                <input type="number" step="any" class="form-control" id="longitude" name="longitude"
                value="{{ old('longitude', $telegramUser->data['default_location']['longitude'] ?? '') }}" placeholder="Contoh: 106.8456">
              </div>
            </div>

            <button type="button" class="btn btn-outline-primary mb-3" id="useCurrentLocationBtn">
              <i class="bi bi-crosshair me-2"></i>
              Gunakan lokasi saat ini
            </button>

            <hr>
            <h5>Notifikasi</h5>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="notifications_enabled" name="notifications_enabled"
              {{ old('notifications_enabled', $telegramUser->data['notifications_enabled'] ?? false) ? 'checked' : '' }} value="1">
              <label class="form-check-label" for="notifications_enabled">Aktifkan notifikasi waktu shalat</label>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="saveBtn">Simpan Pengaturan</button>
          </form>
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
  const form = document.getElementById('settingsForm');
  const saveBtn = document.getElementById('saveBtn');
  const useLocationBtn = document.getElementById('useCurrentLocationBtn');

  function requestCurrentLocation() {
    return new Promise((resolve, reject) => {
    const tg = window.Telegram?.WebApp;
    if(!tg) {
    reject("Telegram WebApp tidak tersedia.");
    return;
    }

    if(tg.LocationManager && typeof tg.LocationManager.getLocation === 'function') {
    tg.LocationManager.getLocation((locationData) => {
    if(locationData) {
    resolve(locationData);
    } else {
    reject("Izin lokasi ditolak.");
    }
    });
    } else {
    // Fallback ke browser geolocation
    if(navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((position) => {
    resolve({
    latitude: position.coords.latitude,
    longitude: position.coords.longitude
    });
    }, (error) => {
    reject("Gagal mendapatkan lokasi: "+ error.message);
    }, { enableHighAccuracy: true, timeout: 10000 });
    } else {
    reject("Geolocation tidak didukung di browser anda.");
    }
    }
    });
  }

  useLocationBtn.addEventListener("click", async function() {
  useLocationBtn.disabled = true;
  useLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mendapatkan lokasi...';

  try {
  const location = await requestCurrentLocation();
  document.getElementById('city').value = "";
  document.getElementById('latitude').value = location.latitude.toFixed(6);
  document.getElementById('longitude').value = location.longitude.toFixed(6);
  showToast("Lokasi berhasil didapatkan", 'success'):
  } catch(error) {
  showToast(error.message, 'danger');
  } finally {
  useLocationBtn.disabled = false;
  useLocationBtn.innerHTML = '<i class="bi bi-crosshair me-2"></i>Gunakan lokasi saat ini';
  }
  });

  form.addEventListener('submit', async (e) => {
  e.preventDefault();

  // Tampilkan loading
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

  // Kumpulkan data
  const city = document.getElementById('city').value;
  const latitude = document.getElementById('latitude').value;
  const longitude = document.getElementById('longitude').value;
  const notificationsEnabled = document.getElementById('notifications_enabled').checked;

  const formData = {
  city: city || undefined,
  latitude: latitude ? parseFloat(latitude) : undefined,
  longitude: longitude ? parseFloat(longitude) : undefined,
  notifications_enabled: notificationsEnabled
  };

  // Hapus field kosong
  Object.keys(formData).forEach(key => formData[key] === undefined && delete formData[key]);

  try {
  const response = await fetch('{{ secure_url(config("app.url")) }}/api/prayer/settings', {
  method: 'POST',
  headers: {
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'X-Telegram-Init-Data': window.Telegram?.WebApp?.initData || @json(request()->get('initData', '')) // dikirim via header atau body
  },
  body: JSON.stringify(formData)
  });

  const result = await response.json();

  if (result.success) {
  showToast(result.message, 'success');
  } else {
  let errorMsg = result.message || 'Terjadi kesalahan.';
  if (result.errors) {
  errorMsg = Object.values(result.errors).flat().join('<br>');
  }
  showToast(errorMsg, 'danger');
  }
  } catch (error) {
  console.error('Error:', error);
  showToast('Gagal terhubung ke server.', 'danger');
  } finally {
  saveBtn.disabled = false;
  saveBtn.innerHTML = 'Simpan Pengaturan';
  }
  });
</script>
@endpush