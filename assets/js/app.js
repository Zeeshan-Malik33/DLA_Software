// =========================================================
// Lightweight SPA-style navigation.
// The sidebar (and its Business Summary numbers) is rendered
// once on full page load and never touched again — only the
// #page-content region is swapped when navigating between
// Dashboard / Customer Management / etc.
// =========================================================

const CITIES_BY_COUNTRY = {
  'Pakistan': ['Lahore', 'Karachi', 'Islamabad', 'Peshawar', 'Rawalpindi'],
  'Afghanistan': ['Kabul', 'Herat', 'Kandahar', 'Mazar-i-Sharif'],
  'Australia': ['Sydney', 'Melbourne', 'Perth', 'Brisbane'],
  'Norway': ['Oslo', 'Bergen', 'Trondheim'],
  'United States': ['New York', 'Los Angeles', 'Chicago', 'Houston'],
  'United Kingdom': ['London', 'Manchester', 'Birmingham'],
  'Canada': ['Toronto', 'Vancouver', 'Montreal'],
  'Germany': ['Berlin', 'Munich', 'Hamburg'],
  'France': ['Paris', 'Lyon', 'Marseille'],
  'United Arab Emirates': ['Dubai', 'Abu Dhabi', 'Sharjah'],
};

let revenueChartInstance = null;
let statusChartInstance = null;

// ---------------------------------------------------------
// Navigation core
// ---------------------------------------------------------
async function navigateTo(url, push = true) {
  const content = document.getElementById('page-content');
  if (!content) { window.location.href = url; return; }

  content.classList.add('opacity-50', 'pointer-events-none');
  try {
    const sep = url.includes('?') ? '&' : '?';
    const res = await fetch(url + sep + 'partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load page');
    const html = await res.text();
    content.innerHTML = html;
    if (push) history.pushState({}, '', url);
    setActiveSidebarLink(url);
    initPageScripts();
    window.scrollTo({ top: 0, behavior: 'instant' });
    closeMobileSidebar();
  } catch (err) {
    console.error(err);
    window.location.href = url; // fall back to a normal page load
  } finally {
    content.classList.remove('opacity-50', 'pointer-events-none');
  }
}

function setActiveSidebarLink(url) {
  let page = null;
  if (url.includes('/dashboard/')) page = 'dashboard';
  else if (url.includes('/customer/')) page = 'customers';
  else if (url.includes('/order/')) page = 'orders';
  else if (url.includes('/payment/')) page = 'payments';
  else if (url.includes('/reports/')) page = 'reports';

  document.querySelectorAll('#sidebarNav a[data-page]').forEach((a) => {
    const isActive = a.dataset.page === page;
    a.classList.toggle('bg-white/15', isActive);
    a.classList.toggle('text-white', isActive);
    a.classList.toggle('bg-white/5', !isActive);
    a.classList.toggle('text-white/90', !isActive);
  });
}

function closeMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  if (sidebar && window.innerWidth < 768) sidebar.classList.add('hidden');
}

document.addEventListener('click', function (e) {
  const link = e.target.closest('[data-spa]');
  if (!link) return;
  e.preventDefault();
  navigateTo(link.getAttribute('href'), true);
});

window.addEventListener('popstate', function () {
  navigateTo(location.pathname + location.search, false);
});

// ---------------------------------------------------------
// Page-specific script initializers — called on first load
// AND after every SPA content swap.
// ---------------------------------------------------------
function initPageScripts() {
  initMobileMenuToggle();
  initCustomerForm();
  initFilterForm();
  initDeleteButtons();
  initDashboardCharts();
  initDashboardRangeForm();
}

function initDashboardRangeForm() {
  const form = document.getElementById('dashboardRangeForm');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('index.php' + (params ? '?' + params : ''), true);
  });
}

function initMobileMenuToggle() {
  const btn = document.getElementById('menuToggle');
  if (!btn || btn.dataset.bound) return;
  btn.dataset.bound = '1';
  btn.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('hidden');
  });
}

