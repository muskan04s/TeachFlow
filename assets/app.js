function toggleForm(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden');
  if (!el.classList.contains('hidden')) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const first = el.querySelector('input:not([type=hidden]), select, textarea');
    if (first) setTimeout(() => first.focus(), 180);
  }
}
function filterRows(inputId, rowClass) {
  const input = document.getElementById(inputId);
  const query = (input?.value || '').toLowerCase().trim();
  document.querySelectorAll('.' + rowClass).forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
  });
}
document.addEventListener('DOMContentLoaded', () => {
  const menu = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  if (menu && sidebar) menu.addEventListener('click', () => sidebar.classList.toggle('sidebar-open'));
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
      }
    });
  });
});
