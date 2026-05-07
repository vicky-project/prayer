// main.js for Prayer Times - Auto-save location after geolocation
(function(window, document, undefined) {
  'use strict';

  const Core = window.PrayerAppCore;
  const UI = window.PrayerAppUI;
  if (!Core || !UI) {
    console.error('Core atau UI tidak tersedia');
    return;
  }

  // ----- API calls -----
  async function fetchSettings() {
    try {
      const res = await Core.api.get('/api/prayer/settings');
      if (res.success) {
        Core.setState({
          settings: res.data
        });
        return res.data;
      }
      throw new Error(res.message || 'Gagal memuat pengaturan');
    } catch (err) {
      console.error('fetchSettings error:', err);
      Core.showToast('Gagal memuat pengaturan: ' + err.message, 'danger');
      Core.setState({
        settings: {}
      });
      return {};
    }
  }

  async function fetchPrayerTimes(lat, lon, city) {
    try {
      Core.showLoading('Memuat jadwal shalat...');
      const body = {};
      if (city) {
        body.city = city;
      } else if (typeof lat === 'number' && typeof lon === 'number') {
        body.lat = lat;
        body.lon = lon;
      } else {
        throw new Error('Tidak ada lokasi yang diberikan');
      }
      const res = await Core.api.post('/api/prayer/times', body);
      if (!res.success) throw new Error(res.message || 'Gagal memuat jadwal');
      Core.setState({
        prayer: res.data,
        cityTimezoneOffset: res.data.timezone_offset || null,
        loading: false,
        error: null
      });
    } catch (err) {
      Core.setState({
        loading: false, error: err.message
      });
      Core.showToast(err.message, 'danger');
    } finally {
      Core.hideLoading();
    }
  }

  // ----- Fungsi untuk menyimpan lokasi ke settings (otomatis) -----
  async function saveLocationToSettings(lat, lon) {
    try {
      const currentSettings = Core.getState().settings || {};
      const res = await Core.api.post('/api/prayer/settings', {
        city: undefined, // kosongkan city
        latitude: lat,
        longitude: lon,
        notifications_enabled: currentSettings.notifications_enabled === true
      });
      if (res.success) {
        console.log('Location automatically saved to settings:', {
          lat, lon
        });
        await fetchSettings(); // refresh state settings
        return true;
      } else {
        throw new Error(res.message || 'Gagal menyimpan lokasi');
      }
    } catch (err) {
      console.warn('Auto-save location failed:', err);
      Core.showToast('Gagal menyimpan lokasi otomatis: ' + err.message, 'warning');
      return false;
    }
  }

  // ----- Geolocation with timeout -----
  function getTelegramLocation(timeoutMs = 15000) {
    return new Promise((resolve, reject) => {
      const tg = window.Telegram?.WebApp;
      if (!tg || !tg.LocationManager) {
        reject(new Error('Telegram LocationManager tidak tersedia'));
        return;
      }
      const timeoutId = setTimeout(() => reject(new Error(`Telegram location timeout ${timeoutMs}ms`)), timeoutMs);
      tg.LocationManager.init(() => {
        tg.LocationManager.getLocation((location) => {
          clearTimeout(timeoutId);
          if (location && location.latitude && location.longitude) {
            resolve( {
              lat: location.latitude, lon: location.longitude
            });
          } else {
            reject(new Error('Akses lokasi ditolak'));
          }
        });
      });
    });
  }

  function getBrowserLocation(timeoutMs = 15000) {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Geolocation tidak didukung'));
        return;
      }
      const timeoutId = setTimeout(() => reject(new Error(`Browser geolocation timeout ${timeoutMs}ms`)), timeoutMs);
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          clearTimeout(timeoutId); resolve( {
            lat: pos.coords.latitude, lon: pos.coords.longitude
          });
        },
        (err) => {
          clearTimeout(timeoutId); reject(new Error(err.message));
        }
      );
    });
  }

  // ----- Load from geolocation (auto-save before fetch) -----
  async function loadFromGeolocation() {
    const TIMEOUT = 15000;
    try {
      Core.showLoading('Meminta lokasi (maks 15 detik)...');
      let loc;
      try {
        loc = await getTelegramLocation(TIMEOUT);
      } catch (e) {
        console.warn('Telegram location gagal, fallback ke browser',
          e);
        Core.showToast('Telegram: ' + e.message,
          'warning');
        loc = await getBrowserLocation(TIMEOUT);
      }
      // Simpan lokasi ke settings terlebih dahulu
      await saveLocationToSettings(loc.lat,
        loc.lon);
      // Kemudian ambil jadwal shalat
      await fetchPrayerTimes(loc.lat,
        loc.lon);
      Core.setState({
        currentView: 'prayer'
      });
    } catch (err) {
      console.error('loadFromGeolocation error:',
        err);
      Core.setState({
        loading: false,
        error: err.message
      });
      Core.showToast(err.message,
        'danger');
      Core.setState({
        currentView: 'settings'
      });
    } finally {
      Core.hideLoading();
    }
  }

  async function loadDefaultLocation() {
    try {
      Core.showLoading('Memuat pengaturan...');
      const settings = await fetchSettings();
      if (settings.city) {
        await fetchPrayerTimes(null, null, settings.city);
        // Jika ada city, kita tidak menyimpan ulang (biarkan city sebagai default)
      } else if (settings.latitude && settings.longitude) {
        await fetchPrayerTimes(settings.latitude, settings.longitude);
        // Tidak perlu simpan ulang karena sudah ada di settings
      } else {
        // Tidak ada pengaturan, coba geolocation dan auto-save
        await loadFromGeolocation();
      }
      Core.setState({
        currentView: 'prayer'
      });
    } catch (err) {
      Core.setState({
        loading: false, error: err.message
      });
      Core.showToast(err.message, 'danger');
      Core.setState({
        currentView: 'settings', settings: Core.getState().settings || {}
      });
    } finally {
      Core.hideLoading();
    }
  }

  async function saveSettings(formData) {
    try {
      Core.showLoading('Menyimpan pengaturan...');
      const res = await Core.api.post('/api/prayer/settings', formData);
      if (res.success) {
        Core.showToast('Pengaturan disimpan');
        await fetchSettings();
        const newSettings = Core.getState().settings;
        if (newSettings.city) {
          await fetchPrayerTimes(null, null, newSettings.city);
        } else if (newSettings.latitude && newSettings.longitude) {
          await fetchPrayerTimes(newSettings.latitude, newSettings.longitude);
        } else {
          await loadFromGeolocation();
        }
        Core.setState({
          currentView: 'prayer'
        });
      } else {
        throw new Error(res.message || 'Gagal menyimpan');
      }
    } catch (err) {
      Core.showToast('Error: ' + err.message, 'danger');
    } finally {
      Core.hideLoading();
    }
  }

  async function refreshPrayer() {
    const state = Core.getState();
    if (state.prayer) {
      if (state.prayer.city) {
        await fetchPrayerTimes(null, null, state.prayer.city);
      } else if (state.prayer.latitude && state.prayer.longitude) {
        await fetchPrayerTimes(state.prayer.latitude, state.prayer.longitude);
      } else if (state.prayer.lat && state.prayer.lon) {
        await fetchPrayerTimes(state.prayer.lat, state.prayer.lon);
      } else {
        await loadDefaultLocation();
      }
    } else {
      await loadDefaultLocation();
    }
  }

  // ----- Event Delegation (dengan pengecekan elemen) -----
  function setupEventDelegation() {
    document.body.addEventListener('click', (e) => {
      const target = e.target;
      if (target.id === 'settingsBtn' || target.closest('#settingsBtn')) {
        Core.setState({
          currentView: 'settings'
        });
        UI.renderSettingsView(Core.getState());
      } else if (target.id === 'refreshPrayerBtn' || target.closest('#refreshPrayerBtn')) {
        refreshPrayer();
      } else if (target.id === 'backToPrayerBtn' || target.closest('#backToPrayerBtn')) {
        Core.setState({
          currentView: 'prayer'
        });
        UI.renderPrayerView(Core.getState());
      } else if (target.id === 'useDefaultLocationBtn' || target.closest('#useDefaultLocationBtn')) {
        const sett = Core.getState().settings;
        if (sett.city) fetchPrayerTimes(null, null, sett.city);
        else if (sett.latitude && sett.longitude) fetchPrayerTimes(sett.latitude, sett.longitude);
      } else if (target.id === 'autoLocationBtn' || target.closest('#autoLocationBtn')) {
        (async () => {
          const statusSpan = document.getElementById('locationStatus');
          if (statusSpan) statusSpan.innerText = 'Meminta lokasi...';
          try {
            const loc = await getTelegramLocation(10000);
            const latInput = document.getElementById('latitude');
            const lonInput = document.getElementById('longitude');
            const cityInput = document.getElementById('city');
            if (latInput && lonInput && cityInput) {
              latInput.value = loc.lat;
              lonInput.value = loc.lon;
              cityInput.value = '';
              // Opsional: langsung simpan setelah ambil lokasi? Lebih baik user klik simpan.
              // Untuk konsisten, kita tidak auto-save di sini.
            }
            if (statusSpan) statusSpan.innerText = 'Lokasi berhasil diambil.';
          } catch (err) {
            try {
              const locBrowser = await getBrowserLocation(10000);
              const latInput = document.getElementById('latitude');
              const lonInput = document.getElementById('longitude');
              const cityInput = document.getElementById('city');
              if (latInput && lonInput && cityInput) {
                latInput.value = locBrowser.lat;
                lonInput.value = locBrowser.lon;
                cityInput.value = '';
              }
              if (statusSpan) statusSpan.innerText = 'Lokasi berhasil diambil (browser).';
            } catch (err2) {
              if (statusSpan) statusSpan.innerText = 'Gagal mengambil lokasi.';
              Core.showToast(err2.message, 'danger');
            }
          }
        })();
      }
    });

    document.body.addEventListener('submit', (e) => {
      if (e.target && e.target.id === 'settingsForm') {
        e.preventDefault();
        const cityEl = document.getElementById('city');
        const latEl = document.getElementById('latitude');
        const lonEl = document.getElementById('longitude');
        const notifyEl = document.getElementById('notifications_enabled');
        if (!cityEl || !latEl || !lonEl || !notifyEl) return;
        const formData = {
          city: cityEl.value || undefined,
          latitude: latEl.value ? parseFloat(latEl.value): undefined,
          longitude: lonEl.value ? parseFloat(lonEl.value): undefined,
          notifications_enabled: notifyEl.checked
        };
        saveSettings(formData);
      }
    });
  }

  // ----- Subscribe state change -----
  function onStateChange(state) {
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!prayerDiv || !settingsDiv) return;
    if (state.currentView === 'prayer') {
      UI.renderPrayerView(state);
    } else if (state.currentView === 'settings') {
      UI.renderSettingsView(state);
    }
  }

  // ----- SAFE INITIALIZATION -----
  function init() {
    const loadingDiv = document.getElementById('loading-view');
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!loadingDiv || !prayerDiv || !settingsDiv) {
      console.error('Required elements missing');
      return;
    }
    loadingDiv.style.display = 'flex';
    prayerDiv.style.display = 'none';
    settingsDiv.style.display = 'none';

    Core.subscribe(onStateChange);
    setupEventDelegation();
    loadDefaultLocation();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);