/**
 * GlobexSky Live Streaming Client
 *
 * Streamer side:
 * - Access camera/mic via getUserMedia()
 * - Create WebRTC peer connection
 * - Broadcast media stream to server
 * - Handle stream controls (mute, camera off, screen share)
 * - Pin/unpin products
 * - Moderate chat
 *
 * Viewer side:
 * - Connect to stream via WebRTC
 * - Receive and display media stream
 * - Send/receive chat messages (Socket.io)
 * - Send reactions
 * - View pinned products
 * - Add to cart directly from stream
 *
 * Fallback: If WebRTC not supported, show "Browser not supported" message.
 */

(function() {
    'use strict';

    const SOCKET_URL = window.SOCKET_URL || 'http://localhost:3001';

    let socket = null;
    let localStream = null;
    let screenStream = null;
    let peerConnections = {};  // peerId -> RTCPeerConnection
    let isMicOn = true;
    let isCamOn = true;
    let isScreenSharing = false;
    let streamStartTime = null;
    let durationInterval = null;

    // ── Initialize ───────────────────────────────────────────
    function initSocket() {
        if (typeof io === 'undefined') {
            console.warn('Socket.io not loaded. Real-time features disabled.');
            return null;
        }
        socket = io(SOCKET_URL, {
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: 5,
            reconnectionDelay: 2000
        });

        socket.on('connect', () => {
            console.log('Connected to streaming server');
        });

        socket.on('disconnect', () => {
            console.log('Disconnected from streaming server');
        });

        socket.on('connect_error', (err) => {
            console.warn('Socket connection error:', err.message);
        });

        return socket;
    }

    // ── Camera / Mic Access ──────────────────────────────────
    async function enableCamera() {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
                audio: true
            });

            const videoEl = document.getElementById('localVideo');
            if (videoEl) {
                videoEl.srcObject = localStream;
                videoEl.play().catch(() => {});
            }

            const noCamMsg = document.getElementById('noCameraMsg');
            if (noCamMsg) noCamMsg.style.display = 'none';

            // Enable control buttons
            enableControls(true);

            return localStream;
        } catch (err) {
            console.error('Camera access error:', err);
            alert('Could not access camera/microphone. Please check permissions.');
            return null;
        }
    }

    function enableControls(enabled) {
        ['toggleMic', 'toggleCam', 'toggleScreen'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.disabled = !enabled;
        });
    }

    // ── Toggle Controls ──────────────────────────────────────
    function toggleMic() {
        if (!localStream) return;
        const audioTracks = localStream.getAudioTracks();
        audioTracks.forEach(track => {
            track.enabled = !track.enabled;
        });
        isMicOn = !isMicOn;
        const btn = document.getElementById('toggleMic');
        if (btn) {
            btn.innerHTML = isMicOn
                ? '<i class="bi bi-mic"></i> Mic On'
                : '<i class="bi bi-mic-mute text-danger"></i> Mic Off';
        }
    }

    function toggleCamera() {
        if (!localStream) return;
        const videoTracks = localStream.getVideoTracks();
        videoTracks.forEach(track => {
            track.enabled = !track.enabled;
        });
        isCamOn = !isCamOn;
        const btn = document.getElementById('toggleCam');
        if (btn) {
            btn.innerHTML = isCamOn
                ? '<i class="bi bi-camera-video"></i> Cam On'
                : '<i class="bi bi-camera-video-off text-danger"></i> Cam Off';
        }
    }

    async function toggleScreenShare() {
        if (isScreenSharing) {
            // Stop screen share, revert to camera
            if (screenStream) {
                screenStream.getTracks().forEach(t => t.stop());
                screenStream = null;
            }
            const videoEl = document.getElementById('localVideo');
            if (videoEl && localStream) videoEl.srcObject = localStream;
            isScreenSharing = false;
        } else {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const videoEl = document.getElementById('localVideo');
                if (videoEl) videoEl.srcObject = screenStream;
                isScreenSharing = true;

                // When user stops screen share via browser UI
                screenStream.getVideoTracks()[0].onended = () => {
                    toggleScreenShare();
                };
            } catch (err) {
                console.error('Screen share error:', err);
            }
        }
        const btn = document.getElementById('toggleScreen');
        if (btn) {
            btn.innerHTML = isScreenSharing
                ? '<i class="bi bi-display text-primary"></i> Stop Sharing'
                : '<i class="bi bi-display"></i> Screen Share';
        }
    }

    // ── Streamer: Start Stream ───────────────────────────────
    async function startStream(streamId, streamerId, title, category) {
        if (!socket) initSocket();
        if (!socket) return;

        await enableCamera();
        if (!localStream) return;

        socket.emit('stream_start', { streamId, streamerId, title, category });

        streamStartTime = Date.now();
        startDurationTimer();

        const statusCard = document.getElementById('streamStatusCard');
        if (statusCard) statusCard.classList.remove('d-none');

        const startBtn = document.getElementById('startStreamBtn');
        if (startBtn) startBtn.disabled = true;

        // Listen for viewer connections (WebRTC signaling)
        socket.on('webrtc_offer', async (data) => {
            const pc = createPeerConnection(data.senderId, streamId);
            await pc.setRemoteDescription(new RTCSessionDescription(data.offer));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            socket.emit('webrtc_answer', { targetId: data.senderId, answer, streamId });
        });

        socket.on('webrtc_ice_candidate', (data) => {
            const pc = peerConnections[data.senderId];
            if (pc && data.candidate) {
                pc.addIceCandidate(new RTCIceCandidate(data.candidate)).catch(() => {});
            }
        });

        setupStreamEvents(streamId);
    }

    function createPeerConnection(peerId, streamId) {
        const config = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };

        const pc = new RTCPeerConnection(config);
        peerConnections[peerId] = pc;

        // Add local tracks
        const stream = isScreenSharing ? screenStream : localStream;
        if (stream) {
            stream.getTracks().forEach(track => {
                pc.addTrack(track, stream);
            });
        }

        pc.onicecandidate = (event) => {
            if (event.candidate && socket) {
                socket.emit('webrtc_ice_candidate', {
                    targetId: peerId,
                    candidate: event.candidate,
                    streamId
                });
            }
        };

        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed') {
                pc.close();
                delete peerConnections[peerId];
            }
        };

        return pc;
    }

    // ── Viewer: Join Stream ──────────────────────────────────
    function joinStream(streamId, userId, username) {
        if (!socket) initSocket();
        if (!socket) return;

        socket.emit('stream_join', { streamId, userId, username });

        socket.on('webrtc_offer', async (data) => {
            const pc = createViewerPeerConnection(data.senderId, streamId);
            await pc.setRemoteDescription(new RTCSessionDescription(data.offer));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            socket.emit('webrtc_answer', { targetId: data.senderId, answer, streamId });
        });

        socket.on('webrtc_answer', async (data) => {
            const pc = peerConnections[data.senderId];
            if (pc) {
                await pc.setRemoteDescription(new RTCSessionDescription(data.answer));
            }
        });

        socket.on('webrtc_ice_candidate', (data) => {
            const pc = peerConnections[data.senderId];
            if (pc && data.candidate) {
                pc.addIceCandidate(new RTCIceCandidate(data.candidate)).catch(() => {});
            }
        });

        setupStreamEvents(streamId);
    }

    function createViewerPeerConnection(peerId, streamId) {
        const config = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };

        const pc = new RTCPeerConnection(config);
        peerConnections[peerId] = pc;

        pc.ontrack = (event) => {
            const videoEl = document.getElementById('remoteVideo');
            if (videoEl && event.streams[0]) {
                videoEl.srcObject = event.streams[0];
                videoEl.play().catch(() => {});
            }
            const noStreamMsg = document.getElementById('noStreamMsg');
            if (noStreamMsg) noStreamMsg.style.display = 'none';
        };

        pc.onicecandidate = (event) => {
            if (event.candidate && socket) {
                socket.emit('webrtc_ice_candidate', {
                    targetId: peerId,
                    candidate: event.candidate,
                    streamId
                });
            }
        };

        return pc;
    }

    // ── Stream Events (shared between streamer and viewer) ───
    function setupStreamEvents(streamId) {
        // Chat messages
        socket.on('stream_chat_message', (data) => {
            appendChatMessage(data);
        });

        // Reactions
        socket.on('stream_reaction_broadcast', (data) => {
            showReactionAnimation(data.emoji);
        });

        // Pinned product
        socket.on('stream_product_pin', (data) => {
            showPinnedProduct(data.product);
        });

        socket.on('stream_product_unpin', () => {
            hidePinnedProduct();
        });

        // Viewer count
        socket.on('stream_viewer_count', (data) => {
            updateViewerCount(data.count);
        });

        // Stream ended
        socket.on('stream_ended', (data) => {
            handleStreamEnded(data);
        });

        // Banned
        socket.on('stream_user_banned', (data) => {
            if (typeof window.CURRENT_USER_ID !== 'undefined' && data.userId === window.CURRENT_USER_ID) {
                alert('You have been removed from this stream.');
                window.location.href = '/pages/live/index.php';
            }
        });

        // Questions
        socket.on('stream_question_broadcast', (data) => {
            appendChatMessage({
                username: data.username,
                message: data.question,
                type: 'question',
                timestamp: data.timestamp
            });
        });
    }

    // ── Chat ─────────────────────────────────────────────────
    function sendChatMessage(streamId, userId, username, message) {
        if (!socket || !message.trim()) return;
        socket.emit('stream_chat', {
            streamId,
            userId,
            username,
            message: message.trim(),
            type: 'message'
        });
    }

    function appendChatMessage(data) {
        const container = document.getElementById('chatMessages');
        if (!container) return;

        const div = document.createElement('div');
        div.className = 'mb-2';

        if (data.type === 'reaction') {
            div.innerHTML = `<strong class="small">${escapeHtml(data.username)}</strong> <span class="fs-5">${escapeHtml(data.message)}</span>`;
        } else if (data.type === 'question') {
            div.innerHTML = `<strong class="small">${escapeHtml(data.username)}</strong> <span class="badge bg-info text-dark">Q</span> <span class="small">${escapeHtml(data.message)}</span>`;
        } else {
            div.innerHTML = `<strong class="small">${escapeHtml(data.username)}</strong> <span class="small text-muted">${escapeHtml(data.message)}</span>`;
        }

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    // ── Reactions ────────────────────────────────────────────
    function sendReaction(streamId, userId, emoji) {
        if (!socket) return;
        socket.emit('stream_reaction', { streamId, userId, emoji });
    }

    function showReactionAnimation(emoji) {
        const container = document.getElementById('videoContainer') || document.body;
        const span = document.createElement('span');
        span.textContent = emoji;
        span.style.cssText = `
            position: absolute; bottom: 20%; font-size: 2rem;
            left: ${20 + Math.random() * 60}%;
            animation: floatUp 2s ease-out forwards;
            pointer-events: none; z-index: 100;
        `;
        container.appendChild(span);
        setTimeout(() => span.remove(), 2000);
    }

    // ── Pinned Product ───────────────────────────────────────
    function showPinnedProduct(product) {
        // This could update a product overlay panel
        const event = new CustomEvent('productPinned', { detail: product });
        document.dispatchEvent(event);
    }

    function hidePinnedProduct() {
        const event = new CustomEvent('productUnpinned');
        document.dispatchEvent(event);
    }

    // ── Viewer Count ─────────────────────────────────────────
    function updateViewerCount(count) {
        const el = document.getElementById('viewerCountNum');
        if (el) el.textContent = count;
        const liveEl = document.getElementById('liveViewerCount');
        if (liveEl) liveEl.textContent = count;
    }

    // ── Duration Timer ───────────────────────────────────────
    function startDurationTimer() {
        const durationEl = document.getElementById('liveDuration');
        if (!durationEl) return;
        durationInterval = setInterval(() => {
            if (!streamStartTime) return;
            const elapsed = Math.floor((Date.now() - streamStartTime) / 1000);
            const hours = Math.floor(elapsed / 3600);
            const mins = Math.floor((elapsed % 3600) / 60);
            const secs = elapsed % 60;
            durationEl.textContent = hours
                ? `${hours}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
                : `${mins}:${String(secs).padStart(2, '0')}`;
        }, 1000);
    }

    // ── Stream Ended ─────────────────────────────────────────
    function handleStreamEnded(data) {
        if (durationInterval) clearInterval(durationInterval);
        // Close all peer connections
        Object.values(peerConnections).forEach(pc => pc.close());
        peerConnections = {};
        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }
    }

    // ── End Stream (streamer action) ─────────────────────────
    function endStream(streamId, streamerId) {
        if (!socket) return;
        socket.emit('stream_end', { streamId, streamerId });
        handleStreamEnded({});
    }

    // ── Utility ──────────────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Add CSS animation ────────────────────────────────────
    const style = document.createElement('style');
    style.textContent = `
        @keyframes floatUp {
            0% { opacity: 1; transform: translateY(0) scale(1); }
            100% { opacity: 0; transform: translateY(-150px) scale(1.5); }
        }
    `;
    document.head.appendChild(style);

    // ── DOM Event Bindings ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Enable camera button
        const enableCamBtn = document.getElementById('enableCamera');
        if (enableCamBtn) {
            enableCamBtn.addEventListener('click', enableCamera);
        }

        // Toggle controls
        const micBtn = document.getElementById('toggleMic');
        if (micBtn) micBtn.addEventListener('click', toggleMic);

        const camBtn = document.getElementById('toggleCam');
        if (camBtn) camBtn.addEventListener('click', toggleCamera);

        const screenBtn = document.getElementById('toggleScreen');
        if (screenBtn) screenBtn.addEventListener('click', toggleScreenShare);

        // Chat send
        const sendBtn = document.getElementById('sendChat');
        const chatInput = document.getElementById('chatInput');
        if (sendBtn && chatInput) {
            sendBtn.addEventListener('click', () => {
                const streamId = sendBtn.dataset.stream || window.STREAM_ID;
                sendChatMessage(streamId, window.CURRENT_USER_ID, window.CURRENT_USERNAME, chatInput.value);
                chatInput.value = '';
            });
            chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') sendBtn.click();
            });
        }

        // Reaction buttons
        document.querySelectorAll('.reaction-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const emoji = btn.dataset.emoji;
                const streamId = btn.dataset.stream || window.STREAM_ID;
                sendReaction(streamId, window.CURRENT_USER_ID, emoji);
            });
        });

        // Start stream button (streamer page)
        const startBtn = document.getElementById('startStreamBtn');
        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                const form = document.getElementById('startStreamForm');
                if (!form) return;

                const title = form.querySelector('[name="title"]')?.value || 'Live Stream';
                const category = form.querySelector('[name="category"]')?.value || 'general';

                // Submit form via AJAX to get streamId
                const formData = new FormData(form);
                try {
                    const response = await fetch(form.action, { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success && result.stream_id) {
                        startStream(result.stream_id, window.CURRENT_USER_ID, title, category);
                    } else {
                        alert(result.message || 'Failed to start stream.');
                    }
                } catch (err) {
                    console.error('Stream start error:', err);
                    alert('Failed to start stream. Please try again.');
                }
            });
        }

        // End stream button
        const endBtn = document.getElementById('endStreamBtn');
        if (endBtn) {
            endBtn.addEventListener('click', () => {
                if (window.STREAM_ID) {
                    endStream(window.STREAM_ID, window.CURRENT_USER_ID);
                    // Also POST to API
                    fetch(`${window.API_BASE}/live.php?action=end&id=${window.STREAM_ID}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${window.STREAM_ID}`
                    }).then(() => {
                        window.location.href = '/pages/live/index.php';
                    });
                }
            });
        }

        // Auto-join stream for viewers
        if (typeof window.IS_VIEWER !== 'undefined' && window.IS_VIEWER && window.IS_LIVE && window.STREAM_ID) {
            initSocket();
            joinStream(window.STREAM_ID, window.CURRENT_USER_ID, window.CURRENT_USERNAME);
        }
    });

    // ── Public API ───────────────────────────────────────────
    window.GlobexSkyLive = {
        initSocket,
        enableCamera,
        startStream,
        joinStream,
        endStream,
        sendChatMessage,
        sendReaction,
        toggleMic,
        toggleCamera,
        toggleScreenShare
    };
})();
