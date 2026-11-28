const sidebar = document.getElementById("mySidebar");
const overlay = document.getElementById("myOverlay");

function w3_open() {
  sidebar.classList.add("is-open");
  if (window.innerWidth <= 992) {
    sidebar.style.display = "flex";
    overlay.style.display = "block";
  }
}

function w3_close() {
  // 只在小螢幕 (≤992px) 才收起 sidebar，桌面版保持 w3.css 的 display:block!important
  if (window.innerWidth <= 992) {
    sidebar.classList.remove("is-open");
    sidebar.style.display = "none";
  }
  overlay.style.display = "none";
}

// Ensure the mobile menu starts closed, including after browser history restoration.
if (window.innerWidth <= 992) w3_close();
window.addEventListener("pageshow", () => { if (window.innerWidth <= 992) w3_close(); });
window.addEventListener("resize", () => {
  if (window.innerWidth > 992) {
    sidebar.classList.remove("is-open");
    sidebar.style.removeProperty("display");
    overlay.style.display = "none";
  } else if (!sidebar.classList.contains("is-open")) {
    w3_close();
  }
});

// ========== FAQ 卡片點擊展開 ========= 
document.querySelectorAll('.faq-card').forEach(function (card) {
  const trigger = card.querySelector('.faq-question');
  if (!trigger) return;
  function toggleFaq() {
    const isOpen = card.classList.toggle('open');
    trigger.setAttribute('aria-expanded', String(isOpen));
  }
  trigger.addEventListener('click', toggleFaq);
  trigger.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      toggleFaq();
    }
  });
});

