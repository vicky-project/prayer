@extends('coreui::layouts.mini-app')
@section('title', 'Pengaturan Jadwal Shalat')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card shadow">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Pengaturan Prayer</h4>
        </div>
        <div class="card-body">
          @if(session('success'))
          <div class="alert alert-success">
            {{ session('success') }}
          </div>
          @endif

          <form method="POST" action="{{ route('apps.prayer.settings') }}">
            @csrf
            <h5>Lokasi Default</h5>
            <p class="text-muted small">
              Jika diisi, lokasi ini akan digunakan saat membuka jadwal shalat.
            </p>

            <div class="mb-3">
              <label for="city" class="form-label">Nama Kota</label>
              <input type="text" class="form-control" id="city" name="city"
              value="{{ old('city', $telegramUser->data['default_location']['city'] ?? '') }}">
              <div class="form-text">
                Atau isi koordinat di bawah jika ingin lebih spesifik.
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="latitude" class="form-label">Latitude</label>
                <input type="number" step="any" class="form-control" id="latitude" name="latitude"
                value="{{ old('latitude', $telegramUser->data['default_location']['latitude'] ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label for="longitude" class="form-label">Longitude</label>
                <input type="number" step="any" class="form-control" id="longitude" name="longitude"
                value="{{ old('longitude', $telegramUser->data['default_location']['longitude'] ?? '') }}">
              </div>
            </div>

            <hr>
            <h5>Notifikasi</h5>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="notifications_enabled" name="notifications_enabled"
              {{ old('notifications_enabled', $telegramUser->data['notifications_enabled'] ?? false) ? 'checked' : '' }} value="1">
              <label class="form-check-label" for="notifications_enabled">Aktifkan notifikasi waktu shalat</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Simpan Pengaturan</button>
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