// ---------------------------------------------------------
// Add / Edit Customer form
// ---------------------------------------------------------
function initCustomerForm() {
  const form = document.getElementById('addCustomerForm');
  if (!form) return;

  const photoTrigger = document.getElementById('photoTrigger');
  const photoInput = document.getElementById('photoInput');
  const photoPreview = document.getElementById('photoPreview');
  const photoIcon = document.getElementById('photoPlaceholderIcon');
  const photoError = document.getElementById('photoError');

  photoTrigger.addEventListener('click', () => photoInput.click());
  photoInput.addEventListener('change', function () {
    const file = photoInput.files[0];
    photoError.classList.add('hidden');
    if (!file) return;

    if (!['image/jpeg', 'image/png'].includes(file.type)) {
      photoError.textContent = 'Only JPEG or PNG images are allowed.';
      photoError.classList.remove('hidden');
      photoInput.value = '';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      photoError.textContent = 'Image must be 2MB or smaller.';
      photoError.classList.remove('hidden');
      photoInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreview.src = e.target.result;
      photoPreview.classList.remove('hidden');
      photoIcon.classList.add('hidden');
    };
    reader.readAsDataURL(file);
  });

  // Dependent City dropdown
  const countrySelect = document.getElementById('countrySelect');
  const citySelect = document.getElementById('citySelect');
  function populateCities(selected) {
    const cities = CITIES_BY_COUNTRY[countrySelect.value] || [];
    citySelect.innerHTML = '<option value="">City</option>' +
      cities.map(c => `<option value="${c}" ${c === selected ? 'selected' : ''}>${c}</option>`).join('');
  }
  countrySelect.addEventListener('change', () => populateCities(null));
  populateCities(citySelect.dataset.selected || null);

  // Submit via fetch so the sidebar never reloads
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
    document.getElementById('formGeneralError').classList.add('hidden');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    const isEdit = form.dataset.mode === 'edit';
    const action = isEdit ? 'editcustomer.php' : 'addcustomer.php';

    try {
      const res = await fetch(action + (isEdit ? '?id=' + form.customer_id.value : ''), {
        method: 'POST',
        body: new FormData(form),
      });
      const data = await res.json();

      if (data.success) {
        navigateTo('listcustomer.php', true);
      } else {
        if (data.errors) {
          Object.entries(data.errors).forEach(([field, msg]) => {
            const el = form.querySelector(`.field-error[data-field="${field}"]`);
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            else if (field === 'photo') {
              photoError.textContent = msg;
              photoError.classList.remove('hidden');
            }
          });
        } else {
          const genEl = document.getElementById('formGeneralError');
          genEl.textContent = data.message || 'Something went wrong. Please try again.';
          genEl.classList.remove('hidden');
        }
      }
    } catch (err) {
      console.error(err);
      const genEl = document.getElementById('formGeneralError');
      genEl.textContent = 'Network error. Please try again.';
      genEl.classList.remove('hidden');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
}

// ---------------------------------------------------------
// Customer list filters
// ---------------------------------------------------------
function initFilterForm() {
  const form = document.getElementById('customerFilterForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('listcustomer.php' + (params ? '?' + params : ''), true);
  });

  const resetBtn = document.getElementById('resetFiltersBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => navigateTo('listcustomer.php', true));
  }
}

// ---------------------------------------------------------
// Delete customer (list + profile page)
// ---------------------------------------------------------
function initDeleteButtons() {
  document.querySelectorAll('[data-delete-customer]').forEach((btn) => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async function () {
      if (!confirm('Delete this customer? This cannot be undone.')) return;
      const id = btn.dataset.deleteCustomer;
      try {
        const res = await fetch('listcustomer.php?action=delete&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listcustomer.php', true);
        } else {
          alert(data.message || 'Failed to delete this customer.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    });
  });
}

// ---------------------------------------------------------
// Dashboard charts (data comes from data-* attributes so it
// survives being injected via innerHTML during SPA navigation)
// ---------------------------------------------------------
function initDashboardCharts() {
  const revenueCanvas = document.getElementById('revenueChart');
  if (revenueCanvas && window.Chart) {
    if (revenueChartInstance) revenueChartInstance.destroy();
    const labels = JSON.parse(revenueCanvas.dataset.labels || '[]');
    const revenue = JSON.parse(revenueCanvas.dataset.revenue || '[]');
    const cost = JSON.parse(revenueCanvas.dataset.cost || '[]');

    revenueChartInstance = new Chart(revenueCanvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Revenue', data: revenue, backgroundColor: '#A5B4FC', borderRadius: 4 },
          { label: 'Cost', data: cost, backgroundColor: '#E0E7FF', borderRadius: 4 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: '#F3F4F6' }, ticks: { color: '#9CA3AF' } },
          x: { grid: { display: false }, ticks: { color: '#9CA3AF' } },
        },
      },
    });
  }

  const statusCanvas = document.getElementById('statusChart');
  if (statusCanvas && window.Chart) {
    if (statusChartInstance) statusChartInstance.destroy();
    const labels = JSON.parse(statusCanvas.dataset.labels || '[]');
    const data = JSON.parse(statusCanvas.dataset.values || '[]');
    const colorMap = { Delivered: '#10B981', Shipped: '#3B82F6', Processing: '#F59E0B', Pending: '#9CA3AF', Cancelled: '#EF4444' };

    statusChartInstance = new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data, backgroundColor: labels.map(l => colorMap[l] || '#9CA3AF'), borderWidth: 0 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: { legend: { display: false } },
      },
    });
  }
}

document.addEventListener('DOMContentLoaded', function () {
  initPageScripts();
  setActiveSidebarLink(location.pathname);
});
