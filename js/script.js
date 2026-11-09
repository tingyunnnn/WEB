const sidebar = document.getElementById("mySidebar");
const overlay = document.getElementById("myOverlay");

function w3_open() {
  sidebar.style.display = "block";
  overlay.style.display = "block";
}

function w3_close() {
  // 只在小螢幕 (≤992px) 才收起 sidebar，桌面版保持 w3.css 的 display:block!important
  if (window.innerWidth <= 992) {
    sidebar.style.display = "none";
  }
  overlay.style.display = "none";
}

// ========== FAQ 卡片點擊展開 ==========
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
(function () {
  const ITEMS_PER_PAGE = 6;
  const articles = Array.from(document.querySelectorAll('#menu-grid article'));
  const bar = document.getElementById('pagination-bar');
  const filterButtons = document.querySelectorAll('.filter-button');
  const dialogs = ['dish-dialog', 'cart-dialog', 'review-dialog'];
  const dishDialog = document.getElementById('dish-dialog');
  const cartDialog = document.getElementById('cart-dialog');
  const reviewDialog = document.getElementById('review-dialog');
  const quantityInput = document.getElementById('dish-quantity');
  const cartBadge = document.getElementById('cart-badge');
  const storageKey = 'tidebites_demo_cart_v1';
  if (!articles.length || !bar || !dishDialog || !cartDialog || !reviewDialog) return;

  // Use the existing article content as the single source of truth for the menu.
  const menu = articles.map(function (article) {
    const meta = article.querySelector('.card-meta').textContent;
    const priceMatch = meta.match(/NT\$\s*(\d+)/i);
    return {
      id: article.dataset.id,
      name: article.querySelector('.card-title').textContent.trim(),
      category: article.dataset.category,
      price: priceMatch ? Number(priceMatch[1]) : 0,
      description: article.querySelector('.card-desc').textContent.trim(),
      image: article.querySelector('img').getAttribute('src'),
      alt: article.querySelector('img').alt
    };
  });
  const byId = new Map(menu.map(item => [item.id, item]));
  let category = 'all';
  let currentPage = 1;
  let selectedDishId = null;
  let cart = {};
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
      Object.entries(saved).forEach(([id, qty]) => {
        if (byId.has(id) && Number.isInteger(qty) && qty > 0 && qty <= 99) cart[id] = qty;
      });
    }
  } catch (error) { cart = {}; }

  const money = n => 'NT$' + n.toLocaleString('en-US');
  const clampQty = value => Math.min(99, Math.max(1, Number.parseInt(value, 10) || 1));
  const cartTotal = () => Object.entries(cart).reduce((sum, [id, qty]) => sum + byId.get(id).price * qty, 0);
  const cartQuantity = () => Object.values(cart).reduce((sum, qty) => sum + qty, 0);
  function saveCart() {
    try { localStorage.setItem(storageKey, JSON.stringify(cart)); } catch (error) { /* storage may be blocked */ }
    const count = cartQuantity();
    cartBadge.textContent = count;
    cartBadge.hidden = count === 0;
  }
  function notify(message) {
    const toast = document.getElementById('cart-toast');
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(notify.timeout);
    notify.timeout = setTimeout(() => { toast.hidden = true; }, 2500);
  }
  function openDialog(dialog) {
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  }
  function closeDialog(dialog) { dialog.close ? dialog.close() : dialog.removeAttribute('open'); }
  dialogs.forEach(id => {
    const dialog = document.getElementById(id);
    dialog.addEventListener('click', event => { if (event.target === dialog) closeDialog(dialog); });
  });
  document.querySelectorAll('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => closeDialog(document.getElementById(btn.dataset.closeDialog))));

  // Filtering and pagination must share the SAME visible subset.
  function showMenu() {
    const filtered = articles.filter(article => category === 'all' || article.dataset.category === category);
    const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    articles.forEach(article => { article.hidden = true; article.style.display = 'none'; });
    filtered.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE).forEach(article => {
      article.hidden = false; article.style.display = '';
    });
    bar.replaceChildren();
    bar.parentElement.style.display = totalPages <= 1 ? 'none' : '';
    if (totalPages > 1) {
      addPageButton('«', currentPage - 1, currentPage === 1, false);
      for (let page = 1; page <= totalPages; page++) addPageButton(String(page), page, false, page === currentPage);
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
    button.addEventListener('click', () => {
      currentPage = page;
      showMenu();
      document.getElementById('portfolio').scrollIntoView({behavior:'smooth'});
    });
    bar.appendChild(button);
  }
  filterButtons.forEach(button => button.addEventListener('click', function () {
    category = button.dataset.category;
    currentPage = 1;
    filterButtons.forEach(btn => {
      btn.classList.toggle('w3-black', btn === button);
      btn.classList.toggle('w3-white', btn !== button);
    });
    showMenu();
  }));

  function updateDishSubtotal() {
    const dish = byId.get(selectedDishId);
    if (!dish) return;
    const qty = clampQty(quantityInput.value);
    quantityInput.value = qty;
    document.getElementById('dish-subtotal').textContent = money(dish.price * qty);
  }
  function openDish(id) {
    const dish = byId.get(id);
    if (!dish) return;
    selectedDishId = id;
    const photo = document.getElementById('dish-dialog-image');
    photo.src = dish.image;
    photo.alt = dish.alt;
    document.getElementById('dish-dialog-title').textContent = dish.name;
    document.getElementById('dish-dialog-meta').textContent = (dish.category === 'meals' ? 'Meals' : 'Desserts') + ' · ' + money(dish.price);
    document.getElementById('dish-dialog-desc').textContent = dish.description;
    quantityInput.value = 1;
    updateDishSubtotal();
    openDialog(dishDialog);
  }
  articles.forEach(article => {
    article.addEventListener('click', () => openDish(article.dataset.id));
    article.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDish(article.dataset.id); }
    });
  });
  document.getElementById('dish-minus').addEventListener('click', () => { quantityInput.value = Math.max(1, clampQty(quantityInput.value) - 1); updateDishSubtotal(); });
  document.getElementById('dish-plus').addEventListener('click', () => { quantityInput.value = Math.min(99, clampQty(quantityInput.value) + 1); updateDishSubtotal(); });
  quantityInput.addEventListener('change', updateDishSubtotal);
  quantityInput.addEventListener('input', () => { if (quantityInput.value !== '') updateDishSubtotal(); });
  document.getElementById('add-to-cart').addEventListener('click', () => {
    if (!selectedDishId) return;
    const qty = clampQty(quantityInput.value);
    cart[selectedDishId] = Math.min(99, (cart[selectedDishId] || 0) + qty);
    saveCart();
    closeDialog(dishDialog);
    notify('Added to cart!');
  });

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
    entries.forEach(([id, qty]) => {
      const item = byId.get(id);
      const row = document.createElement('div');
      row.className = 'tb-cart-row';
      const img = document.createElement('img');
      img.src = item.image; img.alt = item.alt;
      const info = document.createElement('div');
      const name = document.createElement('strong'); name.textContent = item.name;
      const price = document.createElement('p'); price.textContent = money(item.price) + ' × ' + qty + ' = ' + money(item.price * qty);
      const controls = document.createElement('div'); controls.className = 'tb-cart-controls';
      function addControl(label, action, ariaLabel) {
        const b = document.createElement('button'); b.type = 'button'; b.textContent = label;
        b.setAttribute('aria-label', ariaLabel); b.addEventListener('click', action); controls.appendChild(b);
      }
      addControl('−', () => changeCartQty(id, -1), 'Decrease ' + item.name);
      const number = document.createElement('span'); number.textContent = String(qty); controls.appendChild(number);
      addControl('+', () => changeCartQty(id, 1), 'Increase ' + item.name);
      addControl('Remove', () => { delete cart[id]; saveCart(); renderCart(); }, 'Remove ' + item.name);
      info.append(name, price, controls); row.append(img, info); container.appendChild(row);
    });
    document.getElementById('cart-total').textContent = money(cartTotal());
  }
  function changeCartQty(id, delta) {
    const newQty = cart[id] + delta;
    if (newQty <= 0) delete cart[id]; else cart[id] = Math.min(99, newQty);
    saveCart(); renderCart();
  }
  document.getElementById('open-cart').addEventListener('click', () => {
    if (window.innerWidth <= 992) w3_close();
    renderCart(); openDialog(cartDialog);
  });
  document.getElementById('review-order').addEventListener('click', () => {
    if (cartQuantity() === 0) return;
    const pickup = document.getElementById('pickup-location');
    if (!pickup.value) { pickup.reportValidity(); pickup.focus(); return; }
    const summary = document.getElementById('review-details');
    summary.replaceChildren();
    Object.entries(cart).forEach(([id, qty]) => {
      const line = document.createElement('p');
      line.textContent = byId.get(id).name + ' × ' + qty + ' — ' + money(qty * byId.get(id).price);
      summary.appendChild(line);
    });
    const note = document.getElementById('cart-note').value.trim();
    [['Total', money(cartTotal())], ['Pickup', pickup.value], ['Notes', note || '(None)']].forEach(([label, value]) => {
      const row = document.createElement('p');
      const strong = document.createElement('strong'); strong.textContent = label + ': ';
      row.append(strong, document.createTextNode(value)); summary.appendChild(row);
    });
    closeDialog(cartDialog); openDialog(reviewDialog);
  });
  document.getElementById('back-to-cart').addEventListener('click', () => {
    closeDialog(reviewDialog); renderCart(); openDialog(cartDialog);
  });
  document.getElementById('confirm-demo-order').addEventListener('click', () => {
    // No backend exists: this is only a simulated confirmation.
    cart = {};
    saveCart();
    document.getElementById('cart-note').value = '';
    document.getElementById('pickup-location').value = '';
    closeDialog(reviewDialog);
    notify('Demo order confirmed. No real order was placed.');
  });
  saveCart();
  showMenu();
})();

