'use strict';
const navToggle = document.querySelector('.nav-toggle');
if (navToggle) {
  const header = document.querySelector('.site-header');
  const nav = document.getElementById('admin-nav');
  header.classList.add('enhanced');
  navToggle.hidden = false;
  navToggle.addEventListener('click', () => {
    const expanded = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', String(!expanded));
    nav.classList.toggle('is-open', !expanded);
  });
  header.addEventListener('keydown', event => {
    if (event.key === 'Escape' && nav.classList.contains('is-open')) {
      nav.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.focus();
    }
  });
}
const imageInput = document.getElementById('image');
if (imageInput) {
  const preview = document.getElementById('image-preview');
  const label = document.getElementById('preview-label');
  const error = document.getElementById('preview-error');
  const filename = document.getElementById('file-name');
  filename.textContent = 'No image selected';
  const original = {src: preview.src, className: preview.className, label: label.textContent};
  let objectUrl;
  imageInput.addEventListener('change', () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    error.textContent = '';
    imageInput.setCustomValidity('');
    preview.src = original.src;
    preview.className = original.className;
    label.textContent = original.label;
    const file = imageInput.files[0];
    filename.textContent = file ? file.name : 'No image selected';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2097152) {
      error.textContent = 'Choose a JPG, PNG or WebP image no larger than 2 MiB.';
      imageInput.setCustomValidity(error.textContent);
      return;
    }
    objectUrl = URL.createObjectURL(file);
    const probe = new Image();
    const currentUrl = objectUrl;
    probe.onload = () => {
      if (currentUrl !== objectUrl) return;
      if (probe.width > 6000 || probe.height > 6000 || probe.width * probe.height > 16000000) {
        error.textContent = 'Choose an image up to 6,000 px per side and 16 megapixels.';
        imageInput.setCustomValidity(error.textContent);
        return;
      }
      preview.src = currentUrl;
      preview.classList.remove('placeholder-image');
      label.textContent = 'New image preview';
    };
    probe.onerror = () => {
      if (currentUrl !== objectUrl) return;
      error.textContent = 'This image could not be opened. Choose another file.';
      imageInput.setCustomValidity(error.textContent);
    };
    probe.src = objectUrl;
  });
}
// A successful POST redirects; do not retain a disabled button after back navigation.
document.querySelectorAll('form[method="post"]').forEach(form => {
  form.addEventListener('submit', () => {
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
});
