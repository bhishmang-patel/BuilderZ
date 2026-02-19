</div> <!-- Closes content-wrapper -->

            <footer class="main-footer">
                <span>Powered by <strong>DEPLOY-X</strong></span>
            </footer>

        </div> <!-- Closes main-content -->
    </div> <!-- Closes wrapper -->



    <style>
    /* ── Main Footer ──────────────────────────────────────── */
    .main-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 1.5rem;
        border-top: 1.5px solid #e8e3db;
        background: #ffffff;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.75rem;
        color: #9e9690;
        letter-spacing: 0.02em;
    }
    .main-footer strong {
        font-weight: 700;
        color: #2a58b5;
        letter-spacing: 0.04em;
    }

    /* ── SweetAlert2 — cream design system ───────────────── */
    .premium-swal-popup {
        font-family: 'DM Sans', sans-serif !important;
        border-radius: 16px !important;
        border: 1.5px solid #e8e3db !important;
        box-shadow: 0 20px 50px rgba(26,23,20,0.16) !important;
        padding: 0 !important;
        overflow: hidden !important;
        max-width: 400px !important;
        background: #ffffff !important;
    }

    /* Icon area */
    .premium-swal-popup .swal2-icon {
        margin: 2rem auto 0.5rem !important;
        width: 52px !important;
        height: 52px !important;
        border-width: 2px !important;
    }
    .premium-swal-popup .swal2-icon.swal2-warning {
        border-color: #e0c9b5 !important;
        color: #b5622a !important;
    }

    /* Title */
    .premium-swal-title {
        font-family: 'Fraunces', serif !important;
        font-size: 1.15rem !important;
        font-weight: 700 !important;
        color: #1a1714 !important;
        padding: 0.5rem 2rem 0 !important;
        line-height: 1.3 !important;
    }

    /* Body text */
    .premium-swal-content {
        font-size: 0.875rem !important;
        color: #6b6560 !important;
        padding: 0.4rem 2rem 0 !important;
        line-height: 1.6 !important;
    }

    /* Actions row */
    .premium-swal-popup .swal2-actions {
        padding: 1.2rem 1.6rem !important;
        gap: 0.65rem !important;
        border-top: 1.5px solid #f0ece5 !important;
        background: #fdfcfa !important;
        margin-top: 1.2rem !important;
        justify-content: flex-end !important;
        flex-wrap: nowrap !important;
    }

    /* Cancel button */
    .premium-swal-cancel {
        font-family: 'DM Sans', sans-serif !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        padding: 0.6rem 1.25rem !important;
        border-radius: 8px !important;
        border: 1.5px solid #e8e3db !important;
        background: #ffffff !important;
        color: #6b6560 !important;
        transition: all 0.18s ease !important;
        cursor: pointer !important;
    }
    .premium-swal-cancel:hover {
        border-color: #b5622a !important;
        color: #b5622a !important;
        background: #fdf8f3 !important;
    }
    .premium-swal-cancel:focus { box-shadow: none !important; }

    /* Confirm button */
    .premium-swal-confirm {
        font-family: 'DM Sans', sans-serif !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        padding: 0.6rem 1.4rem !important;
        border-radius: 8px !important;
        border: 1.5px solid #1a1714 !important;
        background: #1a1714 !important;
        color: #ffffff !important;
        transition: all 0.18s ease !important;
        cursor: pointer !important;
    }
    .premium-swal-confirm:hover {
        background: #b5622a !important;
        border-color: #b5622a !important;
        box-shadow: 0 4px 14px rgba(181,98,42,0.28) !important;
    }
    .premium-swal-confirm:focus { box-shadow: none !important; }

    /* Danger variant — used when confirming destructive actions */
    .swal-danger .premium-swal-confirm {
        background: #dc2626 !important;
        border-color: #dc2626 !important;
    }
    .swal-danger .premium-swal-confirm:hover {
        background: #b91c1c !important;
        border-color: #b91c1c !important;
        box-shadow: 0 4px 14px rgba(220,38,38,0.28) !important;
    }
    .swal-danger .premium-swal-popup .swal2-icon.swal2-warning {
        border-color: #fecaca !important;
        color: #dc2626 !important;
    }
    </style>

    <script>
        /* ── Sidebar toggle ─────────────────────── */
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed',
                sidebar.classList.contains('collapsed') ? 'true' : 'false');
        }

        document.addEventListener('DOMContentLoaded', function () {

            /* Restore sidebar collapse state */
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && localStorage.getItem('sidebar_collapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }

            /* Sidebar scroll persistence */
            const sidebarMenu = document.querySelector('.sidebar-menu');
            if (sidebarMenu) {
                const savedScroll = localStorage.getItem('sidebar_scroll_pos');
                if (savedScroll) sidebarMenu.scrollTop = parseInt(savedScroll, 10);
                window.addEventListener('beforeunload', () => {
                    localStorage.setItem('sidebar_scroll_pos', sidebarMenu.scrollTop);
                });
            }

            /* Auto-dismiss flash alerts after 5 s */
            document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.3s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 320);
                }, 5000);
            });
        });

        /* ── Global confirm dialog ──────────────── */
        function confirmAction(
            e,
            message     = 'Are you sure you want to proceed?',
            confirmText = 'Yes, do it!',
            isDanger    = false
        ) {
            e.preventDefault();
            const targetUrl = e.currentTarget.getAttribute('href');
            const form      = e.currentTarget.closest('form');

            Swal.fire({
                title:             'Are you sure?',
                text:              message,
                icon:              'warning',
                showCancelButton:  true,
                buttonsStyling:    false,
                confirmButtonText: confirmText,
                cancelButtonText:  'Cancel',
                focusCancel:       true,
                reverseButtons:    true,
                customClass: {
                    popup:         'premium-swal-popup'  + (isDanger ? ' swal-danger' : ''),
                    title:         'premium-swal-title',
                    htmlContainer: 'premium-swal-content',
                    confirmButton: 'premium-swal-confirm',
                    cancelButton:  'premium-swal-cancel',
                }
            }).then(result => {
                if (result.isConfirmed) {
                    if (targetUrl) window.location.href = targetUrl;
                    else if (form)  form.submit();
                }
            });

            return false;
        }
    </script>
</body>
</html>