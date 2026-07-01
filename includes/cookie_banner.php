<?php
/**
 * Cookie Consent Banner (PDPA / GDPR)
 * - แสดงเมื่อผู้ใช้ยังไม่ให้ความยินยอม
 * - มีปุ่ม "ยอมรับทั้งหมด", "ปฏิเสธทั้งหมด", "ตั้งค่า"
 */

// ตรวจสอบว่าเรียกผ่านระบบหรือไม่
if (!defined('ACCESS_ALLOWED')) {
    http_response_code(403);
    die('Forbidden');
}
?>
<!-- แบนเนอร์คุกกี้ -->
<div id="cookieConsent" class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-2xl border-t-4 border-blue-500 p-4 md:p-5 transition-all duration-500 ease-in-out transform translate-y-0">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- ข้อความ -->
        <div class="flex-1 text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-2 mb-1">
                <i class="fas fa-cookie-bite text-blue-500 text-xl"></i>
                <span class="font-bold text-gray-800 text-sm md:text-base">🍪 เราใช้คุกกี้</span>
                <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full">PDPA</span>
            </div>
            <p class="text-gray-600 text-xs md:text-sm leading-relaxed max-w-3xl">
                เว็บไซต์นี้ใช้คุกกี้เพื่อปรับปรุงประสบการณ์การใช้งานของคุณ 
                <span class="hidden sm:inline">และการวิเคราะห์ประสิทธิภาพ</span>
                <br class="block sm:hidden">
                <a href="#" class="text-blue-500 hover:text-blue-700 underline font-medium" onclick="showCookiePolicy(); return false;">
                    เรียนรู้เพิ่มเติม
                </a>
            </p>
        </div>
        
        <!-- ปุ่ม -->
        <div class="flex flex-wrap items-center justify-center gap-2 flex-shrink-0">
            <button onclick="rejectAllCookies()" 
                    class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-200 border border-gray-200">
                <i class="fas fa-times mr-1"></i> ปฏิเสธทั้งหมด
            </button>
            <button onclick="acceptAllCookies()" 
                    class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                <i class="fas fa-check mr-1"></i> ยอมรับทั้งหมด
            </button>
            <button onclick="customizeCookies()" 
                    class="px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 border border-blue-200">
                <i class="fas fa-sliders-h mr-1"></i> 
                <span class="hidden xs:inline">ตั้งค่า</span>
            </button>
        </div>
    </div>
    
    <!-- แถบความคืบหน้า (ตกแต่ง) -->
    <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-100">
        <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full transition-all duration-1000" style="width: 0%;" id="consentProgress"></div>
    </div>
</div>

<!-- Modal นโยบายคุกกี้ -->
<div id="cookiePolicyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4" onclick="if(event.target===this) closeCookiePolicy()">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto shadow-2xl p-6 md:p-8" onclick="event.stopPropagation()">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-cookie-bite text-blue-500 mr-2"></i> นโยบายการใช้คุกกี้
            </h3>
            <button onclick="closeCookiePolicy()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        
        <div class="space-y-4 text-gray-600 text-sm leading-relaxed">
            <p><strong>เว็บไซต์นี้ใช้คุกกี้เพื่อวัตถุประสงค์ดังนี้:</strong></p>
            
            <!-- คุกกี้ที่จำเป็น -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-800">🔒 คุกกี้ที่จำเป็น (Essential)</span>
                        <p class="text-xs text-gray-500 mt-0.5">จำเป็นสำหรับการใช้งานเว็บไซต์ ไม่สามารถปิดได้</p>
                    </div>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">เปิดใช้งานตลอด</span>
                </div>
            </div>
            
            <!-- คุกกี้วิเคราะห์ -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-800">📊 คุกกี้วิเคราะห์ (Analytics)</span>
                        <p class="text-xs text-gray-500 mt-0.5">ช่วยให้เราเข้าใจพฤติกรรมผู้ใช้และปรับปรุงเว็บไซต์</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="analyticsConsent" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
                    </label>
                </div>
            </div>
            
            <!-- คุกกี้การตลาด -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-800">🎯 คุกกี้การตลาด (Marketing)</span>
                        <p class="text-xs text-gray-500 mt-0.5">ใช้สำหรับแสดงเนื้อหาที่เกี่ยวข้องกับคุณ</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="marketingConsent" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
                    </label>
                </div>
            </div>
            
            <p class="text-xs text-gray-400 mt-4">
                คุณสามารถเปลี่ยนแปลงการตั้งค่าได้ตลอดเวลาโดยคลิกที่ "จัดการคุกกี้" ที่ด้านล่างของหน้าเว็บไซต์
            </p>
        </div>
        
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
            <button onclick="closeCookiePolicy()" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">ปิด</button>
            <button onclick="saveCookiePreferences()" class="px-5 py-2 text-sm font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition-colors shadow-sm">
                <i class="fas fa-save mr-1"></i> บันทึกการตั้งค่า
            </button>
        </div>
    </div>
</div>

<script>
/**
 * ฟังก์ชันสำหรับ Cookie Banner (เชื่อมต่อกับ cookie-consent.js)
 */
// เปิด Modal นโยบาย
function showCookiePolicy() {
    CookieConsent.showPolicy();
}

// ปิด Modal นโยบาย
function closeCookiePolicy() {
    CookieConsent.closePolicy();
}

// บันทึกการตั้งค่าจาก Modal
function saveCookiePreferences() {
    CookieConsent.saveFromModal();
}

// ยอมรับทั้งหมด
function acceptAllCookies() {
    CookieConsent.acceptAll();
}

// ปฏิเสธทั้งหมด
function rejectAllCookies() {
    CookieConsent.rejectAll();
}

// เปิด Modal ตั้งค่า
function customizeCookies() {
    CookieConsent.showPolicy();
}

// แสดงข้อความยืนยัน (สำหรับ progress bar)
function showConsentSaved(message) {
    const progress = document.getElementById('consentProgress');
    if (progress) {
        progress.style.width = '100%';
        setTimeout(() => {
            progress.style.width = '0%';
        }, 1500);
    }
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: message || 'บันทึกการตั้งค่าเรียบร้อยแล้ว',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'bottom-end'
        });
    }
}
</script>