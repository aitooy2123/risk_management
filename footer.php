<?php
/**
 * Footer Template - ปิดท้ายหน้าเว็บ
 */
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}
?>
    </div><!-- Close main-content -->

    <!-- ==================== TOAST NOTIFICATION FUNCTION ==================== -->
    <script>
        /**
         * แสดง Toast Notification
         * @param {string} message - ข้อความที่ต้องการแสดง
         * @param {string} type - ประเภท (success, error, warning, info)
         * @param {number} duration - ระยะเวลาแสดง (มิลลิวินาที)
         */
        function showToast(message, type = 'info', duration = 3000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            const colors = {
                success: 'bg-green-50 border-green-200 text-green-800',
                error: 'bg-red-50 border-red-200 text-red-800',
                warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
                info: 'bg-blue-50 border-blue-200 text-blue-800'
            };

            const toast = document.createElement('div');
            toast.className = `flex items-center gap-3 px-4 py-3 rounded-lg border shadow-lg ${colors[type]} animate-slide-in min-w-[300px]`;
            toast.innerHTML = `
                <span class="text-lg">${icons[type]}</span>
                <span class="flex-1 text-sm font-medium">${message}</span>
                <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        /**
         * แสดง/ซ่อน Loading Overlay
         * @param {boolean} show - true เพื่อแสดง, false เพื่อซ่อน
         */
        function toggleLoading(show = true) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                if (show) {
                    overlay.classList.remove('hidden');
                } else {
                    overlay.classList.add('hidden');
                }
            }
        }

        /**
         * ฟังก์ชันยืนยันการลบแบบทั่วไป
         * @param {string} message - ข้อความยืนยัน
         * @param {function} callback - ฟังก์ชันที่ทำงานเมื่อยืนยัน
         */
        function confirmAction(message, callback) {
            Swal.fire({
                title: 'ยืนยันการดำเนินการ?',
                html: `<p class="text-slate-600">${message}</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-check mr-1"></i> ยืนยัน',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> ยกเลิก',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    title: 'text-lg font-bold',
                    confirmButton: 'rounded-lg font-semibold',
                    cancelButton: 'rounded-lg font-semibold'
                }
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        }

        /**
         * ฟังก์ชัน Format Date เป็น พ.ศ.
         * @param {string|Date} date - วันที่
         * @param {boolean} showTime - แสดงเวลาด้วยหรือไม่
         * @returns {string} วันที่ในรูปแบบ พ.ศ.
         */
        function formatThaiDate(date, showTime = false) {
            if (!date) return '-';
            
            const d = new Date(date);
            if (isNaN(d.getTime())) return '-';
            
            const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                           'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            
            const day = d.getDate();
            const month = months[d.getMonth()];
            const year = d.getFullYear() + 543;
            
            let result = `${day} ${month} ${year}`;
            
            if (showTime) {
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                result += ` ${hours}:${minutes} น.`;
            }
            
            return result;
        }

        /**
         * ฟังก์ชัน Format Number
         * @param {number} number - ตัวเลข
         * @returns {string} ตัวเลขที่จัดรูปแบบแล้ว
         */
        function formatNumber(number) {
            return new Intl.NumberFormat('th-TH').format(number);
        }

        /**
         * ฟังก์ชัน Debounce
         * @param {function} func - ฟังก์ชันที่ต้องการ debounce
         * @param {number} wait - ระยะเวลารอ (มิลลิวินาที)
         * @returns {function}
         */
        function debounce(func, wait = 300) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // ==================== KEYBOARD SHORTCUTS ====================
        document.addEventListener('keydown', function(e) {
            // Ctrl+K หรือ Cmd+K สำหรับ focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"], input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape เพื่อปิด dropdown/modal
            if (e.key === 'Escape') {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // ==================== AUTO HIDE ALERTS ====================
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });

        // ==================== INITIALIZATION LOG ====================
        console.log('✅ Risk Management System initialized');
        console.log('👤 User:', '<?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?>');
        console.log('🔑 Role:', '<?= isset($is_admin) && $is_admin ? 'Admin' : 'User' ?>');
        console.log('📄 Page:', document.title);
    </script>

    <!-- ==================== CSRF TOKEN REFRESH ==================== -->
    <script>
        // Auto-refresh CSRF token every 30 minutes
        setInterval(function() {
            fetch('ajax/refresh_csrf.php')
                .then(response => response.json())
                .then(data => {
                    if (data.csrf_token) {
                        document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
                        const csrfInputs = document.querySelectorAll('input[name="csrf_token"]');
                        csrfInputs.forEach(input => {
                            input.value = data.csrf_token;
                        });
                    }
                })
                .catch(error => console.error('CSRF refresh failed:', error));
        }, 1800000); // 30 minutes
    </script>

</body>
</html>