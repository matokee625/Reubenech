// admin-users.js – fetch admin data and render
// This script runs on admin/users.html to load data from admin/data.php
(async () => {
  const container = document.getElementById('admin-data');
  if (!container) return;
  container.innerHTML = '<p>Loading admin data…</p>';
  try {
    const response = await fetch('data.php');
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const html = await response.text();
    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = `<p style="color:#b91c1c;">Error loading admin data: ${e.message}</p>`;
  }
})();
