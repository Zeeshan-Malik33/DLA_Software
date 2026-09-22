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
  else if (url.includes('/settings/')) page = 'settings';
  else if (url.includes('/expenses/')) page = 'expenses';

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

// ---------------------------------------------------------
// Custom delete confirmation modal
// Returns a Promise<boolean> — resolves true if user confirms
// ---------------------------------------------------------
function confirmDelete(title, body) {
  return new Promise((resolve) => {
    const modal   = document.getElementById('deleteModal');
    const box     = document.getElementById('deleteModalBox');
    const titleEl = document.getElementById('deleteModalTitle');
    const bodyEl  = document.getElementById('deleteModalBody');
    const btnOk   = document.getElementById('deleteModalConfirm');
    const btnCancel = document.getElementById('deleteModalCancel');
    const overlay = document.getElementById('deleteModalOverlay');
    if (!modal) { resolve(window.confirm(body)); return; }

    titleEl.textContent = title || 'Confirm Deletion';
    bodyEl.textContent  = body  || 'Are you sure? This action cannot be undone.';

    // Show
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
      box.classList.remove('scale-95', 'opacity-0');
      box.classList.add('scale-100', 'opacity-100');
    });

    function close(result) {
      box.classList.remove('scale-100', 'opacity-100');
      box.classList.add('scale-95', 'opacity-0');
      setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }, 180);
      btnOk.removeEventListener('click', onOk);
      btnCancel.removeEventListener('click', onCancel);
      overlay.removeEventListener('click', onCancel);
      resolve(result);
    }

    function onOk()     { close(true);  }
    function onCancel() { close(false); }

    btnOk.addEventListener('click', onOk);
    btnCancel.addEventListener('click', onCancel);
    overlay.addEventListener('click', onCancel);
  });
}

