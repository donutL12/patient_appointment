/**
 * Enhanced Call System with Messenger-like Call Notifications
 * Save as: public/js/call-system.js
 */

const CallSystem = {
    currentCall: null,
    callCheckInterval: null,
    callTimer: null,
    callDuration: 0,
    missedCallTimeout: null,
    isVideoEnabled: true,
    isMuted: false,
    
    init() {
        this.startCallChecking();
        this.setupEventListeners();
        console.log('📞 Enhanced Call System initialized');
    },
    
    setupEventListeners() {
        window.addEventListener('click', (e) => {
            const outgoingModal = document.getElementById('outgoingCallModal');
            const incomingModal = document.getElementById('incomingCallModal');
            
            if (e.target === outgoingModal) this.endCall('cancelled');
            if (e.target === incomingModal) this.rejectCall();
        });
    },
    
    startCallChecking() {
        this.callCheckInterval = setInterval(() => {
            this.checkIncomingCalls();
        }, 2000);
    },
    
    async checkIncomingCalls() {
        try {
            const response = await fetch('../routes/routes.php?action=check_incoming_calls');
            const data = await response.json();
            
            if (data.success && data.incoming_calls.length > 0) {
                const call = data.incoming_calls[0];
                if (!this.currentCall || this.currentCall.id !== call.id) {
                    this.showIncomingCall(call);
                }
            }
        } catch (error) {
            console.error('Error checking calls:', error);
        }
    },
    
    async initiateCall(receiverId, receiverType, receiverName, callType = 'audio', receiverPhoto = null) {
        try {
            const formData = new FormData();
            formData.append('action', 'initiate_call');
            formData.append('receiver_id', receiverId);
            formData.append('receiver_type', receiverType);
            formData.append('call_type', callType);
            
            const response = await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentCall = {
                    id: data.call_id,
                    receiver_id: receiverId,
                    receiver_name: receiverName,
                    receiver_photo: receiverPhoto,
                    receiver_type: receiverType,
                    type: callType,
                    status: 'calling',
                    start_time: Date.now()
                };
                
                this.showOutgoingCall();
                this.updateCallStatus(data.call_id, 'ringing');
                
                // Auto-end if no answer after 30 seconds
                this.missedCallTimeout = setTimeout(() => {
                    if (this.currentCall && this.currentCall.status === 'calling') {
                        this.endCall('no_answer');
                    }
                }, 30000);
            } else {
                alert('Failed to initiate call: ' + data.message);
            }
        } catch (error) {
            console.error('Error initiating call:', error);
            alert('Failed to initiate call. Please check your connection.');
        }
    },
    
    showOutgoingCall() {
        const modal = document.getElementById('outgoingCallModal');
        const receiverName = document.getElementById('outgoingReceiverName');
        const receiverAvatar = document.getElementById('outgoingAvatar');
        const callType = document.getElementById('outgoingCallType');
        
        receiverName.textContent = this.currentCall.receiver_name;
        callType.innerHTML = this.currentCall.type === 'video' 
            ? '<i class="fas fa-video mr-2"></i>Video Call'
            : '<i class="fas fa-phone mr-2"></i>Voice Call';
        
        if (this.currentCall.receiver_photo && this.currentCall.receiver_photo !== 'default.png') {
            receiverAvatar.innerHTML = `<img src="../public/uploads/${this.currentCall.receiver_photo}" alt="Avatar" class="w-full h-full object-cover">`;
        } else {
            receiverAvatar.innerHTML = `<i class="fas fa-user text-6xl text-white opacity-80"></i>`;
        }
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.playRingtone();
    },
    
    showIncomingCall(call) {
        this.currentCall = {
            id: call.id,
            caller_id: call.caller_id,
            caller_name: call.caller_name,
            caller_photo: call.caller_photo,
            caller_type: call.caller_type,
            type: call.call_type,
            status: 'ringing'
        };
        
        const modal = document.getElementById('incomingCallModal');
        const callerName = document.getElementById('incomingCallerName');
        const callerPhoto = document.getElementById('incomingCallerPhoto');
        const callType = document.getElementById('incomingCallType');
        
        callerName.textContent = call.caller_type === 'doctor' 
            ? 'Dr. ' + call.caller_name 
            : call.caller_name;
        
        if (call.caller_photo && call.caller_photo !== 'default.png') {
            callerPhoto.innerHTML = `<img src="../public/uploads/${call.caller_photo}" alt="Caller" class="w-full h-full object-cover">`;
        } else {
            const initial = call.caller_name.charAt(0).toUpperCase();
            callerPhoto.innerHTML = `<span class="text-white text-6xl font-bold">${initial}</span>`;
        }
        
        callType.innerHTML = call.call_type === 'video'
            ? '<i class="fas fa-video mr-2"></i>Video Call'
            : '<i class="fas fa-phone mr-2"></i>Voice Call';
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.playRingtone();
    },
    
    async answerCall() {
        if (!this.currentCall) return;
        
        await this.updateCallStatus(this.currentCall.id, 'answered');
        this.currentCall.status = 'active';
        this.currentCall.start_time = Date.now();
        
        document.getElementById('incomingCallModal').classList.add('hidden');
        this.stopRingtone();
        
        if (this.currentCall.type === 'video') {
            this.showVideoCall();
        } else {
            this.showActiveCall();
        }
        
        this.startCallTimer();
    },
    
    async rejectCall() {
        if (!this.currentCall) return;
        
        await this.updateCallStatus(this.currentCall.id, 'rejected');
        
        document.getElementById('incomingCallModal').classList.add('hidden');
        document.body.style.overflow = '';
        this.stopRingtone();
        this.currentCall = null;
    },
    
    showActiveCall() {
        const modal = document.getElementById('activeCallModal');
        const contactName = document.getElementById('activeCallName');
        const contactAvatar = document.getElementById('activeCallAvatar');
        const duration = document.getElementById('callDuration');
        
        const name = this.currentCall.receiver_name || this.currentCall.caller_name;
        const photo = this.currentCall.receiver_photo || this.currentCall.caller_photo;
        
        contactName.textContent = name;
        duration.textContent = '00:00';
        
        if (photo && photo !== 'default.png') {
            contactAvatar.innerHTML = `<img src="../public/uploads/${photo}" alt="Avatar" class="w-full h-full object-cover">`;
        } else {
            const initial = name.charAt(0).toUpperCase();
            contactAvatar.innerHTML = `<span class="text-white text-7xl font-bold">${initial}</span>`;
        }
        
        modal.classList.remove('hidden');
        console.log('📞 Audio call connected');
    },
    
    showVideoCall() {
        const modal = document.getElementById('activeVideoCallModal');
        const contactName = document.getElementById('videoCallName');
        const remoteAvatar = document.getElementById('remoteVideoAvatar');
        const duration = document.getElementById('videoCallDuration');
        
        const name = this.currentCall.receiver_name || this.currentCall.caller_name;
        const photo = this.currentCall.receiver_photo || this.currentCall.caller_photo;
        
        contactName.textContent = name;
        duration.textContent = '00:00';
        
        if (photo && photo !== 'default.png') {
            remoteAvatar.innerHTML = `<img src="../public/uploads/${photo}" alt="Avatar" class="w-full h-full object-cover">`;
        } else {
            remoteAvatar.innerHTML = `<i class="fas fa-user text-8xl text-white"></i>`;
        }
        
        modal.classList.remove('hidden');
        this.isVideoEnabled = true;
        console.log('📹 Video call connected');
    },
    
    startCallTimer() {
        this.callDuration = 0;
        this.callTimer = setInterval(() => {
            this.callDuration++;
            const minutes = Math.floor(this.callDuration / 60);
            const seconds = this.callDuration % 60;
            const duration = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            const audioDuration = document.getElementById('callDuration');
            const videoDuration = document.getElementById('videoCallDuration');
            
            if (audioDuration) audioDuration.textContent = duration;
            if (videoDuration) videoDuration.textContent = duration;
        }, 1000);
    },
    
    async endCall(endReason = 'ended') {
        if (!this.currentCall) return;
        
        if (this.missedCallTimeout) {
            clearTimeout(this.missedCallTimeout);
            this.missedCallTimeout = null;
        }
        
        const callDuration = this.callDuration;
        const callId = this.currentCall.id;
        
        await this.updateCallStatus(callId, endReason);
        
        document.getElementById('outgoingCallModal')?.classList.add('hidden');
        document.getElementById('incomingCallModal')?.classList.add('hidden');
        document.getElementById('activeCallModal')?.classList.add('hidden');
        document.getElementById('activeVideoCallModal')?.classList.add('hidden');
        document.body.style.overflow = '';
        
        this.stopRingtone();
        
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
        
        // Create call notification in messages
        await this.createCallNotificationMessage(callId, endReason, callDuration);
        
        this.currentCall = null;
        this.callDuration = 0;
        this.isVideoEnabled = true;
        this.isMuted = false;
        
        console.log('📞 Call ended:', endReason);
        
        // Refresh to show call notification
        setTimeout(() => {
            if (window.location.href.includes('chat.php')) {
                window.location.reload();
            }
        }, 500);
    },
    
    async createCallNotificationMessage(callId, status, duration) {
        try {
            const formData = new FormData();
            formData.append('action', 'create_call_notification');
            formData.append('call_id', callId);
            formData.append('status', status);
            formData.append('duration', duration);
            
            await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error creating call notification:', error);
        }
    },
    
    async updateCallStatus(callId, status) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_call_status');
            formData.append('call_id', callId);
            formData.append('status', status);
            
            await fetch('../routes/routes.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error updating call status:', error);
        }
    },
    
    toggleMute() {
        this.isMuted = !this.isMuted;
        const audioBtn = document.getElementById('muteBtn');
        const videoBtn = document.getElementById('videoMuteBtn');
        
        [audioBtn, videoBtn].forEach(btn => {
            if (!btn) return;
            const icon = btn.querySelector('i');
            
            if (this.isMuted) {
                icon.classList.remove('fa-microphone');
                icon.classList.add('fa-microphone-slash');
                btn.classList.add('bg-red-500', 'text-white');
                btn.classList.remove('bg-gray-700');
            } else {
                icon.classList.remove('fa-microphone-slash');
                icon.classList.add('fa-microphone');
                btn.classList.remove('bg-red-500', 'text-white');
                btn.classList.add('bg-gray-700');
            }
        });
        
        console.log('🎤 Microphone:', this.isMuted ? 'Muted' : 'Unmuted');
    },
    
    toggleSpeaker() {
        const btn = document.getElementById('speakerBtn');
        if (!btn) return;
        
        const icon = btn.querySelector('i');
        
        if (icon.classList.contains('fa-volume-up')) {
            icon.classList.remove('fa-volume-up');
            icon.classList.add('fa-volume-mute');
        } else {
            icon.classList.remove('fa-volume-mute');
            icon.classList.add('fa-volume-up');
        }
    },
    
    toggleVideo() {
        this.isVideoEnabled = !this.isVideoEnabled;
        const btn = document.getElementById('videoToggleBtn');
        if (!btn) return;
        
        const icon = btn.querySelector('i');
        
        if (this.isVideoEnabled) {
            icon.classList.remove('fa-video-slash');
            icon.classList.add('fa-video');
            btn.classList.remove('bg-red-500', 'text-white');
            btn.classList.add('bg-gray-700');
        } else {
            icon.classList.remove('fa-video');
            icon.classList.add('fa-video-slash');
            btn.classList.add('bg-red-500', 'text-white');
            btn.classList.remove('bg-gray-700');
        }
        
        console.log('📹 Video:', this.isVideoEnabled ? 'Enabled' : 'Disabled');
    },
    
    switchToVideo() {
        if (!this.currentCall || this.currentCall.type === 'video') return;
        
        console.log('📹 Switching to video call...');
        this.currentCall.type = 'video';
        
        document.getElementById('activeCallModal').classList.add('hidden');
        this.showVideoCall();
    },
    
    switchCamera() {
        console.log('📷 Switching camera...');
    },
    
    toggleFullscreen() {
        const modal = document.getElementById('activeVideoCallModal');
        if (!document.fullscreenElement) {
            modal.requestFullscreen().catch(err => {
                console.error('Error entering fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    },
    
    async loadCallHistory() {
        try {
            const response = await fetch('../routes/routes.php?action=get_call_history');
            const data = await response.json();
            
            if (data.success) {
                this.renderCallHistory(data.calls);
            }
        } catch (error) {
            console.error('Error loading call history:', error);
        }
    },
    
    renderCallHistory(calls) {
        const historyList = document.getElementById('callHistoryList');
        if (!historyList) return;
        
        if (calls.length === 0) {
            historyList.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-phone-slash text-gray-300 text-5xl mb-4"></i>
                    <p class="text-gray-500">No call history yet</p>
                </div>
            `;
            return;
        }
        
        historyList.innerHTML = calls.map(call => this.createCallHistoryItem(call)).join('');
    },
    
    createCallHistoryItem(call) {
        const isMissed = call.is_missed;
        const statusIcon = this.getCallStatusIcon(call);
        const statusColor = isMissed ? 'text-red-500' : 
                           call.direction === 'outgoing' ? 'text-green-500' : 'text-blue-500';
        
        const photoUrl = call.contact_photo && call.contact_photo !== 'default.png' 
            ? `../public/uploads/${call.contact_photo}`
            : null;
        
        const initial = call.contact_name.charAt(0).toUpperCase();
        
        return `
            <div class="p-4 hover:bg-gray-50 rounded-xl transition border border-gray-200 mb-3 ${isMissed ? 'bg-red-50 border-red-200' : ''}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4 flex-1 min-w-0">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center flex-shrink-0 overflow-hidden">
                            ${photoUrl ? `<img src="${photoUrl}" alt="Avatar" class="w-full h-full object-cover">` : `<span class="text-white font-bold">${initial}</span>`}
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1">
                                <h3 class="font-semibold text-gray-900 truncate">${call.contact_name}</h3>
                                <span class="px-2 py-0.5 bg-${call.call_type === 'video' ? 'purple' : 'blue'}-100 text-${call.call_type === 'video' ? 'purple' : 'blue'}-600 text-xs rounded-full">
                                    <i class="fas fa-${call.call_type === 'video' ? 'video' : 'phone'} mr-1"></i>${call.call_type}
                                </span>
                            </div>
                            <div class="flex items-center space-x-3 text-sm text-gray-600">
                                <span class="flex items-center ${statusColor}">
                                    ${statusIcon}
                                    ${this.getCallStatusText(call)}
                                </span>
                                <span>${call.time_ago}</span>
                                ${call.duration > 0 ? `<span><i class="fas fa-clock mr-1"></i>${call.formatted_duration}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2 flex-shrink-0">
                        <button onclick="CallSystem.initiateCall(${call.contact_id}, '${call.contact_type}', '${call.contact_name}', 'audio', '${call.contact_photo}')" 
                                class="p-3 bg-blue-100 text-blue-600 hover:bg-blue-200 rounded-full transition transform hover:scale-105" 
                                title="Audio Call">
                            <i class="fas fa-phone"></i>
                        </button>
                        <button onclick="CallSystem.initiateCall(${call.contact_id}, '${call.contact_type}', '${call.contact_name}', 'video', '${call.contact_photo}')" 
                                class="p-3 bg-purple-100 text-purple-600 hover:bg-purple-200 rounded-full transition transform hover:scale-105" 
                                title="Video Call">
                            <i class="fas fa-video"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    },
    
    getCallStatusIcon(call) {
        if (call.is_missed) return '<i class="fas fa-phone-slash mr-1"></i>';
        if (call.direction === 'outgoing') return '<i class="fas fa-arrow-up mr-1"></i>';
        return '<i class="fas fa-arrow-down mr-1"></i>';
    },
    
    getCallStatusText(call) {
        if (call.is_missed) return 'Missed Call';
        if (call.status === 'rejected') return 'Declined';
        if (call.status === 'cancelled') return 'Cancelled';
        if (call.direction === 'outgoing') return 'Outgoing';
        return 'Incoming';
    },
    
    openCallHistory() {
        document.getElementById('callHistoryModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.loadCallHistory();
    },
    
    closeCallHistory() {
        document.getElementById('callHistoryModal').classList.add('hidden');
        document.body.style.overflow = '';
    },
    
    filterCallHistory() {
        const filter = document.getElementById('callTypeFilter').value;
        const items = document.querySelectorAll('#callHistoryList > div');
        
        items.forEach(item => {
            if (filter === 'all') {
                item.style.display = 'block';
            } else {
                const matchesFilter = item.textContent.toLowerCase().includes(filter.toLowerCase());
                item.style.display = matchesFilter ? 'block' : 'none';
            }
        });
    },
    
    playRingtone() {
        console.log('🔔 Playing ringtone...');
    },
    
    stopRingtone() {
        console.log('🔕 Stopping ringtone...');
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    CallSystem.init();
});

// Cleanup
window.addEventListener('beforeunload', () => {
    if (CallSystem.callCheckInterval) {
        clearInterval(CallSystem.callCheckInterval);
    }
    if (CallSystem.currentCall) {
        CallSystem.endCall('ended');
    }
});

// Search
document.getElementById('callHistorySearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const items = document.querySelectorAll('#callHistoryList > div');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});

// Expose globally
window.CallSystem = CallSystem;