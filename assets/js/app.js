document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('open');
  });

  document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
    button.addEventListener('click', () => document.querySelector('[data-filters], .filters')?.classList.toggle('open'));
  });

  const grid = document.querySelector('[data-results-grid]');
  document.querySelector('[data-grid-toggle]')?.addEventListener('click', () => grid?.classList.toggle('list-mode'));

  document.querySelectorAll('[data-thumb]').forEach((button) => {
    button.addEventListener('click', () => {
      const main = document.querySelector('[data-gallery-main]');
      if (main) main.src = button.dataset.thumb;
    });
  });

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
    if (leaseMonthsDisplay && leaseEndDate) {
      leaseMonthsDisplay.value = monthsUntil(leaseEndDate.value);
    }
  };
  leaseToggle?.addEventListener('change', syncLeaseFields);
  leaseEndDate?.addEventListener('change', syncLeaseFields);
  syncLeaseFields();
});