// ---------------------------------------------------------
// Toast notification (type: 'error' | 'success' | 'info')
// ---------------------------------------------------------
function showToast(message, type = 'error') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const colors = {
    error:   'bg-red-600 text-white',
    success: 'bg-emerald-600 text-white',
    info:    'bg-gray-800 text-white',
  };
  const icons = { error: 'ti-alert-circle', success: 'ti-circle-check', info: 'ti-info-circle' };

  const toast = document.createElement('div');
  toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium
    ${colors[type] || colors.info} transform translate-y-4 opacity-0 transition-all duration-300`;
  toast.innerHTML = `<i class="ti ${icons[type] || icons.info} text-lg flex-shrink-0"></i><span>${message}</span>`;

  container.appendChild(toast);
  requestAnimationFrame(() => {
    toast.classList.remove('translate-y-4', 'opacity-0');
  });
  setTimeout(() => {
    toast.classList.add('translate-y-4', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

document.addEventListener('click', function (e) {
  // Handle dropdown menus
  const isToggle = e.target.closest('.action-toggle');
  if (isToggle) {
    const dropdown = isToggle.nextElementSibling;
    if (dropdown && dropdown.classList.contains('action-dropdown')) {
      const isHidden = dropdown.classList.contains('hidden');
      document.querySelectorAll('.action-dropdown').forEach(el => el.classList.add('hidden'));
      if (isHidden) dropdown.classList.remove('hidden');
    }
    return;
  }
  
  if (!e.target.closest('.action-dropdown')) {
    document.querySelectorAll('.action-dropdown').forEach(el => el.classList.add('hidden'));
  }

  // Handle image preview
  const previewBtn = e.target.closest('[data-preview-src]');
  if (previewBtn) {
    const src = previewBtn.dataset.previewSrc;
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewModalImg');
    const dlBtn = document.getElementById('previewDownloadBtn');
    if (modal && img && dlBtn) {
      img.src = src;
      dlBtn.href = src;
      dlBtn.download = src.split('/').pop();
      modal.classList.remove('hidden');
    }
    return;
  }

  // Handle image preview close
  const closePreviewBtn = e.target.closest('#closePreviewModalBtn');
  if (closePreviewBtn) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewModalImg');
    if (modal) modal.classList.add('hidden');
    if (img) img.src = '';
    return;
  }

  // Handle click outside modal
  if (e.target.id === 'imagePreviewModal') {
    e.target.classList.add('hidden');
    const img = document.getElementById('previewModalImg');
    if (img) img.src = '';
    return;
  }

  // Handle SPA links
  const link = e.target.closest('[data-spa]');
  if (!link) return;
  e.preventDefault();
  navigateTo(link.getAttribute('href'), true);
});

window.addEventListener('popstate', function () {
  navigateTo(location.pathname + location.search, false);
});

// Intercept GET form submissions with data-spa-form and route via SPA
document.addEventListener('submit', function (e) {
  const form = e.target.closest('form[data-spa-form]');
  if (!form) return;
  e.preventDefault();
  const data = new FormData(form);
  const params = new URLSearchParams();
  for (const [key, value] of data.entries()) params.append(key, value);
  // Use getAttribute (raw value) not form.action (which returns the full resolved
  // URL including the current query string and would break on subsequent changes)
  const rawAction = form.getAttribute('action');
  const base = rawAction ? rawAction : location.pathname;
  navigateTo(base + '?' + params.toString(), true);
});

document.addEventListener('change', function (e) {
  if (e.target.classList.contains('filter-checkbox')) {
    const field = document.getElementById(e.target.value);
    if (field) {
      if (e.target.checked) field.classList.remove('hidden');
      else {
        field.classList.add('hidden');
        const input = field.querySelector('input, select');
        if (input) input.value = '';
      }
    }
    const form = document.getElementById('customerFilterForm');
    if (form) {
      const anyChecked = Array.from(document.querySelectorAll('.filter-checkbox')).some(cb => cb.checked);
      if (anyChecked) form.classList.remove('hidden');
      else {
        form.classList.add('hidden');
        // Auto submit to reset if we unchecked the last one
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }
    }
  }
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
  initAddOrderForm();
  initOrderDeleteButtons();
  initAddPaymentForm();
  initPaymentFilterForm();
  initPaymentDeleteButtons();
  initPaymentViewModal();
  initReportsRangeForm();
  initExportReportToggle();
  initReportsCharts();
  initProfileForm();
  initPreferencesForm();
  initPasswordModal();
  initExpenseForm();
  initExpenseFilterForm();
  initExpenseDeleteButtons();
  initExpensePresets();
  initExportExpenseToggle();
  initBulkImportOrders();
}

function initDashboardRangeForm() {
  const form = document.getElementById('dashboardRangeForm');
  if (!form) return;

  const typeSelect  = document.getElementById('dashFilterType');
  const monthInput  = document.getElementById('dashFilterMonth');
  const yearSelect  = document.getElementById('dashFilterYear');
  const monthWrap   = document.getElementById('filter_monthly_wrapper');
  const yearWrap    = document.getElementById('filter_yearly_wrapper');
  const downloadBtn = document.getElementById('dashDownloadBtn');

  // Toggle visible picker based on selected type
  function applyTypeVisibility(type) {
    if (monthWrap) monthWrap.classList.toggle('hidden', type !== 'monthly');
    if (yearWrap)  yearWrap.classList.toggle('hidden',  type !== 'yearly');
  }

  // Build URL from current form state and update download button href
  function buildUrl() {
    const params = new URLSearchParams(new FormData(form));
    return 'index.php?' + params.toString();
  }

  function updateDownloadHref() {
    if (!downloadBtn) return;
    const params = new URLSearchParams(new FormData(form));
    downloadBtn.href = 'export_pdf.php?' + params.toString();
  }

  // Hidden-iframe print: intercept the download button click so the
  // print dialog opens on the *same* tab with no extra visible page.
  if (downloadBtn) {
    downloadBtn.addEventListener('click', function (e) {
      e.preventDefault();

      const url = downloadBtn.href;

      // Show loading state
      const origHtml = downloadBtn.innerHTML;
      downloadBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin"></i> Preparing…';
      downloadBtn.style.pointerEvents = 'none';

      // Create a hidden iframe
      const iframe = document.createElement('iframe');
      iframe.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;';
      document.body.appendChild(iframe);

      // Listen for the "ready" signal from the iframe
      function onReady(evt) {
        if (evt.data !== 'dla-report-ready') return;
        window.removeEventListener('message', onReady);

        // Trigger print on the iframe's window
        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        // Restore button after a short delay (print dialog may still be open)
        setTimeout(function () {
          downloadBtn.innerHTML = origHtml;
          downloadBtn.style.pointerEvents = '';
          // Remove iframe after print dialog is dismissed
          setTimeout(function () { iframe.remove(); }, 2000);
        }, 500);
      }

      window.addEventListener('message', onReady);
      iframe.src = url;
    });
  }

  // Navigate on type change (show/hide pickers first, then navigate)
  if (typeSelect) {
    typeSelect.addEventListener('change', function () {
      applyTypeVisibility(this.value);
      updateDownloadHref();
      navigateTo(buildUrl(), true);
    });
  }

  // Navigate on month picker change
  if (monthInput) {
    monthInput.addEventListener('change', function () {
      updateDownloadHref();
      navigateTo(buildUrl(), true);
    });
  }

  // Navigate on year dropdown change
  if (yearSelect) {
    yearSelect.addEventListener('change', function () {
      updateDownloadHref();
      navigateTo(buildUrl(), true);
    });
  }

  // Also handle form submit (fallback)
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    navigateTo(buildUrl(), true);
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
      const ok = await confirmDelete(
        'Delete Customer',
        'Are you sure you want to delete this customer? This action cannot be undone.'
      );
      if (!ok) return;
      const id = btn.dataset.deleteCustomer;
      try {
        const res = await fetch('listcustomer.php?action=delete&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listcustomer.php', true);
        } else {
          showToast(data.message || 'Failed to delete this customer.');
        }
      } catch (err) {
        showToast('Network error. Please try again.');
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

// ---------------------------------------------------------
// Add / Edit Order form — product line items + live totals
// ---------------------------------------------------------
function initAddOrderForm() {
  const form = document.getElementById('addOrderForm');
  if (!form) return;

  const rowsBody = document.getElementById('productRows');
  const isEdit = form.dataset.mode === 'edit';
  let rowSeq = 0;

  // ---- Portal dropdown (escapes overflow-x-auto clipping) ----
  // One shared dropdown rendered in <body> with position:fixed,
  // repositioned below whichever product-name input is active.
  let productPortal = document.getElementById('dla-product-portal');
  if (!productPortal) {
    productPortal = document.createElement('div');
    productPortal.id = 'dla-product-portal';
    productPortal.style.cssText = [
      'position:fixed',
      'z-index:9999',
      'background:#fff',
      'border:1px solid #e5e7eb',
      'border-radius:8px',
      'box-shadow:0 8px 24px rgba(0,0,0,0.12)',
      'max-height:220px',
      'overflow-y:auto',
      'min-width:220px',
      'display:none',
    ].join(';');
    document.body.appendChild(productPortal);
  }
  let portalActiveRow = null;

  function positionPortal(input) {
    const r = input.getBoundingClientRect();
    productPortal.style.left  = r.left + 'px';
    productPortal.style.width = Math.max(r.width, 260) + 'px';
    // show below or above depending on available space
    const spaceBelow = window.innerHeight - r.bottom;
    if (spaceBelow >= 180 || spaceBelow >= window.innerHeight / 2) {
      productPortal.style.top    = (r.bottom + 4) + 'px';
      productPortal.style.bottom = 'auto';
    } else {
      productPortal.style.bottom = (window.innerHeight - r.top + 4) + 'px';
      productPortal.style.top    = 'auto';
    }
  }

  function hidePortal() {
    productPortal.style.display = 'none';
    portalActiveRow = null;
  }

  // Portal item click — fill the active row
  productPortal.addEventListener('mousedown', function (e) {
    // mousedown fires before the input loses focus, so we can fill safely
    const item = e.target.closest('.suggestion-item');
    if (!item || !portalActiveRow) return;
    e.preventDefault(); // prevent input blur
    const nameInp  = portalActiveRow.querySelector('.product-name-input');
    const priceInp = portalActiveRow.querySelector('.price-input');
    nameInp.value  = item.dataset.name;
    priceInp.value = item.dataset.price;
    portalActiveRow.dataset.sku       = item.dataset.sku || '';
    portalActiveRow.dataset.productId = item.dataset.id;
    hidePortal();
    recalcTotals();
  });

  // Close portal when clicking outside
  document.addEventListener('click', function (e) {
    if (!productPortal.contains(e.target) && !e.target.classList.contains('product-name-input')) {
      hidePortal();
    }
  });

  // Reposition portal on scroll
  window.addEventListener('scroll', function () {
    if (productPortal.style.display !== 'none' && portalActiveRow) {
      positionPortal(portalActiveRow.querySelector('.product-name-input'));
    }
  }, true);

  function rowTemplate(item) {
    rowSeq++;
    const id = 'row' + rowSeq;
    const name = item?.product_name || '';
    const sku = item?.sku || '';
    const qty = item?.quantity || 1;
    const price = item?.unit_price !== undefined ? item.unit_price : '';
    const productId = item?.product_id || '';
    const unitCost = item?.unit_cost !== undefined ? item.unit_cost : '';

    const tr = document.createElement('tr');
    tr.dataset.rowId = id;
    tr.dataset.productId = productId;
    tr.dataset.sku = sku;
    tr.dataset.unitCost = unitCost;
    tr.innerHTML = `
      <td class="py-2 pr-2">
        <label class="cursor-pointer inline-flex items-center justify-center w-10 h-10 rounded bg-gray-100 border border-gray-200 text-gray-400 hover:text-brand hover:border-brand">
          <i class="ti ti-photo"></i>
          <input type="file" class="hidden image-input" accept="image/*">
        </label>
        <img class="image-preview hidden w-10 h-10 object-cover rounded border border-gray-200 cursor-pointer" alt="Preview">
      </td>
      <td class="py-2 pr-2 relative">
        <input type="text" class="product-name-input w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand" placeholder="Search product..." value="${name.replace(/"/g, '&quot;')}" autocomplete="off">
      </td>
      <td class="py-2 pr-2 text-center">
        <input type="text" inputmode="numeric" class="qty-input w-20 rounded-lg border border-gray-300 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand" value="${qty}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
      </td>
      <td class="py-2 pr-2 text-right">
        <input type="text" inputmode="decimal" class="price-input w-24 rounded-lg border border-gray-300 px-2 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand" value="${price}" placeholder="0" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*?)\\..*/g, '$1')">
      </td>
      <td class="py-2 pr-2 text-right line-total font-medium text-gray-800">Rs. 0</td>
      <td class="py-2 text-right">
        <button type="button" class="remove-row text-gray-300 hover:text-red-600"><i class="ti ti-x"></i></button>
      </td>
    `;
    return tr;
  }

  function bindRow(tr) {
    const nameInput = tr.querySelector('.product-name-input');
    const qtyInput = tr.querySelector('.qty-input');
    const priceInput = tr.querySelector('.price-input');
    const removeBtn = tr.querySelector('.remove-row');
    const imageInput = tr.querySelector('.image-input');
    const imagePreview = tr.querySelector('.image-preview');
    const label = tr.querySelector('label');

    if (imageInput) {
      imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = (e) => {
            imagePreview.src = e.target.result;
            imagePreview.classList.remove('hidden');
            label.classList.add('hidden');
          };
          reader.readAsDataURL(file);
        } else {
          imagePreview.classList.add('hidden');
          imagePreview.src = '';
          label.classList.remove('hidden');
        }
      });
      imagePreview.addEventListener('click', () => {
        imageInput.click();
      });
    }

    nameInput.addEventListener('input', function () {
      tr.dataset.productId = ''; // typing invalidates the previous selection
      tr.dataset.sku = '';
    });

    [qtyInput, priceInput].forEach(input => input.addEventListener('input', recalcTotals));
    removeBtn.addEventListener('click', function () {
      if (rowsBody.children.length <= 1) return; // keep at least one row
      tr.remove();
      recalcTotals();
    });
  }

  function addRow(item) {
    const tr = rowTemplate(item);
    rowsBody.appendChild(tr);
    bindRow(tr);
    recalcTotals();
  }

  function recalcTotals() {
    let subtotal = 0;
    rowsBody.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
      const price = parseFloat(tr.querySelector('.price-input').value) || 0;
      const lineTotal = qty * price;
      const lineTotalEl = tr.querySelector('.line-total');
      if (lineTotalEl) lineTotalEl.textContent = 'Rs. ' + lineTotal.toLocaleString();
      subtotal += lineTotal;
    });
    const shippingInput = document.getElementById('shippingInput');
    const shipping = shippingInput ? parseFloat(shippingInput.value) || 0 : 0;
    const grandTotal = subtotal;

    const sumSubtotalEl = document.getElementById('sumSubtotal');
    if (sumSubtotalEl) sumSubtotalEl.textContent = 'Rs. ' + subtotal.toLocaleString();
    
    const sumGrandTotalEl = document.getElementById('sumGrandTotal');
    if (sumGrandTotalEl) sumGrandTotalEl.textContent = 'Rs. ' + grandTotal.toLocaleString();

    // --- Rebuild dynamic per-product cost inputs ---
    const costSection = document.getElementById('productCostSection');
    const costRowsContainer = document.getElementById('productCostRows');
    const costDisplay = document.getElementById('costOfGoodsDisplay');
    const costHidden = document.getElementById('costOfGoodsHidden');
    if (!costSection || !costRowsContainer) return;

    // Collect current product names from the product rows
    const products = [];
    rowsBody.querySelectorAll('tr').forEach(tr => {
      const name = tr.querySelector('.product-name-input').value.trim();
      if (name !== '') {
        products.push({ name, rowId: tr.dataset.rowId, unitCost: tr.dataset.unitCost || '' });
      }
    });

    if (products.length === 0) {
      costSection.classList.add('hidden');
      costRowsContainer.innerHTML = '';
      if (costDisplay) costDisplay.textContent = 'Rs. 0';
      if (costHidden) costHidden.value = '0';
      return;
    }

    costSection.classList.remove('hidden');

    // Preserve existing cost values by rowId
    const existingCosts = {};
    costRowsContainer.querySelectorAll('[data-cost-row]').forEach(el => {
      const rid = el.dataset.costRow;
      const input = el.querySelector('.product-cost-input');
      if (input) existingCosts[rid] = input.value;
    });

    // Rebuild cost rows
    costRowsContainer.innerHTML = products.map(p => {
      const prev = existingCosts[p.rowId] !== undefined ? existingCosts[p.rowId] : p.unitCost;
      return `<div class="flex items-center justify-between gap-2" data-cost-row="${p.rowId}">
        <span class="text-sm text-gray-600 truncate flex-1" title="${p.name.replace(/"/g, '&quot;')}">${p.name}</span>
        <input type="text" inputmode="decimal" value="${prev}" placeholder="0"
               oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*?)\\..*/g, '$1')"
               class="product-cost-input w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand">
      </div>`;
    }).join('');

    // Bind input listeners on cost inputs to recalc cost of goods
    costRowsContainer.querySelectorAll('.product-cost-input').forEach(inp => {
      inp.addEventListener('input', recalcCostOfGoods);
    });

    recalcCostOfGoods();
  }

  function recalcCostOfGoods() {
    const costRowsContainer = document.getElementById('productCostRows');
    const costDisplay = document.getElementById('costOfGoodsDisplay');
    const costHidden = document.getElementById('costOfGoodsHidden');
    const shippingInput = document.getElementById('shippingInput');
    if (!costRowsContainer) return;

    let totalProductCost = 0;
    costRowsContainer.querySelectorAll('.product-cost-input').forEach(inp => {
      totalProductCost += parseFloat(inp.value) || 0;
    });
    const costOfGoods = totalProductCost;

    if (costDisplay) costDisplay.textContent = 'Rs. ' + costOfGoods.toLocaleString();
    if (costHidden) costHidden.value = costOfGoods;
  }

  // Seed initial rows
  const initialItems = JSON.parse(rowsBody.dataset.initial || 'null');
  if (initialItems && initialItems.length) {
    initialItems.forEach(addRow);
  } else {
    addRow(null);
  }

  document.getElementById('addProductRow').addEventListener('click', () => addRow(null));
  const shippingInput = document.getElementById('shippingInput');
  if (shippingInput) {
    shippingInput.addEventListener('input', () => {
      recalcTotals();
      recalcCostOfGoods();
    });
  }

  // --- Quick Add: search existing customers (Add Order only) ---
  const quickAddToggle = document.getElementById('quickAddToggle');
  if (quickAddToggle) {
    const box = document.getElementById('customerSearchBox');
    const input = document.getElementById('customerSearchInput');
    const results = document.getElementById('customerSearchResults');

    quickAddToggle.addEventListener('click', () => {
      box.classList.toggle('hidden');
      if (!box.classList.contains('hidden')) input.focus();
    });

    let debounceTimer;
    input.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      const q = input.value.trim();
      if (q.length < 2) { results.classList.add('hidden'); return; }
      debounceTimer = setTimeout(async () => {
        try {
          const res = await fetch('customer_search.php?q=' + encodeURIComponent(q));
          const customers = await res.json();
          if (!customers.length) { results.classList.add('hidden'); return; }
          results.innerHTML = customers.map(c =>
            `<button type="button" class="customer-result block w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
               data-id="${c.customer_id}" data-name="${(c.full_name || '').replace(/"/g, '&quot;')}"
               data-instagram="${c.instagram_handle || ''}" data-whatsapp="${c.whatsapp_number || ''}" data-gender="${c.gender || ''}">
              <span class="font-medium text-gray-800">${c.full_name || 'Unnamed'}</span>
              <span class="text-gray-400 text-xs block">${c.whatsapp_number || ''}</span>
            </button>`
          ).join('');
          results.classList.remove('hidden');
        } catch (err) { /* silent */ }
      }, 250);
    });

    results.addEventListener('click', function (e) {
      const item = e.target.closest('.customer-result');
      if (!item) return;
      if (document.getElementById('customerIdField')) document.getElementById('customerIdField').value = item.dataset.id;
      if (document.getElementById('fullNameField')) document.getElementById('fullNameField').value = item.dataset.name;
      if (document.getElementById('instagramField')) document.getElementById('instagramField').value = item.dataset.instagram;
      if (document.getElementById('whatsappField')) document.getElementById('whatsappField').value = item.dataset.whatsapp;
      if (item.dataset.gender === 'male' && document.getElementById('genderMale')) document.getElementById('genderMale').checked = true;
      if (item.dataset.gender === 'female' && document.getElementById('genderFemale')) document.getElementById('genderFemale').checked = true;
      results.classList.add('hidden');
      box.classList.add('hidden');
    });
  }

  // --- Payment status conditional amount field (Add Order only) ---
  const paymentRadios = form.querySelectorAll('input[name="payment_status"]');
  const partialWrap = document.getElementById('partialAmountWrap');
  if (paymentRadios.length && partialWrap) {
    paymentRadios.forEach(r => r.addEventListener('change', () => {
      partialWrap.classList.toggle('hidden', r.form.payment_status.value !== 'partial');
    }));
  }

  // --- Submit ---
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
    document.getElementById('formGeneralError').classList.add('hidden');

    const items = [];
    let validIdx = 0;
    Array.from(rowsBody.querySelectorAll('tr')).forEach(tr => {
      const name = tr.querySelector('.product-name-input').value.trim();
      if (name !== '') {
        const imgInput = tr.querySelector('.image-input');
        if (imgInput) imgInput.name = `items_image_${validIdx}`;
        
        const rowId = tr.dataset.rowId;
        const costRow = document.querySelector(`[data-cost-row="${rowId}"]`);
        const unitCost = costRow ? parseFloat(costRow.querySelector('.product-cost-input').value) || 0 : 0;

        items.push({
          product_id: tr.dataset.productId || null,
          product_name: name,
          sku: tr.dataset.sku || '',
          quantity: parseInt(tr.querySelector('.qty-input').value, 10) || 0,
          unit_price: parseFloat(tr.querySelector('.price-input').value) || 0,
          unit_cost: unitCost,
        });
        validIdx++;
      }
    });

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData(form);
    formData.append('items', JSON.stringify(items));

    try {
      const res = await fetch(isEdit ? 'editorder.php' : 'addorder.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        navigateTo(isEdit ? 'vieworder.php?id=' + data.order_id : 'listorder.php', true);
      } else if (data.errors) {
        Object.entries(data.errors).forEach(([field, msg]) => {
          const el = form.querySelector(`.field-error[data-field="${field}"]`);
          if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        });
      } else {
        const genEl = document.getElementById('formGeneralError');
        genEl.textContent = data.message || 'Something went wrong. Please try again.';
        genEl.classList.remove('hidden');
      }
    } catch (err) {
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
// Delete order (list page)
// ---------------------------------------------------------
function initOrderDeleteButtons() {
  document.querySelectorAll('[data-delete-order]').forEach((btn) => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async function () {
      const ok = await confirmDelete(
        'Delete Order',
        'Are you sure you want to delete this order? This action cannot be undone.'
      );
      if (!ok) return;
      const id = btn.dataset.deleteOrder;
      try {
        const res = await fetch('deleteorder.php?id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listorder.php', true);
        } else {
          showToast(data.message || 'Failed to delete this order.');
        }
      } catch (err) {
        showToast('Network error. Please try again.');
      }
    });
  });
}

// ---------------------------------------------------------
// Add Payment form
// ---------------------------------------------------------
function initAddPaymentForm() {
  const form = document.getElementById('addPaymentForm');
  if (!form) return;

  const searchInput = document.getElementById('orderSearchInput');
  const results = document.getElementById('orderSearchResults');
  const orderIdField = document.getElementById('paymentOrderId');
  const customerField = document.getElementById('paymentCustomerName');
  const amountInput = document.getElementById('amountReceivedInput');

  const sumOutstanding = document.getElementById('sumOutstanding');
  const sumAmountToPay = document.getElementById('sumAmountToPay');
  const sumRemaining = document.getElementById('sumRemaining');

  let outstandingBalance = 0;
  let currency = 'PKR';

  function recalcSummary() {
    const amount = parseFloat(amountInput.value) || 0;
    sumAmountToPay.textContent = 'Rs. ' + amount.toLocaleString();
    const remaining = Math.max(0, outstandingBalance - amount);
    sumRemaining.textContent = 'Rs. ' + remaining.toLocaleString();
  }

  let debounceTimer;
  searchInput.addEventListener('input', function () {
    orderIdField.value = '';
    clearTimeout(debounceTimer);
    const q = searchInput.value.trim();
    if (q.length < 1) { results.classList.add('hidden'); return; }
    debounceTimer = setTimeout(async () => {
      try {
        const res = await fetch('order_search.php?q=' + encodeURIComponent(q));
        const orders = await res.json();
        if (!orders.length) { results.classList.add('hidden'); return; }
        results.innerHTML = orders.map(o =>
          `<button type="button" class="order-result block w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
             data-id="${o.order_id}" data-name="${(o.full_name || '').replace(/"/g, '&quot;')}"
             data-balance="${o.remaining_balance}" data-currency="${o.currency}" data-label="${o.order_label}">
            <span class="font-medium text-gray-800">#${o.order_label}</span>
            <span class="text-gray-400 text-xs block">${o.full_name || 'Unnamed'} · Balance: Rs. ${Number(o.remaining_balance).toLocaleString()}</span>
          </button>`
        ).join('');
        results.classList.remove('hidden');
      } catch (err) { /* silent */ }
    }, 250);
  });

  results.addEventListener('click', function (e) {
    const item = e.target.closest('.order-result');
    if (!item) return;
    orderIdField.value = item.dataset.id;
    customerField.value = item.dataset.name;
    searchInput.value = '#' + item.dataset.label;
    outstandingBalance = parseFloat(item.dataset.balance) || 0;
    currency = item.dataset.currency || 'PKR';
    sumOutstanding.textContent = 'Rs. ' + outstandingBalance.toLocaleString();
    results.classList.add('hidden');
    recalcSummary();
  });

  document.addEventListener('click', function (e) {
    if (!searchInput.contains(e.target) && !results.contains(e.target)) results.classList.add('hidden');
  });

  amountInput.addEventListener('input', recalcSummary);

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
    document.getElementById('formGeneralError').classList.add('hidden');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    try {
      const res = await fetch('addpayment.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();

      if (data.success) {
        navigateTo('listpayment.php', true);
      } else if (data.errors) {
        Object.entries(data.errors).forEach(([field, msg]) => {
          const el = form.querySelector(`.field-error[data-field="${field}"]`);
          if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        });
      } else {
        const genEl = document.getElementById('formGeneralError');
        genEl.textContent = data.message || 'Something went wrong. Please try again.';
        genEl.classList.remove('hidden');
      }
    } catch (err) {
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
// Payment list filters
// ---------------------------------------------------------
function initPaymentFilterForm() {
  const form = document.getElementById('paymentFilterForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('listpayment.php' + (params ? '?' + params : ''), true);
  });

  const resetBtn = document.getElementById('resetFiltersBtn');
  if (resetBtn) resetBtn.addEventListener('click', () => navigateTo('listpayment.php', true));
}

// ---------------------------------------------------------
// Delete payment
// ---------------------------------------------------------
function initPaymentDeleteButtons() {
  document.querySelectorAll('[data-delete-payment]').forEach((btn) => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async function () {
      const ok = await confirmDelete(
        'Delete Payment',
        'Are you sure you want to delete this payment? This will adjust the order balance and cannot be undone.'
      );
      if (!ok) return;
      const id = btn.dataset.deletePayment;
      try {
        const res = await fetch('listpayment.php?action=delete&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listpayment.php', true);
        } else {
          showToast(data.message || 'Failed to delete this payment.');
        }
      } catch (err) {
        showToast('Network error. Please try again.');
      }
    });
  });
}

// ---------------------------------------------------------
// View payment modal
// ---------------------------------------------------------
function initPaymentViewModal() {
  const modal = document.getElementById('paymentModal');
  if (!modal) return;

  const body = document.getElementById('paymentModalBody');
  const closeBtn = document.getElementById('closePaymentModal');

  const labels = {
    transaction_id: 'Transaction ID', payment_date: 'Date', full_name: 'Customer',
    payment_method: 'Method', amount: 'Amount', status: 'Status', reference_number: 'Reference',
  };

  document.querySelectorAll('[data-view-payment]').forEach((btn) => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
      const row = btn.closest('[data-payment]');
      const payment = JSON.parse(row.dataset.payment);
      body.innerHTML = Object.entries(labels).map(([key, label]) => `
        <div class="flex justify-between border-b border-gray-50 pb-2">
          <dt class="text-gray-500">${label}</dt>
          <dd class="text-gray-800 font-medium">${payment[key] ?? '—'}</dd>
        </div>
      `).join('');
      modal.classList.remove('hidden');
    });
  });

  if (!closeBtn.dataset.bound) {
    closeBtn.dataset.bound = '1';
    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });
  }
}

// ---------------------------------------------------------
// Sales Reports: range filter + export dropdown + charts
// ---------------------------------------------------------
function initReportsRangeForm() {
  const form = document.getElementById('reportsRangeForm');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('monthly_report.php' + (params ? '?' + params : ''), true);
  });
}

function initExportReportToggle() {
  const toggle = document.getElementById('exportReportToggle');
  const menu = document.getElementById('exportReportMenu');
  if (!toggle || !menu) return;
  if (toggle.dataset.bound) return;
  toggle.dataset.bound = '1';

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  });
  document.addEventListener('click', () => menu.classList.add('hidden'));
}

let revenueTrendChartInstance = null;
let refundTrendChartInstance = null;

function initReportsCharts() {
  const revenueCanvas = document.getElementById('revenueTrendChart');
  if (revenueCanvas && window.Chart) {
    if (revenueTrendChartInstance) revenueTrendChartInstance.destroy();
    const labels = JSON.parse(revenueCanvas.dataset.labels || '[]');
    const revenue = JSON.parse(revenueCanvas.dataset.revenue || '[]');

    revenueTrendChartInstance = new Chart(revenueCanvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Revenue', data: revenue, borderColor: '#14302A', backgroundColor: 'rgba(20,48,42,0.08)',
          fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: '#F3F4F6' }, ticks: { color: '#9CA3AF' } },
          x: { grid: { display: false }, ticks: { color: '#9CA3AF' } },
        },
      },
    });
  }

  const refundCanvas = document.getElementById('refundTrendChart');
  if (refundCanvas && window.Chart) {
    if (refundTrendChartInstance) refundTrendChartInstance.destroy();
    const labels = JSON.parse(refundCanvas.dataset.labels || '[]');
    const sales = JSON.parse(refundCanvas.dataset.sales || '[]');
    const refunds = JSON.parse(refundCanvas.dataset.refunds || '[]');

    refundTrendChartInstance = new Chart(refundCanvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Sales', data: sales, backgroundColor: '#14302A', borderRadius: 4 },
          { label: 'Refunds', data: refunds, backgroundColor: '#C7D2FE', borderRadius: 4 },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: '#F3F4F6' }, ticks: { color: '#9CA3AF' } },
          x: { grid: { display: false }, ticks: { color: '#9CA3AF' } },
        },
      },
    });
  }
}

// ---------------------------------------------------------
// Settings: Profile Information form
// ---------------------------------------------------------
function initProfileForm() {
  const form = document.getElementById('profileForm');
  if (!form) return;

  const trigger = document.getElementById('userPhotoTrigger');
  const changeBtn = document.getElementById('changePictureBtn');
  const input = document.getElementById('userPhotoInput');
  const preview = document.getElementById('userPhotoPreview');
  const initialsEl = document.getElementById('userPhotoInitials');
  const errorEl = document.getElementById('userPhotoError');

  [trigger, changeBtn].forEach(el => el.addEventListener('click', () => input.click()));

  input.addEventListener('change', function () {
    const file = input.files[0];
    errorEl.classList.add('hidden');
    if (!file) return;

    if (!['image/jpeg', 'image/png'].includes(file.type)) {
      errorEl.textContent = 'Only JPEG or PNG images are allowed.';
      errorEl.classList.remove('hidden');
      input.value = '';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      errorEl.textContent = 'Image must be 2MB or smaller.';
      errorEl.classList.remove('hidden');
      input.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      preview.src = e.target.result;
      preview.classList.remove('hidden');
      if (initialsEl) initialsEl.classList.add('hidden');
    };
    reader.readAsDataURL(file);
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('#profileForm .field-error').forEach(el => el.classList.add('hidden'));
    document.getElementById('profileSuccess').classList.add('hidden');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData(form);
    formData.append('action', 'profile');

    try {
      const res = await fetch('index.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        document.getElementById('profileSuccess').classList.remove('hidden');
      } else if (data.errors) {
        Object.entries(data.errors).forEach(([field, msg]) => {
          const el = form.querySelector(`.field-error[data-field="${field}"]`);
          if (el) { el.textContent = msg; el.classList.remove('hidden'); }
          else if (field === 'photo') { errorEl.textContent = msg; errorEl.classList.remove('hidden'); }
        });
      }
    } catch (err) {
      alert('Network error. Please try again.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
}

// ---------------------------------------------------------
// Settings: General Preferences form
// ---------------------------------------------------------
function initPreferencesForm() {
  const form = document.getElementById('preferencesForm');
  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    formData.append('action', 'preferences');

    try {
      const res = await fetch('index.php', { method: 'POST', body: formData });
      const data = await res.json();
      const successEl = document.getElementById('preferencesSuccess');
      if (data.success) {
        successEl.classList.remove('hidden');
        setTimeout(() => successEl.classList.add('hidden'), 2000);
      }
    } catch (err) { /* silent */ }
  });
}

// ---------------------------------------------------------
// Settings: Update Password modal
// ---------------------------------------------------------
function initPasswordModal() {
  const modal = document.getElementById('passwordModal');
  if (!modal) return;

  const openBtn = document.getElementById('openPasswordModal');
  const closeBtn = document.getElementById('closePasswordModal');
  const form = document.getElementById('passwordForm');

  openBtn.addEventListener('click', () => modal.classList.remove('hidden'));
  closeBtn.addEventListener('click', () => { modal.classList.add('hidden'); form.reset(); });
  modal.addEventListener('click', (e) => { if (e.target === modal) { modal.classList.add('hidden'); form.reset(); } });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('#passwordForm .field-error').forEach(el => el.classList.add('hidden'));

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';

    const formData = new FormData(form);
    formData.append('action', 'password');

    try {
      const res = await fetch('index.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        modal.classList.add('hidden');
        form.reset();
        navigateTo(location.pathname, false); // refresh "Last changed" text
      } else if (data.errors) {
        Object.entries(data.errors).forEach(([field, msg]) => {
          const el = form.querySelector(`.field-error[data-field="${field}"]`);
          if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        });
      }
    } catch (err) {
      alert('Network error. Please try again.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
}

// ---------------------------------------------------------
// Add / Edit Expense form
// ---------------------------------------------------------
function initExpenseForm() {
  const form = document.getElementById('expenseForm');
  if (!form) return;

  const isEdit = form.dataset.mode === 'edit';

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    document.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
    document.getElementById('formGeneralError').classList.add('hidden');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    try {
      const res = await fetch(isEdit ? 'editexpense.php' : 'addexpense.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();

      if (data.success) {
        navigateTo('listexpense.php', true);
      } else if (data.errors) {
        Object.entries(data.errors).forEach(([field, msg]) => {
          const el = form.querySelector(`.field-error[data-field="${field}"]`);
          if (el) { el.textContent = msg; el.classList.remove('hidden'); }
        });
      } else {
        const genEl = document.getElementById('formGeneralError');
        genEl.textContent = data.message || 'Something went wrong. Please try again.';
        genEl.classList.remove('hidden');
      }
    } catch (err) {
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
// Expense list filters
// ---------------------------------------------------------
function initExpenseFilterForm() {
  const form = document.getElementById('expenseFilterForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('listexpense.php' + (params ? '?' + params : ''), true);
  });

  const resetBtn = document.getElementById('resetFiltersBtn');
  if (resetBtn) resetBtn.addEventListener('click', () => navigateTo('listexpense.php', true));
}

// ---------------------------------------------------------
// Quick date-range presets (Today / This Week / This Month / This Year / All Time)
// ---------------------------------------------------------
function initExpensePresets() {
  document.querySelectorAll('[data-expense-preset]').forEach((btn) => {
    btn.addEventListener('click', function () {
      const today = new Date();
      const iso = (d) => d.toISOString().slice(0, 10);
      let from = '', to = '';

      switch (btn.dataset.expensePreset) {
        case 'today':
          from = to = iso(today);
          break;
        case 'week': {
          const start = new Date(today);
          start.setDate(today.getDate() - today.getDay());
          from = iso(start); to = iso(today);
          break;
        }
        case 'month':
          from = iso(new Date(today.getFullYear(), today.getMonth(), 1));
          to = iso(today);
          break;
        case 'year':
          from = iso(new Date(today.getFullYear(), 0, 1));
          to = iso(today);
          break;
        case 'all':
        default:
          from = ''; to = '';
      }

      const params = new URLSearchParams();
      if (from) params.set('date_from', from);
      if (to) params.set('date_to', to);
      navigateTo('listexpense.php' + (params.toString() ? '?' + params.toString() : ''), true);
    });
  });
}

// ---------------------------------------------------------
// Delete expense
// ---------------------------------------------------------
function initExpenseDeleteButtons() {
  document.querySelectorAll('[data-delete-expense]').forEach((btn) => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async function () {
      const ok = await confirmDelete(
        'Delete Expense',
        'Are you sure you want to delete this expense? This action cannot be undone.'
      );
      if (!ok) return;
      const id = btn.dataset.deleteExpense;
      try {
        const res = await fetch('listexpense.php?action=delete&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listexpense.php', true);
        } else {
          showToast(data.message || 'Failed to delete this expense.');
        }
      } catch (err) {
        showToast('Network error. Please try again.');
      }
    });
  });
}

// ---------------------------------------------------------
// Export dropdown (PDF / Excel)
// ---------------------------------------------------------
function initExportExpenseToggle() {
  const toggle = document.getElementById('exportExpenseToggle');
  const menu = document.getElementById('exportExpenseMenu');
  if (!toggle || !menu) return;
  if (toggle.dataset.bound) return;
  toggle.dataset.bound = '1';

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  });
  document.addEventListener('click', () => menu.classList.add('hidden'));
}

// ---------------------------------------------------------
// Bulk Import Orders (Settings page)
// ---------------------------------------------------------
function initBulkImportOrders() {
  const openBtn = document.getElementById('openBulkImportModal');
  if (!openBtn) return;
  if (openBtn.dataset.bound) return;
  openBtn.dataset.bound = '1';

  const modal = document.getElementById('bulkImportModal');
  const closeBtn = document.getElementById('closeBulkImportModal');
  const stepView = document.getElementById('bulkImportStepView');
  const previewView = document.getElementById('bulkImportPreview');
  const resultsView = document.getElementById('bulkImportResults');

  const dropzone = document.getElementById('bulkImportDropzone');
  const fileInput = document.getElementById('bulkImportFileInput');
  const chooseBtn = document.getElementById('bulkImportChooseFile');
  const chooseDifferentBtn = document.getElementById('bulkImportChooseDifferentFile');
  const cancelBtn = document.getElementById('bulkImportCancel');
  const confirmBtn = document.getElementById('bulkImportConfirm');
  const doneBtn = document.getElementById('bulkImportDone');
  const downloadSampleBtn = document.getElementById('downloadOrderSample');
  const generalError = document.getElementById('bulkImportGeneralError');

  let parsedRows = [];

  const COLUMNS = [
    'Order Ref', 'Customer Name', 'WhatsApp Number', 'Instagram Username', 'City', 'Country',
    'Item Name', 'Quantity', 'Unit Price', 'Order Date', 'Expected Delivery Date',
    'Shipping Cost', 'Cost of Goods', 'Amount Paid', 'Currency', 'Status',
  ];

  function resetModal() {
    stepView.classList.remove('hidden');
    previewView.classList.add('hidden');
    resultsView.classList.add('hidden');
    generalError.classList.add('hidden');
    fileInput.value = '';
    parsedRows = [];
  }

  function openModal() { resetModal(); modal.classList.remove('hidden'); }
  function closeModal() { modal.classList.add('hidden'); }

  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  doneBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // --- Step 1: sample download ---
  downloadSampleBtn.addEventListener('click', function () {
    const todayStr = new Date().toISOString().slice(0, 10);
    const sampleRows = [
      {
        'Order Ref': 1, 'Customer Name': 'Ahmad Shah', 'WhatsApp Number': '+923001234567',
        'Instagram Username': 'ahmad.styles', 'City': 'Lahore', 'Country': 'Pakistan',
        'Item Name': 'Embroidered Lawn Shirt', 'Quantity': 1, 'Unit Price': 2500,
        'Order Date': todayStr, 'Expected Delivery Date': '2026-09-10', 'Shipping Cost': 250,
        'Cost of Goods': 1200, 'Amount Paid': 1500, 'Currency': 'PKR', 'Status': 'pending',
      },
      {
        // Same Order Ref as above -> 2nd item on the SAME order (Order-level costs left BLANK per rules)
        'Order Ref': 1, 'Customer Name': 'Ahmad Shah', 'WhatsApp Number': '+923001234567',
        'Instagram Username': 'ahmad.styles', 'City': 'Lahore', 'Country': 'Pakistan',
        'Item Name': 'Matching Dupatta', 'Quantity': 2, 'Unit Price': 800,
        'Order Date': todayStr, 'Expected Delivery Date': '2026-09-10', 'Shipping Cost': '',
        'Cost of Goods': '', 'Amount Paid': '', 'Currency': 'PKR', 'Status': 'pending',
      },
      {
        // New Order Ref -> separate single-item order (incrementing to 2)
        'Order Ref': 2, 'Customer Name': 'Zahra Noor', 'WhatsApp Number': '+923123456789',
        'Instagram Username': 'zahra.official', 'City': 'Karachi', 'Country': 'Pakistan',
        'Item Name': 'Silk Kurti', 'Quantity': 1, 'Unit Price': 4500,
        'Order Date': todayStr, 'Expected Delivery Date': '2026-09-09', 'Shipping Cost': 200,
        'Cost of Goods': 2100, 'Amount Paid': 4700, 'Currency': 'PKR', 'Status': 'delivered',
      },
    ];

    const worksheet = XLSX.utils.json_to_sheet(sampleRows, { header: COLUMNS });

    // Set column widths for optimal display
    const colWidths = [12, 20, 18, 18, 14, 14, 26, 10, 12, 14, 22, 14, 14, 14, 10, 12];
    worksheet['!cols'] = colWidths.map(w => ({ wch: w }));

    // Apply header styling matching royal blue design (with bold white text and borders)
    const range = XLSX.utils.decode_range(worksheet['!ref']);

    const headerStyle = {
      fill: { fgColor: { rgb: "2563EB" } }, // Royal Blue header background matching reference
      font: { name: "Arial", sz: 11, bold: true, color: { rgb: "FFFFFF" } },
      alignment: { horizontal: "center", vertical: "center", wrapText: true },
      border: {
        top: { style: "medium", color: { rgb: "1D4ED8" } },
        bottom: { style: "medium", color: { rgb: "1D4ED8" } },
        left: { style: "thin", color: { rgb: "60A5FA" } },
        right: { style: "thin", color: { rgb: "60A5FA" } }
      }
    };

    const alignRightCols = new Set(['Quantity', 'Unit Price', 'Shipping Cost', 'Cost of Goods', 'Amount Paid']);
    const alignCenterCols = new Set(['Order Ref', 'Order Date', 'Expected Delivery Date', 'Currency', 'Status']);

    for (let R = range.s.r; R <= range.e.r; ++R) {
      for (let C = range.s.c; C <= range.e.c; ++C) {
        const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
        if (!worksheet[cellRef]) continue;

        if (R === 0) {
          worksheet[cellRef].s = headerStyle;
        } else {
          const colName = COLUMNS[C];
          let align = 'left';
          if (alignRightCols.has(colName)) align = 'right';
          else if (alignCenterCols.has(colName)) align = 'center';

          worksheet[cellRef].s = {
            font: { name: "Arial", sz: 10, color: { rgb: "1F2937" } },
            alignment: { vertical: "center", horizontal: align },
            border: {
              top: { style: "thin", color: { rgb: "E5E7EB" } },
              bottom: { style: "thin", color: { rgb: "E5E7EB" } },
              left: { style: "thin", color: { rgb: "E5E7EB" } },
              right: { style: "thin", color: { rgb: "E5E7EB" } }
            }
          };
        }
      }
    }

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Orders');
    XLSX.writeFile(workbook, 'sample_orders_import.xlsx');
  });

  // --- Step 2: upload + parse ---
  chooseBtn.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-brand', 'bg-brand/10'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-brand', 'bg-brand/10'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('border-brand', 'bg-brand/10');
    if (e.dataTransfer.files.length) parseFile(e.dataTransfer.files[0]);
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) parseFile(fileInput.files[0]);
  });
  if (chooseDifferentBtn) chooseDifferentBtn.addEventListener('click', resetModal);

  function parseFile(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const workbook = XLSX.read(e.target.result, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        parsedRows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
        renderPreview();
      } catch (err) {
        generalError.textContent = 'Could not read that file. Make sure it is a valid .xlsx or .xls file.';
        generalError.classList.remove('hidden');
      }
    };
    reader.readAsArrayBuffer(file);
  }

  // --- Step 3: preview ---
  function renderPreview() {
    stepView.classList.add('hidden');
    previewView.classList.remove('hidden');
    generalError.classList.add('hidden');

    const orderKeys = new Set(parsedRows.map((r, i) => {
      const ref = String(r['Order Ref'] || '').trim();
      return ref !== '' ? 'ref:' + ref : 'row:' + i;
    }));

    document.getElementById('bulkImportRowCount').textContent = parsedRows.length;
    document.getElementById('bulkImportOrderCount').textContent = orderKeys.size;

    const body = document.getElementById('bulkImportPreviewBody');
    body.innerHTML = parsedRows.map(r => `
      <tr>
        <td class="px-2 py-2">${r['Customer Name'] || ''}</td>
        <td class="px-2 py-2">${r['Item Name'] || ''}</td>
        <td class="px-2 py-2 text-right">${r['Quantity'] || ''}</td>
        <td class="px-2 py-2 text-right">${r['Unit Price'] || ''}</td>
        <td class="px-2 py-2">${r['Order Date'] || ''}</td>
      </tr>
    `).join('');
  }

  // --- Confirm import ---
  confirmBtn.addEventListener('click', async function () {
    if (!parsedRows.length) return;
    confirmBtn.disabled = true;
    const originalLabel = confirmBtn.textContent;
    confirmBtn.textContent = 'Importing...';
    generalError.classList.add('hidden');

    try {
      const res = await fetch('import_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(parsedRows),
      });
      const data = await res.json();

      if (!data.success) {
        generalError.textContent = data.message || 'Import failed. Please try again.';
        generalError.classList.remove('hidden');
        return;
      }

      previewView.classList.add('hidden');
      resultsView.classList.remove('hidden');

      const summary = document.getElementById('bulkImportResultsSummary');
      summary.innerHTML = `<i class="ti ti-circle-check-filled text-emerald-500 text-lg align-middle mr-2"></i>
        <span class="font-medium">${data.created} order${data.created === 1 ? '' : 's'} imported successfully.</span>`;

      const errorsWrap = document.getElementById('bulkImportResultsErrors');
      const errorsList = document.getElementById('bulkImportResultsErrorList');
      if (data.failed && data.failed.length) {
        errorsWrap.classList.remove('hidden');
        errorsList.innerHTML = data.failed.map(f => `<li>• ${f.customer || 'Unknown'}: ${f.reason}</li>`).join('');
      } else {
        errorsWrap.classList.add('hidden');
      }
    } catch (err) {
      generalError.textContent = 'Network error. Please try again.';
      generalError.classList.remove('hidden');
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.textContent = originalLabel;
    }
  });
}

document.addEventListener('DOMContentLoaded', function () {
  initPageScripts();
  setActiveSidebarLink(location.pathname);
});
