document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('open');
  });

  const offlineBanner = document.querySelector('[data-offline-banner]');
  const syncOfflineState = () => {
    if (!offlineBanner) return;
    offlineBanner.hidden = navigator.onLine;
  };
  window.addEventListener('online', syncOfflineState);
  window.addEventListener('offline', syncOfflineState);
  syncOfflineState();

  const toastRegion = document.querySelector('[data-toast-region]');
  const showToast = (message, type = 'info') => {
    if (!toastRegion || !message) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.setAttribute('aria-label', 'Dismiss message');
    dismiss.textContent = '×';
    toast.append(text, dismiss);
    toastRegion.append(toast);
    const close = () => toast.remove();
    dismiss.addEventListener('click', close);
    window.setTimeout(close, type === 'error' ? 5200 : 3600);
  };

  document.querySelectorAll('[data-dismiss-alert]').forEach((button) => {
    button.addEventListener('click', () => button.closest('.flash')?.remove());
  });
  document.querySelectorAll('[data-flash-alert]').forEach((alert) => {
    window.setTimeout(() => alert.remove(), 7200);
  });

  const confirmModal = document.querySelector('[data-confirm-modal]');
  const confirmMessage = confirmModal?.querySelector('[data-confirm-message]');
  const confirmAccept = confirmModal?.querySelector('[data-confirm-accept]');
  const confirmCancelButtons = confirmModal ? [...confirmModal.querySelectorAll('[data-confirm-cancel]')] : [];
  let pendingConfirm = null;
  const askConfirm = (message) => new Promise((resolve) => {
    if (!confirmModal || !confirmMessage || !confirmAccept) {
      resolve(true);
      return;
    }
    confirmMessage.textContent = message || 'Are you sure?';
    confirmModal.hidden = false;
    confirmAccept.focus();
    pendingConfirm = resolve;
  });
  const closeConfirm = (value) => {
    if (!pendingConfirm || !confirmModal) return;
    confirmModal.hidden = true;
    const resolve = pendingConfirm;
    pendingConfirm = null;
    resolve(value);
  };
  confirmAccept?.addEventListener('click', () => closeConfirm(true));
  confirmCancelButtons.forEach((button) => button.addEventListener('click', () => closeConfirm(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && confirmModal?.hidden === false) closeConfirm(false);
  });

  document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const layout = button.closest('[data-browse-layout]') || document.querySelector('[data-browse-layout]');
      const filters = layout?.querySelector('[data-filters], .filters') || document.querySelector('[data-filters], .filters');
      if (layout) {
        const shouldOpen = layout.classList.contains('filters-collapsed');
        layout.classList.toggle('filters-collapsed', !shouldOpen);
        layout.classList.toggle('filters-open', shouldOpen);
        layout.querySelectorAll('[data-filter-toggle]').forEach((toggle) => {
          toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });
      }
      filters?.classList.toggle('open');
    });
  });

  const grid = document.querySelector('[data-results-grid]');
  document.querySelector('[data-grid-toggle]')?.addEventListener('click', () => grid?.classList.toggle('list-mode'));

  const galleryMain = document.querySelector('[data-gallery-main]');
  const lightbox = document.querySelector('[data-lightbox]');
  const lightboxImage = document.querySelector('[data-lightbox-image]');
  const galleryCaption = document.querySelector('[data-gallery-caption]');
  const lightboxCaption = document.querySelector('[data-lightbox-caption]');
  const thumbs = [...document.querySelectorAll('[data-thumb]')];
  let activeImageIndex = 0;
  const setGalleryImage = (index) => {
    if (!galleryMain || thumbs.length === 0) return;
    activeImageIndex = (index + thumbs.length) % thumbs.length;
    const src = thumbs[activeImageIndex].dataset.thumb;
    const title = thumbs[activeImageIndex].dataset.title || '';
    galleryMain.src = src;
    galleryMain.alt = title;
    if (galleryCaption) galleryCaption.textContent = title;
    if (lightboxImage) {
      lightboxImage.src = src;
      lightboxImage.alt = title;
    }
    if (lightboxCaption) lightboxCaption.textContent = title;
    thumbs.forEach((button, i) => {
      button.classList.toggle('active', i === activeImageIndex);
      if (i === activeImageIndex) button.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    });
  };
  thumbs.forEach((button, index) => {
    button.addEventListener('click', () => setGalleryImage(index));
  });
  document.querySelectorAll('[data-gallery-prev]').forEach((button) => {
    button.addEventListener('click', () => setGalleryImage(activeImageIndex - 1));
  });
  document.querySelectorAll('[data-gallery-next]').forEach((button) => {
    button.addEventListener('click', () => setGalleryImage(activeImageIndex + 1));
  });
  galleryMain?.addEventListener('click', () => {
    if (!lightbox || !lightboxImage) return;
    lightboxImage.src = galleryMain.src;
    lightbox.hidden = false;
    document.body.classList.add('lightbox-open');
  });
  document.querySelector('[data-lightbox-close]')?.addEventListener('click', () => {
    if (!lightbox) return;
    lightbox.hidden = true;
    document.body.classList.remove('lightbox-open');
  });
  lightbox?.addEventListener('click', (event) => {
    if (event.target === lightbox) {
      lightbox.hidden = true;
      document.body.classList.remove('lightbox-open');
    }
  });
  document.addEventListener('keydown', (event) => {
    if (lightbox?.hidden === false && event.key === 'Escape') {
      lightbox.hidden = true;
      document.body.classList.remove('lightbox-open');
    } else if (galleryMain && event.key === 'ArrowLeft') {
      setGalleryImage(activeImageIndex - 1);
    } else if (galleryMain && event.key === 'ArrowRight') {
      setGalleryImage(activeImageIndex + 1);
    }
  });
  setGalleryImage(0);

  const saved = new Set(JSON.parse(localStorage.getItem('kinyan_saved_cars') || '[]'));
  const saveSavedCars = () => localStorage.setItem('kinyan_saved_cars', JSON.stringify([...saved]));
  const syncSaveButtons = (root = document) => {
    root.querySelectorAll('[data-save-car]').forEach((button) => {
      if (button.dataset.saveReady) return;
      button.dataset.saveReady = '1';
      const id = button.dataset.saveCar;
      if (saved.has(id)) {
        button.classList.add('saved');
        button.textContent = '♥';
      }
      button.addEventListener('click', (event) => {
        event.preventDefault();
        saved.has(id) ? saved.delete(id) : saved.add(id);
        saveSavedCars();
        button.classList.toggle('saved');
        button.textContent = saved.has(id) ? '♥' : '♡';
        const savedPage = document.querySelector('[data-saved-page]');
        if (savedPage && !saved.has(id)) {
          button.closest('.car-card')?.remove();
          const remaining = document.querySelectorAll('[data-saved-results] .car-card').length;
          const status = document.querySelector('[data-saved-status]');
          if (status) status.textContent = remaining ? `${remaining} saved ${remaining === 1 ? 'listing' : 'listings'}` : 'No saved listings yet.';
          if (!remaining) {
            const results = document.querySelector('[data-saved-results]');
            if (results) results.innerHTML = '<div class="empty-state"><h3>No saved cars yet</h3><p>Tap the heart on a car to save it here for later.</p><a class="button" href="cars.php">Browse cars</a></div>';
          }
        }
      });
    });
  };
  syncSaveButtons();

  const savedPage = document.querySelector('[data-saved-page]');
  if (savedPage) {
    const results = savedPage.querySelector('[data-saved-results]');
    const status = savedPage.querySelector('[data-saved-status]');
    const ids = [...saved];
    if (!ids.length) {
      if (status) status.textContent = 'No saved listings yet.';
      if (results) results.innerHTML = '<div class="empty-state"><h3>No saved cars yet</h3><p>Tap the heart on a car to save it here for later.</p><a class="button" href="cars.php">Browse cars</a></div>';
    } else {
      fetch(`saved.php?partial=1&ids=${encodeURIComponent(ids.join(','))}`, { headers: { Accept: 'text/html' } })
        .then((response) => {
          if (!response.ok) throw new Error('Saved listings could not load.');
          return response.text();
        })
        .then((html) => {
          if (results) {
            results.innerHTML = html;
            syncSaveButtons(results);
          }
          const count = results?.querySelectorAll('.car-card').length || 0;
          if (status) status.textContent = count ? `${count} saved ${count === 1 ? 'listing' : 'listings'}` : 'No saved listings found.';
        })
        .catch(() => {
          if (status) status.textContent = 'Saved listings could not load.';
          if (results) results.innerHTML = '<div class="empty-state"><h3>Could not load saved cars</h3><p>Please refresh the page or try again later.</p></div>';
        });
    }
  }

  const recentKey = 'kinyan_recent_cars';
  const listingMatch = location.pathname.match(/listing\.php/);
  const listingId = new URLSearchParams(location.search).get('id');
  if (listingMatch && listingId) {
    const recent = JSON.parse(localStorage.getItem(recentKey) || '[]').filter((id) => id !== listingId);
    recent.unshift(listingId);
    localStorage.setItem(recentKey, JSON.stringify(recent.slice(0, 12)));
  }

  const trackListingAction = (method) => {
    if (!method) return;
    const url = new URL(location.href);
    url.searchParams.set('contact', method);
    navigator.sendBeacon(url.toString());
  };

  document.querySelector('[data-copy-link]')?.addEventListener('click', async (event) => {
    trackListingAction(event.currentTarget.dataset.trackContact);
    try {
      await navigator.clipboard.writeText(location.href);
      showToast('Copied to clipboard.', 'success');
    } catch {
      showToast('Failed to copy link. Please try again.', 'error');
    }
  });
  document.querySelector('[data-share]')?.addEventListener('click', async (event) => {
    trackListingAction(event.currentTarget.dataset.trackContact);
    const text = event.currentTarget.dataset.shareText || document.querySelector('meta[name="description"]')?.content || '';
    try {
      if (navigator.share) await navigator.share({ title: document.title, text, url: location.href });
      else await navigator.clipboard.writeText(location.href);
      showToast(navigator.share ? 'Share opened.' : 'Share link copied to clipboard.', 'success');
    } catch {
      showToast('Failed to open share. Please try again.', 'error');
    }
  });

  document.querySelectorAll('[data-track-contact]').forEach((link) => {
    if (link.matches('[data-copy-link], [data-share]')) return;
    link.addEventListener('click', () => {
      trackListingAction(link.dataset.trackContact);
      let opened = false;
      const markOpened = () => { opened = true; };
      window.addEventListener('blur', markOpened, { once: true });
      document.addEventListener('visibilitychange', markOpened, { once: true });
      window.setTimeout(() => {
        window.removeEventListener('blur', markOpened);
        document.removeEventListener('visibilitychange', markOpened);
        if (!opened && document.visibilityState === 'visible') {
          showToast('Failed to open. Please try again.', 'error');
        }
      }, 1400);
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const submit = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
      const message = submit?.dataset.confirm || form.dataset.confirm;
      if (message && form.dataset.confirmed !== 'true') {
        event.preventDefault();
        askConfirm(message).then((confirmed) => {
          if (!confirmed) return;
          form.dataset.confirmed = 'true';
          if (submit) form.requestSubmit(submit);
          else form.requestSubmit();
        });
        return;
      }
      delete form.dataset.confirmed;
      if (form.matches('[data-upload-progress]')) {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';

        const files = form.querySelector('input[name="images[]"]')?.files?.length || 0;
        const hasReport = (form.querySelector('input[name="history_report"]')?.files?.length || 0) > 0;
        const uploadLabel = files && hasReport ? `${files} photo${files === 1 ? '' : 's'} and a history report` : files ? `${files} photo${files === 1 ? '' : 's'}` : hasReport ? 'a history report' : '';
        const status = form.querySelector('[data-upload-status]');
        const stage = form.querySelector('[data-upload-stage]');
        const percentText = form.querySelector('[data-upload-percent]');
        const bar = form.querySelector('[data-upload-bar]');
        const detail = form.querySelector('[data-upload-detail]');
        const setProgress = (percent, title, text) => {
          const value = Math.max(0, Math.min(100, Math.round(percent)));
          if (stage) stage.textContent = title;
          if (percentText) percentText.textContent = `${value}%`;
          if (bar) bar.style.width = `${value}%`;
          if (detail) detail.textContent = text;
        };

        status.hidden = false;
        setProgress(4, 'Checking listing details', uploadLabel ? `Preparing ${uploadLabel} for upload.` : 'Submitting your listing without new files.');
        if (submit && submit.classList.contains('button')) {
          submit.dataset.originalText = submit.textContent;
          submit.textContent = uploadLabel ? 'Uploading...' : 'Submitting...';
          submit.setAttribute('aria-busy', 'true');
          submit.disabled = true;
        }

        const xhr = new XMLHttpRequest();
        let processingTimer = null;
        xhr.open(form.method || 'POST', form.action || location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', (progress) => {
          if (!progress.lengthComputable) {
            setProgress(20, 'Uploading files', 'Uploading your selected files. Larger files can take a little longer.');
            return;
          }
          const uploaded = progress.loaded / progress.total;
          const percent = 8 + uploaded * 77;
          setProgress(percent, 'Uploading files', `${uploadLabel || 'Your files'} ${Math.round(uploaded * 100)}% uploaded.`);
        });
        xhr.upload.addEventListener('load', () => {
          let value = 86;
          setProgress(value, files > 1 ? 'Optimizing photos' : 'Saving listing', files ? 'The server is checking and optimizing each photo now.' : 'Saving your listing now.');
          processingTimer = window.setInterval(() => {
            value = Math.min(97, value + (files > 4 ? 1 : 2));
            setProgress(value, files > 1 ? 'Optimizing photos' : 'Saving listing', files > 4 ? 'Several photos are being re-encoded. Keep this page open.' : 'Almost done. Keep this page open.');
          }, 900);
        });
        xhr.addEventListener('load', () => {
          if (processingTimer) window.clearInterval(processingTimer);
          setProgress(100, 'Done', 'Redirecting you now.');
          try {
            const response = JSON.parse(xhr.responseText);
            window.location.href = response.redirect || xhr.responseURL || 'dashboard.php';
          } catch (error) {
            document.open();
            document.write(xhr.responseText);
            document.close();
          }
        });
        xhr.addEventListener('error', () => {
          if (processingTimer) window.clearInterval(processingTimer);
          form.dataset.submitting = 'false';
          if (submit && submit.classList.contains('button')) {
            submit.textContent = submit.dataset.originalText || 'Submit listing';
            submit.removeAttribute('aria-busy');
            submit.disabled = false;
          }
          setProgress(100, 'Upload interrupted', 'The upload did not finish. Please check your connection and try again.');
        });
        xhr.send(new FormData(form));
        return;
      }
      const browseMain = form.closest('.browse-layout')?.querySelector('.browse-main');
      if (browseMain) {
        const results = browseMain.querySelector('[data-results-grid], .stack');
        if (results) {
          const count = results.classList.contains('stack') ? 4 : 8;
          results.innerHTML = Array.from({ length: count }, () => '<article class="skeleton-card"><div></div><span></span><span></span><span></span></article>').join('');
          results.classList.add('is-loading');
        }
      }
      if (submit && submit.classList.contains('button')) {
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Working...';
        submit.setAttribute('aria-busy', 'true');
      }
    });
  });

  document.querySelectorAll('a[data-confirm]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      askConfirm(link.dataset.confirm).then((confirmed) => {
        if (confirmed) window.location.href = link.href;
      });
    });
  });

  document.querySelectorAll('[data-confirm-check]').forEach((input) => {
    input.addEventListener('change', (event) => {
      if (!input.checked) return;
      event.preventDefault();
      input.checked = false;
      askConfirm(input.dataset.confirmCheck).then((confirmed) => {
        input.checked = confirmed;
      });
    });
  });

  const leaseToggle = document.querySelector('[data-lease-toggle]');
  const leaseFields = document.querySelector('[data-lease-fields]');
  const priceField = document.querySelector('[data-price-field]');
  const priceLabel = document.querySelector('[data-price-label]');
  const priceInput = priceField?.querySelector('input[name="price"]');
  const leaseEndDate = document.querySelector('[data-lease-end-date]');
  const leaseMonthsDisplay = document.querySelector('[data-lease-months-display]');
  const leaseRequiredInputs = document.querySelectorAll('[data-required-when-lease]');

  const monthsUntil = (value) => {
    if (!value) return '';
    const today = new Date();
    const end = new Date(`${value}T00:00:00`);
    if (Number.isNaN(end.getTime()) || end <= today) return '0';
    let months = (end.getFullYear() - today.getFullYear()) * 12 + (end.getMonth() - today.getMonth());
    if (end.getDate() > today.getDate()) months += 1;
    return String(Math.max(months, 0));
  };

  const syncLeaseFields = () => {
    if (!leaseFields || !leaseToggle) return;
    const isLease = leaseToggle.checked;
    leaseFields.hidden = !isLease;
    if (priceField && priceLabel && priceInput) {
      priceField.hidden = isLease;
      priceInput.required = !isLease;
      priceLabel.textContent = isLease ? 'Takeover amount due' : 'Asking price';
    }
    leaseRequiredInputs.forEach((input) => {
      input.required = isLease;
    });
    if (leaseMonthsDisplay && leaseEndDate) {
      leaseMonthsDisplay.value = monthsUntil(leaseEndDate.value);
    }
  };
  leaseToggle?.addEventListener('change', syncLeaseFields);
  leaseEndDate?.addEventListener('change', syncLeaseFields);
  syncLeaseFields();

  const fetchVinDetails = async (vin) => {
    if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(vin)) {
      throw new Error('Enter a valid 17-character VIN first.');
    }
    const response = await fetch(`vin-lookup.php?vin=${encodeURIComponent(vin)}`, { headers: { Accept: 'application/json' } });
    if (response.redirected && response.url.includes('login.php')) {
      throw new Error('Please log in to use VIN check.');
    }
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'VIN lookup failed.');
    return data;
  };

  const vinButton = document.querySelector('[data-vin-lookup]');
  vinButton?.addEventListener('click', async () => {
    const vinInput = document.querySelector('input[name="vin"]');
    const status = document.querySelector('[data-vin-status]');
    const vin = (vinInput?.value || '').trim().toUpperCase();
    if (!vinInput || !status) return;
    vinButton.disabled = true;
    status.textContent = 'Checking VIN...';
    status.className = 'vin-status';
    try {
      const data = await fetchVinDetails(vin);
      const setValue = (name, value) => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field && value) field.value = value;
      };
      const setSelect = (name, value) => {
        const field = document.querySelector(`select[name="${name}"]`);
        if (!field || !value) return;
        const normalized = value.toLowerCase();
        const option = [...field.options].find((item) => item.value.toLowerCase() === normalized || normalized.includes(item.value.toLowerCase()));
        if (option) field.value = option.value;
      };
      setValue('year', data.year);
      setValue('make', data.make);
      setValue('model', data.model);
      setValue('trim', data.trim);
      setValue('drivetrain', data.drivetrain);
      setValue('engine', data.engine);
      setSelect('body_type', data.body_type);
      setSelect('fuel_type', data.fuel_type);
      setSelect('transmission', data.transmission);
      const title = document.querySelector('[name="title"]');
      if (title && !title.value.trim()) {
        title.value = [data.year, data.make, data.model, data.trim].filter(Boolean).join(' ');
      }
      status.textContent = 'VIN details added. Please review and edit anything that needs correction.';
      status.className = 'vin-status success';
    } catch (error) {
      status.textContent = error.message || 'VIN lookup failed. You can still fill the fields manually.';
      status.className = 'vin-status error';
    } finally {
      vinButton.disabled = false;
    }
  });

  const vinCheckButton = document.querySelector('[data-vin-check]');
  vinCheckButton?.addEventListener('click', async () => {
    const vinInput = document.querySelector('[data-vin-check-input]');
    const status = document.querySelector('[data-vin-check-status]');
    const results = document.querySelector('[data-vin-check-results]');
    const historyNote = document.querySelector('[data-vin-history-note]');
    const vin = (vinInput?.value || '').trim().toUpperCase();
    if (!vinInput || !status || !results) return;
    vinCheckButton.disabled = true;
    status.textContent = 'Checking VIN...';
    status.className = 'vin-status';
    results.hidden = true;
    if (historyNote) historyNote.hidden = true;
    results.replaceChildren();
    if (historyNote) historyNote.replaceChildren();
    try {
      const data = await fetchVinDetails(vin);
      (data.details || []).forEach((group) => {
        const section = document.createElement('section');
        section.className = 'vin-result-section';
        const heading = document.createElement('h2');
        const grid = document.createElement('div');
        grid.className = 'spec-grid';
        heading.textContent = group.title;
        (group.items || []).forEach(([label, value]) => {
          if (!value) return;
          const item = document.createElement('div');
          const name = document.createElement('span');
          const detail = document.createElement('strong');
          name.textContent = label;
          detail.textContent = value;
          item.append(name, detail);
          grid.append(item);
        });
        if (grid.children.length) {
          section.append(heading, grid);
          results.append(section);
        }
      });
      if (!results.children.length) {
        throw new Error('VIN decoded, but no detailed fields were returned.');
      }
      results.hidden = false;
      if (historyNote && data.history_note) {
        const heading = document.createElement('h2');
        const note = document.createElement('p');
        heading.textContent = 'Mileage, title, and sale history';
        note.textContent = data.history_note;
        historyNote.append(heading, note);
        historyNote.hidden = false;
      }
      status.textContent = 'VIN details found. Confirm these details against the actual vehicle and title.';
      status.className = 'vin-status success';
    } catch (error) {
      status.textContent = error.message || 'VIN lookup failed. Please try again.';
      status.className = 'vin-status error';
    } finally {
      vinCheckButton.disabled = false;
    }
  });
});
