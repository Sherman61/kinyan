document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('open');
  });

  document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
    button.addEventListener('click', () => document.querySelector('[data-filters], .filters')?.classList.toggle('open'));
  });

  const grid = document.querySelector('[data-results-grid]');
  document.querySelector('[data-grid-toggle]')?.addEventListener('click', () => grid?.classList.toggle('list-mode'));

  const galleryMain = document.querySelector('[data-gallery-main]');
  const lightbox = document.querySelector('[data-lightbox]');
  const lightboxImage = document.querySelector('[data-lightbox-image]');
  const thumbs = [...document.querySelectorAll('[data-thumb]')];
  let activeImageIndex = 0;
  const setGalleryImage = (index) => {
    if (!galleryMain || thumbs.length === 0) return;
    activeImageIndex = (index + thumbs.length) % thumbs.length;
    const src = thumbs[activeImageIndex].dataset.thumb;
    galleryMain.src = src;
    if (lightboxImage) lightboxImage.src = src;
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
  document.querySelectorAll('[data-save-car]').forEach((button) => {
    const id = button.dataset.saveCar;
    if (saved.has(id)) {
      button.classList.add('saved');
      button.textContent = '♥';
    }
    button.addEventListener('click', (event) => {
      event.preventDefault();
      saved.has(id) ? saved.delete(id) : saved.add(id);
      localStorage.setItem('kinyan_saved_cars', JSON.stringify([...saved]));
      button.classList.toggle('saved');
      button.textContent = saved.has(id) ? '♥' : '♡';
    });
  });

  const recentKey = 'kinyan_recent_cars';
  const listingMatch = location.pathname.match(/listing\.php/);
  const listingId = new URLSearchParams(location.search).get('id');
  if (listingMatch && listingId) {
    const recent = JSON.parse(localStorage.getItem(recentKey) || '[]').filter((id) => id !== listingId);
    recent.unshift(listingId);
    localStorage.setItem(recentKey, JSON.stringify(recent.slice(0, 12)));
  }

  document.querySelector('[data-copy-link]')?.addEventListener('click', async () => {
    await navigator.clipboard.writeText(location.href);
  });
  document.querySelector('[data-share]')?.addEventListener('click', async (event) => {
    const text = event.currentTarget.dataset.shareText || document.querySelector('meta[name="description"]')?.content || '';
    if (navigator.share) await navigator.share({ title: document.title, text, url: location.href });
    else await navigator.clipboard.writeText(location.href);
  });

  document.querySelectorAll('[data-track-contact]').forEach((link) => {
    link.addEventListener('click', () => {
      const url = new URL(location.href);
      url.searchParams.set('contact', link.dataset.trackContact);
      navigator.sendBeacon(url.toString());
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const submit = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
      const message = submit?.dataset.confirm || form.dataset.confirm;
      if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
      }
      if (form.matches('[data-upload-progress]')) {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';

        const files = form.querySelector('input[type="file"]')?.files?.length || 0;
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
        setProgress(4, 'Checking listing details', files ? `Preparing ${files} photo${files === 1 ? '' : 's'} for upload.` : 'Submitting your listing without new photos.');
        if (submit && submit.classList.contains('button')) {
          submit.dataset.originalText = submit.textContent;
          submit.textContent = files ? 'Uploading...' : 'Submitting...';
          submit.setAttribute('aria-busy', 'true');
          submit.disabled = true;
        }

        const xhr = new XMLHttpRequest();
        let processingTimer = null;
        xhr.open(form.method || 'POST', form.action || location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', (progress) => {
          if (!progress.lengthComputable) {
            setProgress(20, 'Uploading photos', 'Uploading your photos. Larger files can take a little longer.');
            return;
          }
          const uploaded = progress.loaded / progress.total;
          const percent = 8 + uploaded * 77;
          setProgress(percent, 'Uploading photos', `${files || 'Your'} photo${files === 1 ? '' : 's'} ${Math.round(uploaded * 100)}% uploaded.`);
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
      if (submit && submit.classList.contains('button')) {
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Working...';
        submit.setAttribute('aria-busy', 'true');
      }
    });
  });

  document.querySelectorAll('a[data-confirm]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (!window.confirm(link.dataset.confirm)) event.preventDefault();
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
});
