<script>
const mobileNavToggle = document.getElementById('mobileNavToggle');
const mobileNavClose = document.getElementById('mobileNavClose');
const mobileNav = document.getElementById('mobileNav');

function openMobileNav() {
  if (!mobileNav) {
    return;
  }
  mobileNav.hidden = false;
  mobileNav.classList.add('open');
  document.body.classList.add('mobile-nav-open');
  mobileNavToggle?.setAttribute('aria-expanded', 'true');
  requestAnimationFrame(() => mobileNavClose?.focus());
}

function closeMobileNav() {
  if (!mobileNav) {
    return;
  }
  mobileNav.classList.remove('open');
  mobileNav.hidden = true;
  document.body.classList.remove('mobile-nav-open');
  mobileNavToggle?.setAttribute('aria-expanded', 'false');
  mobileNavToggle?.focus();
}

mobileNavToggle?.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();
  if (mobileNav?.classList.contains('open')) {
    closeMobileNav();
  } else {
    openMobileNav();
  }
});
mobileNavClose?.addEventListener('click', () => closeMobileNav());
mobileNav?.addEventListener('click', (e) => {
  if (e.target === mobileNav) {
    closeMobileNav();
  }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && mobileNav?.classList.contains('open')) {
    closeMobileNav();
  }
});

const docutrustThemeToggle = document.getElementById('docutrustThemeToggle');
const docutrustThemeColor = document.getElementById('docutrustThemeColor');
const docutrustThemeToggleLabel = document.getElementById('docutrustThemeToggleLabel');

function syncDocutrustLogos(isDark) {
  document.querySelectorAll('.logo-img[data-logo-default]').forEach((img) => {
    const defaultSrc = img.dataset.logoDefault;
    const lightSrc = img.dataset.logoLight;
    img.src = isDark ? defaultSrc : (lightSrc || defaultSrc);
  });
}

function updateDocutrustThemeUi(isDark) {
  const nextThemeLabel = isDark ? @json(__('Light mode')) : @json(__('Dark mode'));
  docutrustThemeToggle?.setAttribute('title', isDark ? @json(__('Switch to light mode')) : @json(__('Switch to dark mode')));
  docutrustThemeToggle?.setAttribute('aria-label', isDark ? @json(__('Switch to light mode')) : @json(__('Switch to dark mode')));
  if (docutrustThemeToggleLabel) {
    docutrustThemeToggleLabel.textContent = nextThemeLabel;
  }
  if (docutrustThemeColor) {
    docutrustThemeColor.content = isDark ? '#0d1117' : '#f0f7f5';
  }
  syncDocutrustLogos(isDark);
}

function setDocutrustTheme(isDark, persist) {
  document.documentElement.classList.toggle('dark-scheme', isDark);
  document.documentElement.classList.toggle('dark', isDark);
  document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

  if (persist) {
    try {
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
    } catch (error) {
      // Ignore storage errors in restricted browsing contexts.
    }
  }

  updateDocutrustThemeUi(isDark);
}

updateDocutrustThemeUi(document.documentElement.classList.contains('dark-scheme'));

docutrustThemeToggle?.addEventListener('click', function () {
  setDocutrustTheme(! document.documentElement.classList.contains('dark-scheme'), true);
});
</script>
