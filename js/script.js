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



  // ===== 餐點圖片放大功能 =====
  const zoomButton = document.getElementById("enlarge-dish-photo");
  const lightbox = document.getElementById("image-lightbox");
  const lightboxImage = document.getElementById("lightbox-image");
  const closeZoomButton = document.getElementById("close-lightbox");

  if (zoomButton && lightbox && lightboxImage && closeZoomButton) {

    zoomButton.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();

      const originalImage = document.getElementById("dish-dialog-image");

      if (!originalImage || !originalImage.src) return;

      lightboxImage.src = originalImage.currentSrc || originalImage.src;
      lightboxImage.alt = originalImage.alt;

      if (!lightbox.open) {
        lightbox.showModal();
      }
    });

    // 點擊 X 關閉放大圖片
    closeZoomButton.addEventListener("click", function () {
      lightbox.close();
    });

    // 點擊圖片外側關閉
    lightbox.addEventListener("click", function (event) {
      if (event.target === lightbox) {
        const rect = lightboxImage.getBoundingClientRect();

        const clickedInside =
          event.clientX >= rect.left &&
          event.clientX <= rect.right &&
          event.clientY >= rect.top &&
          event.clientY <= rect.bottom;

        if (!clickedInside) {
          lightbox.close();
        }
      }
    });
  }

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
    notify('Order confirmed!');
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

// ========== Welcome Overlay ==========//
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
      // The text is already revealed character by character; avoid clipping its container.
      typewriterEl.style.width = "auto";
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
document.getElementById('treasureImg').addEventListener('click', function() {
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

    const offsetX = Math.floor(Math.random() * 240) - 80;
    const offsetY = Math.floor(Math.random() * 180) - 80;

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