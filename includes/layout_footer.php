  </div><!-- /content -->
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Auto-dismiss alerts
  document.querySelectorAll('.alert').forEach(el => setTimeout(() => {
    bootstrap.Alert.getOrCreateInstance(el).close();
  }, 4500));

  // Confirm deletes
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });
</script>
</body>
</html>
