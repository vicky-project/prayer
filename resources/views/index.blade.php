@extends('telegram::layouts.mini-app')

@section('title', 'Jadwal Shalat')

@section('content')
<div class="container py-0" style="max-width:600px; margin:0 auto;">
  <div id="prayer-app">
    <div id="loading-view" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">
        Memuat jadwal shalat...
      </p>
    </div>
    <div id="prayer-view" style="display:none;"></div>
    <div id="settings-view" style="display:none;"></div>
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
  .table {
    color: var(--tg-theme-text-color);
  }
  .table-hover tbody tr:hover {
    background-color: var(--tg-theme-section-separator-color);
  }
  .table td, .table th {
    border-color: var(--tg-theme-section-separator-color);
  }
  .table-active {
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
</style>
@endpush

@push('scripts')
<script>
  (function() {
  const { fetchWithAuth, showToast, showLoading, hideLoading, escapeHtml } = window.TelegramApp;

  let currentView = 'prayer';
  let prayerData = null;
  let settingsData = null;
  let countdownInterval = null;
  let cityTimezoneOffset = null;

  function stopCountdown() {
  if (countdownInterval) {
  clearInterval(countdownInterval);
  countdownInterval = null;
  }
  }

  function getCurrentCityTime() {
  if (cityTimezoneOffset !== null) {
  const nowUTC = new Date();
  const utcTime = nowUTC.getTime() + (nowUTC.getTimezoneOffset() * 60 * 1000);
  const cityTime = new Date(utcTime + (cityTimezoneOffset * 60 * 1000));
  return cityTime;
  }
  return new Date();
  }

  function timeToMinutes(timeStr) {
  if (!timeStr) return 0;
  const parts = timeStr.split(':');
  if (parts.length < 2) return 0;
  return parseInt(parts[0]) * 60 + parseInt(parts[1]);
  }

  function getPrayerName(key) {
  const names = {
  'imsak': 'Imsak', 'subuh': 'Subuh', 'terbit': 'Terbit', 'dhuha': 'Dhuha',
  'dzuhur': 'Dzuhur', 'ashar': 'Ashar', 'maghrib': 'Maghrib', 'isya': 'Isya'
  };
  return names[key] || key;
  }

  function startCountdown(prayerTimes) {
  stopCountdown();
  if (!prayerTimes) return;

  const order = ['imsak', 'subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'];
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

  if (nextPrayer === null) {
  countdownDiv.innerHTML = '<div class="text-center">✨ Shalat hari ini sudah selesai ✨</div>';
  return;
  }

  let diffSeconds = (nextMinutes - nowMinutes) * 60 - now.getSeconds();
  if (diffSeconds < 0) diffSeconds += 24 * 60 * 60;

  function updateDisplay() {
  const hours = Math.floor(diffSeconds / 3600);
  const minutes = Math.floor((diffSeconds % 3600) / 60);
  const seconds = diffSeconds % 60;
  countdownDiv.innerHTML = `
  <div class="text-center">
  <div class="next-label">Menuju ${getPrayerName(nextPrayer)}</div>
  <div class="timer">${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}</div>
  </div>
  `;
  if (diffSeconds <= 0) {
  stopCountdown();
  startCountdown(prayerTimes);
  }
  diffSeconds--;
  }
  updateDisplay();
  countdownInterval = setInterval(updateDisplay, 1000);
  }

  async function loadPrayerTimes(lat = null, lon = null, city = null) {
  showLoading('Memuat jadwal shalat...');
  try {
  const body = {};
  if (city) body.city = city;
  else if (lat && lon) { body.latitude = lat; body.longitude = lon; }
  else throw new Error('Tidak ada lokasi yang diberikan');
  alert(JSON.stringify(body));

  const response = await fetchWithAuth('{{ config("app.url") }}/api/prayer/times', { method: 'POST', body: JSON.stringify(body) });
  if (!response.success) throw new Error(response.message || 'Gagal memuat jadwal');
  prayerData = response.data;
  alert(prayerData):
  cityTimezoneOffset = prayerData.timezone_offset || null;
  renderPrayerView();
  } catch (err) {
  alert(err.message);
  handleGlobalError(err, 'loadPrayerTimes');
  } finally {
  hideLoading();
  }
  }

  async function loadSettings() {
  try {
  const res = await fetchWithAuth('{{ config("app.url") }}/api/prayer/settings');
  settingsData = res.data || {};
  } catch(e) {
  alert(e.message);
  settingsData = {};
  }
  }

  async function saveSettings(formData) {
  showLoading('Menyimpan...');
  try {
  const res = await fetchWithAuth('{{ config("app.url") }}/api/prayer/settings', { method: 'POST', body: JSON.stringify(formData) });
  if (res.success) {
  showToast('Pengaturan disimpan');
  await loadSettings();
  showPrayerView();
  } else {
  throw new Error(res.message || 'Gagal menyimpan');
  }
  } catch(err) {
  handleGlobalError(err, 'saveSettings');
  } finally {
  hideLoading();
  }
  }

  function renderPrayerView() {
  if (!prayerData) return;
  const jadwal = prayerData.jadwal;
  const prayerOrder = ['imsak', 'subuh', 'terbit', 'dhuha', 'dzuhur', 'ashar', 'maghrib', 'isya'];
  const now = getCurrentCityTime();
  const nowMinutes = now.getHours() * 60 + now.getMinutes();
  let currentPrayer = null;
  for (let i = 0; i < prayerOrder.length; i++) {
  const name = prayerOrder[i];
  const minutes = timeToMinutes(jadwal[name]);
  if (minutes <= nowMinutes) currentPrayer = name;
  else break;
  }

  let rows = '';
  for (let name of prayerOrder) {
  const time = jadwal[name] || '-';
  const isCurrent = (name === currentPrayer);
  const rowClass = isCurrent ? 'table-active' : '';
  rows += `<tr class="${rowClass}"><th scope="row">${getPrayerName(name)}</th><td class="text-end">${time}</td></tr>`;
  }

  let extraButton = '';
  if (settingsData?.city || (settingsData?.latitude && settingsData?.longitude)) {
  extraButton = `<button id="useDefaultLocationBtn" class="btn btn-outline-secondary w-100 mt-2"><i class="bi bi-arrow-repeat me-2"></i>Kembali ke Lokasi Default</button>`;
  }

  const html = `
  <div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0"><i class="bi bi-moon-stars me-2"></i>Jadwal Waktu Shalat</h4>
  <div>
  <button id="settingsBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-gear-fill"></i></button>
  <button id="refreshPrayerBtn" class="btn btn-sm btn-outline-light ms-2"><i class="bi bi-arrow-repeat"></i></button>
  </div>
  </div>
  <div class="card-body">
  <div class="text-center mb-2">
  <i class="bi bi-geo-alt-fill text-primary"></i>
  <span class="ms-2" id="locationDisplay">${escapeHtml(prayerData.city || `${prayerData.latitude}, ${prayerData.longitude}`)}</span>
  </div>
  <div class="text-center small text-muted mb-2" id="dateDisplay">📅 ${prayerData.date} | ${prayerData.hijri}</div>
  <div id="countdown"></div>
  <div class="table-responsive"><table class="table table-hover"><tbody>${rows}</tbody></table></div>
  <div class="text-muted small text-center mt-2"><i class="bi bi-info-circle me-1"></i>Waktu berdasarkan lokasi terdekat</div>
  ${extraButton}
  </div>
  </div>
  `;
  document.getElementById('prayer-view').innerHTML = html;
  document.getElementById('prayer-view').style.display = 'block';
  document.getElementById('settings-view').style.display = 'none';
  document.getElementById('loading-view').style.display = 'none';

  document.getElementById('settingsBtn').addEventListener('click', () => showSettingsView());
  document.getElementById('refreshPrayerBtn').addEventListener('click', () => {
  if (prayerData.city) loadPrayerTimes(null, null, prayerData.city);
  else loadPrayerTimes(prayerData.latitude, prayerData.longitude);
  });
  if (extraButton) {
  document.getElementById('useDefaultLocationBtn')?.addEventListener('click', () => {
  if (settingsData.city) loadPrayerTimes(null, null, settingsData.city);
  else if (settingsData.latitude && settingsData.longitude) loadPrayerTimes(settingsData.latitude, settingsData.longitude);
  });
  }
  startCountdown(jadwal);
  }

  async function showSettingsView() {
  if (!settingsData) await loadSettings();
  const city = settingsData.city || '';
  const lat = settingsData.latitude || '';
  const lon = settingsData.longitude || '';
  const notifications = settingsData.notifications_enabled || false;

  const html = `
  <div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Pengaturan Jadwal Shalat</h4>
  <button id="backToPrayerBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Kembali</button>
  </div>
  <div class="card-body">
  <form id="settingsForm">
  <h5>Lokasi Default</h5>
  <p class="text-muted small">Kosongkan untuk meminta lokasi setiap kali.</p>
  <div class="mb-3">
  <label class="form-label">Nama Kota</label>
  <input type="text" class="form-control" id="city" value="${escapeHtml(city)}" placeholder="Contoh: Jakarta">
  </div>
  <div class="row">
  <div class="col-md-6 mb-3">
  <label class="form-label">Latitude</label>
  <input type="number" step="any" class="form-control" id="latitude" value="${escapeHtml(lat)}" placeholder="-6.2088">
  </div>
  <div class="col-md-6 mb-3">
  <label class="form-label">Longitude</label>
  <input type="number" step="any" class="form-control" id="longitude" value="${escapeHtml(lon)}" placeholder="106.8456">
  </div>
  </div>
  <div class="mb-3">
  <button type="button" class="btn btn-outline-primary" id="autoLocationBtn"><i class="bi bi-geo-alt me-2"></i>Ambil lokasi saat ini</button>
  <span class="text-muted ms-2" id="locationStatus"></span>
  </div>
  <hr>
  <div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" id="notifications_enabled" ${notifications ? 'checked' : ''}>
  <label class="form-check-label">Aktifkan notifikasi waktu shalat</label>
  </div>
  <button type="submit" class="btn btn-primary w-100">Simpan Pengaturan</button>
  </form>
  </div>
  </div>
  `;
  document.getElementById('settings-view').innerHTML = html;
  document.getElementById('prayer-view').style.display = 'none';
  document.getElementById('settings-view').style.display = 'block';
  document.getElementById('loading-view').style.display = 'none';

  document.getElementById('backToPrayerBtn').addEventListener('click', () => showPrayerView());
  document.getElementById('autoLocationBtn').addEventListener('click', requestCurrentLocation);
  document.getElementById('settingsForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = {
  city: document.getElementById('city').value || undefined,
  latitude: document.getElementById('latitude').value ? parseFloat(document.getElementById('latitude').value) : undefined,
  longitude: document.getElementById('longitude').value ? parseFloat(document.getElementById('longitude').value) : undefined,
  notifications_enabled: document.getElementById('notifications_enabled').checked
  };
  saveSettings(formData);
  });
  }

  function showPrayerView() {
  if (prayerData) renderPrayerView();
  else loadDefaultLocation();
  }

  async function loadDefaultLocation() {
  showLoading('Mengambil lokasi default...');
  try {
  const settings = await fetchWithAuth('{{ config("app.url") }}/api/prayer/settings');
  settingsData = settings.data || {};
  alert(settingsData);
  if (settingsData.city) {
  await loadPrayerTimes(null, null, settingsData.city);
  } else if (settingsData.latitude && settingsData.longitude) {
  await loadPrayerTimes(settingsData.latitude, settingsData.longitude);
  } else {
  requestLiveLocation();
  }
  } catch(e) {
  alert(e.message);
  requestLiveLocation();
  } finally {
  hideLoading();
  }
  }

  function requestLiveLocation() {
  showLoading('Meminta lokasi...');
  const tg = window.Telegram?.WebApp;
  if (tg && tg.LocationManager && typeof tg.LocationManager.getLocation === 'function') {
  tg.LocationManager.getLocation((location) => {
  if (location) loadPrayerTimes(location.latitude, location.longitude);
  else browserGeolocation();
  });
  } else {
  browserGeolocation();
  }
  function browserGeolocation() {
  if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
  (pos) => loadPrayerTimes(pos.coords.latitude, pos.coords.longitude),
  () => { showToast('Gagal mendapatkan lokasi, silakan atur lokasi default di pengaturan.'); showSettingsView(); }
  );
  } else {
  showToast('Geolocation tidak didukung, silakan atur lokasi manual.');
  showSettingsView();
  }
  }
  }

  function requestCurrentLocation() {
  const statusSpan = document.getElementById('locationStatus');
  if (statusSpan) statusSpan.innerText = 'Meminta lokasi...';
  const tg = window.Telegram?.WebApp;
  if (tg && tg.LocationManager && typeof tg.LocationManager.getLocation === 'function') {
  tg.LocationManager.getLocation((location) => {
  if (location) {
  document.getElementById('latitude').value = location.latitude;
  document.getElementById('longitude').value = location.longitude;
  document.getElementById('city').value = '';
  if (statusSpan) statusSpan.innerText = 'Lokasi berhasil diambil.';
  } else {
  if (statusSpan) statusSpan.innerText = 'Akses ditolak.';
  }
  });
  } else {
  if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
  (pos) => {
  document.getElementById('latitude').value = pos.coords.latitude;
  document.getElementById('longitude').value = pos.coords.longitude;
  document.getElementById('city').value = '';
  if (statusSpan) statusSpan.innerText = 'Lokasi berhasil diambil.';
  },
  () => { if (statusSpan) statusSpan.innerText = 'Gagal mendapatkan lokasi.'; }
  );
  } else {
  if (statusSpan) statusSpan.innerText = 'Geolocation tidak didukung.';
  }
  }
  }

  function handleGlobalError(error, context) {
  console.error(`[${context}]`, error);
  showToast(error.message);
  const errorHtml = `<div class="error-container"><div class="error-message"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal memuat data</div><div class="error-detail">${escapeHtml(error.message)}</div><button class="btn btn-primary btn-sm mt-3" onclick="location.reload()">Muat Ulang</button></div>`;
  document.getElementById('prayer-view').innerHTML = errorHtml;
  document.getElementById('prayer-view').style.display = 'block';
  document.getElementById('loading-view').style.display = 'none';
  }

  loadDefaultLocation();
  })();
</script>
@endpush