// ========== Menu Filtering + Pagination + Shopping Cart ==========
(async function () {
  const ITEMS_PER_PAGE = 6;
  const grid = document.getElementById('menu-grid');
  const bar = document.getElementById('pagination-bar');
  const filterButtons = document.querySelectorAll('.filter-button');
  const dialogs = ['dish-dialog', 'cart-dialog', 'review-dialog', 'complete-dialog'];
  const dishDialog = document.getElementById('dish-dialog');
  const cartDialog = document.getElementById('cart-dialog');
  const reviewDialog = document.getElementById('review-dialog');
  const completeDialog = document.getElementById('complete-dialog');
  const quantityInput = document.getElementById('dish-quantity');
  const cartBadge = document.getElementById('cart-badge');
  const storageKey = 'tidebites_demo_cart_v1';
  if (!grid || !bar || !dishDialog || !cartDialog || !reviewDialog) return;

  // ── Safe HTML escaping for dynamic rendering ──
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }


  // ── Pickup locations and times come from the server (single source: config/order-options.php) ──
  // Times are computed on the server in Asia/Taipei, so the customer's device clock does not matter.
  const pickupSelect = document.getElementById('pickup-location');
  const pickupTimeSelect = document.getElementById('pickup-time');
  let pickupStatus = 'loading'; // 'loading' | 'ready' | 'failed'
  let pickupSchedule = null;    // { accepting, day, date, slots, message }

  function pickupTimeLabel(value) {
    // value: "YYYY-MM-DD HH:MM" (Asia/Taipei)
    if (!value) return '';
    const parts = value.split(' ');
    const day = pickupSchedule && pickupSchedule.date === parts[0]
      ? (pickupSchedule.day === 'tomorrow' ? 'Tomorrow' : 'Today') + ' · '
      : '';
    return day + parts[0] + ' ' + parts[1];
  }
  function renderPickupTimes(schedule) {
    pickupSchedule = schedule;
    const previous = pickupTimeSelect.value;
    if (!schedule || !schedule.accepting || !Array.isArray(schedule.slots) || schedule.slots.length === 0) {
      pickupTimeSelect.replaceChildren(new Option((schedule && schedule.message) || 'No pickup times available', ''));
      return;
    }
    const options = schedule.slots.map(function (slot) {
      const value = schedule.date + ' ' + slot;
      return new Option(pickupTimeLabel(value), value);
    });
    pickupTimeSelect.replaceChildren(new Option('Choose a pickup time', ''), ...options);
    if (options.some(function (o) { return o.value === previous; })) pickupTimeSelect.value = previous;
  }
  async function loadOrderOptions() {
    try {
      const res = await fetch('api/orders/options.php');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const list = data && Array.isArray(data.pickup_locations)
        ? data.pickup_locations.filter(function (v) { return typeof v === 'string' && v !== ''; })
        : [];
      if (list.length === 0 || !data.pickup_schedule) throw new Error('Invalid order options');
      const previous = pickupSelect.value;
      pickupSelect.replaceChildren(
        new Option('Choose a campus pickup location', ''),
        ...list.map(function (v) { return new Option(v, v); })
      );
      if (list.includes(previous)) pickupSelect.value = previous;
      renderPickupTimes(data.pickup_schedule);
      pickupStatus = 'ready';
    } catch (err) {
      console.error('Order options API failed:', err);
      if (pickupStatus !== 'ready') {
        pickupSelect.replaceChildren(new Option('Pickup locations unavailable — please reload the page', ''));
        pickupTimeSelect.replaceChildren(new Option('Pickup times unavailable — please reload the page', ''));
        pickupStatus = 'failed';
      }
    }
  }
  loadOrderOptions();

  let menu = [];
  try {
    const res = await fetch('api/foods/list.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (!Array.isArray(data)) throw new Error('Invalid response');
    menu = data;
  } catch (err) {
    console.error('Menu API failed:', err);
    showMenuUnavailable();
    return;
  }

  // Menu could not load: explain it, offer a retry, and keep the cart button responsive.
  function showMenuUnavailable() {
    const box = document.createElement('div');
    box.className = 'tb-muted';
    box.style.cssText = 'grid-column:1/-1;padding:2rem;text-align:center';
    const message = document.createElement('p');
    message.textContent = 'Unable to load the menu right now. Ordering is paused until the menu is available.';
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'w3-button w3-black';
    retry.textContent = 'Try Again';
    retry.addEventListener('click', function () { window.location.reload(); });
    box.append(message, retry);
    grid.replaceChildren(box);
    bar.parentElement.style.display = 'none';
    document.getElementById('open-cart').addEventListener('click', function () {
      if (window.innerWidth <= 992) w3_close();
      notify('The menu is unavailable right now, so ordering is paused. Please try again later.');
    });
  }

  // Remove the loading placeholder once menu data is ready.
  const loadingEl = document.getElementById('menu-loading');
  if (loadingEl) loadingEl.remove();

  // ── Render article cards into #menu-grid ──
  grid.replaceChildren();
  menu.forEach(function (item) {
    const article = document.createElement('article');
    article.dataset.id = String(item.id);
    article.dataset.category = item.category;
    article.tabIndex = 0;
    article.setAttribute('role', 'button');
    article.setAttribute('aria-label', 'View dish details');
    const categoryLabel = item.category === 'meals' ? 'Meals' : 'Desserts';
    article.innerHTML =
      '<img src="' + escHtml(item.image_path) + '" alt="' + escHtml(item.image_alt) + '" class="w3-hover-opacity" style="width:100%">' +
      '<div class="w3-container w3-white">' +
      '<p class="card-title">' + escHtml(item.name) + '</p>' +
      '<p class="card-meta">' + categoryLabel + ' · NT$' + Math.round(item.price) + '</p>' +
      '<p class="card-desc">' + escHtml(item.description) + '</p>' +
      '</div>';
    grid.appendChild(article);
  });

  if (menu.length === 0) {
    const message = document.createElement('p');
    message.textContent = 'No dishes are currently available.';
    grid.appendChild(message);
  }

  const articles = Array.from(grid.querySelectorAll('article'));

  // byId uses String keys to match localStorage (which also stores string keys).
  const byId = new Map(menu.map(function (item) {
    return [String(item.id), {
      id: String(item.id), name: item.name, category: item.category,
      price: item.price, description: item.description,
      image: item.image_path, alt: item.image_alt
    }];
  }));

  let category = 'all';
  let currentPage = 1;
  let selectedDishId = null;
  let cart = {};
  let removedFromCart = false;
  let reviewedItems = [];
  let reviewedStudentDiscount = false;
  let isPlacingOrder = false;

  // Load saved cart; drop items that are no longer active in the DB.
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
      Object.entries(saved).forEach(function ([id, qty]) {
        if (byId.has(id) && Number.isInteger(qty) && qty > 0 && qty <= 99) {
          cart[id] = qty;
        } else if (Number.isInteger(qty) && qty > 0) {
          removedFromCart = true; // item was removed or deactivated
        }
      });
    }
  } catch (error) { cart = {}; }

  const money = function (n) { return 'NT$' + n.toLocaleString('en-US'); };
  const clampQty = function (v) { return Math.min(99, Math.max(1, Number.parseInt(v, 10) || 1)); };
  const cartSubtotal = function () {
    return Object.entries(cart).reduce(function (sum, pair) { return sum + byId.get(pair[0]).price * pair[1]; }, 0);
  };
  // Student discount: 10 % only when the customer promises to show a student ID at pickup.
  const studentDiscountBox = document.getElementById('student-discount');
  function wantsStudentDiscount() { return !!(studentDiscountBox && studentDiscountBox.checked); }
  function setReminder(id, show) {
    const el = document.getElementById(id);
    if (el) el.hidden = !show;
  }
  function cartAmounts() {
    const subtotal = cartSubtotal();
    const discount = wantsStudentDiscount() ? Math.round(subtotal * 0.1) : 0;
    return { subtotal: subtotal, discount: discount, total: subtotal - discount };
  }
  const cartQuantity = function () { return Object.values(cart).reduce(function (s, q) { return s + q; }, 0); };

  function saveCart() {
    try { localStorage.setItem(storageKey, JSON.stringify(cart)); } catch (e) { }
    const count = cartQuantity();
    cartBadge.textContent = count;
    cartBadge.hidden = count === 0;
  }
  function notify(message) {
    const toast = document.getElementById('cart-toast');
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(notify.timeout);
    notify.timeout = setTimeout(function () { toast.hidden = true; }, 2500);
  }
  function openDialog(dialog) {
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  }
  function closeDialog(dialog) { dialog.close ? dialog.close() : dialog.removeAttribute('open'); }
  function setOrderLoading(loading) {
    isPlacingOrder = loading;
    const placeButton = document.getElementById('place-order');
    const backButton = document.getElementById('back-to-cart');
    const closeButton = reviewDialog.querySelector('.tb-close');
    placeButton.disabled = loading;
    backButton.disabled = loading;
    closeButton.disabled = loading;
    placeButton.textContent = loading
        ? 'Placing order…'
        : 'Place Order';
}

  dialogs.forEach(function (id) {
    const dialog = document.getElementById(id);
    dialog.addEventListener('click', function (event) {
        if (dialog === reviewDialog && isPlacingOrder) { return; }
        if (event.target === dialog) { closeDialog(dialog);}});
  });
  reviewDialog.addEventListener('cancel', function (event) {
    if (isPlacingOrder) {event.preventDefault();}
  });
  document.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
    btn.addEventListener('click', function () { 
      const dialog = document.getElementById(btn.dataset.closeDialog);
      if (dialog === reviewDialog && isPlacingOrder) return;

      closeDialog(dialog);; });
  });

  // ===== 餐點圖片放大功能 =====
  const zoomButton = document.getElementById('enlarge-dish-photo');
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('lightbox-image');
  const closeZoomButton = document.getElementById('close-lightbox');

  if (zoomButton && lightbox && lightboxImage && closeZoomButton) {
    zoomButton.addEventListener('click', function (event) {
      event.preventDefault(); event.stopPropagation();
      const originalImage = document.getElementById('dish-dialog-image');
      if (!originalImage || !originalImage.src) return;
      lightboxImage.src = originalImage.currentSrc || originalImage.src;
      lightboxImage.alt = originalImage.alt;
      if (!lightbox.open) lightbox.showModal();
    });
    closeZoomButton.addEventListener('click', function () { lightbox.close(); });
    lightbox.addEventListener('click', function (event) {
      if (event.target === lightbox) {
        const rect = lightboxImage.getBoundingClientRect();
        const inside = event.clientX >= rect.left && event.clientX <= rect.right &&
          event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) lightbox.close();
      }
    });
  }

  // ── Filtering & Pagination ──
  function showMenu() {
    const filtered = articles.filter(function (a) { return category === 'all' || a.dataset.category === category; });
    const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    articles.forEach(function (a) { a.hidden = true; a.style.display = 'none'; });
    filtered.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE)
      .forEach(function (a) { a.hidden = false; a.style.display = ''; });
    bar.replaceChildren();
    bar.parentElement.style.display = totalPages <= 1 ? 'none' : '';
    if (totalPages > 1) {
      addPageButton('«', currentPage - 1, currentPage === 1, false);
      for (let p = 1; p <= totalPages; p++) addPageButton(String(p), p, false, p === currentPage);
      addPageButton('»', currentPage + 1, currentPage === totalPages, false);
    }
  }
  function addPageButton(label, page, disabled, active) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'w3-bar-item w3-button ' + (active ? 'w3-black' : 'w3-white w3-hover-black');
    button.textContent = label;
    button.disabled = disabled;
    if (active) button.setAttribute('aria-current', 'page');
    button.addEventListener('click', function () {
      currentPage = page; showMenu();
      document.getElementById('portfolio').scrollIntoView({ behavior: 'smooth' });
    });
    bar.appendChild(button);
  }
  filterButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      category = button.dataset.category; currentPage = 1;
      filterButtons.forEach(function (btn) {
        btn.classList.toggle('w3-black', btn === button);
        btn.classList.toggle('w3-white', btn !== button);
      });
      showMenu();
    });
  });

  // ── Dish dialog ──
  function updateDishSubtotal() {
    const dish = byId.get(selectedDishId);
    if (!dish) return;
    const qty = clampQty(quantityInput.value);
    quantityInput.value = qty;
    document.getElementById('dish-subtotal').textContent = money(dish.price * qty);
  }
  function openDish(id) {
    const dish = byId.get(id);
    if (!dish) {
      notify('This dish is no longer available.');
      return;}
    selectedDishId = id;
    const photo = document.getElementById('dish-dialog-image');
    photo.src = dish.image; photo.alt = dish.alt;
    document.getElementById('dish-dialog-title').textContent = dish.name;
    document.getElementById('dish-dialog-meta').textContent =
      (dish.category === 'meals' ? 'Meals' : 'Desserts') + ' · ' + money(dish.price);
    document.getElementById('dish-dialog-desc').textContent = dish.description;
    quantityInput.value = 1;
    updateDishSubtotal();
    openDialog(dishDialog);
  }
  articles.forEach(function (article) {
    article.addEventListener('click', function () { openDish(article.dataset.id); });
    article.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDish(article.dataset.id); }
    });
  });
  document.getElementById('dish-minus').addEventListener('click', function () {
    quantityInput.value = Math.max(1, clampQty(quantityInput.value) - 1); updateDishSubtotal();
  });
  document.getElementById('dish-plus').addEventListener('click', function () {
    quantityInput.value = Math.min(99, clampQty(quantityInput.value) + 1); updateDishSubtotal();
  });
  quantityInput.addEventListener('change', updateDishSubtotal);
  quantityInput.addEventListener('input', function () { if (quantityInput.value !== '') updateDishSubtotal(); });
  document.getElementById('add-to-cart').addEventListener('click', function () {
    if (!selectedDishId) return;
    const qty = clampQty(quantityInput.value);
    cart[selectedDishId] = Math.min(99, (cart[selectedDishId] || 0) + qty);
    saveCart(); closeDialog(dishDialog); notify('Added to cart!');
  });

  // ── Cart dialog ──
  function renderCart() {
    const container = document.getElementById('cart-items');
    container.replaceChildren();
    const entries = Object.entries(cart);
    document.getElementById('cart-checkout').hidden = entries.length === 0;
    if (entries.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'tb-muted';
      empty.textContent = 'Your cart is empty. Choose a dish to get started!';
      container.appendChild(empty);
    }
    entries.forEach(function ([id, qty]) {
      const item = byId.get(id);
      const row = document.createElement('div'); row.className = 'tb-cart-row';
      const img = document.createElement('img'); img.src = item.image; img.alt = item.alt;
      const info = document.createElement('div');
      const name = document.createElement('strong'); name.textContent = item.name;
      const price = document.createElement('p');
      price.textContent = money(item.price) + ' × ' + qty + ' = ' + money(item.price * qty);
      const controls = document.createElement('div'); controls.className = 'tb-cart-controls';
      function addControl(label, action, ariaLabel) {
        const b = document.createElement('button'); b.type = 'button'; b.textContent = label;
        b.setAttribute('aria-label', ariaLabel); b.addEventListener('click', action); controls.appendChild(b);
      }
      addControl('−', function () { changeCartQty(id, -1); }, 'Decrease ' + item.name);
      const number = document.createElement('span'); number.textContent = String(qty); controls.appendChild(number);
      addControl('+', function () { changeCartQty(id, 1); }, 'Increase ' + item.name);
      addControl('Remove', function () { delete cart[id]; saveCart(); renderCart(); }, 'Remove ' + item.name);
      info.append(name, price, controls); row.append(img, info); container.appendChild(row);
    });
    const amounts = cartAmounts();
    document.getElementById('cart-subtotal').textContent = money(amounts.subtotal);
    document.getElementById('cart-discount').textContent = (amounts.discount ? '-' : '') + money(amounts.discount);
    document.getElementById('cart-total').textContent = money(amounts.total);
    setReminder('student-id-reminder', wantsStudentDiscount());
  }
  if (studentDiscountBox) studentDiscountBox.addEventListener('change', renderCart);
  function changeCartQty(id, delta) {
    const newQty = cart[id] + delta;
    if (newQty <= 0) delete cart[id]; else cart[id] = Math.min(99, newQty);
    saveCart(); renderCart();
  }
  document.getElementById('open-cart').addEventListener('click', function () {
    if (window.innerWidth <= 992) w3_close();
    loadOrderOptions(); // pickup times depend on the current time; refresh them each time
    renderCart(); openDialog(cartDialog);
  });

  // ── Phone validation (same rule as api/orders/place.php) ──
  const phoneField = document.getElementById('customer-phone');
  function phoneError(value) {
    if (!/^\+?[0-9 ()-]+$/.test(value)) return 'Use digits, spaces, hyphens, parentheses and an optional leading +.';
    const digits = value.replace(/\D/g, '').length;
    if (digits < 8 || digits > 15) return 'Enter a phone number with 8–15 digits.';
    return '';
  }
  phoneField.addEventListener('input', function () { phoneField.setCustomValidity(''); });

  // ── Review Order: validate fields and build summary ──
  document.getElementById('review-order').addEventListener('click', function () {
    if (cartQuantity() === 0) return;

    const nameInput = document.getElementById('customer-name');
    const phoneInput = phoneField;
    const pickup = pickupSelect;

    if (pickupStatus !== 'ready') {
      alert(pickupStatus === 'loading'
        ? 'Pickup locations are still loading. Please try again in a moment.'
        : 'Pickup locations are unavailable. Please reload the page.');
      return;
    }
    if (!nameInput.value.trim()) { nameInput.reportValidity(); nameInput.focus(); return; }
    phoneInput.setCustomValidity('');
    if (!phoneInput.value.trim()) { phoneInput.reportValidity(); phoneInput.focus(); return; }
    if (!pickup.value) { pickup.reportValidity(); pickup.focus(); return; }
    if (!pickupSchedule || !pickupSchedule.accepting) {
      alert((pickupSchedule && pickupSchedule.message) || 'No pickup times are available right now.');
      return;
    }
    if (!pickupTimeSelect.value) { pickupTimeSelect.reportValidity(); pickupTimeSelect.focus(); return; }
    phoneInput.setCustomValidity(phoneError(phoneInput.value.trim()));
    if (!phoneInput.checkValidity()) { phoneInput.reportValidity(); phoneInput.focus(); return; }

    reviewedItems = Object.entries(cart).map(function ([id, qty]) {
    return { id: parseInt(id, 10), quantity: qty, expected_price: byId.get(id).price};});

    const summary = document.getElementById('review-details');
    summary.replaceChildren();
    Object.entries(cart).forEach(function ([id, qty]) {
      const line = document.createElement('p');
      line.textContent = byId.get(id).name + ' × ' + qty + ' — ' + money(qty * byId.get(id).price);
      summary.appendChild(line);
    });
    const note = document.getElementById('cart-note').value.trim();
    const amounts = cartAmounts();
    [
      ['Customer Name', nameInput.value.trim()],
      ['Phone', phoneInput.value.trim()],
      ['Subtotal', money(amounts.subtotal)],
      wantsStudentDiscount()
        ? ['Student Discount (10%)', '-' + money(amounts.discount)]
        : ['Student Discount', 'Not applied'],
      ['Total', money(amounts.total)],
      ['Campus Pickup Location', pickup.value],
      ['Pickup Time', pickupTimeLabel(pickupTimeSelect.value)],
      ['Notes', note || '(None)']
    ].forEach(function ([label, value]) {
      const row = document.createElement('p');
      const strong = document.createElement('strong'); strong.textContent = label + ': ';
      row.append(strong, document.createTextNode(value));
      summary.appendChild(row);
    });
    reviewedStudentDiscount = wantsStudentDiscount();
    setReminder('review-student-reminder', reviewedStudentDiscount);
    closeDialog(cartDialog); openDialog(reviewDialog);
  });

  document.getElementById('back-to-cart').addEventListener('click', function () {
    if (isPlacingOrder) return; closeDialog(reviewDialog); renderCart(); openDialog(cartDialog);
  });

  // ── Place Order: POST to API (B: api/orders/place.php) ──
  document.getElementById('place-order').addEventListener('click', async function () {
    if ( isPlacingOrder || !reviewDialog.open || cartQuantity() === 0) return;
    setOrderLoading(true);
    
    try {
      const items = reviewedItems;
      let res;
      try {
        res = await fetch('api/orders/place.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            customer_name: document.getElementById('customer-name').value.trim(),
            phone: document.getElementById('customer-phone').value.trim(),
            delivery_location: document.getElementById('pickup-location').value,
            notes: document.getElementById('cart-note').value.trim(),
            pickup_time: pickupTimeSelect.value,
            student_discount: reviewedStudentDiscount,
            items: items
          })
        });
      } catch (networkError) {
        // fetch() only rejects when the request never reached the server.
        alert('Network error. Please check your connection and try again.');
        return;
      }

      // The server may answer with a non-JSON page (e.g. a PHP fatal error).
      let data = null;
      try { data = await res.json(); } catch (parseError) { data = null; }

      if (!res.ok) {
        if (res.status === 409 && data && data.code === 'PRICE_CHANGED' && Array.isArray(data.price_changes)) {
            const changes = data.price_changes.map(function (item) { return item.name + ': ' + money(item.old_price) + ' → ' + money(item.new_price);});
            alert( 'Menu prices have changed:\n\n' + changes.join('\n') + '\n\nPlease review your order again.');
            saveCart();
            window.location.reload();
            return;
        }
        if (res.status === 422 && data && data.code === 'ITEM_UNAVAILABLE') {
            // Reloading re-fetches the menu; the saved cart drops inactive items and shows a notice.
            const names = (Array.isArray(data.unavailable_ids) ? data.unavailable_ids : [])
              .map(function (id) { const dish = byId.get(String(id)); return dish ? dish.name : null; })
              .filter(Boolean);
            alert('Some items in your cart are no longer available' + (names.length ? ':\n\n' + names.join('\n') : '.') +
              '\n\nThe page will reload so you can update your cart.');
            saveCart();
            window.location.reload();
            return;
        }
        if (res.status === 422 && data && data.code === 'PICKUP_TIME_UNAVAILABLE') {
            // The chosen slot expired while the customer was reviewing; show fresh times in the cart.
            if (data.pickup_schedule) renderPickupTimes(data.pickup_schedule);
            alert(data.error || 'The selected pickup time is no longer available. Please choose another pickup time.');
            closeDialog(reviewDialog); renderCart(); openDialog(cartDialog);
            return;
        }
        alert((data && data.error) || 'The server could not process your order (HTTP ' + res.status + '). Please try again later.');
        return;
      }

      if (!data || !Number.isInteger(data.order_id) || !Array.isArray(data.items)) {
        // Status was OK, so the order may have been saved; avoid encouraging a duplicate.
        alert('Your order may have been received, but the confirmation could not be read. Please do not place it again right away; contact TideBites to check your order.');
        return;
      }

      // Clear state as soon as the order is confirmed, before rendering the summary.
      cart = {};
      saveCart();
      document.getElementById('cart-note').value = '';
      document.getElementById('pickup-location').value = '';
      pickupTimeSelect.value = '';
      document.getElementById('customer-name').value = '';
      document.getElementById('customer-phone').value = '';
      if (studentDiscountBox) studentDiscountBox.checked = false;
      renderCart();

      // Show order ID in complete dialog.
      const orderIdEl = document.getElementById('complete-order-id');
      if (orderIdEl) orderIdEl.textContent = '#' + data.order_id;

      // Render the actual order details returned by the server.
      const completeDetails = document.getElementById('complete-details');
      completeDetails.replaceChildren();

      // Display actual ordered items returned by PHP.
      data.items.forEach(function (item) {
          const line = document.createElement('p');
          line.textContent = item.name + ' × ' + item.quantity + ' — ' + money(item.unit_price * item.quantity);
          completeDetails.appendChild(line);});

      // Display actual order information returned by PHP.
      [
          ['Customer Name', data.customer_name],
          ['Phone', data.phone],
          ['Subtotal', money(data.subtotal)],
          data.student_discount === true
            ? ['Student Discount (10%)', '-' + money(data.discount_amount)]
            : ['Student Discount', 'Not applied'],
          ['Total', money(data.total_amount)],
          ['Campus Pickup Location', data.delivery_location],
          ['Pickup Time', pickupTimeLabel(data.pickup_time)],
          ['Notes', data.notes || '(None)']

      ].forEach(function ([label, value]) {
          const row = document.createElement('p');
          const strong = document.createElement('strong');

          strong.textContent = label + ': ';

          row.append(
              strong,
              document.createTextNode(String(value))
          );

          completeDetails.appendChild(row);
      });
      setReminder('complete-student-reminder', data.student_discount === true);

      closeDialog(reviewDialog);
      openDialog(completeDialog);
    } catch (err) {
      console.error('Order confirmation failed:', err);
      alert('Something went wrong while showing your order confirmation. Please reload the page before ordering again.');
    } finally {
        setOrderLoading(false);
    }
  });

  // Notify user if saved cart items were removed (deactivated by admin).
  if (removedFromCart) {
    setTimeout(function () {
      notify('Some items in your saved cart are no longer available and have been removed.');
    }, 1200);
  }

  saveCart();
  showMenu();
})();

