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

window.CustomConfirm = function(message) {
  return new Promise((resolve) => {
    const modal = document.getElementById('customDeleteModal');
    if (!modal) {
      resolve(confirm(message));
      return;
    }
    
    const msgEl = modal.querySelector('p');
    if (msgEl && message) msgEl.textContent = message;

    const confirmBtn = document.getElementById('customDeleteConfirm');
    const cancelBtn = document.getElementById('customDeleteCancel');
    const overlay = document.getElementById('customDeleteOverlay');

    modal.classList.remove('hidden');

    const cleanup = () => {
      modal.classList.add('hidden');
      confirmBtn.removeEventListener('click', onConfirm);
      cancelBtn.removeEventListener('click', onCancel);
      overlay.removeEventListener('click', onCancel);
    };

    const onConfirm = () => { cleanup(); resolve(true); };
    const onCancel = () => { cleanup(); resolve(false); };

    confirmBtn.addEventListener('click', onConfirm);
    cancelBtn.addEventListener('click', onCancel);
    overlay.addEventListener('click', onCancel);
  });
};

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
    try {
      initPageScripts();
    } catch (scriptErr) {
      console.error('Page script init error:', scriptErr);
    }
    window.scrollTo({ top: 0, behavior: 'instant' });
    closeMobileSidebar();
  } catch (err) {
    console.error('Navigation error:', err);
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

document.addEventListener('click', function (e) {
  // Global action-toggle handler
  const toggle = e.target.closest('.action-toggle');
  if (toggle) {
    const container = toggle.closest('.relative') || toggle.parentElement;
    const dropdown = container.querySelector('.action-dropdown');
    if (dropdown) {
      const isHidden = dropdown.classList.contains('hidden');
      document.querySelectorAll('.action-dropdown').forEach(dd => dd.classList.add('hidden'));
      if (isHidden) dropdown.classList.remove('hidden');
    }
    return;
  }
  
  // Close all action dropdowns if click is outside
  if (!e.target.closest('.action-dropdown')) {
    document.querySelectorAll('.action-dropdown').forEach(dd => dd.classList.add('hidden'));
  }

  const link = e.target.closest('[data-spa]');
  if (!link) return;
  e.preventDefault();
  
  const targetUrl = link.getAttribute('href');
  const targetObj = new URL(targetUrl, window.location.href);
  
  if (targetObj.pathname === window.location.pathname && targetObj.search === window.location.search) {
    return; // Already on this page
  }
  
  navigateTo(targetUrl, true);
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
  initAddOrderForm();
  initOrderFilterForm();
  initOrderFilterDropdownToggle();
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
  initExpenseFilterDropdownToggle();
  initPaymentFilterDropdownToggle();
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
  const cityList = document.getElementById('cityList');
  if (countrySelect && citySelect && cityList) {
    function populateCities(selected) {
      const cities = CITIES_BY_COUNTRY[countrySelect.value] || [];
      cityList.innerHTML = cities.map(c => `<option value="${c}"></option>`).join('');
    }
    countrySelect.addEventListener('change', () => populateCities(null));
    countrySelect.addEventListener('input', () => populateCities(null));
    populateCities(citySelect.dataset.selected || null);
  }

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

  // Filter checkboxes (for toggling fields)
  document.querySelectorAll('.filter-checkbox').forEach(cb => {
    if (cb.dataset.bound) return;
    cb.dataset.bound = '1';
    
    cb.addEventListener('change', function() {
      const targetId = this.value;
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        if (this.checked) {
          targetEl.classList.remove('hidden');
        } else {
          targetEl.classList.add('hidden');
          targetEl.querySelectorAll('input, select').forEach(inp => inp.value = '');
        }
      }
      
      const anyChecked = Array.from(document.querySelectorAll('.filter-checkbox')).some(c => c.checked);
      if (anyChecked) {
        form.classList.remove('hidden');
      } else {
        form.classList.add('hidden');
      }
    });
  });
  
  // Show form initially if any filter is active
  const anyCheckedInit = Array.from(document.querySelectorAll('.filter-checkbox')).some(c => c.checked);
  if (anyCheckedInit) {
    form.classList.remove('hidden');
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
      const confirmed = await window.CustomConfirm('Are you sure you want to delete this customer? This cannot be undone.');
      if (!confirmed) return;
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

// ---------------------------------------------------------
// Add / Edit Order form — product line items + live totals
// ---------------------------------------------------------
function initAddOrderForm() {
  const form = document.getElementById('addOrderForm');
  if (!form) return;

  const rowsBody = document.getElementById('productRows');
  const isEdit = form.dataset.mode === 'edit';
  let rowSeq = 0;

  function rowTemplate(item) {
    rowSeq++;
    const id = 'row' + rowSeq;
    const name = item?.product_name || '';
    const qty = item?.quantity || 1;
    const price = item?.unit_price || 0;
    const productId = item?.product_id || '';

    const tr = document.createElement('tr');
    tr.dataset.rowId = id;
    tr.dataset.productId = productId;
    tr.innerHTML = `
      <td class="py-2 pr-2">
        <input type="file" class="item-image-input hidden" accept="image/*" id="img_${id}">
        <label for="img_${id}" class="cursor-pointer w-10 h-10 rounded bg-gray-100 flex items-center justify-center text-gray-400 hover:text-brand hover:bg-brand-50 border border-gray-200 overflow-hidden relative">
          <i class="ti ti-photo"></i>
          <img src="" class="absolute inset-0 w-full h-full object-cover hidden preview-img">
        </label>
      </td>
      <td class="py-2 pr-2 relative">
        <input type="text" class="product-name-input w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand" placeholder="Search product..." value="${name.replace(/"/g, '&quot;')}" autocomplete="off">
        <div class="product-suggestions hidden absolute z-10 bottom-full mb-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"></div>
      </td>
      <td class="py-2 pr-2 text-center">
        <input type="number" class="qty-input w-20 rounded-lg border border-gray-300 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand" value="${qty}" min="1">
      </td>
      <td class="py-2 pr-2 text-right">
        <input type="number" class="price-input w-24 rounded-lg border border-gray-300 px-2 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand" value="${price}" min="0" step="0.01">
      </td>
      <td class="py-2 pr-2 text-right line-total font-medium text-gray-800">PKR 0</td>
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
    const suggestBox = tr.querySelector('.product-suggestions');
    const removeBtn = tr.querySelector('.remove-row');
    const imgInput = tr.querySelector('.item-image-input');
    const imgPreview = tr.querySelector('.preview-img');

    if (imgInput && imgPreview) {
      imgInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
          const url = URL.createObjectURL(file);
          imgPreview.src = url;
          imgPreview.classList.remove('hidden');
          imgPreview.parentElement.querySelector('i').classList.add('hidden');
        } else {
          imgPreview.src = '';
          imgPreview.classList.add('hidden');
          imgPreview.parentElement.querySelector('i').classList.remove('hidden');
        }
      });
    }

    let debounceTimer;
    nameInput.addEventListener('input', function () {
      tr.dataset.productId = ''; // typing invalidates the previous selection
      clearTimeout(debounceTimer);
      const q = nameInput.value.trim();
      if (q.length < 2) { suggestBox.classList.add('hidden'); return; }
      debounceTimer = setTimeout(async () => {
        try {
          const res = await fetch('product_search.php?q=' + encodeURIComponent(q));
          const products = await res.json();
          if (!products.length) { suggestBox.classList.add('hidden'); return; }
          suggestBox.innerHTML = products.map(p =>
            `<button type="button" class="suggestion-item block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" data-id="${p.product_id}" data-price="${p.unit_price}" data-name="${p.name.replace(/"/g, '&quot;')}">
              <span class="font-medium text-gray-800">${p.name}</span>
              <span class="text-gray-400 text-xs block">PKR ${Number(p.unit_price).toLocaleString()}</span>
            </button>`
          ).join('');
          suggestBox.classList.remove('hidden');
        } catch (err) { /* silent fail, manual entry still works */ }
      }, 250);
    });

    suggestBox.addEventListener('click', function (e) {
      const item = e.target.closest('.suggestion-item');
      if (!item) return;
      nameInput.value = item.dataset.name;
      priceInput.value = item.dataset.price;
      tr.dataset.productId = item.dataset.id;
      suggestBox.classList.add('hidden');
      recalcTotals();
    });

    document.addEventListener('click', function (e) {
      if (!tr.contains(e.target)) suggestBox.classList.add('hidden');
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
      tr.querySelector('.line-total').textContent = 'PKR ' + lineTotal.toLocaleString();
      subtotal += lineTotal;
    });
    const shipping = parseFloat(document.getElementById('shippingInput').value) || 0;
    const taxEl = document.getElementById('sumTax');
    const tax = taxEl ? subtotal * 0.10 : 0;
    const grandTotal = subtotal + tax + shipping;

    document.getElementById('sumSubtotal').textContent = 'PKR ' + subtotal.toLocaleString();
    if (taxEl) taxEl.textContent = 'PKR ' + tax.toLocaleString();
    document.getElementById('sumGrandTotal').textContent = 'PKR ' + grandTotal.toLocaleString();
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
  if (shippingInput) shippingInput.addEventListener('input', recalcTotals);

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
               data-instagram="${c.instagram_handle || ''}" data-whatsapp="${c.whatsapp_number || ''}" 
               data-country="${c.country || ''}" data-city="${c.city || ''}" data-gender="${c.gender || ''}">
              <span class="font-medium text-gray-800">${c.full_name || 'Unnamed'}</span>
              <span class="text-gray-400 text-xs block">${c.whatsapp_number || c.instagram_handle || ''}</span>
            </button>`
          ).join('');
          results.classList.remove('hidden');
        } catch (err) { /* silent */ }
      }, 250);
    });

    results.addEventListener('click', function (e) {
      const item = e.target.closest('.customer-result');
      if (!item) return;
      document.getElementById('customerIdField').value = item.dataset.id;
      document.getElementById('fullNameField').value = item.dataset.name;
      
      const instaField = document.getElementById('instagramField');
      if(instaField) instaField.value = item.dataset.instagram;
      
      const waField = document.getElementById('whatsappField');
      if(waField) waField.value = item.dataset.whatsapp;
      
      const countryField = document.getElementById('countrySelect');
      if(countryField) {
          countryField.value = item.dataset.country;
          countryField.dispatchEvent(new Event('change')); // Trigger city list update
      }
      
      const cityField = document.getElementById('citySelect');
      if(cityField) {
          setTimeout(() => { cityField.value = item.dataset.city; }, 50);
      }

      const mGender = document.getElementById('genderMale');
      const fGender = document.getElementById('genderFemale');
      if (mGender && item.dataset.gender === 'male') mGender.checked = true;
      if (fGender && item.dataset.gender === 'female') fGender.checked = true;
      
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

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData(form);
    const rows = Array.from(rowsBody.querySelectorAll('tr')).filter(tr => tr.querySelector('.product-name-input').value.trim() !== '');
    
    let itemsJson = [];
    rows.forEach((tr, idx) => {
      const itemObj = {
        product_id: tr.dataset.productId || null,
        product_name: tr.querySelector('.product-name-input').value.trim(),
        sku: '',
        quantity: parseInt(tr.querySelector('.qty-input').value) || 1,
        unit_price: parseFloat(tr.querySelector('.price-input').value) || 0,
      };
      itemsJson.push(itemObj);

      const imgInput = tr.querySelector('.item-image-input');
      if (imgInput && imgInput.files.length > 0) {
        formData.append(`items_image_${idx}`, imgInput.files[0]);
      }
    });
    formData.append('items', JSON.stringify(itemsJson));

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
      const confirmed = await window.CustomConfirm('Are you sure you want to delete this order? This cannot be undone.');
      if (!confirmed) return;
      const id = btn.dataset.deleteOrder;
      try {
        const res = await fetch('deleteorder.php?id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listorder.php', true);
        } else {
          alert(data.message || 'Failed to delete this order.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
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
    sumAmountToPay.textContent = currency + ' ' + amount.toLocaleString();
    const remaining = Math.max(0, outstandingBalance - amount);
    sumRemaining.textContent = currency + ' ' + remaining.toLocaleString();
  }

  let debounceTimer;
  searchInput.addEventListener('input', function () {
    orderIdField.value = '';
    customerField.value = '';
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
             data-balance="${o.remaining_balance}" data-currency="${o.currency}">
            <span class="font-medium text-gray-800">#${o.order_label}</span>
            <span class="text-gray-400 text-xs block">${o.full_name || 'Unnamed'} · Balance: ${o.currency} ${Number(o.remaining_balance).toLocaleString()}</span>
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
    searchInput.value = item.querySelector('.font-medium').textContent.trim();
    outstandingBalance = parseFloat(item.dataset.balance) || 0;
    currency = item.dataset.currency || 'PKR';
    sumOutstanding.textContent = currency + ' ' + outstandingBalance.toLocaleString();
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
      const confirmed = await window.CustomConfirm('Are you sure you want to delete this payment? This will adjust the order balance and cannot be undone.');
      if (!confirmed) return;
      const id = btn.dataset.deletePayment;
      try {
        const res = await fetch('listpayment.php?action=delete&id=' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          navigateTo('listpayment.php', true);
        } else {
          alert(data.message || 'Failed to delete this payment.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
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
      const row = btn.closest('tr');
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
        if (data.photo_path) {
          const newSrc = '../' + data.photo_path;
          document.querySelectorAll('img[alt="Logo"], img[alt="Business Logo"]').forEach(img => img.src = newSrc);
        }
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
// Order list filters
// ---------------------------------------------------------
function initOrderFilterForm() {
  const form = document.getElementById('orderFilterForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    navigateTo('listorder.php' + (params ? '?' + params : ''), true);
  });

  const resetBtn = document.getElementById('resetOrderFiltersBtn');
  if (resetBtn) resetBtn.addEventListener('click', () => navigateTo('listorder.php', true));
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
      const id = btn.dataset.deleteExpense;
      const confirmed = await window.CustomConfirm('Are you sure you want to delete this expense? This cannot be undone.');
      if (confirmed) {
        deleteExpense(id);
      }
    });
  });

  async function deleteExpense(id) {
    try {
      const res = await fetch('listexpense.php?action=delete&id=' + id, { method: 'POST' });
      const data = await res.json();
      if (data.success) {
        navigateTo('listexpense.php', true);
      } else {
        alert(data.message || 'Failed to delete this expense.');
      }
    } catch (err) {
      alert('Network error. Please try again.');
    }
  }
}

// ---------------------------------------------------------
// Expense Filter dropdown
// ---------------------------------------------------------
function initExpenseFilterDropdownToggle() {
  const toggle = document.getElementById('expenseFilterToggle');
  const menu = document.getElementById('expenseFilterMenu');
  if (!toggle || !menu) return;
  if (toggle.dataset.bound) return;
  toggle.dataset.bound = '1';

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  });
  menu.addEventListener('click', (e) => {
    e.stopPropagation(); // keep menu open when interacting with form
  });
  document.addEventListener('click', () => menu.classList.add('hidden'));
}

// ---------------------------------------------------------
// Order Filter dropdown
// ---------------------------------------------------------
function initOrderFilterDropdownToggle() {
  const toggle = document.getElementById('orderFilterToggle');
  const menu = document.getElementById('orderFilterMenu');
  if (!toggle || !menu) return;
  if (toggle.dataset.bound) return;
  toggle.dataset.bound = '1';

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  });
  menu.addEventListener('click', (e) => {
    e.stopPropagation(); // keep menu open when interacting with form
  });
  document.addEventListener('click', () => menu.classList.add('hidden'));
}

// ---------------------------------------------------------
// Payment Filter dropdown
// ---------------------------------------------------------
function initPaymentFilterDropdownToggle() {
  const toggle = document.getElementById('paymentFilterToggle');
  const menu = document.getElementById('paymentFilterMenu');
  if (!toggle || !menu) return;
  if (toggle.dataset.bound) return;
  toggle.dataset.bound = '1';

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  });
  menu.addEventListener('click', (e) => {
    e.stopPropagation(); // keep menu open when interacting with form
  });
  document.addEventListener('click', () => menu.classList.add('hidden'));
}

document.addEventListener('DOMContentLoaded', function () {
  initPageScripts();
  setActiveSidebarLink(location.pathname);
});
