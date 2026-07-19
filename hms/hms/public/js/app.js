// Close modals on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
  }
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity 0.5s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 500);
  }, 4000);
});

// Confirm delete forms
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// Active nav link highlight based on URL
const currentPath = window.location.pathname;
document.querySelectorAll('.nav-item').forEach(item => {
  const href = item.getAttribute('href');
  if (href && currentPath.endsWith(href)) {
    item.classList.add('active');
  }
});