// ========== Click Bubble Animation (visual only, no sound) ==========
(function () {
  var USE_IMAGE = false;

  function spawnBubbles(x, y) {
    var count = Math.floor(Math.random() * 3) + 4;

    for (var i = 0; i < count; i++) {
      var size = Math.random() * 25 + 8;
      var spreadX = (Math.random() - 0.5) * 60;
      var floatDur = (Math.random() * 1.0 + 1.0).toFixed(2);
      var swayDur = (Math.random() * 0.7 + 0.4).toFixed(2);
      var delay = (Math.random() * 0.6).toFixed(2);
      var swayAmount = (Math.random() > 0.5 ? 1 : -1) * (Math.random() * 10 + 35);
      var wrapper = document.createElement('div');
      wrapper.className = 'bubble-wrapper';
      wrapper.style.width = size + 'px';
      wrapper.style.height = size + 'px';
      wrapper.style.left = (x + spreadX - size / 2) + 'px';
      wrapper.style.top = (y - size / 2) + 'px';
      wrapper.style.animation = 'bubbleFloatUp ' + floatDur + 's ease-in ' + delay + 's forwards';

      var el = document.createElement('div');
      el.className = 'click-bubble' + (USE_IMAGE ? ' use-image' : '');
      el.style.width = size + 'px';
      el.style.height = size + 'px';
      el.style.setProperty('--sway-amount', swayAmount + 'px');
      el.style.animation = 'bubbleSway ' + swayDur + 's ease-in-out ' + delay + 's infinite alternate';

      wrapper.appendChild(el);
      document.body.appendChild(wrapper);
      wrapper.addEventListener('animationend', function () {
        this.remove();
      });
    }
  }

  // 滑鼠點擊
  document.addEventListener('click', function (e) {
    spawnBubbles(e.clientX, e.clientY);
  });

  // 手機觸控
  document.addEventListener('touchstart', function (e) {
    Array.from(e.touches).forEach(function (t) {
      spawnBubbles(t.clientX, t.clientY);
    });
  }, { passive: true });

})();


