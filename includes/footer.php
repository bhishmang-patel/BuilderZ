            </div> <!-- Closes content-wrapper -->
            <footer class="main-footer">
                <p>Powered by <strong>Deploy-X</strong></p>
            </footer>
        </div> <!-- Closes main-content -->
    </div> <!-- Closes wrapper -->
    
    <script src="<?= BASE_URL ?>assets/js/script.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
        }
        
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            // Restore Sidebar State
            const sidebar = document.querySelector('.sidebar');
            const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }

            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Sidebar Scroll Persistence
            const sidebarMenu = document.querySelector('.sidebar-menu');
            if (sidebarMenu) {
                const savedScroll = localStorage.getItem('sidebar_scroll_pos');
                if (savedScroll) {
                    sidebarMenu.scrollTop = parseInt(savedScroll, 10);
                }

                window.addEventListener('beforeunload', () => {
                    localStorage.setItem('sidebar_scroll_pos', sidebarMenu.scrollTop);
                });
            }
        });
    </script>
</body>
</html>
