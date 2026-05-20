<script>
(function () {
  function docuTrustSyncColorScheme () {
    var m = window.matchMedia('(prefers-color-scheme: light)');
    document.documentElement.classList.toggle('light-scheme', m.matches);
  }
  docuTrustSyncColorScheme();
  var mq = window.matchMedia('(prefers-color-scheme: light)');
  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', docuTrustSyncColorScheme);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(docuTrustSyncColorScheme);
  }
})();
</script>
