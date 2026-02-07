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

        // Global Confirmation Dialog (SweetAlert2)
        function confirmAction(e, message = "Are you sure you want to proceed?", confirmBtnText = "Yes, do it!") {
            e.preventDefault();
            const targetUrl = e.currentTarget.getAttribute('href');
            const form = e.currentTarget.closest('form');
            
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#ef4444',
                confirmButtonText: confirmBtnText,
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'premium-swal-popup',
                    title: 'premium-swal-title',
                    content: 'premium-swal-content',
                    confirmButton: 'premium-swal-confirm',
                    cancelButton: 'premium-swal-cancel'
                },
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    } else if (form) {
                        // If button is a submit button, we might need to recreate the click or just submit
                        // But if confirmAction is on an 'a' tag acting as submit (rare), targetUrl handles it.
                        // If it's a <button type="submit">, form.submit() works but bypasses other handlers? 
                        // Usually safer to allow default if we weren't interrupting.
                        form.submit();
                    }
                }
            });
            return false;
        }
    </script>
</body>
</html>