// ========== Click Bubble Animation ==========
(function () {
  var bubbleSound = new Audio('assets/audio/bubble.mp3');
  bubbleSound.volume = 0.4; 
  var USE_IMAGE = false; 

  function spawnBubbles(x, y) {
    var s = bubbleSound.cloneNode();
    s.volume = 0.4;
    s.play().catch(function () {}); 
    var count = Math.floor(Math.random() * 3) + 4; 

    for (var i = 0; i < count; i++) {
      var size     = Math.random() * 25 + 8;              
      var spreadX  = (Math.random() - 0.5) * 60;            
      var floatDur = (Math.random() * 1.0 + 1.0).toFixed(2); 
      var swayDur  = (Math.random() * 0.7 + 0.4).toFixed(2); 
      var delay    = (Math.random() * 0.6).toFixed(2);      
      var swayAmount = (Math.random() > 0.5 ? 1 : -1) * (Math.random() * 10 + 35);
      var wrapper = document.createElement('div');
      wrapper.className = 'bubble-wrapper';
      wrapper.style.width     = size + 'px';
      wrapper.style.height    = size + 'px';
      wrapper.style.left      = (x + spreadX - size / 2) + 'px';
      wrapper.style.top       = (y - size / 2) + 'px';
      wrapper.style.animation = 'bubbleFloatUp ' + floatDur + 's ease-in ' + delay + 's forwards';

      var el = document.createElement('div');
      el.className = 'click-bubble' + (USE_IMAGE ? ' use-image' : '');
      el.style.width     = size + 'px';
      el.style.height    = size + 'px';
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

// ========== Welcome Overlay ==========
// script 在 </body> 之前，DOM 已解析完畢，直接執行即可，不需 DOMContentLoaded。
(function () {
  const welcomeOverlay = document.getElementById("welcome-overlay");
  const typewriterEl = document.getElementById("typewriter-container");
  const hintEl = document.getElementById("welcome-hint");
  const dolphinEl = document.getElementById("welcome-dolphin");
  const bgMusic = document.getElementById("bg-music");

  const sloganText = "Catch a Wave of Flavor!";
  let charIndex = 0;

  function typeWriter() {
    if (charIndex < sloganText.length) {
      typewriterEl.textContent += sloganText.charAt(charIndex);
      typewriterEl.style.width = ((charIndex + 1) / sloganText.length) * 100 + "%";
      charIndex++;
      setTimeout(typeWriter, 70);
    } else {
      triggerDolphinJump();
    }
  }
  setTimeout(typeWriter, 400);

  function triggerDolphinJump() {
    const iconEl = document.getElementById("welcome-icon");
    const iconRect = iconEl.getBoundingClientRect();

    dolphinEl.style.top = (iconRect.top + iconRect.height / 2 - 80) + "px";
    dolphinEl.style.left = (iconRect.left + iconRect.width / 2 - 90) + "px";
    dolphinEl.classList.add("animate-dolphin");

    setTimeout(() => {
      hintEl.classList.add("show");
    }, 0);
  }

  let hasEntered = false;
  welcomeOverlay.addEventListener("click", function () {
    if (hasEntered) return;
    hasEntered = true;

    if (bgMusic) {
      bgMusic.volume = 0.5;
      bgMusic.play().catch(error => {
        console.log("自動播放被瀏覽器阻擋：", error);
      });
    }

    welcomeOverlay.style.opacity = "0";
    setTimeout(() => {
      welcomeOverlay.style.display = "none";
    }, 800);
  });
})();