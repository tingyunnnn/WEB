const sidebar = document.getElementById("mySidebar");
const overlay = document.getElementById("myOverlay");

function w3_open() {
  sidebar.style.display = "block";
  overlay.style.display = "block";
}

function w3_close() {
  sidebar.style.display = "none";
  overlay.style.display = "none";
}

// ========== Menu Pagination ==========
(function () {
  const ITEMS_PER_PAGE = 6;
  const grid = document.getElementById('menu-grid');
  const bar  = document.getElementById('pagination-bar');

  if (!grid || !bar) return;
  const articles   = Array.from(grid.querySelectorAll('article'));
  const totalPages = Math.ceil(articles.length / ITEMS_PER_PAGE);
  let   currentPage = 1;

  function showPage(page) {
    currentPage = page;

    articles.forEach(function (article, index) {
      const belongsToPage = Math.floor(index / ITEMS_PER_PAGE) + 1;
      article.style.display = (belongsToPage === page) ? '' : 'none';
    });

    updateButtons();
  }

  function updateButtons() {
    bar.querySelectorAll('a[data-page]').forEach(function (btn) {
      const isActive = parseInt(btn.dataset.page) === currentPage;
      btn.classList.toggle('w3-black',       isActive);
      btn.classList.toggle('w3-white',       !isActive);
      btn.classList.toggle('w3-hover-black', !isActive);
    });
  }

  function buildBar() {
    bar.innerHTML = '';

    const prev = makeBtn('«', null, function () {
      if (currentPage > 1) showPage(currentPage - 1);
    });
    bar.appendChild(prev);

    for (let i = 1; i <= totalPages; i++) {
      const btn = makeBtn(i, i, function () {
        showPage(parseInt(this.dataset.page));
      });
      bar.appendChild(btn);
    }

    const next = makeBtn('»', null, function () {
      if (currentPage < totalPages) showPage(currentPage + 1);
    });
    bar.appendChild(next);
  }

  function makeBtn(label, pageNum, onClick) {
    const a = document.createElement('a');
    a.href      = '#portfolio';          
    a.className = 'w3-bar-item w3-button w3-white w3-hover-black';
    a.textContent = label;
    if (pageNum !== null) a.dataset.page = pageNum;
    a.addEventListener('click', onClick);
    return a;
  }

  buildBar();
  showPage(1);

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

document.addEventListener("DOMContentLoaded", function () {
  const overlay = document.getElementById("welcome-overlay");
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
    } 
    else {
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
  overlay.addEventListener("click", function () {
    if (hasEntered) return;
    hasEntered = true;

    if (bgMusic) {
      bgMusic.volume = 0.5;
      bgMusic.play().catch(error => {
        console.log("自動播放被瀏覽器阻擋：", error);
      });
    }

    overlay.style.opacity = "0";
    setTimeout(() => {
      overlay.style.display = "none";
    }, 800);
  });
});