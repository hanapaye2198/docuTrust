<script>
(function () {
  var isDark = localStorage.getItem('theme') === 'dark';
  document.documentElement.classList.toggle('dark-scheme', isDark);
  document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
  var themeColor = document.getElementById('docutrustThemeColor');
  if (themeColor) {
    themeColor.content = isDark ? '#0d1117' : '#f0f7f5';
  }
})();
</script>
