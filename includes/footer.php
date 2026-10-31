<?php
// includes/footer.php
?>
            </div>
            
            <!-- Friendly Studio Footer (Inside scrollable content area) -->
            <footer class="flex-shrink-0 py-4 px-8 border-t border-stone-200 bg-white text-xs text-stone-500 flex flex-col sm:flex-row items-center justify-between gap-2 no-print">
                <div class="flex items-center gap-2">
                    <span>&copy; <?= date('Y') ?></span>
                    <strong class="text-stone-800 font-bold"><?= htmlspecialchars(getSetting('shop_name', 'Aquiris Print')) ?></strong>
                    <span class="text-stone-300">•</span>
                    <span class="text-stone-500">Pusat Cetak & Rental Papan Akrilik</span>
                </div>
                <div class="flex items-center gap-3 text-stone-400 text-[11px]">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-circle-dot text-emerald-500 text-[9px]"></i> Sistem Kasir Siap Pakai</span>
                    <span>•</span>
                    <span>Laragon Localhost</span>
                </div>
            </footer>
        </main>
    </div>

    <!-- Global App Scripts -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        // Global Toast Notification
        const Toast = (typeof Swal !== 'undefined') ? Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
            customClass: {
                popup: 'rounded-2xl border border-stone-200 shadow-xl font-sans text-xs bg-white text-stone-800'
            }
        }) : null;

        function showToast(message, icon = 'success') {
            if (Toast) {
                Toast.fire({
                    icon: icon,
                    title: message
                });
            } else {
                console.log(`[${icon}] ${message}`);
            }
        }

        // Modern In-App Logout Confirmation (Replaces ugly browser native confirm)
        function confirmLogout() {
            if (typeof Swal === 'undefined') {
                window.location.href = 'logout.php';
                return false;
            }

            Swal.fire({
                title: 'Keluar dari Akun?',
                text: 'Sesi Anda saat ini akan diakhiri. Pastikan transaksi cetak dan catatan sewa kasir telah tersimpan.',
                icon: 'question',
                iconColor: '#d97706',
                showCancelButton: true,
                confirmButtonColor: '#1c1917',
                cancelButtonColor: '#78716c',
                confirmButtonText: '<i class="fa-solid fa-arrow-right-from-bracket mr-1.5"></i> Ya, Keluar',
                cancelButtonText: 'Tetap di Sini',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl border border-stone-200/90 shadow-2xl p-6 font-sans bg-white',
                    title: 'text-base font-extrabold text-stone-900',
                    htmlContainer: 'text-xs text-stone-500 font-medium leading-relaxed mt-2',
                    confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 shadow-sm border-0',
                    cancelButton: 'rounded-xl text-xs font-bold px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 border-0'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
            return false;
        }

        // Modern In-App Delete Confirmation
        function confirmDelete(url, message = 'Data yang dihapus tidak dapat dikembalikan!') {
            if (typeof Swal === 'undefined') {
                window.location.href = url;
                return false;
            }

            Swal.fire({
                title: 'Hapus data ini?',
                text: message,
                icon: 'warning',
                iconColor: '#e11d48',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#78716c',
                confirmButtonText: '<i class="fa-solid fa-trash-can mr-1.5"></i> Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl border border-stone-200/90 shadow-2xl p-6 font-sans bg-white',
                    title: 'text-base font-extrabold text-stone-900',
                    htmlContainer: 'text-xs text-stone-500 font-medium leading-relaxed mt-2',
                    confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white shadow-sm border-0',
                    cancelButton: 'rounded-xl text-xs font-bold px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 border-0'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        }

        // Global Alert Override (Intercepts any native alert() and converts to SweetAlert)
        window.alert = function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Pemberitahuan',
                    text: message,
                    icon: 'info',
                    iconColor: '#b88448',
                    confirmButtonColor: '#1c1917',
                    confirmButtonText: 'Mengerti',
                    customClass: {
                        popup: 'rounded-3xl border border-stone-200/90 shadow-2xl p-6 font-sans',
                        title: 'text-base font-extrabold text-stone-900',
                        htmlContainer: 'text-xs text-stone-600 font-medium leading-relaxed mt-2',
                        confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5 bg-stone-900 text-amber-300'
                    }
                });
            } else {
                console.warn('Alert intercepted:', message);
            }
        };

        // Auto format number as rupiah
        document.querySelectorAll('.format-rupiah').forEach(input => {
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/[^0-9]/g, '');
                this.value = val ? parseInt(val, 10).toLocaleString('id-ID') : '';
            });
        });
    </script>
</body>
</html>
