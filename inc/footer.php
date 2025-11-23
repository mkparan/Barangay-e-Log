<?php
// Output fixed footer using PHP to avoid parse errors when included from PHP-only context
$year = date('Y');
echo <<<HTML
</div> <!-- /container -->

<!-- spacer to prevent fixed footer from overlapping content -->
<div class="pb-5"></div>

<footer class="fixed-bottom bg-primary-custom text-white text-center py-3">
  <div class="container">
    <small>© {$year} Barangay e-Log System</small>
  </div>
</footer>

<!-- Bootstrap 5 bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
  const html = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  
  // Load saved theme or default to light
  const savedTheme = localStorage.getItem('bs-theme') || 'light';
  html.setAttribute('data-bs-theme', savedTheme);
  updateIcon(savedTheme);
  
  // Toggle theme
  if (themeToggle) {
    themeToggle.addEventListener('click', function() {
      const currentTheme = html.getAttribute('data-bs-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('bs-theme', newTheme);
      updateIcon(newTheme);
    });
  }
  
  function updateIcon(theme) {
    if (themeIcon) {
      if (theme === 'dark') {
        themeIcon.className = 'bi bi-sun-fill';
        themeToggle.setAttribute('title', 'Switch to light mode');
      } else {
        themeIcon.className = 'bi bi-moon-fill';
        themeToggle.setAttribute('title', 'Switch to dark mode');
      }
    }
  }
})();
</script>
</body>
</html>
HTML;
?>
