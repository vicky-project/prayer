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
      rows += `<tr class="${rowClass}"><th scope="row">${Core.getPrayerName(name)}</th><td class="text-end">${time}</td></tr>`;
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
    <button id="settingsBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-gear-fill"></i></button>
    <button id="refreshPrayerBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-repeat"></i></button>
    <button id="weeklyViewBtn" class="btn btn-sm btn-outline-light" title="Jadwal Mingguan">
    <i class="bi bi-calendar-week"></i>
    </button>
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

    // PERBAIKAN: Ambil kota dari default_location atau legacy city
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

    // Autocomplete kota (sama seperti sebelumnya)
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

  function renderWeeklyTableView(weeklyData) {
    const prayerDiv = document.getElementById('prayer-view');
    const settingsDiv = document.getElementById('settings-view');
    if (!prayerDiv) return;

    if (settingsDiv) settingsDiv.style.display = 'none';

    const dates = weeklyData.map(day => day.date);
    const hijriDates = weeklyData.map(day => day.hijri);

    // Urutan shalat: imsak, subuh, terbit, dzuhur, ashar, maghrib, isya
    const prayerNames = ['imsak',
      'subuh',
      'terbit',
      'dzuhur',
      'ashar',
      'maghrib',
      'isya'];
    const prayerLabels = {
      imsak: 'Imsak',
      subuh: 'Subuh',
      terbit: 'Terbit',
      dzuhur: 'Dzuhur',
      ashar: 'Ashar',
      maghrib: 'Maghrib',
      isya: 'Isya'
    };

    let tableHtml = `
    <div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Jadwal Shalat 7 Hari</h4>
    <button id="backToPrayerFromWeeklyBtn" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Kembali</button>
    </div>
    <div class="card-body px-0">
    <div class="table-responsive">
    <table id="table-range" class="table table-bordered table-striped mb-0 text-center">
    <thead>
    <tr>
    <th>Waktu Shalat</th>
    `;
    for (let i = 0; i < dates.length; i++) {
      // Deteksi apakah hari Jumat (6 hari setelah hari ini? Perhitungan berdasarkan data tanggal)
      // Kita cek dengan parsing date (dd-mm-yyyy)
      const parts = dates[i].split('-'); // dd-mm-yyyy
      const dateObj = new Date(parts[2], parts[1]-1, parts[0]);
      const isFriday = dateObj.getDay() === 5; // 5 = Jumat
      const fridayClass = isFriday ? ' class="text-warning fw-bold"': '';
      tableHtml += `<th${fridayClass}>${Core.escapeHtml(dates[i])}<br><small class="text-muted">${Core.escapeHtml(hijriDates[i])}</small></th>`;
    }
    tableHtml += `</tr></thead><tbody>`;

    for (let p of prayerNames) {
      tableHtml += `<tr><th class="text-bg-light">${prayerLabels[p]}</th>`;
      for (let i = 0; i < weeklyData.length; i++) {
        const time = weeklyData[i].jadwal[p] || '-';
        tableHtml += `<td>${Core.escapeHtml(time)}</td>`;
      }
      tableHtml += `</tr>`;
    }
    tableHtml += `</tbody></table></div></div></div>`;

    prayerDiv.innerHTML = tableHtml;
    prayerDiv.style.display = 'block';

    const backBtn = document.getElementById('backToPrayerFromWeeklyBtn');
    if (backBtn) {
      backBtn.addEventListener('click', () => {
        Core.setState({
          currentView: 'prayer'
        });
      });
    }
  }

  // Update ekspor UI
  window.PrayerAppUI = {
    renderPrayerView: renderPrayerView,
    renderSettingsView: renderSettingsView,
    renderWeeklyTableView: renderWeeklyTableView
  };
})(window, document);