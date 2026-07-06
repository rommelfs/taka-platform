(function () {
  'use strict';

  var dbPromise = null;
  var logPrefix = '[TAKA Event Operations] ';

  console.log('TAKA event operations JS loaded');

  function closestElement(target, selector) {
    return target && target.closest ? target.closest(selector) : null;
  }

  function label(root, key, fallback) {
    return root.getAttribute('data-label-' + key) || fallback;
  }

  function labelCount(root, key, count, fallback) {
    return label(root, key, fallback).replace('%d', count);
  }

  function logError(context, error) {
    console.error(logPrefix + context, error);
  }

  function logWarn(context, data) {
    console.warn(logPrefix + context, data || '');
  }

  function formatDetail(detail) {
    if (!detail) {
      return '';
    }
    if (detail instanceof Error) {
      return detail.name ? detail.name + ': ' + detail.message : detail.message;
    }
    if ('string' === typeof detail) {
      return detail;
    }
    try {
      return JSON.stringify(detail);
    } catch (error) {
      return String(detail);
    }
  }

  function debugTime() {
    return new Date().toLocaleTimeString([], { hour12: false });
  }

  function appendDebug(root, message, detail) {
    var target = root.querySelector('[data-taka-debug-log]');
    var line = debugTime() + ' ' + message;
    var formatted = formatDetail(detail);

    if (formatted) {
      line += ': ' + formatted;
    }
    root._takaDebugEntries = root._takaDebugEntries || [];
    root._takaDebugEntries.push(line);
    if (target) {
      target.textContent = root._takaDebugEntries.join("\n");
      target.scrollTop = target.scrollHeight;
    }
    if (detail) {
      console.log(logPrefix + message, detail);
    } else {
      console.log(logPrefix + message);
    }
  }

  function renderDiagnostics(root) {
    var target = root.querySelector('[data-taka-camera-status]');
    var order = root._takaDiagnosticOrder || [];
    var values = root._takaDiagnostics || {};

    if (!target) {
      return;
    }
    target.innerHTML = '';
    order.forEach(function (key) {
      var item = values[key];
      var row;
      var badge;
      var text;

      if (!item || !item.text) {
        return;
      }
      row = document.createElement('li');
      row.className = 'is-' + (item.state || 'info');
      badge = document.createElement('span');
      badge.className = 'taka-operations-camera-diagnostics__badge';
      badge.textContent = 'warning' === item.state ? 'Warn' : ('error' === item.state ? 'Error' : ('ok' === item.state ? 'OK' : 'Info'));
      text = document.createElement('span');
      text.textContent = item.text;
      row.appendChild(badge);
      row.appendChild(text);
      target.appendChild(row);
    });
  }

  function setDiagnostic(root, key, state, text) {
    root._takaDiagnostics = root._takaDiagnostics || {};
    root._takaDiagnosticOrder = root._takaDiagnosticOrder || [];
    if (root._takaDiagnosticOrder.indexOf(key) === -1) {
      root._takaDiagnosticOrder.push(key);
    }
    root._takaDiagnostics[key] = {
      state: state || 'info',
      text: text || ''
    };
    renderDiagnostics(root);
  }

  function replacePlaceholder(template, value) {
    return template.replace('%s', value).replace('%d', value);
  }

  function browserInfo() {
    var ua = window.navigator.userAgent || '';
    var browser = 'Unknown browser';
    var os = 'Unknown OS';
    var match;

    if ((match = ua.match(/Edg\/([\d.]+)/))) {
      browser = 'Microsoft Edge ' + match[1];
    } else if ((match = ua.match(/CriOS\/([\d.]+)/))) {
      browser = 'Chrome iOS ' + match[1];
    } else if ((match = ua.match(/Chrome\/([\d.]+)/))) {
      browser = 'Chrome ' + match[1];
    } else if ((match = ua.match(/Firefox\/([\d.]+)/))) {
      browser = 'Firefox ' + match[1];
    } else if ((match = ua.match(/Version\/([\d.]+).*Safari/))) {
      browser = 'Safari ' + match[1];
    }

    if (/iPhone|iPad|iPod/.test(ua)) {
      os = 'iOS';
    } else if (/Android/.test(ua)) {
      os = 'Android';
    } else if (/Mac OS X/.test(ua)) {
      os = 'macOS';
    } else if (/Windows NT/.test(ua)) {
      os = 'Windows';
    } else if (/Linux/.test(ua)) {
      os = 'Linux';
    }

    return {
      browser: browser,
      os: os,
      userAgent: ua
    };
  }

  function initializeDiagnostics(root) {
    var info = browserInfo();
    var supportsMedia = !!(window.navigator.mediaDevices && window.navigator.mediaDevices.getUserMedia);
    var secure = !!window.isSecureContext || ['localhost', '127.0.0.1', '::1'].indexOf(window.location.hostname) !== -1;

    root._takaDebugEntries = root._takaDebugEntries || [];
    setDiagnostic(root, 'browser', 'info', replacePlaceholder(label(root, 'browser-label', 'Browser: %s'), info.browser));
    setDiagnostic(root, 'os', 'info', replacePlaceholder(label(root, 'os-label', 'Operating system: %s'), info.os));
    setDiagnostic(root, 'secure-context', secure ? 'ok' : 'error', replacePlaceholder(label(root, 'secure-context-label', 'Secure Context: %s'), secure ? 'true' : 'false'));
    setDiagnostic(root, 'https', secure ? 'ok' : 'error', label(root, secure ? 'https-ok' : 'https-fail', secure ? 'HTTPS or localhost detected.' : 'Secure context missing.'));
    setDiagnostic(root, 'get-user-media', supportsMedia ? 'ok' : 'error', label(root, supportsMedia ? 'get-user-media-ok' : 'get-user-media-fail', supportsMedia ? 'Browser supports getUserMedia.' : 'getUserMedia not supported.'));
    setDiagnostic(root, 'barcode-detector', 'BarcodeDetector' in window ? 'ok' : 'warning', label(root, 'BarcodeDetector' in window ? 'barcode-ok' : 'barcode-fallback', 'BarcodeDetector not available (using html5-qrcode fallback).'));
    setDiagnostic(root, 'html5-qrcode', window.Html5Qrcode ? 'ok' : 'warning', label(root, window.Html5Qrcode ? 'html5-ok' : 'html5-fail', window.Html5Qrcode ? 'html5-qrcode loaded.' : 'html5-qrcode not loaded.'));
    appendDebug(root, 'Diagnostics initialized', { eventId: root.getAttribute('data-event-id'), browser: info.browser, os: info.os, secureContext: window.isSecureContext });
  }

  function enumerateCameras(root) {
    appendDebug(root, 'Enumerating devices');
    if (!window.navigator.mediaDevices || !window.navigator.mediaDevices.enumerateDevices) {
      setDiagnostic(root, 'camera-count', 'error', label(root, 'camera-not-found', 'No camera device found.'));
      return Promise.resolve([]);
    }
    return window.navigator.mediaDevices.enumerateDevices().then(function (devices) {
      var cameras = devices.filter(function (device) { return 'videoinput' === device.kind; });
      appendDebug(root, 'Found cameras', cameras.length);
      setDiagnostic(root, 'camera-count', cameras.length ? 'ok' : 'error', cameras.length ? labelCount(root, 'camera-found-count', cameras.length, 'Camera found (%d devices).') : label(root, 'camera-not-found', 'No camera device found.'));
      return cameras;
    }).catch(function (error) {
      appendDebug(root, 'enumerateDevices failed', error);
      setDiagnostic(root, 'camera-count', 'error', error.message || label(root, 'camera-not-found', 'No camera device found.'));
      return [];
    });
  }

  function streamLabel(stream) {
    var track = stream && stream.getVideoTracks ? stream.getVideoTracks()[0] : null;
    var settings = track && track.getSettings ? track.getSettings() : {};
    var labelText = track && track.label ? track.label : 'Camera';
    var resolution = settings.width && settings.height ? settings.width + 'x' + settings.height : '';
    var lower = (labelText + ' ' + (settings.facingMode || '')).toLowerCase();
    var rear = lower.indexOf('back') !== -1 || lower.indexOf('rear') !== -1 || lower.indexOf('environment') !== -1 || lower.indexOf('rueck') !== -1;

    return {
      label: labelText,
      resolution: resolution,
      rear: rear,
      settings: settings
    };
  }

  function updateStreamDiagnostics(root, stream) {
    var info = streamLabel(stream);
    var cameraTemplate = label(root, info.rear ? 'rear-camera-opened' : 'camera-opened', info.rear ? 'Rear camera opened: %s' : 'Camera opened: %s');

    setDiagnostic(root, 'permission', 'ok', label(root, 'permission-granted', 'Camera permission granted.'));
    setDiagnostic(root, 'active-camera', 'ok', replacePlaceholder(cameraTemplate, info.label));
    if (info.resolution) {
      setDiagnostic(root, 'resolution', 'ok', replacePlaceholder(label(root, 'resolution-label', 'Resolution: %s'), info.resolution));
    }
    appendDebug(root, 'Camera opened', { label: info.label, resolution: info.resolution, settings: info.settings });
  }

  function diagnoseCameraError(root, error) {
    var message = error && error._takaCameraMessage ? error._takaCameraMessage : (error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.'));
    setDiagnostic(root, 'permission', 'error', message);
    appendDebug(root, 'getUserMedia failed', error);
  }

  function copyDebug(root) {
    var info = browserInfo();
    var statuses = [];
    var values = root._takaDiagnostics || {};
    var order = root._takaDiagnosticOrder || [];
    var payload;

    order.forEach(function (key) {
      if (values[key] && values[key].text) {
        statuses.push((values[key].state || 'info').toUpperCase() + ': ' + values[key].text);
      }
    });

    payload = [
      'TAKA Event Operations Debug',
      'Timestamp: ' + new Date().toISOString(),
      'Event ID: ' + (root.getAttribute('data-event-id') || ''),
      'URL: ' + window.location.href,
      'Browser: ' + info.browser,
      'Operating system: ' + info.os,
      'Secure Context: ' + String(window.isSecureContext),
      'User Agent: ' + info.userAgent,
      '',
      'Status:',
      statuses.join("\n"),
      '',
      'Debug Log:',
      (root._takaDebugEntries || []).join("\n")
    ].join("\n");

    function copied() {
      appendDebug(root, 'Debug information copied');
      setDiagnostic(root, 'copy-debug', 'ok', label(root, 'copy-debug-copied', 'Debug information copied.'));
    }

    if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
      return window.navigator.clipboard.writeText(payload).then(copied).catch(function (error) {
        appendDebug(root, 'Clipboard copy failed', error);
        setDiagnostic(root, 'copy-debug', 'error', label(root, 'copy-debug-failed', 'Could not copy debug information.'));
      });
    }

    setDiagnostic(root, 'copy-debug', 'error', label(root, 'copy-debug-failed', 'Could not copy debug information.'));
    appendDebug(root, 'Clipboard API unavailable');
    return Promise.resolve();
  }

  function setResult(root, status, message, data) {
    var target = root.querySelector('[data-taka-scan-result]');
    var parts = [message || status];

    if (!target) {
      return;
    }
    if (data && data.participantName) {
      parts.push(data.participantName);
    }
    if (data && data.ticketType) {
      parts.push(data.ticketType);
    }
    if (data && data.paymentStatus) {
      parts.push(label(root, 'payment', 'Payment') + ': ' + data.paymentStatus);
    }
    if (data && data.checkedInAt) {
      parts.push(data.checkedInAt);
    }

    target.className = 'taka-operations-scan-result is-' + (status || 'info');
    target.textContent = parts.filter(Boolean).join(' / ');
    appendDebug(root, 'Status: ' + target.textContent);
  }

  function setOfflineStatus(root, text) {
    var target = root.querySelector('[data-taka-offline-status]');
    if (target) {
      target.textContent = text;
    }
  }

  function getDeviceId() {
    var key = 'taka_operations_device_id';
    var existing = window.localStorage ? window.localStorage.getItem(key) : '';
    var value;

    if (existing) {
      return existing;
    }
    value = 'dev-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    if (window.localStorage) {
      window.localStorage.setItem(key, value);
    }
    return value;
  }

  function openDb() {
    if (dbPromise) {
      return dbPromise;
    }
    dbPromise = new Promise(function (resolve, reject) {
      var request;

      if (!window.indexedDB) {
        reject(new Error('IndexedDB is not available.'));
        return;
      }
      request = window.indexedDB.open('taka-operations-checkin', 1);
      request.onupgradeneeded = function () {
        var db = request.result;
        var store;

        if (!db.objectStoreNames.contains('manifests')) {
          db.createObjectStore('manifests', { keyPath: 'event_id' });
        }
        if (!db.objectStoreNames.contains('checkins')) {
          store = db.createObjectStore('checkins', { keyPath: 'local_id' });
          store.createIndex('event_id', 'event_id', { unique: false });
          store.createIndex('ticket_token', 'ticket_token', { unique: false });
        }
      };
      request.onerror = function () {
        reject(request.error || new Error('IndexedDB could not be opened.'));
      };
      request.onsuccess = function () {
        resolve(request.result);
      };
    });
    return dbPromise;
  }

  function dbGet(storeName, key) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readonly');
        var request = tx.objectStore(storeName).get(key);

        request.onerror = function () { reject(request.error); };
        request.onsuccess = function () { resolve(request.result || null); };
      });
    });
  }

  function dbPut(storeName, value) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readwrite');

        tx.objectStore(storeName).put(value);
        tx.onerror = function () { reject(tx.error); };
        tx.oncomplete = function () { resolve(value); };
      });
    });
  }

  function dbAllByEvent(storeName, eventId) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readonly');
        var index = tx.objectStore(storeName).index('event_id');
        var request = index.getAll(parseInt(eventId, 10));

        request.onerror = function () { reject(request.error); };
        request.onsuccess = function () { resolve(request.result || []); };
      });
    });
  }

  function extractToken(payload) {
    var match;

    payload = (payload || '').toString().trim();
    match = payload.match(/\/checkin\/t\/([A-Za-z0-9_-]+)/);
    if (match) {
      return match[1];
    }
    return /^[A-Za-z0-9_-]{24,80}$/.test(payload) ? payload : '';
  }

  function ajax(root, action, data) {
    var ajaxUrl = root.getAttribute('data-ajax-url') || '';
    var nonce = root.getAttribute('data-nonce') || '';
    var form = new window.FormData();

    if (!ajaxUrl || !nonce || !action) {
      return Promise.reject(new Error(label(root, 'config-missing', 'Check-in configuration missing.')));
    }

    form.append('action', action);
    form.append('nonce', nonce);
    Object.keys(data || {}).forEach(function (key) {
      form.append(key, data[key]);
    });

    return window.fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    }).then(function (response) {
      return response.json();
    }).then(function (json) {
      if (!json || !json.success) {
        throw new Error(json && json.data && json.data.message ? json.data.message : label(root, 'request-failed', 'Request failed.'));
      }
      return json.data;
    });
  }

  function validateConfig(root, mode) {
    var required = ['ajax-url', 'nonce', 'event-id'];
    var missing = [];

    if ('scan' === mode) {
      required.push('scan-action');
    } else if ('offline' === mode) {
      required.push('manifest-action');
    } else if ('sync' === mode) {
      required.push('sync-action');
    } else {
      required.push('scan-action', 'manifest-action', 'sync-action');
    }

    required.forEach(function (key) {
      if (!root.getAttribute('data-' + key)) {
        missing.push('data-' + key);
      }
    });

    if (missing.length) {
      setResult(root, 'invalid', label(root, 'config-missing', 'Check-in configuration missing.') + ' (' + missing.join(', ') + ')');
      setOfflineStatus(root, label(root, 'config-missing', 'Check-in configuration missing.'));
      logError('Check-in configuration missing', missing);
      return false;
    }
    return true;
  }

  function refreshPending(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);

    return dbAllByEvent('checkins', eventId).then(function (items) {
      var count = items.filter(function (item) { return 'pending' === item.sync_status; }).length;
      var target = root.querySelector('[data-taka-offline-pending]');

      if (target) {
        target.textContent = count.toString();
      }
      return count;
    }).catch(function (error) {
      logError('Refreshing pending offline check-ins failed', error);
      return 0;
    });
  }

  function loadOfflineData(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);

    if (!validateConfig(root, 'offline')) {
      return Promise.resolve();
    }

    setOfflineStatus(root, label(root, 'offline-loading', 'Loading offline data...'));
    return ajax(root, root.getAttribute('data-manifest-action'), { event_id: eventId }).then(function (data) {
      return dbPut('manifests', {
        event_id: eventId,
        event_name: data.eventName || '',
        generated_at: data.generatedAt || '',
        expires_at: data.expiresAt || '',
        tickets: data.tickets || []
      }).then(function () {
        setOfflineStatus(root, labelCount(root, 'offline-ready', (data.tickets || []).length, 'Offline ready: %d tickets loaded.'));
        return refreshPending(root);
      });
    }).catch(function (error) {
      logError('Offline manifest failed', error);
      setOfflineStatus(root, error.message || label(root, 'offline-init-failed', 'Offline mode is not available in this browser.'));
    });
  }

  function findOfflineTicket(manifest, payload) {
    var token = extractToken(payload);

    return (manifest && manifest.tickets ? manifest.tickets : []).find(function (ticket) {
      return ticket.payload === payload || ticket.legacy_payload === payload || ticket.ticket_token === token;
    }) || null;
  }

  function offlineScan(root, payload) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);
    var token = extractToken(payload);

    return dbGet('manifests', eventId).then(function (manifest) {
      var ticket;

      if (!manifest || !manifest.tickets || !manifest.tickets.length) {
        setResult(root, 'not_found', label(root, 'offline-not-loaded', 'Offline data is not loaded for this event.'));
        return null;
      }
      if (manifest.expires_at && Date.parse(manifest.expires_at.replace(' ', 'T') + 'Z') < Date.now()) {
        setResult(root, 'invalid', label(root, 'offline-expired', 'Offline data has expired. Load offline data again.'));
        return null;
      }
      ticket = findOfflineTicket(manifest, payload);
      if (!ticket) {
        setResult(root, 'not_found', label(root, 'ticket-not-found', 'Ticket not found in offline data.'));
        return null;
      }
      if ('cancelled' === ticket.order_status || 'cancelled' === ticket.checkin_status) {
        setResult(root, 'cancelled', label(root, 'ticket-cancelled', 'Ticket cancelled.'), ticket);
        return null;
      }
      if ('paid' !== ticket.payment_status) {
        setResult(root, 'payment_pending', label(root, 'payment-pending', 'Payment pending.'), ticket);
        return null;
      }

      return dbAllByEvent('checkins', eventId).then(function (items) {
        var duplicate = items.some(function (item) {
          return item.ticket_token === (ticket.ticket_token || token) && 'pending' === item.sync_status;
        }) || 'checked_in' === ticket.checkin_status;

        if (duplicate) {
          setResult(root, 'already_checked_in', label(root, 'already-local', 'Already checked in locally.'), ticket);
          return refreshPending(root);
        }

        return dbPut('checkins', {
          local_id: 'local-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8),
          event_id: eventId,
          payload: payload,
          ticket_token: ticket.ticket_token || token,
          device_id: getDeviceId(),
          scanned_at: new Date().toISOString(),
          sync_status: 'pending'
        }).then(function () {
          ticket.checkin_status = 'checked_in';
          setResult(root, 'checked_in', label(root, 'offline-stored', 'Offline check-in stored.'), ticket);
          return dbPut('manifests', manifest).then(function () {
            return refreshPending(root);
          });
        });
      });
    }).catch(function (error) {
      logError('Offline scan failed', error);
      setResult(root, 'invalid', error.message || label(root, 'offline-unavailable', 'Offline data is not available.'));
    });
  }

  function onlineScan(root, payload) {
    if (!validateConfig(root, 'scan')) {
      return Promise.resolve();
    }

    appendDebug(root, 'Sending scan payload to server');
    return ajax(root, root.getAttribute('data-scan-action'), {
      event_id: root.getAttribute('data-event-id'),
      payload: payload,
      device_id: getDeviceId()
    }).then(function (data) {
      appendDebug(root, 'Server scan result', data);
      setResult(root, data.status, data.message, data);
    }).catch(function (error) {
      logError('Online scan failed', error);
      appendDebug(root, 'Online scan failed', error);
      setResult(root, 'invalid', error.message || label(root, 'request-failed', 'Request failed.'));
    });
  }

  function handlePayload(root, payload) {
    if (!payload || payload === root._takaLastPayload) {
      return;
    }
    root._takaLastPayload = payload;
    appendDebug(root, 'Handling QR payload');
    if (!window.navigator.onLine) {
      appendDebug(root, 'Browser offline; using offline scan');
      offlineScan(root, payload);
      return;
    }
    onlineScan(root, payload);
  }

  function syncOffline(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);

    if (!validateConfig(root, 'sync')) {
      return Promise.resolve();
    }

    return dbAllByEvent('checkins', eventId).then(function (items) {
      var pending = items.filter(function (item) { return 'pending' === item.sync_status; });

      if (!pending.length) {
        setOfflineStatus(root, label(root, 'no-unsynced', 'No offline check-ins to synchronize.'));
        return null;
      }

      return ajax(root, root.getAttribute('data-sync-action'), {
        event_id: eventId,
        checkins: JSON.stringify(pending)
      }).then(function (data) {
        var updates = (data.results || []).map(function (item) {
          var local = pending.find(function (candidate) { return candidate.local_id === item.localId; });
          var status;

          if (!local) {
            return Promise.resolve();
          }
          status = item.result && item.result.status;
          local.sync_status = ('checked_in' === status || 'already_checked_in' === status) ? 'synced' : 'conflict';
          local.sync_result = item.result || {};
          return dbPut('checkins', local);
        });

        return Promise.all(updates).then(function () {
          setOfflineStatus(root, labelCount(root, 'sync-finished', updates.length, 'Synchronization finished: %d check-ins processed.'));
          return refreshPending(root);
        });
      });
    }).catch(function (error) {
      logError('Offline sync failed', error);
      setOfflineStatus(root, error.message || label(root, 'sync-failed', 'Synchronization failed.'));
    });
  }

  function isLocalhost() {
    return ['localhost', '127.0.0.1', '::1'].indexOf(window.location.hostname) !== -1;
  }

  function cameraSupportError(root) {
    if (!window.isSecureContext && !isLocalhost()) {
      return label(root, 'camera-insecure', 'Camera access requires HTTPS or localhost. Please open this check-in page over HTTPS.');
    }
    if (!window.navigator.mediaDevices || !window.navigator.mediaDevices.getUserMedia) {
      return label(root, 'camera-unsupported', 'Camera access is not supported in this browser/context.');
    }
    return '';
  }

  function cameraErrorMessage(root, error) {
    var name = error && error.name ? error.name : '';
    var raw = 'string' === typeof error ? error : (error && error.message ? error.message : '');
    var lower = raw.toLowerCase();

    if (lower.indexOf('permission') !== -1 || lower.indexOf('notallowed') !== -1 || lower.indexOf('not allowed') !== -1 || lower.indexOf('denied') !== -1) {
      return label(root, 'camera-permission', 'Camera permission denied. Please allow camera access in your browser.');
    }
    if (lower.indexOf('notfound') !== -1 || lower.indexOf('not found') !== -1 || lower.indexOf('no camera') !== -1 || lower.indexOf('no video') !== -1) {
      return label(root, 'camera-not-found', 'No camera device found.');
    }
    if (lower.indexOf('notreadable') !== -1 || lower.indexOf('in use') !== -1 || lower.indexOf('could not start') !== -1) {
      return label(root, 'camera-in-use', 'Camera is already in use by another app.');
    }
    if ('NotAllowedError' === name || 'SecurityError' === name || 'PermissionDeniedError' === name) {
      return label(root, 'camera-permission', 'Camera permission denied. Please allow camera access in your browser.');
    }
    if ('NotFoundError' === name || 'DevicesNotFoundError' === name) {
      return label(root, 'camera-not-found', 'No camera device found.');
    }
    if ('NotReadableError' === name || 'TrackStartError' === name) {
      return label(root, 'camera-in-use', 'Camera is already in use by another app.');
    }
    return raw || label(root, 'camera-failed', 'Camera could not be opened.');
  }

  function cameraConstraints() {
    return [
      { video: { facingMode: { ideal: 'environment' } } },
      { video: { facingMode: 'environment' } },
      { video: true }
    ];
  }

  function canRetryCameraError(error) {
    var name = error && error.name ? error.name : '';
    var raw = 'string' === typeof error ? error : (error && error.message ? error.message : '');

    return ['OverconstrainedError', 'ConstraintNotSatisfiedError', 'TypeError'].indexOf(name) !== -1 || raw.toLowerCase().indexOf('constraint') !== -1;
  }

  function normalizeCameraError(root, error) {
    var normalized = error instanceof Error ? error : new Error(String(error || label(root, 'camera-failed', 'Camera could not be opened.')));
    normalized._takaCameraMessage = cameraErrorMessage(root, error);
    return normalized;
  }

  function getCameraStream(root, index) {
    var supportError = cameraSupportError(root);
    var constraints = cameraConstraints();

    index = index || 0;
    if (supportError) {
      appendDebug(root, 'Camera support check failed', supportError);
      return Promise.reject(new Error(supportError));
    }

    appendDebug(root, 'Requesting getUserMedia', constraints[index]);
    return window.navigator.mediaDevices.getUserMedia(constraints[index]).catch(function (error) {
      if (index + 1 < constraints.length && canRetryCameraError(error)) {
        logError('Camera constraint failed, trying fallback ' + (index + 2), error);
        appendDebug(root, 'Camera constraint failed, trying fallback ' + (index + 2), error);
        return getCameraStream(root, index + 1);
      }
      throw normalizeCameraError(root, error);
    });
  }

  function setModeButtons(root, mode) {
    var stopCameraButton = root.querySelector('[data-taka-scan-stop]');
    var stopScanningButton = root.querySelector('[data-taka-stop-scanning]');

    if (stopCameraButton) {
      stopCameraButton.hidden = 'test' !== mode;
    }
    if (stopScanningButton) {
      stopScanningButton.hidden = 'scan' !== mode;
    }
  }

  function stopCamera(root, showStatus) {
    var video = root.querySelector('[data-taka-scan-video]');
    var reader = root.querySelector('[data-taka-html5-reader]');
    var html5 = root._takaHtml5Scanner;
    var wasScanning = 'scan' === root._takaCameraMode;
    var hadActiveCamera = !!root._takaCameraActive || !!root._takaStopCamera || !!html5 || !!(video && video.srcObject);

    appendDebug(root, wasScanning ? 'Stopping scanner' : 'Stopping camera');
    if (root._takaStopCamera) {
      root._takaStopCamera();
      root._takaStopCamera = null;
    }
    if (html5 && html5.stop) {
      html5.stop().then(function () {
        if (html5.clear) {
          html5.clear();
        }
      }).catch(function (error) {
        logError('Stopping html5-qrcode failed', error);
      });
      root._takaHtml5Scanner = null;
    }
    root._takaCameraActive = false;
    root._takaCameraMode = '';

    if (video) {
      if (video.srcObject) {
        video.srcObject.getTracks().forEach(function (track) { track.stop(); });
      }
      video.srcObject = null;
      video.hidden = true;
    }
    if (reader) {
      reader.hidden = true;
      reader.innerHTML = '';
    }
    setModeButtons(root, '');
    if (showStatus) {
      setResult(root, 'info', hadActiveCamera ? label(root, wasScanning ? 'scanner-stopped' : 'camera-stopped', wasScanning ? 'Scanner stopped.' : 'Camera stopped.') : label(root, 'no-active-camera', 'No active camera.'));
    }
  }

  function showCameraStream(root, stream, mode) {
    var video = root.querySelector('[data-taka-scan-video]');
    var reader = root.querySelector('[data-taka-html5-reader]');

    if (!video) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      throw new Error(label(root, 'camera-unsupported', 'Camera access is not supported in this browser/context.'));
    }
    if (reader) {
      reader.hidden = true;
      reader.innerHTML = '';
    }

    video.setAttribute('autoplay', 'autoplay');
    video.setAttribute('playsinline', 'playsinline');
    video.muted = true;
    video.srcObject = stream;
    video.hidden = false;
    root._takaCameraActive = true;
    root._takaCameraMode = mode || 'test';
    setModeButtons(root, root._takaCameraMode);
    updateStreamDiagnostics(root, stream);
    root._takaStopCamera = function () {
      stream.getTracks().forEach(function (track) { track.stop(); });
      video.srcObject = null;
      video.hidden = true;
      root._takaCameraActive = false;
      root._takaCameraMode = '';
      setModeButtons(root, '');
    };

    return video.play().catch(function (error) {
      logError(mode + ' video playback failed', error);
    });
  }

  function testCamera(root) {
    appendDebug(root, 'Test Camera requested');
    setResult(root, 'info', label(root, 'camera-test-starting', 'Starting camera test...'));
    stopCamera(root, false);
    enumerateCameras(root).then(function () {
      appendDebug(root, 'Requesting permission');
      return getCameraStream(root, 0);
    }).then(function (stream) {
      return showCameraStream(root, stream, 'test').then(function () {
        setResult(root, 'info', label(root, 'camera-test-running', 'Camera test is running.'));
      });
    }).catch(function (error) {
      logError('Camera test failed', error);
      diagnoseCameraError(root, error);
      setResult(root, 'invalid', error._takaCameraMessage || error.message || label(root, 'camera-failed', 'Camera could not be opened.'));
    });
  }

  function startHtml5Scanner(root, Html5Qrcode, reader, index) {
    var configs = [
      { facingMode: 'environment' },
      { facingMode: { exact: 'environment' } },
      null
    ];
    var config = configs[index || 0];
    var scanner = root._takaHtml5Scanner;

    if (!scanner) {
      scanner = new Html5Qrcode(reader.id, false);
      root._takaHtml5Scanner = scanner;
    }

    function startWith(cameraConfig) {
      return scanner.start(
        cameraConfig,
        { fps: 10, qrbox: { width: 260, height: 260 } },
        function (decodedText) {
          handlePayload(root, decodedText);
        },
        function () {}
      );
    }

    if (config) {
      return startWith(config).catch(function (error) {
        if (index + 1 < configs.length && canRetryCameraError(error)) {
          logError('html5-qrcode camera constraint failed, trying fallback ' + (index + 2), error);
          return startHtml5Scanner(root, Html5Qrcode, reader, index + 1);
        }
        throw normalizeCameraError(root, error);
      });
    }

    return Html5Qrcode.getCameras().then(function (cameras) {
      if (!cameras || !cameras.length) {
        throw new Error(label(root, 'camera-not-found', 'No camera device found.'));
      }
      return startWith(cameras[0].id);
    });
  }

  function startHtml5ScannerUi(root, Html5Qrcode, reader) {
    var video = root.querySelector('[data-taka-scan-video]');

    if (!Html5Qrcode || !reader) {
      setDiagnostic(root, 'scanner-library', 'error', label(root, 'html5-fail', 'html5-qrcode not loaded.'));
      appendDebug(root, 'html5-qrcode library missing');
      setResult(root, 'invalid', label(root, 'scanner-library-missing', 'QR scanner library could not be loaded. Please reload the page.'));
      return false;
    }
    setDiagnostic(root, 'scanner-library', 'ok', label(root, 'html5-ok', 'html5-qrcode loaded.'));
    if (video) {
      if (video.srcObject) {
        video.srcObject.getTracks().forEach(function (track) { track.stop(); });
      }
      video.srcObject = null;
      video.hidden = true;
    }
    if (!reader.id) {
      reader.id = 'taka-html5-qrcode-' + (root.getAttribute('data-event-id') || 'event');
    }
    reader.innerHTML = '';
    reader.hidden = false;
    root._takaCameraMode = 'scan';
    setModeButtons(root, 'scan');
    appendDebug(root, 'Initializing html5-qrcode scanner');

    startHtml5Scanner(root, Html5Qrcode, reader, 0).then(function () {
      root._takaCameraActive = true;
      root._takaCameraMode = 'scan';
      setModeButtons(root, 'scan');
      setDiagnostic(root, 'scanner', 'ok', label(root, 'waiting-qr', 'Waiting for QR code...'));
      appendDebug(root, 'html5-qrcode initialized');
      appendDebug(root, 'Waiting for QR code');
      setResult(root, 'info', label(root, 'scanner-running', 'Camera scanner is running.'));
    }).catch(function (error) {
      logError('Starting html5-qrcode failed', error);
      setDiagnostic(root, 'scanner', 'error', error._takaCameraMessage || (error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.')));
      appendDebug(root, 'html5-qrcode initialization failed', error);
      setResult(root, 'invalid', error._takaCameraMessage || (error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.')));
    });
    return true;
  }

  function startScanner(root) {
    var video = root.querySelector('[data-taka-scan-video]');
    var reader = root.querySelector('[data-taka-html5-reader]');
    var Html5Qrcode = window.Html5Qrcode || (window.__Html5QrcodeLibrary__ && window.__Html5QrcodeLibrary__.Html5Qrcode);
    var supportError = cameraSupportError(root);
    var detector;
    var active = true;

    appendDebug(root, 'Initializing scanner');
    setResult(root, 'info', label(root, 'scanner-starting', 'Starting camera scanner...'));
    if (!validateConfig(root, 'scan')) {
      return;
    }
    stopCamera(root, false);
    enumerateCameras(root);

    if (supportError) {
      setDiagnostic(root, 'scanner', 'error', supportError);
      appendDebug(root, 'Scanner support check failed', supportError);
      setResult(root, 'invalid', supportError);
      return;
    }

    if (!('BarcodeDetector' in window)) {
      setDiagnostic(root, 'barcode-detector', 'warning', label(root, 'barcode-fallback', 'BarcodeDetector not available (using html5-qrcode fallback).'));
      appendDebug(root, 'BarcodeDetector not available; using html5-qrcode fallback');
      startHtml5ScannerUi(root, Html5Qrcode, reader);
      return;
    }

    try {
      detector = new window.BarcodeDetector({ formats: ['qr_code'] });
      setDiagnostic(root, 'barcode-detector', 'ok', label(root, 'barcode-ok', 'BarcodeDetector available.'));
      appendDebug(root, 'BarcodeDetector initialized');
    } catch (error) {
      logError('BarcodeDetector initialization failed', error);
      setDiagnostic(root, 'barcode-detector', 'warning', label(root, 'barcode-fallback', 'BarcodeDetector not available (using html5-qrcode fallback).'));
      appendDebug(root, 'BarcodeDetector initialization failed; using fallback', error);
      startHtml5ScannerUi(root, Html5Qrcode, reader);
      return;
    }

    getCameraStream(root, 0).then(function (stream) {
      return showCameraStream(root, stream, 'scan').then(function () {
        root._takaStopCamera = (function (originalStop) {
          return function () {
            active = false;
            if (originalStop) {
              originalStop();
            }
          };
        })(root._takaStopCamera);
      });
    }).then(function () {
      setDiagnostic(root, 'scanner', 'ok', label(root, 'waiting-qr', 'Waiting for QR code...'));
      appendDebug(root, 'Waiting for QR code');
      setResult(root, 'info', label(root, 'scanner-running', 'Camera scanner is running.'));

      function tick() {
        if (!active) {
          return;
        }
        detector.detect(video).then(function (codes) {
          if (codes && codes[0] && codes[0].rawValue) {
            appendDebug(root, 'QR code detected');
            handlePayload(root, codes[0].rawValue);
          }
        }).catch(function (error) {
          logError('BarcodeDetector frame failed', error);
        }).finally(function () {
          window.requestAnimationFrame(tick);
        });
      }

      tick();
    }).catch(function (error) {
      logError('Camera scanner failed', error);
      diagnoseCameraError(root, error);
      setDiagnostic(root, 'scanner', 'error', error._takaCameraMessage || error.message || label(root, 'camera-failed', 'Camera could not be opened.'));
      setResult(root, 'invalid', error._takaCameraMessage || error.message || label(root, 'camera-failed', 'Camera could not be opened.'));
    });
  }

  function scannerRootForTrigger(trigger) {
    var root = closestElement(trigger, '[data-taka-operations-scanner]');
    var target;

    if (root) {
      return root;
    }
    target = trigger.getAttribute('data-taka-scan-target');
    if (target) {
      return document.querySelector(target);
    }
    return document.querySelector('[data-taka-operations-scanner]');
  }

  function handleAction(root, action) {
    if (!root) {
      logError('Scanner root missing', action);
      return;
    }

    try {
      if ('start' === action) {
        root.scrollIntoView({ block: 'start', behavior: 'smooth' });
        setResult(root, 'info', label(root, 'scan-clicked', 'Scan button clicked. Starting camera scanner...'));
        window.setTimeout(function () { startScanner(root); }, 50);
      } else if ('test' === action) {
        root.scrollIntoView({ block: 'start', behavior: 'smooth' });
        setResult(root, 'info', label(root, 'test-clicked', 'Test camera clicked. Starting camera test...'));
        window.setTimeout(function () { testCamera(root); }, 50);
      } else if ('stop' === action) {
        setResult(root, 'info', label(root, 'stop-clicked', 'Stop camera clicked.'));
        stopCamera(root, true);
      } else if ('stop-scanning' === action) {
        setResult(root, 'info', label(root, 'stop-scanning-clicked', 'Stop scanning clicked.'));
        stopCamera(root, true);
      } else if ('load' === action) {
        setOfflineStatus(root, label(root, 'offline-clicked', 'Load offline clicked.'));
        window.setTimeout(function () { loadOfflineData(root); }, 50);
      } else if ('sync' === action) {
        setOfflineStatus(root, label(root, 'sync-clicked', 'Synchronize clicked.'));
        window.setTimeout(function () { syncOffline(root); }, 50);
      } else if ('copy-debug' === action) {
        copyDebug(root);
      }
    } catch (error) {
      logError('Check-in action failed', error);
      setResult(root, 'invalid', error && error.message ? error.message : String(error));
    }
  }

  function bindButton(root, selector, action) {
    var button = root.querySelector(selector);

    if (!button) {
      logWarn('Check-in button missing: ' + selector);
      return;
    }
    if (button._takaEventOperationsBound) {
      return;
    }
    button._takaEventOperationsBound = true;
    button.addEventListener('click', function (event) {
      event._takaEventOperationsHandled = true;
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      handleAction(root, action);
    });
  }

  function bindScanner(root) {
    if (!root) {
      return;
    }
    setResult(root, 'info', label(root, 'js-loaded', 'Check-in JavaScript loaded.'));
    initializeDiagnostics(root);
    validateConfig(root, 'all');
    bindButton(root, '#taka-scan-qr', 'start');
    bindButton(root, '#taka-test-camera', 'test');
    bindButton(root, '#taka-stop-camera', 'stop');
    bindButton(root, '#taka-stop-scanning', 'stop-scanning');
    bindButton(root, '#taka-load-offline', 'load');
    bindButton(root, '#taka-sync-offline', 'sync');
    bindButton(root, '#taka-copy-debug', 'copy-debug');
    refreshPending(root);
  }

  function bindAll() {
    document.querySelectorAll('[data-taka-operations-scanner]').forEach(bindScanner);
  }

  document.addEventListener('click', function (event) {
    var start = closestElement(event.target, '[data-taka-scan-start]');
    var test = closestElement(event.target, '[data-taka-camera-test]');
    var stop = closestElement(event.target, '[data-taka-scan-stop]');
    var stopScanning = closestElement(event.target, '[data-taka-stop-scanning]');
    var load = closestElement(event.target, '[data-taka-offline-load]');
    var sync = closestElement(event.target, '[data-taka-offline-sync]');
    var copyDebugButton = closestElement(event.target, '[data-taka-copy-debug]');
    var trigger = start || test || stop || stopScanning || load || sync || copyDebugButton;
    var root = trigger ? scannerRootForTrigger(trigger) : null;

    if (!trigger || event._takaEventOperationsHandled) {
      return;
    }
    event._takaEventOperationsHandled = true;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    if (start) {
      handleAction(root, 'start');
    } else if (test) {
      handleAction(root, 'test');
    } else if (stop) {
      handleAction(root, 'stop');
    } else if (stopScanning) {
      handleAction(root, 'stop-scanning');
    } else if (load) {
      handleAction(root, 'load');
    } else if (sync) {
      handleAction(root, 'sync');
    } else if (copyDebugButton) {
      handleAction(root, 'copy-debug');
    }
  }, true);

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', bindAll);
  } else {
    bindAll();
  }
})();
