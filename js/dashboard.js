document.addEventListener('DOMContentLoaded', () => {
  // Theme Toggling
  const themeToggles = document.querySelectorAll('[data-action="toggle-theme"]');
  const root = document.documentElement;
  
  // Check for saved theme
  const savedTheme = localStorage.getItem('milk-theme') || 'light';
  root.setAttribute('data-theme', savedTheme);
  updateThemeIcons(savedTheme);

  themeToggles.forEach(toggle => {
    toggle.addEventListener('click', () => {
      const currentTheme = root.getAttribute('data-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      
      root.setAttribute('data-theme', newTheme);
      localStorage.setItem('milk-theme', newTheme);
      updateThemeIcons(newTheme);
    });
  });

  function updateThemeIcons(theme) {
    const sunIcons = document.querySelectorAll('.icon-sun');
    const moonIcons = document.querySelectorAll('.icon-moon');
    
    if (theme === 'dark') {
      sunIcons.forEach(i => i.style.display = 'none');
      moonIcons.forEach(i => i.style.display = 'block');
    } else {
      sunIcons.forEach(i => i.style.display = 'block');
      moonIcons.forEach(i => i.style.display = 'none');
    }
  }

  // Sidebar Logic
  const sidebarCollapseBtn = document.getElementById('sidebar-collapse');
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const sidebarOverlay = document.getElementById('sidebar-overlay');
  
  // Desktop collapse
  if (sidebarCollapseBtn) {
    sidebarCollapseBtn.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('milk-sidebar', document.body.classList.contains('sidebar-collapsed'));
    });
    
    if (localStorage.getItem('milk-sidebar') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }
  }

  // Mobile drawer
  if (mobileMenuBtn && sidebarOverlay) {
    mobileMenuBtn.addEventListener('click', () => {
      document.body.classList.add('sidebar-open');
    });

    sidebarOverlay.addEventListener('click', () => {
      document.body.classList.remove('sidebar-open');
    });
  }
});
