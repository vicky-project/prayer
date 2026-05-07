// core.js for Prayer Times
(function(window, document, undefined) {
  'use strict';

  const tgApp = window.TelegramApp;
  if (!tgApp) {
    console.error('TelegramApp tidak tersedia');
    return;
  }

  const {
    fetchWithAuth,
    showToast,
    showLoading,
    hideLoading,
    escapeHtml
  } = tgApp;

  let _state = {
    prayer: null,
    // data jadwal dari API
    settings: null,
    loading: true,
    error: null,
    currentView: 'prayer',
    // 'prayer' or 'settings'
    cityTimezoneOffset: null,
    countdownInterval: null
  };
  const _listeners = [];

  const Core = {};

  // State
  Core.getState = function() {
    return _state;
  };
  Core.setState = function(newState) {
    Object.assign(_state, newState);
    _listeners.forEach(fn => {
      try {
        fn(_state);
      } catch(e) {
        console.error(e);
      }
    });
  };
  Core.subscribe = function(fn) {
    _listeners.push(fn);
    return () => {
      const idx = _listeners.indexOf(fn); if (idx !== -1) _listeners.splice(idx, 1);
    };
  };

  // API wrapper
  function _apiRequest(endpoint, method, body) {
    const options = {
      method: method
    };
    if (body) {
      options.body = JSON.stringify(body);
      options.headers = {
        'Content-Type': 'application/json'
      };
    }
    return fetchWithAuth(BASE_URL + endpoint, options);
  }
  Core.api = {
    get: (endpoint) => _apiRequest(endpoint, 'GET'),
    post: (endpoint, body) => _apiRequest(endpoint, 'POST', body),
    put: (endpoint, body) => _apiRequest(endpoint, 'PUT', body),
    del: (endpoint) => _apiRequest(endpoint, 'DELETE')
  };

  // Helpers untuk countdown
  Core.stopCountdown = function() {
    if (_state.countdownInterval) {
      clearInterval(_state.countdownInterval);
      Core.setState({
        countdownInterval: null
      });
    }
  };
  Core.getCurrentCityTime = function() {
    if (_state.cityTimezoneOffset !== null) {
      const nowUTC = new Date();
      const utcTime = nowUTC.getTime() + (nowUTC.getTimezoneOffset() * 60 * 1000);
      return new Date(utcTime + (_state.cityTimezoneOffset * 60 * 1000));
    }
    return new Date();
  };
  Core.timeToMinutes = function(timeStr) {
    if (!timeStr) return 0;
    const parts = timeStr.split(':');
    if (parts.length < 2) return 0;
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
  };
  Core.getPrayerName = function(key) {
    const names = {
      'imsak': 'Imsak',
      'subuh': 'Subuh',
      'terbit': 'Terbit',
      'dhuha': 'Dhuha',
      'dzuhur': 'Dzuhur',
      'ashar': 'Ashar',
      'maghrib': 'Maghrib',
      'isya': 'Isya'
    };
    return names[key] || key;
  };

  // Expose utilities for UI
  Core.showToast = showToast;
  Core.showLoading = showLoading;
  Core.hideLoading = hideLoading;
  Core.escapeHtml = escapeHtml;

  window.PrayerAppCore = Core;
})(window, document);