const seaweeds = document.querySelectorAll('.w3-seaweed');

let lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
let lastScrollTime = Date.now();
let currentPlaybackRate = 1.0;
let inertiaInterval = null;

window.addEventListener('scroll', function () {
  const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const currentTime = Date.now();

  const deltaY = Math.abs(currentScrollTop - lastScrollTop);
  const deltaTime = currentTime - lastScrollTime;

  if (deltaTime > 0) {
    const speed = deltaY / deltaTime;

    if (inertiaInterval) {
      clearInterval(inertiaInterval);
      inertiaInterval = null;
    }

    if (speed > 0.02) {
      currentPlaybackRate = Math.min(Math.max(speed * 3.5, 0.8), 12.0);

      seaweeds.forEach(seaweed => {
        seaweed.style.animationPlayState = 'running';
        seaweed.getAnimations().forEach(anim => {
          anim.playbackRate = currentPlaybackRate;
        });
      });
    }
  }

  lastScrollTop = currentScrollTop;
  lastScrollTime = currentTime;

  clearTimeout(window.scrollStopTimer);
  window.scrollStopTimer = setTimeout(() => {
    let startTime = Date.now();
    let startRate = currentPlaybackRate;
    let duration = 2000;

    inertiaInterval = setInterval(() => {
      let elapsed = Date.now() - startTime;
      let progress = elapsed / duration;

      if (progress < 1) {
        currentPlaybackRate = startRate * (1 - progress);
        seaweeds.forEach(seaweed => {
          seaweed.getAnimations().forEach(anim => {
            anim.playbackRate = Math.max(currentPlaybackRate, 0.1);
          });
        });
      } else {

        clearInterval(inertiaInterval);
        inertiaInterval = null;
        seaweeds.forEach(seaweed => {
          seaweed.style.animationPlayState = 'paused';
        });
      }
    }, 30);
  }, 100);
});

