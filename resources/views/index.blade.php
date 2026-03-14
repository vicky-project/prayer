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
<script>
  function fetchPrayerTimes(lat, lon) {
    fetch('{{ secure_url(config("app.url")) }}/api/prayer/times', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
      },
      body: JSON.stringify({ latitude: lat, longitude: lon })
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    alert(JSON.stringify(data.data));
    //displayPrayerTimes(data.timings, data.date);
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

  function requestLocation() {
    Telegram.LocationManager.init(function() {
    Telegram.LocationManager.getLocation((location) => {
    if (location && location.latitude && location.longitude) {
    document.getElementById('location-status').innerHTML = `
    <i class="bi bi-check-circle-fill me-2 text-success"></i> Lokasi diperoleh, mengambil data jadwal...
    `;
    fetchPrayerTimes(location.latitude, location.longitude);
    } else {
    document.getElementById('location-status').style.display = 'none';
    const errorDiv = document.getElementById('error-message');
    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> Tidak dapat mengakses lokasi. Pastikan Anda memberikan izin lokasi.';
    errorDiv.style.display = 'block';
    }
    });
    });
  }

  function displayPrayerTimes(timings, date) {
    const tbody = document.getElementById('prayer-tbody');
    tbody.innerHTML = '';

    // Mapping nama waktu (dari API) ke tampilan Indonesia
    const prayerNames = {
      'Fajr': 'Subuh',
      'Sunrise': 'Terbit',
      'Dhuhr': 'Dzuhur',
      'Asr': 'Ashar',
      'Maghrib': 'Maghrib',
      'Isha': 'Isya'
    };

    // Urutan tampilan
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
      document.getElementById('prayer-date').innerText = `📅 ${date}`;
    }

    document.getElementById('location-status').style.display = 'none';
    document.getElementById('prayer-times').style.display = 'block';
  }

  document.addEventListener("DOMContentLoaded", function() {
  const Telegram = window.Telegram.WebApp;

  // Minta lokasi saat halaman dimuat
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