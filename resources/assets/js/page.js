// page.js for Prayer Times
(function(window, document, undefined) {
  'use strict';

  const Core = window.PrayerAppCore;
  if (!Core) {
    console.error('PrayerAppCore tidak tersedia');
    return;
  }

  // Render halaman utama jadwal shalat
  function renderPrayerView(state) {
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!prayerDiv || !settingsDiv) return;

    if (state.loading) {
      prayerDiv.style.display = 'none';
      settingsDiv.style.display = 'none';
      return;
    }

    if (state.error) {
      prayerDiv.innerHTML = '<div class="error-container">' +
      '<div class="error-message"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal memuat data</div>' +
      '<div class="error-detail">' + Core.escapeHtml(state.error) + '</div>' +
      '<button class="btn btn-primary btn-sm mt-3" id="retryBtn">Muat Ulang</button></div>';
      prayerDiv.style.display = 'block';
      settingsDiv.style.display = 'none';
      const retryBtn = document.getElementById('retryBtn');
      if (retryBtn) retryBtn.onclick = () => location.reload();
      return;
    }

    if (!state.prayer) {
      prayerDiv.innerHTML = '<div class="alert alert-warning">Data jadwal tidak tersedia</div>';
      prayerDiv.style.display = 'block';
      settingsDiv.style.display = 'none';
      return;
    }

    const jadwal = state.prayer.jadwal;
    const prayerOrder = ['imsak',
      'subuh',
      'terbit',
      'dhuha',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const now = Core.getCurrentCityTime();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    let currentPrayer = null;
    for (let i = 0; i < prayerOrder.length; i++) {
      const name = prayerOrder[i];
      const minutes = Core.timeToMinutes(jadwal[name]);
      if (minutes <= nowMinutes) currentPrayer = name;
      else break;
    }

    let rows = '';
    for (let name of prayerOrder) {
      const time = jadwal[name] || '-';
      const isCurrent = (name === currentPrayer);
      const rowClass = isCurrent ? 'table-active': '';
      rows += `<tr class="${rowClass}"><th scope="row">${Core.getPrayerName(name)}</th><td class="text-end">${time} </td></tr>`;
    }

    let extraButton = '';
    const sett = state.settings || {};
    if (sett.default_location || sett.city || (sett.latitude && sett.longitude)) {
      extraButton = `<button id="useDefaultLocationBtn" class="btn btn-outline-secondary w-100 mt-2"><i class="bi bi-arrow-repeat me-2"></i>Kembali ke Lokasi Default</button>`;
    }

    const html = `
    <div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><i class="bi bi-moon-stars me-2"></i>Jadwal Shalat</h4>
    <div class="d-flex gap-1">
    <button id="searchCityBtn" class="btn btn-sm btn-outline-light" title="Cari Jadwal Kota Lain"><i class="bi bi-search"></i></button>
    <button id="settingsBtn" class="btn btn-sm btn-outline-light" title="Pengaturan"><i class="bi bi-gear-fill"></i></button>
    <button id="refreshPrayerBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-repeat"></i></button>
    <button id="weeklyViewBtn" class="btn btn-sm btn-outline-light" title="Jadwal Mingguan"><i class="bi bi-calendar-week"></i></button>
    </div>
    </div>
    <div class="card-body">
    <div class="text-center mb-2">
    <i class="bi bi-geo-alt-fill text-primary"></i>
    <span class="ms-2">${Core.escapeHtml(state.prayer.city || (state.prayer.latitude + ', ' + state.prayer.longitude))}</span>
    </div>
    <div class="text-center small text-muted mb-2">📅 ${state.prayer.date} | ${state.prayer.hijri}</div>
    <div id="countdown"></div>
    <div class="table-responsive"><table id="main-table" class="table table-hover"><tbody>${rows}</tbody></table></div>
    <div class="text-muted small text-center mt-2"><i class="bi bi-info-circle me-1"></i>Waktu berdasarkan lokasi terdekat</div>
    ${extraButton}
    </div>
    </div>
    `;

    prayerDiv.innerHTML = html;
    prayerDiv.style.display = 'block';
    settingsDiv.style.display = 'none';

    startCountdown(jadwal);
  }

  function startCountdown(jadwal) {
    Core.stopCountdown();
    if (!jadwal) return;

    const order = ['imsak',
      'subuh',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const now = Core.getCurrentCityTime();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    let nextPrayer = null;
    let nextMinutes = null;
    for (let name of order) {
      if (jadwal[name]) {
        const minutes = Core.timeToMinutes(jadwal[name]);
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

    let active = true;
    let intervalId = null;

    function updateDisplay() {
      if (!active) return;
      const hours = Math.floor(diffSeconds / 3600);
      const minutes = Math.floor((diffSeconds % 3600) / 60);
      const seconds = diffSeconds % 60;
      countdownDiv.innerHTML = `
      <div class="text-center">
      <div class="next-label">Menuju ${Core.getPrayerName(nextPrayer)}</div>
      <div class="timer">${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}</div>
      </div>
      `;
      if (diffSeconds <= 0) {
        active = false;
        if (intervalId) clearInterval(intervalId);
        Core.stopCountdown();
        startCountdown(jadwal);
        return;
      }
      diffSeconds--;
    }

    updateDisplay();
    intervalId = setInterval(updateDisplay, 1000);
    Core.setCountdownInterval(intervalId);
  }

  // Render halaman pengaturan
  function renderSettingsView(state) {
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!prayerDiv || !settingsDiv) return;

    const sett = state.settings || {};
    let lat = '',
    lon = '';
    if (sett.default_location && typeof sett.default_location.latitude === 'number') {
      lat = String(sett.default_location.latitude);
      lon = String(sett.default_location.longitude);
    } else if (sett.latitude !== undefined && sett.longitude !== undefined) {
      lat = String(sett.latitude);
      lon = String(sett.longitude);
    }

    let city = '';
    if (sett.default_location && sett.default_location.city) {
      city = String(sett.default_location.city);
    } else if (sett.city && sett.city !== null) {
      city = String(sett.city);
    }

    const notifications = sett.notifications_enabled === true;
    const reminderMinutes = sett.reminder_minutes !== undefined ? sett.reminder_minutes: 0;
    const datalistId = 'city-suggestions';

    const html = `
    <div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Pengaturan</h4>
    <button id="backToPrayerBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Kembali</button>
    </div>
    <div class="card-body">
    <form id="settingsForm">
    <h5>Lokasi Default</h5>
    <p class="text-muted small">Kosongkan untuk meminta lokasi setiap kali.</p>
    <div class="mb-3">
    <label for="city" class="form-label">Nama Kota</label>
    <input type="text" class="form-control" id="city" name="city" list="${datalistId}" value="${Core.escapeHtml(city)}" placeholder="Contoh: Jakarta">
    <datalist id="${datalistId}"></datalist>
    </div>
    <div class="row">
    <div class="col-md-6 mb-3">
    <label for="latitude" class="form-label">Latitude</label>
    <input type="number" step="any" class="form-control" id="latitude" value="${Core.escapeHtml(lat)}" placeholder="-6.2088">
    </div>
    <div class="col-md-6 mb-3">
    <label for="longitude" class="form-label">Longitude</label>
    <input type="number" step="any" class="form-control" id="longitude" value="${Core.escapeHtml(lon)}" placeholder="106.8456">
    </div>
    </div>
    <div class="mb-3">
    <button type="button" class="btn btn-outline-primary" id="autoLocationBtn"><i class="bi bi-geo-alt me-2"></i>Ambil lokasi saat ini</button>
    <span class="text-muted ms-2" id="locationStatus"></span>
    </div>
    <hr>
    <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="notifications_enabled" ${notifications ? 'checked': ''}>
    <label for="notifications_enabled" class="form-check-label">Aktifkan notifikasi waktu shalat</label>
    </div>
    <div class="mb-3">
    <label for="reminder_minutes" class="form-label">⏰ Pengingat Sebelum Adzan</label>
    <select class="form-select" id="reminder_minutes">
    <option value="0">Tepat waktu (0 menit)</option>
    <option value="5">5 menit sebelum</option>
    <option value="10">10 menit sebelum</option>
    <option value="15">15 menit sebelum</option>
    <option value="30">30 menit sebelum</option>
    <option value="45">45 menit sebelum</option>
    <option value="60">60 menit sebelum</option>
    </select>
    <div class="small text-muted mt-1">Notifikasi akan dikirim sesuai pengingat yang dipilih</div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Simpan Pengaturan</button>
    </form>
    </div>
    </div>
    `;

    settingsDiv.innerHTML = html;
    prayerDiv.style.display = 'none';
    settingsDiv.style.display = 'block';

    const reminderSelect = document.getElementById('reminder_minutes');
    if (reminderSelect) reminderSelect.value = reminderMinutes;

    // Autocomplete kota
    const cityInput = document.getElementById('city');
    if (cityInput) {
      let debounceTimer;
      cityInput.addEventListener('input', function(e) {
        clearTimeout(debounceTimer);
        const keyword = e.target.value.trim();
        if (keyword.length < 2) {
          const datalist = document.getElementById(datalistId);
          if (datalist) datalist.innerHTML = '';
          return;
        }
        debounceTimer = setTimeout(async () => {
          try {
            const res = await Core.api.get(`/api/prayer/cities/search?q=${encodeURIComponent(keyword)}`);
            if (res.success && res.data) {
              const datalist = document.getElementById(datalistId);
              if (datalist) {
                datalist.innerHTML = '';
                res.data.forEach(cityItem => {
                  const option = document.createElement('option');
                  option.value = cityItem.name;
                  option.textContent = cityItem.province_name ? `${cityItem.name} (${cityItem.province_name})`: cityItem.name;
                  datalist.appendChild(option);
                });
              }
            }
          } catch (err) {
            console.warn('Autocomplete gagal:', err);
          }
        },
          300);
      });
    }
  }

  // ==================== KALENDER JADWAL SHALAT (BULAN INI) ====================
  async function renderCalendarView() {
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!prayerDiv) return;
    if (settingsDiv) settingsDiv.style.display = 'none';

    // Hitung tanggal pertama dan terakhir bulan ini
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = formatDateToDDMMYYYY(firstDay);
    const endDate = formatDateToDDMMYYYY(lastDay);
    const monthName = firstDay.toLocaleDateString('id-ID', {
      month: 'long', year: 'numeric'
    });

    try {
      Core.showLoading(`Memuat jadwal shalat ${monthName}...`);
      const res = await Core.api.post('/api/prayer/times/range', {
        start_date: startDate,
        end_date: endDate
      });
      if (!res.success || !res.data || res.data.length === 0) {
        throw new Error(res.message || 'Data jadwal tidak tersedia');
      }
      const monthlyData = res.data;

      const html = `
      <div class="card shadow">
      <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Jadwal Shalat - ${monthName}</h4>
      <button id="backToPrayerFromCalendarBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Kembali</button>
      </div>
      <div class="card-body">
      <div class="calendar-wrapper"><div id="prayer-calendar" class="custom-calendar"></div></div>
      <div id="daily-schedule" class="schedule-card mt-3">
      <h6><i class="bi bi-moon-stars"></i> Jadwal Shalat</h6>
      <div id="schedule-content" class="text-center text-muted">Pilih tanggal untuk melihat jadwal</div>
      </div>
      </div>
      </div>
      `;
      prayerDiv.innerHTML = html;
      prayerDiv.style.display = 'block';

      setTimeout(() => initPrayerCalendar(monthlyData), 100);
      document.getElementById('backToPrayerFromCalendarBtn').onclick = () => Core.setState({
        currentView: 'prayer'
      });
    } catch (err) {
      prayerDiv.innerHTML = `<div class="alert alert-danger">${Core.escapeHtml(err.message)}<button class="btn btn-outline-primary mt-2" onclick="location.reload()">Coba Lagi</button></div>`;
      prayerDiv.style.display = 'block';
    } finally {
      Core.hideLoading();
    }
  }

  function formatDateToDDMMYYYY(date) {
    return `${String(date.getDate()).padStart(2, '0')}-${String(date.getMonth()+1).padStart(2, '0')}-${date.getFullYear()}`;
  }

  function initPrayerCalendar(monthlyData) {
    const container = document.getElementById('prayer-calendar');
    if (!container || !window.VanillaCalendarPro) return;
    const {
      Calendar
    } = window.VanillaCalendarPro;

    // Tentukan tanggal pertama dan terakhir bulan ini (dari data atau dari tanggal sistem)
    const today = new Date();
    const currentMonth = today.getMonth() + 1;
    const currentYear = today.getFullYear();
    const firstDayOfMonth = `${currentYear}-${String(currentMonth).padStart(2, '0')}-01`;
    const lastDayOfMonth = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${new Date(currentYear, currentMonth, 0).getDate()}`;

    // Popup tooltip untuk setiap tanggal
    const popups = {};
    monthlyData.forEach(day => {
      const d = convertToYYYYMMDD(day.date);
      if (d) {
        popups[d] = {
          modifier: 'has-prayer-time',
          html: `<div class="prayer-tooltip"><i class="bi bi-moon-stars"></i> Subuh: ${day.jadwal.subuh || '-'}</div>`
        };
      }
    });

    const calendar = new Calendar(container,
      {
        type: 'default',
        selectionDatesMode: 'single',
        firstDayOfWeek: 0,
        selectedMonth: currentMonth - 1,
        selectedYear: currentYear,
        popups: popups,
        displayDateMin: firstDayOfMonth,
        displayDateMax: lastDayOfMonth,
        selectionMonthsMode: false,
        selectionYearsMode: false,
        onClickDate: (self, e) => {
          const fullDate = e.target.closest('[data-vc-date]')?.getAttribute('data-vc-date');
          const dayData = monthlyData.find(d => convertToYYYYMMDD(d.date) === fullDate);
          if (dayData) {
            showDailySchedule(dayData);
          } else {
            showNoDataMessage(fullDate);
          }
        },
        CSSClasses: ['custom-calendar']
      });
    calendar.init();

    // Tampilkan jadwal hari ini secara default
    const todayStr = convertToYYYYMMDD(getTodayDate());
    const todayData = monthlyData.find(d => convertToYYYYMMDD(d.date) === todayStr);
    showDailySchedule(todayData || monthlyData[0]);
  }

  function showDailySchedule(prayerData) {
    const container = document.getElementById('schedule-content');
    if (!container) return;
    const jadwal = prayerData.jadwal;
    const order = ['imsak',
      'subuh',
      'terbit',
      'dhuha',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    let html = `<div class="fw-bold mb-2">${Core.escapeHtml(prayerData.date)} (${Core.escapeHtml(prayerData.hijri)})</div><table class="schedule-table">`;
    for (let name of order) if (jadwal[name]) html += `<tr><th style="width:40%">${Core.getPrayerName(name)}</th><td>${Core.escapeHtml(jadwal[name])}</td></tr>`;
    html += '</table>';
    container.innerHTML = html;
  }

  function showNoDataMessage(dateStr) {
    const container = document.getElementById('schedule-content');
    if (container) container.innerHTML = `<div class="alert alert-warning"><i class="bi bi-calendar-x"></i> Data jadwal untuk tanggal ${dateStr} tidak tersedia.</div>`;
  }

  function convertToYYYYMMDD(dateStr) {
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
  }

  function getTodayDate() {
    const today = new Date();
    return `${String(today.getDate()).padStart(2, '0')}-${String(today.getMonth()+1).padStart(2, '0')}-${today.getFullYear()}`;
  }

  // Update ekspor UI
  window.PrayerAppUI = {
    renderPrayerView: renderPrayerView,
    renderSettingsView: renderSettingsView,
    renderCalendarView: renderCalendarView
  };
})(window, document);