// ========== Pufferfish ==========//
function togglePufferfish() {
  const img = document.getElementById('pufferfish');
  if (img.src.includes('pufferfish1.png')) {
    img.src = 'assets/images/pufferfish2.png';
  } else {
    img.src = 'assets/images/pufferfish1.png';
  }
}

// ========== Submarine ==========//
function toggleSubmarineLight(container) {
  container.classList.toggle('light-on');
}

// ========== Octopus ==========//
function spawnRandomInk(event) {
  const container = event.currentTarget;
  const ink = document.createElement('img');
  ink.src = 'assets/images/ink.png';
  ink.alt = 'Ink';
  ink.className = 'dynamic-ink';
  const randomX = (Math.random() - 0.5) * 140;
  const randomY = (Math.random() - 0.5) * 120;
  const randomRotate = Math.floor(Math.random() * 360);
  ink.style.left = `calc(50% + ${randomX}px)`;
  ink.style.top = `calc(50% + ${randomY}px)`;
  ink.style.transform = `translate(-50%, -50%) rotate(${randomRotate}deg) scale(0.6)`;
  container.appendChild(ink);
  requestAnimationFrame(() => {
    ink.classList.add('fade-in');
    ink.style.transform = `translate(-50%, -50%) rotate(${randomRotate}deg) scale(1)`;
  });
  setTimeout(() => {
    ink.classList.remove('fade-in');
    ink.style.transform = `translate(-50%, -50%) rotate(${randomRotate}deg) scale(0.6)`;
    setTimeout(() => {
      if (ink.parentNode) {
        ink.parentNode.removeChild(ink);
      }
    }, 300);
  }, 3000);
}

