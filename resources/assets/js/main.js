// main.js for Prayer Times - FIXED (loading state issue resolved)
(function(window, document, undefined) {
  'use strict';

  const Core = window.PrayerAppCore;
  const UI = window.PrayerAppUI;
  if (!Core || !UI) {
    console.error('Core atau UI tidak tersedia');
    return;
  }

  // Helper fetch dengan timeout
  async function fetchWithTimeout(promise, timeoutMs = 15000) {
    let timeoutId;
    const timeoutPromise = new Promise((_, reject) => {
      timeoutId = setTimeout(() => reject(new Error(`Request timeout after ${timeoutMs}ms`)), timeoutMs);
    });
    try {
      const result = await Promise.race([promise, timeoutPromise]);
      clearTimeout(timeoutId);
      return result;
    } catch (err) {
      clearTimeout(timeoutId);
      throw err;
    }
  }

  // API calls
  async function fetchSettings() {
    const cached = localStorage.getItem('prayer_settings_cache');
    if (cached) {
      try {
        const {
          data,
          timestamp
        } = JSON.parse(cached);
        if (Date.now() - timestamp < 5 * 60 * 1000) {
          Core.setState({
            settings: data
          });
          return data;
        }
      } catch(e) {}
    }

    try {
      const res = await fetchWithTimeout(Core.api.get('/api/prayer/settings'), 10000);
      if (res.success) {
        Core.setState({
          settings: res.data
        });
        localStorage.setItem('prayer_settings_cache', JSON.stringify({
          data: res.data,
          timestamp: Date.now()
        }));
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
      const res = await fetchWithTimeout(Core.api.post('/api/prayer/times', body), 15000);
      if (!res.success) throw new Error(res.message || 'Gagal memuat jadwal');
      Core.setState({
        prayer: res.data,
        cityTimezoneOffset: res.data.timezone_offset || null,
        loading: false,
        error: null
      });
    } catch (err) {
      console.log(err)
      Core.setState({
        loading: false, error: err.message
      });
      Core.showToast(err.message, 'danger');
    } finally {
      Core.hideLoading();
    }
  }

  async function saveLocationToSettings(lat, lon) {
    try {
      const currentSettings = Core.getState().settings || {};
      const res = await fetchWithTimeout(Core.api.post('/api/prayer/settings', {
        city: undefined,
        latitude: lat,
        longitude: lon,
        notifications_enabled: currentSettings.notifications_prayer_enabled === true
      }), 10000);
      if (res.success) {
        console.log('Location auto-saved:', {
          lat, lon
        });
        localStorage.removeItem('prayer_settings_cache');
        await fetchSettings();
        return true;
      }
      throw new Error(res.message || 'Gagal menyimpan lokasi');
    } catch (err) {
      console.warn('Auto-save location failed:', err);
      Core.showToast('Gagal menyimpan lokasi otomatis: ' + err.message, 'warning');
      return false;
    }
  }

  // Geolocation
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
          clearTimeout(timeoutId);
          resolve( {
            lat: pos.coords.latitude, lon: pos.coords.longitude
          });
        },
        (err) => {
          clearTimeout(timeoutId);
          reject(new Error(err.message));
        }
      );
    });
  }

  let isGeolocating = false;
  async function loadFromGeolocation() {
    if (isGeolocating) {
      console.log('Geolocation already in progress, skipping...');
      return;
    }
    isGeolocating = true;
    const TIMEOUT = 15000;
    try {
      Core.showLoading('Meminta lokasi (maks 15 detik)...');
      let loc;
      try {
        loc = await getTelegramLocation(TIMEOUT);
      } catch (e) {
        console.warn('Telegram location gagal, fallback ke browser', e);
        Core.showToast('Telegram: ' + e.message, 'warning');
        loc = await getBrowserLocation(TIMEOUT);
      }
      await saveLocationToSettings(loc.lat, loc.lon);
      await fetchPrayerTimes(loc.lat, loc.lon);
      Core.setState({
        currentView: 'prayer'
      });
    } catch (err) {
      console.error('loadFromGeolocation error:', err);
      Core.setState({
        loading: false, error: err.message
      });
      Core.showToast(err.message, 'danger');
      Core.setState({
        currentView: 'settings'
      });
    } finally {
      Core.hideLoading();
      isGeolocating = false;
    }
  }

  // PERBAIKAN: Hapus pengecekan state.loading (karena inisialisasi awal loading=true)
  async function loadDefaultLocation() {
    if (isGeolocating) return; // hanya cegah multiple geolocation

    try {
      Core.showLoading('Memuat pengaturan...');
      const settings = await fetchSettings();
      if (settings.default_location && typeof settings.default_location.latitude === 'number' && typeof settings.default_location.longitude === 'number') {
        const {
          latitude,
          longitude
        } = settings.default_location;
        await fetchPrayerTimes(latitude, longitude);
      } else if (settings.city) {
        await fetchPrayerTimes(null, null, settings.city);
      } else if (settings.latitude && settings.longitude) {
        await fetchPrayerTimes(settings.latitude, settings.longitude);
      } else {
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
        currentView: 'settings'
      });
    } finally {
      Core.hideLoading();
    }
  }

  async function saveSettings(formData) {
    try {
      Core.showLoading('Menyimpan pengaturan...');
      const payload = {
        city: formData.city || undefined,
        latitude: formData.latitude !== undefined ? formData.latitude: undefined,
        longitude: formData.longitude !== undefined ? formData.longitude: undefined,
        notifications_enabled: formData.notifications_enabled === true
      };
      const res = await fetchWithTimeout(Core.api.post('/api/prayer/settings', payload), 10000);
      if (res.success) {
        Core.showToast('Pengaturan disimpan');
        localStorage.removeItem('prayer_settings_cache');
        await fetchSettings();
        const newSettings = Core.getState().settings;
        if (newSettings.default_location) {
          await fetchPrayerTimes(newSettings.default_location.latitude, newSettings.default_location.longitude);
        } else if (newSettings.city) {
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
    if (state.loading) return;
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

  // Event delegation (sama seperti sebelumnya, disingkat untuk ruang)
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
        if (sett.default_location) {
          fetchPrayerTimes(sett.default_location.latitude, sett.default_location.longitude);
        } else if (sett.city) {
          fetchPrayerTimes(null, null, sett.city);
        } else if (sett.latitude && sett.longitude) {
          fetchPrayerTimes(sett.latitude, sett.longitude);
        }
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