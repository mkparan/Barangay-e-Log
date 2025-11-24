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

// Splash Screen Logic
(function() {
  const splashScreen = document.getElementById('splashScreen');
  if (!splashScreen) return;
  
  // Check if splash screen has been shown in this session
  const splashShown = sessionStorage.getItem('splash_shown');
  const currentPath = window.location.pathname;
  const isLandingPage = currentPath.includes('public/index.php') || currentPath === '/elog_barangay/public/' || currentPath === '/elog_barangay/public/index.php' || currentPath.endsWith('/elog_barangay/') || currentPath.endsWith('/elog_barangay');
  
  // Only show splash screen on the landing page
  if (!splashShown && isLandingPage) {
    // Show splash screen on first visit in this session
    splashScreen.style.display = 'flex';
    
    // After animation completes, hide splash
    setTimeout(function() {
      splashScreen.classList.add('hide');
      sessionStorage.setItem('splash_shown', 'true');
      
      // Remove splash screen from DOM after fade out
      setTimeout(function() {
        splashScreen.style.display = 'none';
      }, 500);
    }, 2000); // Show for 2 seconds
  } else {
    // Hide splash screen immediately if already shown or not on landing page
    splashScreen.style.display = 'none';
  }
})();
</script>
</body>
</html>
HTML;
?>