// ========== Fish-About ==========//
const fishLeftToRight = ['fish1.png', 'fish2.png', 'fish4.png'];
const fishRightToLeft = ['fish3.png', 'fish5.png', 'fish6.png'];

function spawnBackgroundFish() {
  const container = document.getElementById('fishContainer');
  if (!container) return;
  const fish = document.createElement('img');
  fish.className = 'bg-swimming-fish';
  const isLeftToRight = Math.random() < 0.5;
  let fishList, animationName;

  if (isLeftToRight) {
    fishList = fishLeftToRight;
    animationName = 'swimLeftToRight';
  } else {
    fishList = fishRightToLeft;
    animationName = 'swimRightToLeft';
  }
  const randomFile = fishList[Math.floor(Math.random() * fishList.length)];
  fish.src = `assets/images/${randomFile}`;
  fish.alt = 'Swimming Fish';

  const randomTop = Math.random() * 85 + 5;
  fish.style.top = `${randomTop}%`;

  const randomWidth = Math.floor(Math.random() * 35) + 40;
  fish.style.width = `${randomWidth}px`;

  const randomDuration = Math.random() * 8 + 8;
  fish.style.animation = `${animationName} ${randomDuration}s linear forwards`;

  container.appendChild(fish);

  fish.addEventListener('animationend', () => {
    fish.remove();
  });
}

