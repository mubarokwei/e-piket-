        </div><!-- .content-wrapper -->
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    
    <script>
    // Update jam realtime
    function updateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        const dateStr = now.toLocaleDateString('id-ID', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
        
        const timeEl = document.getElementById('currentTime');
        const dateEl = document.getElementById('currentDate');
        if (timeEl) timeEl.textContent = timeStr;
        if (dateEl) dateEl.textContent = dateStr;
    }
    updateTime();
    setInterval(updateTime, 1000);
    </script>
</body>
</html>
