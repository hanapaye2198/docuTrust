<script>
(function () {
  var savedTheme = null;
  try {
    savedTheme = localStorage.getItem('theme');
  } catch (error) {
    savedTheme = null;
  }

  var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  var isDark = savedTheme === 'dark' || (savedTheme === null && prefersDark);

  document.documentElement.classList.toggle('dark-scheme', isDark);
  document.documentElement.classList.toggle('dark', isDark);
  document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
  var themeColor = document.getElementById('docutrustThemeColor');
  if (themeColor) {
    themeColor.content = isDark ? '#0d1117' : '#f0f7f5';
  }
})();
</script>