setInterval(() => { if (!document.hidden) spawnBackgroundFish(); }, 4000);
spawnBackgroundFish();
spawnBackgroundFish();

// ========== Treasure ==========//
document.getElementById('treasureImg').addEventListener('click', function () {
  const wrapper = document.getElementById('treasureWrapper');

  for (let i = 0; i < 5; i++) {
    const spark = document.createElement('img');
    spark.src = 'assets/images/spark.png';
    spark.className = 'spark-img';

    const isSmallScreen = window.innerWidth <= 390;

    const size = isSmallScreen
      ? Math.floor(Math.random() * 13) + 10
      : Math.floor(Math.random() * 26) + 20;

    spark.style.width = `${size}px`;
    spark.style.height = 'auto';

    const offsetX = isSmallScreen
      ? Math.floor(Math.random() * 140) - 20
      : Math.floor(Math.random() * 240) - 80;

    const offsetY = isSmallScreen
      ? Math.floor(Math.random() * 100) - 30
      : Math.floor(Math.random() * 180) - 80;

    spark.style.left = `${offsetX}px`;
    spark.style.top = `${offsetY}px`;

    spark.style.animationDuration = `${0.3 + Math.random() * 0.5}s`;

    wrapper.appendChild(spark);

    setTimeout(() => {
      spark.remove();
    }, 5000);
  }
});

