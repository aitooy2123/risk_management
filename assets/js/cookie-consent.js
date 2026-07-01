/**
 * Cookie Consent Manager - จัดการสถานะและ UI
 * สอดคล้องกับ PDPA และ GDPR
 * ทำงานร่วมกับ Cookie Banner ใน includes/cookie_banner.php
 */

const CookieConsent = {
    // คีย์สำหรับเก็บใน Cookie
    CONSENT_KEY: 'cookie_consent',
    PREFERENCES_KEY: 'cookie_preferences',
    
    // ค่าเริ่มต้น (ยอมรับ Analytics ไว้ก่อน)
    defaults: {
        analytics: true,
        marketing: false
    },
    
    /**
     * ตรวจสอบว่าผู้ใช้ให้ consent แล้วหรือยัง
     * @returns {boolean} true ถ้าให้ consent แล้ว
     */
    hasConsented() {
        return this.getCookie(this.CONSENT_KEY) === '1';
    },
    
    /**
     * อ่านค่าการตั้งค่าคุกกี้จาก Cookie
     * @returns {Object} { analytics, marketing }
     */
    getPreferences() {
        const prefs = this.getCookie(this.PREFERENCES_KEY);
        if (prefs) {
            try {
                return JSON.parse(decodeURIComponent(prefs));
            } catch (e) {
                return { ...this.defaults };
            }
        }
        return { ...this.defaults };
    },
    
    /**
     * บันทึกการตั้งค่าคุกกี้ลง Cookie (อายุ 1 ปี)
     * @param {Object} preferences { analytics, marketing }
     */
    setPreferences(preferences) {
        const expiryDate = new Date();
        expiryDate.setFullYear(expiryDate.getFullYear() + 1);
        const prefsStr = JSON.stringify(preferences);
        document.cookie = `${this.PREFERENCES_KEY}=${encodeURIComponent(prefsStr)}; expires=${expiryDate.toUTCString()}; path=/; SameSite=Lax`;
        document.cookie = `${this.CONSENT_KEY}=1; expires=${expiryDate.toUTCString()}; path=/; SameSite=Lax`;
        this.saveToServer(preferences);
    },
    
    /**
     * บันทึก Consent ไปยัง Server (สำหรับ Logging)
     * @param {Object} preferences 
     */
    saveToServer(preferences) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('action.php?action=save_cookie_consent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                consent: 1,
                preferences: preferences,
                csrf_token: csrfToken
            })
        }).catch(() => {}); // ไม่ต้องรอผลลัพธ์
    },
    
    /**
     * อ่านค่า Cookie ตามชื่อ
     * @param {string} name ชื่อ Cookie
     * @returns {string|null} ค่า Cookie หรือ null
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    },
    
    /**
     * ยอมรับคุกกี้ทั้งหมด (Analytics + Marketing)
     */
    acceptAll() {
        this.setPreferences({ analytics: true, marketing: true });
        this.hideBanner();
        this.showToast('ยอมรับคุกกี้ทั้งหมดเรียบร้อยแล้ว', 'success');
    },
    
    /**
     * ปฏิเสธคุกกี้ทั้งหมด (เหลือเฉพาะ Essential)
     */
    rejectAll() {
        this.setPreferences({ analytics: false, marketing: false });
        this.hideBanner();
        this.showToast('ปฏิเสธคุกกี้เรียบร้อยแล้ว', 'info');
    },
    
    /**
     * ซ่อน Cookie Banner
     */
    hideBanner() {
        const banner = document.getElementById('cookieConsent');
        if (banner) {
            banner.classList.add('translate-y-full');
            setTimeout(() => {
                banner.style.display = 'none';
            }, 500);
        }
    },
    
    /**
     * แสดง Toast แจ้งเตือน
     * @param {string} message ข้อความ
     * @param {string} type success/info/error
     */
    showToast(message, type = 'success') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type,
                title: message,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'bottom-end'
            });
        }
    },
    
    /**
     * เปิด Modal ตั้งค่าคุกกี้
     */
    showPolicy() {
        const modal = document.getElementById('cookiePolicyModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            const prefs = this.getPreferences();
            const analyticsCheck = document.getElementById('analyticsConsent');
            const marketingCheck = document.getElementById('marketingConsent');
            if (analyticsCheck) analyticsCheck.checked = prefs.analytics !== false;
            if (marketingCheck) marketingCheck.checked = prefs.marketing === true;
        }
    },
    
    /**
     * ปิด Modal ตั้งค่าคุกกี้
     */
    closePolicy() {
        const modal = document.getElementById('cookiePolicyModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    /**
     * บันทึกการตั้งค่าจาก Modal
     */
    saveFromModal() {
        const analytics = document.getElementById('analyticsConsent')?.checked || false;
        const marketing = document.getElementById('marketingConsent')?.checked || false;
        this.setPreferences({ analytics, marketing });
        this.closePolicy();
        this.showToast('บันทึกการตั้งค่าเรียบร้อยแล้ว', 'success');
    }
};

// ทำให้พร้อมใช้งานทั่วโลก (สำหรับ onclick)
window.CookieConsent = CookieConsent;
window.acceptAllCookies = () => CookieConsent.acceptAll();
window.rejectAllCookies = () => CookieConsent.rejectAll();
window.customizeCookies = () => CookieConsent.showPolicy();
window.showCookiePolicy = () => CookieConsent.showPolicy();
window.closeCookiePolicy = () => CookieConsent.closePolicy();
window.saveCookiePreferences = () => CookieConsent.saveFromModal();
window.showConsentSaved = (msg) => CookieConsent.showToast(msg || 'บันทึกการตั้งค่าเรียบร้อยแล้ว', 'success');

// ตรวจสอบสถานะเมื่อโหลดหน้า (ถ้ามี consent แล้วให้ซ่อน Banner)
document.addEventListener('DOMContentLoaded', function() {
    if (CookieConsent.hasConsented()) {
        const banner = document.getElementById('cookieConsent');
        if (banner) {
            banner.style.display = 'none';
        }
    }
});