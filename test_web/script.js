(function () {
  'use strict';

  function showQueryMessage() {
    var area = document.getElementById('flash-area');
    if (!area) return;

    var messageByCode = {
      gagal: ['error', 'Username atau password salah.'],
      berhasil: ['success', 'Pendaftaran berhasil. Silakan masuk.'],
      logout: ['success', 'Anda sudah keluar dari akun.'],
      duplikat: ['error', 'Username sudah digunakan. Pilih username lain.'],
      validasi: ['error', 'Data belum valid. Periksa kembali isian Anda.']
    };
    var code = new URLSearchParams(window.location.search).get('pesan');
    if (!code || !messageByCode[code]) return;

    var message = document.createElement('div');
    message.className = 'flash-message flash-' + messageByCode[code][0];
    message.textContent = messageByCode[code][1];
    area.appendChild(message);
    window.setTimeout(function () {
      message.style.opacity = '0';
      window.setTimeout(function () {
        if (message.parentNode) message.parentNode.removeChild(message);
      }, 450);
    }, 5000);
  }

  function attachSearch(inputId, itemSelector, emptyId, countId, itemLabel, emptyStateId, filterConfig) {
    var input = document.getElementById(inputId);
    var empty = document.getElementById(emptyId);
    var count = document.getElementById(countId);
    var emptyState = document.getElementById(emptyStateId);
    if (!input) return;

    var items = document.querySelectorAll(itemSelector);
    var filterButtons = filterConfig ? document.querySelectorAll(filterConfig.buttonSelector) : [];
    var pagination = filterConfig && filterConfig.paginationId
      ? document.getElementById(filterConfig.paginationId)
      : null;
    var activeFilter = filterConfig ? filterConfig.allValue : '';
    var currentPage = 1;

    function renderPagination(pageCount) {
      if (!pagination) return;
      while (pagination.firstChild) pagination.removeChild(pagination.firstChild);
      pagination.hidden = pageCount <= 1;
      if (pageCount <= 1) return;

      function addButton(label, page, ariaLabel, isCurrent) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pagination-button' + (isCurrent ? ' is-current' : '');
        button.textContent = label;
        button.setAttribute('aria-label', ariaLabel);
        if (isCurrent) {
          button.setAttribute('aria-current', 'page');
          button.disabled = true;
        }
        button.addEventListener('click', function () {
          currentPage = page;
          updateResults();
        });
        pagination.appendChild(button);
      }

      if (currentPage > 1) addButton('Sebelumnya', currentPage - 1, 'Halaman sebelumnya', false);

      var firstPage = Math.max(1, currentPage - 2);
      var lastPage = Math.min(pageCount, firstPage + 4);
      firstPage = Math.max(1, lastPage - 4);
      if (firstPage > 1) {
        addButton('1', 1, 'Halaman 1', currentPage === 1);
        if (firstPage > 2) {
          var leadingGap = document.createElement('span');
          leadingGap.className = 'pagination-gap';
          leadingGap.textContent = '...';
          pagination.appendChild(leadingGap);
        }
      }
      for (var page = firstPage; page <= lastPage; page += 1) {
        if (page === 1 && firstPage > 1) continue;
        addButton(String(page), page, 'Halaman ' + page, page === currentPage);
      }
      if (lastPage < pageCount) {
        if (lastPage < pageCount - 1) {
          var trailingGap = document.createElement('span');
          trailingGap.className = 'pagination-gap';
          trailingGap.textContent = '...';
          pagination.appendChild(trailingGap);
        }
        addButton(String(pageCount), pageCount, 'Halaman ' + pageCount, currentPage === pageCount);
      }

      if (currentPage < pageCount) {
        addButton('Berikutnya', currentPage + 1, 'Halaman berikutnya', false);
      }
    }

    function updateResults() {
      var query = input.value.trim().toLocaleLowerCase();
      var matchingItems = [];
      Array.prototype.forEach.call(items, function (item) {
        var matchesSearch = item.textContent.toLocaleLowerCase().indexOf(query) !== -1;
        var matchesFilter = !filterConfig
          || activeFilter === filterConfig.allValue
          || item.getAttribute(filterConfig.attribute) === activeFilter;
        if (matchesSearch && matchesFilter) matchingItems.push(item);
      });
      var pageSize = filterConfig && filterConfig.pageSize ? filterConfig.pageSize : matchingItems.length || 1;
      var pageCount = Math.ceil(matchingItems.length / pageSize);
      currentPage = Math.min(currentPage, Math.max(pageCount, 1));
      var firstItemIndex = (currentPage - 1) * pageSize;
      var lastItemIndex = Math.min(firstItemIndex + pageSize, matchingItems.length);
      Array.prototype.forEach.call(items, function (item) {
        item.style.display = 'none';
      });
      matchingItems.forEach(function (item, index) {
        item.style.display = index >= firstItemIndex && index < lastItemIndex ? '' : 'none';
      });
      if (empty) {
        empty.hidden = matchingItems.length !== 0
          || (query === '' && (!filterConfig || activeFilter === filterConfig.allValue))
          || (filterConfig && items.length === 0 && query === '');
      }
      if (emptyState) {
        emptyState.hidden = query !== ''
          || (filterConfig && activeFilter !== filterConfig.allValue && items.length > 0);
      }
      if (count) {
        if (filterConfig && filterConfig.pageSize) {
          count.textContent = matchingItems.length
            ? 'Menampilkan ' + (firstItemIndex + 1) + '–' + lastItemIndex + ' dari ' + matchingItems.length + ' ' + itemLabel
            : 'Menampilkan 0 dari 0 ' + itemLabel;
        } else {
          count.textContent = 'Menampilkan ' + matchingItems.length + ' dari ' + items.length + ' ' + itemLabel;
        }
      }
      renderPagination(pageCount);
    }

    input.addEventListener('input', function () {
      currentPage = 1;
      updateResults();
    });
    Array.prototype.forEach.call(filterButtons, function (button) {
      button.addEventListener('click', function () {
        activeFilter = button.getAttribute('data-cat-filter');
        currentPage = 1;
        Array.prototype.forEach.call(filterButtons, function (filterButton) {
          var isActive = filterButton === button;
          filterButton.classList.toggle('is-active', isActive);
          filterButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        updateResults();
      });
    });
    updateResults();
  }

  function attachRegistrationValidation() {
    var form = document.getElementById('form-register');
    if (!form) return;

    form.addEventListener('submit', function (event) {
      var name = document.getElementById('nama_lengkap');
      var username = document.getElementById('reg_username');
      var password = document.getElementById('reg_password');
      var errors = {
        name: document.getElementById('error-nama'),
        username: document.getElementById('error-username'),
        password: document.getElementById('error-password')
      };
      var valid = true;
      var usernameLength = Array.from(username.value.trim()).length;
      errors.name.textContent = '';
      errors.username.textContent = '';
      errors.password.textContent = '';

      if (!name.value.trim()) {
        errors.name.textContent = 'Nama lengkap wajib diisi.';
        valid = false;
      }
      if (usernameLength < 3 || usernameLength > 50) {
        errors.username.textContent = 'Username harus 3-50 karakter.';
        valid = false;
      }
      if (password.value.length < 6) {
        errors.password.textContent = 'Password minimal 6 karakter.';
        valid = false;
      }

      if (!valid) event.preventDefault();
    });
  }

  function attachFormConfirmations() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (form.classList.contains('logout-form')) {
        if (!window.confirm('Apakah Anda yakin ingin logout?')) {
          event.preventDefault();
        }
        return;
      }

      if (!form.classList.contains('confirm-delete')) return;
      var item = form.getAttribute('data-item-name') || 'data ini';
      if (!window.confirm('Hapus ' + item + '? Tindakan ini tidak dapat dibatalkan.')) {
        event.preventDefault();
      }
    });
  }

  function attachGeolocation() {
    var button = document.getElementById('btn-gps');
    if (!button) return;

    button.addEventListener('click', function () {
      var status = document.getElementById('gps-status');
      var latitude = document.getElementById('latitude');
      var longitude = document.getElementById('longitude');
      if (!navigator.geolocation) {
        status.textContent = 'Fitur lokasi tidak didukung browser ini.';
        status.className = 'gps-status gps-error';
        return;
      }

      button.disabled = true;
      status.textContent = 'Meminta izin lokasi...';
      status.className = 'gps-status';
      navigator.geolocation.getCurrentPosition(function (position) {
        latitude.value = position.coords.latitude.toFixed(8);
        longitude.value = position.coords.longitude.toFixed(8);
        status.textContent = 'Koordinat berhasil diambil.';
        status.className = 'gps-status gps-success';
        button.disabled = false;
      }, function (error) {
        var messages = {
          1: 'Izin lokasi ditolak. Periksa pengaturan browser.',
          2: 'Lokasi tidak tersedia saat ini.',
          3: 'Permintaan lokasi melewati batas waktu.'
        };
        status.textContent = messages[error.code] || 'Tidak dapat mengambil lokasi.';
        status.className = 'gps-status gps-error';
        button.disabled = false;
      }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
  }

  function attachCatStatusDetails() {
    var status = document.getElementById('cat-status');
    var details = document.getElementById('cat-status-detail');
    var hint = document.getElementById('cat-status-detail-hint');
    if (!status || !details) return;

    function updateDetailsRequirement() {
      var isOther = status.value === 'lainnya';
      details.required = isOther;
      details.setAttribute('aria-required', isOther ? 'true' : 'false');
      details.placeholder = isOther ? 'Jelaskan kondisi atau alasan lainnya...' : 'Tambahkan keterangan jika diperlukan...';
      if (hint) {
        hint.textContent = isOther
          ? 'Wajib diisi untuk status Lainnya.'
          : 'Opsional untuk Hilang atau Sakit; wajib diisi untuk Lainnya.';
      }
    }

    status.addEventListener('change', updateDetailsRequirement);
    updateDetailsRequirement();
  }

  function attachPhotoPreview(inputId, previewId) {
    var input = document.getElementById(inputId);
    var preview = document.getElementById(previewId);
    if (!input || !preview || !window.URL || !window.URL.createObjectURL) return;

    var originalSource = preview.getAttribute('src') || '';
    var previewUrl = null;
    input.addEventListener('change', function () {
      if (previewUrl) window.URL.revokeObjectURL(previewUrl);

      var file = input.files && input.files[0];
      if (!file) {
        previewUrl = null;
        if (originalSource) {
          preview.src = originalSource;
          preview.hidden = false;
        } else {
          preview.removeAttribute('src');
          preview.hidden = true;
        }
        return;
      }

      previewUrl = window.URL.createObjectURL(file);
      preview.src = previewUrl;
      preview.hidden = false;
    });
  }

  function attachAuthBackgroundVideo() {
    var video = document.querySelector('.auth-background-video');
    if (!video) return;

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      video.preload = 'none';
      return;
    }

    var playRequest = video.play();
    if (playRequest && typeof playRequest.catch === 'function') {
      playRequest.catch(function () {
        video.hidden = true;
      });
    }
  }

  function attachFlashAutoHide() {
    Array.prototype.forEach.call(document.querySelectorAll('.main-content > .flash-message'), function (message) {
      window.setTimeout(function () {
        message.style.opacity = '0';
        window.setTimeout(function () {
          if (message.parentNode) message.parentNode.removeChild(message);
        }, 450);
      }, 5000);
    });
  }

  function attachPasswordToggles() {
    Array.prototype.forEach.call(document.querySelectorAll('.password-toggle'), function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-password-target'));
        if (!input) return;

        var visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? '\u{1F648}' : '\u{1F441}';
        button.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
      });
    });
  }

  function attachFormFeedback() {
    document.addEventListener('submit', function (event) {
      if (event.defaultPrevented) return;

      var form = event.target;
      var button = form.querySelector('button[type="submit"]');
      if (!button || button.disabled) return;

      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = 'Memproses...';
    });
  }

  function attachPageNavigation() {
    var header = document.querySelector('.navbar');
    var backToTop = document.getElementById('back-to-top');
    var scheduled = false;

    function updateNavigation() {
      var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      if (header) header.classList.toggle('is-scrolled', scrollTop > 8);
      if (backToTop) {
        var visible = scrollTop > 360;
        backToTop.classList.toggle('is-visible', visible);
        backToTop.setAttribute('aria-hidden', visible ? 'false' : 'true');
        backToTop.tabIndex = visible ? 0 : -1;
      }

    }

    function scheduleUpdate() {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(function () {
        updateNavigation();
        scheduled = false;
      });
    }

    if (backToTop) {
      backToTop.addEventListener('click', function (event) {
        event.preventDefault();
        var behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        window.scrollTo({ top: 0, behavior: behavior });
      });
    }

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    updateNavigation();
  }

  function attachThemeToggle() {
    var button = document.getElementById('theme-toggle');
    if (!button) return;

    var root = document.documentElement;
    function updateButton(theme) {
      var dark = theme === 'dark';
      button.textContent = dark ? '\u2600 Tema terang' : '\uD83C\uDF19 Tema gelap';
      button.setAttribute('aria-label', dark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap');
    }

    var currentTheme = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', currentTheme);
    updateButton(currentTheme);
    button.addEventListener('click', function () {
      currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', currentTheme);
      updateButton(currentTheme);
      try {
        window.localStorage.setItem('pawtrack-theme', currentTheme);
      } catch (error) {}
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    showQueryMessage();
    attachSearch('cari-kucing', '#tabel-kucing tbody tr', 'cats-no-results', 'cats-count', 'kucing', 'cats-empty-state', {
      buttonSelector: '.cat-status-filter',
      attribute: 'data-status',
      allValue: 'semua',
      pageSize: 5,
      paginationId: 'cats-pagination'
    });
    attachSearch('cari-log', '#tabel-log tbody tr', 'logs-no-results', 'logs-count', 'log', 'logs-empty-state');
    attachSearch('cari-admin-user', '#tabel-admin-users tbody tr', 'admin-users-no-results', 'admin-users-count', 'akun', 'admin-users-empty-state');
    attachRegistrationValidation();
    attachFormConfirmations();
    attachGeolocation();
    attachCatStatusDetails();
    attachPhotoPreview('cat-photo-file', 'cat-photo-preview');
    attachPhotoPreview('profile-photo-file', 'profile-photo-preview');
    attachAuthBackgroundVideo();
    attachFlashAutoHide();
    attachPasswordToggles();
    attachFormFeedback();
    attachPageNavigation();
    attachThemeToggle();
  });
}());
