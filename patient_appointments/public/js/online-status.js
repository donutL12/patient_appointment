/**
 * Real-Time Online Status Manager
 * Messenger-like online/offline status with last seen
 * Save as: public/js/online-status.js
 */

const OnlineStatusManager = {
    heartbeatInterval: null,
    statusCheckInterval: null,
    currentUserId: null,
    currentUserType: null,
    inactivityTimeout: null,
    HEARTBEAT_RATE: 30000, // 30 seconds
    STATUS_CHECK_RATE: 5000, // 5 seconds
    INACTIVITY_THRESHOLD: 240000, // 4 minutes of inactivity = offline
    
    init(userId, userType) {
        this.currentUserId = userId;
        this.currentUserType = userType;
        
        // Set online immediately
        this.setOnline();
        
        // Start heartbeat
        this.startHeartbeat();
        
        // Start checking other users' status
        this.startStatusCheck();
        
        // Track user activity
        this.setupActivityTracking();
        
        // Handle page visibility
        this.setupVisibilityTracking();
        
        // Handle beforeunload (logout/close)
        this.setupUnloadHandler();
        
        console.log('💚 Real-Time Online Status Manager initialized');
    },
    
    async setOnline() {
        try {
            const formData = new FormData();
            formData.append('action', 'set_online');
            
            const response = await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('💚 Status: Online');
                this.resetInactivityTimer();
            }
        } catch (error) {
            console.error('Error setting online:', error);
        }
    },
    
    async setOffline() {
        try {
            const formData = new FormData();
            formData.append('action', 'set_offline');
            
            // Use sendBeacon for reliable offline on page unload
            if (navigator.sendBeacon) {
                const data = new URLSearchParams(formData);
                navigator.sendBeacon('../routes/routes.php', data);
            } else {
                await fetch('../routes/routes.php', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });
            }
            
            console.log('🔴 Status: Offline');
        } catch (error) {
            console.error('Error setting offline:', error);
        }
    },
    
    startHeartbeat() {
        // Send heartbeat every 30 seconds to keep status active
        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, this.HEARTBEAT_RATE);
    },
    
    async sendHeartbeat() {
        try {
            const formData = new FormData();
            formData.append('action', 'heartbeat');
            
            const response = await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('💓 Heartbeat sent:', new Date().toLocaleTimeString());
            }
        } catch (error) {
            console.error('Heartbeat error:', error);
        }
    },
    
    startStatusCheck() {
        // Check contact status every 5 seconds
        this.statusCheckInterval = setInterval(() => {
            this.checkContactStatus();
        }, this.STATUS_CHECK_RATE);
    },
    
    async checkContactStatus() {
        const contactId = this.getContactIdFromPage();
        const contactType = this.getContactTypeFromPage();
        
        if (!contactId || !contactType) return;
        
        try {
            const response = await fetch(`../routes/routes.php?action=get_user_status&user_id=${contactId}&user_type=${contactType}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateStatusDisplay(data);
            }
        } catch (error) {
            console.error('Error checking status:', error);
        }
    },
    
    updateStatusDisplay(statusData) {
        // Update status indicator (green dot)
        const statusIndicators = document.querySelectorAll('[data-status-indicator]');
        statusIndicators.forEach(indicator => {
            if (statusData.is_online) {
                // Online: Show green pulsing dot
                indicator.classList.remove('bg-gray-400', 'hidden');
                indicator.classList.add('bg-green-500', 'animate-pulse');
            } else {
                // Offline: Hide the dot completely
                indicator.classList.remove('bg-green-500', 'animate-pulse', 'bg-gray-400');
                indicator.classList.add('hidden');
            }
        });
        
        // Update status text
        const statusTexts = document.querySelectorAll('[data-status-text]');
        statusTexts.forEach(text => {
            if (statusData.is_online) {
                text.innerHTML = '<span class="w-2 h-2 bg-green-400 rounded-full mr-1.5 animate-pulse inline-block"></span>Active now';
                text.classList.remove('text-gray-500');
                text.classList.add('text-green-600');
            } else {
                // Offline: Show last seen without dot
                text.textContent = statusData.last_seen_text;
                text.classList.remove('text-green-600');
                text.classList.add('text-gray-500');
            }
        });
    },
    
    setupActivityTracking() {
        // Track mouse movement, clicks, keyboard
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.onActivity();
            }, { passive: true });
        });
    },
    
    onActivity() {
        // User is active, reset inactivity timer
        this.resetInactivityTimer();
    },
    
    resetInactivityTimer() {
        // Clear existing timer
        if (this.inactivityTimeout) {
            clearTimeout(this.inactivityTimeout);
        }
        
        // Set new timer - if no activity for 4 minutes, set offline
        this.inactivityTimeout = setTimeout(() => {
            console.log('⚠️ User inactive for 4 minutes, setting offline');
            this.setOffline();
        }, this.INACTIVITY_THRESHOLD);
    },
    
    setupVisibilityTracking() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // User switched tabs or minimized window
                console.log('👁️ Page hidden - user switched tabs');
                // Don't set offline immediately, let the inactivity timer handle it
            } else {
                // User came back
                console.log('👁️ Page visible - user returned');
                this.setOnline();
            }
        });
    },
    
    setupUnloadHandler() {
        // Handle page close/refresh
        window.addEventListener('beforeunload', () => {
            this.setOffline();
        });
        
        // Handle page navigation
        window.addEventListener('pagehide', () => {
            this.setOffline();
        });
    },
    
    getContactIdFromPage() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('doctor_id') || urlParams.get('patient_id');
    },
    
    getContactTypeFromPage() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('doctor_id')) return 'doctor';
        if (urlParams.has('patient_id')) return 'patient';
        return null;
    },
    
    destroy() {
        // Cleanup
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
        }
        if (this.inactivityTimeout) {
            clearTimeout(this.inactivityTimeout);
        }
        this.setOffline();
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const userIdElement = document.querySelector('[data-current-user-id]');
    const userTypeElement = document.querySelector('[data-current-user-type]');
    
    if (userIdElement && userTypeElement) {
        const userId = userIdElement.dataset.currentUserId;
        const userType = userTypeElement.dataset.currentUserType;
        OnlineStatusManager.init(userId, userType);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    OnlineStatusManager.destroy();
});

// Expose globally
window.OnlineStatusManager = OnlineStatusManager;