@extends('telegram::layouts.mini-app')

@section('title', 'Jadwal Shalat')

@section('content')
<div class="container py-0" style="max-width:600px; margin:0 auto;">
  <div id="prayer-app">
    <div id="prayer-view" style="display:none;"></div>
    <div id="settings-view" style="display:none;"></div>
  </div>

  <!-- Modal Cari Kota -->
  <div class="modal fade" id="searchPrayerModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="searchPrayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="searchPrayerModalLabel"><i class="bi bi-search"></i> Cari Jadwal Shalat</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Kota</label>
            <input type="text" class="form-control" id="searchCityInput" placeholder="Contoh: Jakarta, ID" autocomplete="off">
            <datalist id="searchCitySuggestions"></datalist>
            <div class="invalid-feedback">
              Minimal 2 karakter
            </div>
          </div>
          <div id="searchResultArea">
            <!-- Hasil pencarian akan ditampilkan di sini -->
          </div>
          <div id="searchLoadingSpinner" class="text-center d-none">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">
              Memuat jadwal...
            </p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  body {
    background-color: var(--tg-theme-bg-color) !important;
    color: var(--tg-theme-text-color) !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .container {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
  .card {
    background-color: var(--tg-theme-secondary-bg-color) !important;
    border-color: var(--tg-theme-section-separator-color) !important;
    border-radius: 0;
  }
  .card-header {
    background-color: var(--tg-theme-button-color) !important;
    color: var(--tg-theme-button-text-color) !important;
    border-radius: 0;
  }
  .form-control, .input-group-text {
    background-color: var(--tg-theme-bg-color) !important;
    color: var(--tg-theme-text-color) !important;
    border-color: var(--tg-theme-section-separator-color) !important;
  }
  .btn-primary {
    background-color: var(--tg-theme-button-color) !important;
    border-color: var(--tg-theme-button-color) !important;
    color: var(--tg-theme-button-text-color) !important;
  }
  .btn-outline-secondary {
    border-color: var(--tg-theme-section-separator-color) !important;
    color: var(--tg-theme-hint-color) !important;
  }
  .text-muted {
    color: var(--tg-theme-hint-color) !important;
  }
  .table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
  }
  .table-active {
    background-color: rgba(35, 150, 55, 0.6) !important;
    color: white;
  }
  #main-table {
    color: var(--tg-theme-text-color);
  }
  #main-table.table-hover tbody tr:hover {
    background-color: var(--tg-theme-section-separator-color);
  }
  #main-table.table td, #main-table.table th {
    border-color: var(--tg-theme-section-separator-color);
  }
  #main-table.table-active {
    background-color: rgba(35, 150, 55, 0.6) !important;
    color: white;
  }
  .spinner-border {
    color: var(--tg-theme-button-color) !important;
  }
  #countdown {
    font-family: monospace;
    text-align: center;
    background: var(--tg-theme-button-color);
    color: var(--tg-theme-button-text-color);
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
  .error-container {
    background-color: var(--tg-theme-secondary-bg-color);
    border-left: 4px solid #dc3545;
    padding: 16px;
    margin: 16px;
    border-radius: 12px;
  }
  .error-container .error-message {
    color: #dc3545;
    font-weight: 500;
    margin-bottom: 8px;
  }
  .error-container .error-detail {
    font-size: 0.8rem;
    color: var(--tg-theme-hint-color);
    word-break: break-word;
  }
  /* Sticky header dan kolom pertama */
  #range-table thead th {
    position: sticky;
    top: 0;
    background-color: var(--tg-theme-secondary-bg-color);
    z-index: 2;
  }
  #range-table thead th:first-child {
    z-index: 3;
  }
  /* Gaya untuk sel tanggal */
  #range-table tbody td:first-child {
    font-weight: 500;
    background-color: var(--tg-theme-bg-color);
  }
  /* Warna teks untuk hari Jumat */
  #range-table .text-warning {
    color: #ffc107 !important;
    font-weight: bold;
  }
</style>
@endpush

@push('scripts')
<script src="//cdn.jsdelivr.net/npm/eruda"></script>
<script>
  eruda.init(); // Ikon Eruda akan muncul
</script>
<script>
  const BASE_URL = '{{ rtrim(config("app.url"), "/") }}';
  {!! file_get_contents(module_path('prayer', 'resources/assets/js/core.js')); !!}
  {!! file_get_contents(module_path('prayer', 'resources/assets/js/page.js')); !!}
  {!! file_get_contents(module_path('prayer', 'resources/assets/js/main.js')); !!}
</script>
@endpush