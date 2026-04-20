  </main>
</div><!-- /.a-main -->

<script>
// Mobile sidebar toggle
const sidebar = document.getElementById('aSidebar');
const toggleBtn = document.getElementById('aSidebarToggle');
if (toggleBtn && sidebar) {
  toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}
</script>
</body>
</html>