// ========== Crab ==========//
const crab = document.getElementById('crabImg');
const footer = document.querySelector('.tb-footer');

function moveCrab() {
  const footerWidth = footer.clientWidth;
  const crabWidth = crab.clientWidth;

  const minX = 10;
  const maxX = footerWidth - crabWidth - 10;
  const targetX = Math.floor(Math.random() * (maxX - minX + 1)) + minX;

  const duration = (Math.random() * 3 + 2).toFixed(2);
  crab.style.transitionDuration = `${duration}s`;
  crab.style.left = `${targetX}px`;

  const pauseTime = (Math.random() * 1 + 1) * 1000;
  setTimeout(moveCrab, duration * 1000 + pauseTime);
}
moveCrab();

// ========== Fish-Disocunt&FAQ ==========//
document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("contactFishContainer");
  if (!container) return;

  const leftToRightFish = ["assets/images/fish8.png", "assets/images/fish11.png"];
  const rightToLeftFish = ["assets/images/fish7.png", "assets/images/fish10.png", "assets/images/fish12.png"];

  function spawnFish() {
    const isLeftToRight = Math.random() < 0.5;
    const fishSrcs = isLeftToRight ? leftToRightFish : rightToLeftFish;

    const chosenSrc = fishSrcs[Math.floor(Math.random() * fishSrcs.length)];

    const fish = document.createElement("img");
    fish.src = chosenSrc;
    fish.className = "background-swimming-fish";
    if (!isLeftToRight) {
      fish.classList.add("fish-flip");
    }

    const randomWidth = Math.floor(Math.random() * 25) + 35;
    fish.style.width = `${randomWidth}px`;

    const containerHeight = container.clientHeight || 400;
    const randomTop = Math.floor(Math.random() * (containerHeight - 80));
    fish.style.top = `${randomTop}px`;

    const containerWidth = container.clientWidth || window.innerWidth;
    const startX = isLeftToRight ? -120 : containerWidth + 120;
    const endX = isLeftToRight ? containerWidth + 120 : -120;

    fish.style.left = `${startX}px`;
    container.appendChild(fish);

    const duration = (Math.random() * 6 + 6).toFixed(2);
    fish.style.transition = `left ${duration}s linear`;

    void fish.offsetWidth;
    fish.style.left = `${endX}px`;

    setTimeout(() => {
      fish.remove();
    }, duration * 1000);
  }

  function scheduleNextFish() {
    if (!document.hidden) spawnFish();
    const nextTime = Math.random() * 2500 + 2500;
    setTimeout(scheduleNextFish, nextTime);
  }

  setTimeout(scheduleNextFish, 1000);
});