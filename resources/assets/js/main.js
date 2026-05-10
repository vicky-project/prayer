// main.js for Prayer Times - ONLY browser geolocation with proper error handling
(function(window, document, undefined) {
  'use strict';

  const Core = window.PrayerAppCore;
  const UI = window.PrayerAppUI;
  if (!Core || !UI) {
    console.error('Core atau UI tidak tersedia');
    return;
  }

  let isFetchingPrayer = false;
  let isGeolocating = false;
  // Variabel untuk menyimpan AbortController
  let searchAbortController = null;

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

  // ----- API calls -----
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
      if (err.status !== 401) {
        Core.showToast('Gagal memuat pengaturan: ' + err.message, 'danger');
      }
      Core.setState({
        settings: {}
      });
      return {};
    }
  }

  async function fetchPrayerTimes(lat, lon, city) {
    if (isFetchingPrayer) {
      return;
    }
    isFetchingPrayer = true;

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
      Core.setState({
        loading: false, error: err.message
      });
      Core.showToast(err.message, 'danger');
    } finally {
      Core.hideLoading();
      isFetchingPrayer = false;
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
        localStorage.removeItem('prayer_settings_cache');
        await fetchSettings();
        return true;
      }
      throw new Error(res.message || 'Gagal menyimpan lokasi');
    } catch (err) {
      Core.showToast('Gagal menyimpan lokasi otomatis: ' + err.message, 'warning');
      return false;
    }
  }

  // ----- ONLY BROWSER GEOLOCATION dengan error handling user-friendly -----
  function getBrowserLocation(timeoutMs = 15000) {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('🌐 Browser ini tidak mendukung Geolocation. Silakan atur lokasi manual di pengaturan.'));
        return;
      }
      const timeoutId = setTimeout(() => {
        reject(new Error('⏱️ Waktu habis saat mengambil lokasi. Pastikan GPS aktif, izinkan akses lokasi, dan coba lagi.'));
      }, timeoutMs);
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          clearTimeout(timeoutId);
          resolve( {
            lat: pos.coords.latitude, lon: pos.coords.longitude
          });
        },
        (err) => {
          clearTimeout(timeoutId);
          let errorMsg = '';
          switch (err.code) {
            case err.PERMISSION_DENIED:
              errorMsg = '🚫 Izin lokasi ditolak. Silakan aktifkan izin lokasi di browser/Telegram, atau atur lokasi manual di pengaturan.';
              break;
            case err.POSITION_UNAVAILABLE:
              errorMsg = '📡 Lokasi tidak tersedia. Pastikan GPS aktif dan sinyal kuat.';
              break;
            case err.TIMEOUT:
              errorMsg = '⏱️ Waktu habis saat mengambil lokasi. Coba lagi nanti.';
              break;
            default:
              errorMsg = '❌ Gagal mengambil lokasi: ' + err.message;
            }
            reject(new Error(errorMsg));
          }
        );
      });
    }

    // ----- Load from geolocation (tanpa fallback ke Telegram) -----
    async function loadFromGeolocation() {
      if (isGeolocating) {
        return;
      }
      isGeolocating = true;
      const TIMEOUT = 15000;
      try {
        Core.showLoading('Meminta lokasi (browser)... (maks 15 detik)');
        const loc = await getBrowserLocation(TIMEOUT);
        await saveLocationToSettings(loc.lat, loc.lon);
        await fetchPrayerTimes(loc.lat, loc.lon);
        Core.setState({
          currentView: 'prayer'
        });
      } catch (err) {
        console.error('Geolocation error:', err);
        Core.setState({
          loading: false, error: err.message
        });
        Core.showToast(err.message, 'danger');
        // Jika gagal, arahkan ke halaman settings agar user bisa input manual
        Core.setState({
          currentView: 'settings', error: null
        });
      } finally {
        Core.hideLoading();
        isGeolocating = false;
      }
    }

    // ----- Load default location from settings -----
    async function loadDefaultLocation() {
      if (isGeolocating) return;
      Core.showLoading('Memuat pengaturan...');
      try {
        const settings = await fetchSettings();
        const hasDefaultLoc = settings.default_location &&
        (settings.default_location.city ||
          (settings.default_location.latitude && settings.default_location.longitude));
        if (hasDefaultLoc) {
          const {
            latitude,
            longitude,
            city
          } = settings.default_location;
          if (city) {
            await fetchPrayerTimes(null, null, city);
          } else {
            await fetchPrayerTimes(latitude, longitude);
          }
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

    // ----- Save settings dari form -----
    async function saveSettings(formData) {
      try {
        Core.showLoading('Menyimpan pengaturan...');
        const payload = {
          city: formData.city || undefined,
          latitude: formData.latitude !== undefined ? formData.latitude: undefined,
          longitude: formData.longitude !== undefined ? formData.longitude: undefined,
          notifications_enabled: formData.notifications_enabled === true,
          reminder_minutes: formData.reminder_minutes !== undefined ? formData.reminder_minutes: 0
        };
        const res = await fetchWithTimeout(Core.api.post('/api/prayer/settings', payload), 10000);
        if (res.success) {
          Core.showToast('Pengaturan disimpan');
          localStorage.removeItem('prayer_settings_cache');
          await fetchSettings();
          const newSettings = Core.getState().settings;
          if (newSettings.default_location && Object.keys(newSettings.default_location).length > 0 &&
            (newSettings.default_location.city || (newSettings.default_location.latitude && newSettings.default_location.longitude))) {
            if (newSettings.default_location.city) {
              await fetchPrayerTimes(null, null, newSettings.default_location.city);
            } else {
              await fetchPrayerTimes(newSettings.default_location.latitude, newSettings.default_location.longitude);
            }
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
      if (state.loading || isFetchingPrayer) return;
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

    // ======================== RANGE PRAYER TIMES ========================
    async function fetchRangePrayerTimes(days = 7) {
      if (isFetchingPrayer) return;
      isFetchingPrayer = true;
      Core.showLoading(`Memuat jadwal ${days} hari...`);
      try {
        const state = Core.getState();
        let body = {
          days: days
        };

        // Prioritaskan dari data prayer yang sedang ditampilkan
        if (state.prayer && state.prayer.city) {
          body.city = state.prayer.city;
        } else if (state.prayer && state.prayer.latitude && state.prayer.longitude) {
          body.latitude = state.prayer.latitude;
          body.longitude = state.prayer.longitude;
        } else {
          // Fallback ke settings default location
          const settings = state.settings;
          if (settings && settings.default_location) {
            if (settings.default_location.city) {
              body.city = settings.default_location.city;
            } else if (settings.default_location.latitude && settings.default_location.longitude) {
              body.latitude = settings.default_location.latitude;
              body.longitude = settings.default_location.longitude;
            }
          }
        }

        if (!body.city && !body.latitude) {
          throw new Error('Lokasi tidak diketahui. Silakan set lokasi di pengaturan.');
        }

        const res = await Core.api.post('/api/prayer/times/range', body);
        if (res.success && res.data && res.data.length) {
          // Panggil UI renderRangeTableView (harus sudah didefinisikan di page.js)
          UI.renderRangeTableView(res.data, days);
        } else {
          throw new Error(res.message || 'Data jadwal tidak tersedia');
        }
      } catch (err) {
        Core.showToast(err.message, 'danger');
      } finally {
        Core.hideLoading();
        isFetchingPrayer = false;
      }
    }


    // Fungsi untuk melakukan pencarian
    async function performCitySearch(city) {
      const resultArea = document.getElementById('searchResultArea');
      const loadingSpinner = document.getElementById('searchLoadingSpinner');
      if (!resultArea || !loadingSpinner) return;

      // Batalkan request sebelumnya jika ada
      if (searchAbortController) {
        searchAbortController.abort();
      }
      searchAbortController = new AbortController();

      try {
        resultArea.innerHTML = '';
        loadingSpinner.classList.remove('d-none');

        // Gunakan fetch langsung dengan token (karena Core.api tidak support signal)
        const token = localStorage.getItem('telegram_token') || tgApp.getToken();
        const response = await fetch(BASE_URL + '/api/prayer/times', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            city: city,
            ignore_default: true
          }),
          signal: searchAbortController.signal
        });

        if (!response.ok) throw new Error('Network error');
        const data = await response.json();
        if (data.success) {
          displaySearchResult(data.data);
        } else {
          throw new Error(data.message || 'Gagal memuat jadwal');
        }
      } catch (err) {
        if (err.name === 'AbortError') {
          Core.sho('Pencarian dibatalkan', 'warning');
        } else {
          resultArea.innerHTML = `<div class="alert alert-danger">${Core.escapeHtml(err.message)}</div>`;
        }
      } finally {
        loadingSpinner.classList.add('d-none');
        searchAbortController = null;
      }
    }

    // Fungsi menampilkan hasil pencarian di modal
    function displaySearchResult(prayerData) {
      const resultArea = document.getElementById('searchResultArea');
      if (!resultArea) return;

      const jadwal = prayerData.jadwal;
      const timezone = prayerData.timezone || 'Asia/Jakarta';
      const prayerOrder = ['imsak',
        'subuh',
        'terbit',
        'dhuha',
        'dzuhur',
        'ashar',
        'maghrib',
        'isya'];

      // Format waktu saat ini di timezone kota
      const now = new Date();
      const timeFormatter = new Intl.DateTimeFormat('id-ID', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      });
      const currentTimeStr = timeFormatter.format(now);

      // Hitung waktu sekarang dalam menit untuk menentukan shalat berikutnya
      const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: timezone,
        hour: 'numeric',
        minute: 'numeric',
        hour12: false
      }).formatToParts(now);
      let hours = 0,
      minutes = 0;
      for (let part of parts) {
        if (part.type === 'hour') hours = parseInt(part.value);
        if (part.type === 'minute') minutes = parseInt(part.value);
      }
      const nowMinutes = hours * 60 + minutes;

      // Cari shalat berikutnya
      let nextPrayer = null;
      for (let name of prayerOrder) {
        if (jadwal[name]) {
          const [h,
            m] = jadwal[name].split(':').map(Number);
          const prayerMinutes = h * 60 + m;
          if (prayerMinutes > nowMinutes) {
            nextPrayer = name;
            break;
          }
        }
      }

      // Bangun HTML
      let html = `
      <div class="card mt-2">
      <div class="card-header">
      <div class="fw-bold">${Core.escapeHtml(prayerData.city)}</div>
      <div class="small">${Core.escapeHtml(prayerData.date)} (${Core.escapeHtml(prayerData.hijri)})</div>
      <div class="small mt-1">🕐 Waktu setempat: ${Core.escapeHtml(currentTimeStr)}</div>
      </div>
      <div class="card-body p-0">
      <table class="table table-sm mb-0">
      <tbody>
      `;
      for (let name of prayerOrder) {
        if (jadwal[name]) {
          const isActive = (name === nextPrayer);
          const rowClass = isActive ? 'class="table-active"': '';
          html += `<tr ${rowClass}><th>${Core.getPrayerName(name)}</th><td class="text-end">${Core.escapeHtml(jadwal[name])}</td></tr>`;
        }
      }
      html += `
      </tbody>
      </table>
      </div>
      </div>
      `;
      resultArea.innerHTML = html;
    }

    // Fungsi untuk menampilkan modal dan menginisialisasi autocomplete
    function showSearchCityModal() {
      const modalEl = document.getElementById('searchPrayerModal');
      if (!modalEl) return;

      // Inisialisasi modal dengan backdrop static (tidak bisa tutup klik luar)
      const modal = new bootstrap.Modal(modalEl, {
        backdrop: 'static', keyboard: false
      });
      modal.show();

      // Bersihkan konten sebelumnya
      const cityInput = document.getElementById('searchCityInput');
      const resultArea = document.getElementById('searchResultArea');
      const suggestionsDatalist = document.getElementById('searchCitySuggestions');
      if (cityInput) cityInput.value = '';
      if (resultArea) resultArea.innerHTML = '';
      if (suggestionsDatalist) suggestionsDatalist.innerHTML = '';

      // Setup autocomplete
      if (cityInput && suggestionsDatalist) {
        let debounceTimer;
        const handleInput = (e) => {
          clearTimeout(debounceTimer);
          const keyword = e.target.value.trim();
          if (keyword.length < 2) {
            suggestionsDatalist.innerHTML = '';
            return;
          }
          debounceTimer = setTimeout(async () => {
            try {
              const res = await Core.api.get(`/api/prayer/cities/search?q=${encodeURIComponent(keyword)}`);
              if (res.success && res.data) {
                suggestionsDatalist.innerHTML = '';
                res.data.forEach(cityItem => {
                  const option = document.createElement('option');
                  option.value = cityItem.value;
                  option.textContent = cityItem.label;
                  suggestionsDatalist.appendChild(option);
                });
              }
            } catch (err) {
              console.warn('Autocomplete gagal:', err);
            }
          },
            300);
        };
        cityInput.addEventListener('input',
          handleInput);
        // Simpan listener untuk dibersihkan saat modal ditutup (opsional)
        cityInput._handleInput = handleInput;

        // Saat user memilih dari datalist (change event), lakukan pencarian otomatis
        cityInput.addEventListener('change',
          async (e) => {
            const selectedCity = e.target.value;
            if (selectedCity && selectedCity.length > 0) {
              await performCitySearch(selectedCity);
            }
          });
      }

      // Reset saat modal ditutup (bersihkan state)
      modalEl.addEventListener('hidden.bs.modal',
        () => {
          // Batalkan request jika masih berjalan
          if (searchAbortController) {
            searchAbortController.abort();
            searchAbortController = null;
          }
          // Hapus event listener input untuk mencegah memory leak
          if (cityInput && cityInput._handleInput) {
            cityInput.removeEventListener('input', cityInput._handleInput);
            delete cityInput._handleInput;
          }
          // Bersihkan datalist dan hasil
          if (suggestionsDatalist) suggestionsDatalist.innerHTML = '';
          if (resultArea) resultArea.innerHTML = '';
        },
        {
          once: true
        });
    }

    // ----- Event Delegation (semua lokasi menggunakan browser) -----
    function setupEventDelegation() {
      document.body.addEventListener('click',
        (e) => {
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
              if (sett.default_location.city) {
                fetchPrayerTimes(null, null, sett.default_location.city);
              } else {
                fetchPrayerTimes(sett.default_location.latitude, sett.default_location.longitude);
              }
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
                const loc = await getBrowserLocation(10000);
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
                if (statusSpan) statusSpan.innerText = 'Gagal: ' + err.message;
                Core.showToast(err.message, 'danger');
              }
            })();
          } else if (target.id === 'weeklyViewBtn' || target.closest('#weeklyViewBtn')) {
            fetchRangePrayerTimes(7); // default 7 hari
          } else if (target.id === 'searchCityBtn' || target.closest('#searchCityBtn')) {
            showSearchCityModal();
          }
        });

      document.body.addEventListener('submit',
        (e) => {
          if (e.target && e.target.id === 'settingsForm') {
            e.preventDefault();
            const cityEl = document.getElementById('city');
            const latEl = document.getElementById('latitude');
            const lonEl = document.getElementById('longitude');
            const notifyEl = document.getElementById('notifications_enabled');
            const reminderSelect = document.getElementById('reminder_minutes');
            if (!cityEl || !latEl || !lonEl || !notifyEl) return;
            const formData = {
              city: cityEl.value || undefined,
              latitude: latEl.value ? parseFloat(latEl.value): undefined,
              longitude: lonEl.value ? parseFloat(lonEl.value): undefined,
              notifications_enabled: notifyEl.checked,
              reminder_minutes: reminderSelect ? parseInt(reminderSelect.value): 0
            };
            saveSettings(formData);
          }
        });

      document.body.addEventListener('change',
        (e) => {
          if (e.target.id === 'rangeDaysSelect') {
            const days = parseInt(e.target.value);
            if (typeof fetchRangePrayerTimes === 'function') {
              fetchRangePrayerTimes(days);
            }
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
      const prayerDiv = document.getElementById('prayer-view');
      const settingsDiv = document.getElementById('settings-view');
      if (!prayerDiv || !settingsDiv) {
        console.error('Required elements missing');
        return;
      }
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