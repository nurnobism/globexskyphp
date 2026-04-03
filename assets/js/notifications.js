/**
 * GlobexSky Notification Client
 *
 * - Fetches unread count on page load
 * - Listens for new notifications via Socket.io (or polls via AJAX)
 * - Updates badge count in header
 * - Shows toast for new notifications
 * - Desktop notification support (with permission)
 * - AJAX fallback: poll every 30 seconds
 */
const GlobexNotifications = (function() {
    'use strict';

    let baseUrl     = '';
    let csrfToken   = '';
    let unreadCount = 0;
    let pollTimer   = null;
    const POLL_INTERVAL = 30000; // 30 seconds

    function init(config) {
        baseUrl   = config.baseUrl || '';
        csrfToken = config.csrfToken || '';

        fetchUnreadCount();

        // Try Socket.io first
        if (typeof GlobexSocket !== 'undefined' && GlobexSocket.isConnected && !GlobexSocket.isFallback()) {
            GlobexSocket.on('notification', handleNewNotification);
            GlobexSocket.on('unread_count', function(data) {
                updateBadge(data.count || 0);
            });
        } else {
            // AJAX polling fallback
            startPolling();
        }

        // Setup dropdown
        setupDropdown();

        // Request desktop notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            // Delay permission request until user interaction
            document.addEventListener('click', function requestPerm() {
                Notification.requestPermission();
                document.removeEventListener('click', requestPerm);
            }, { once: true });
        }
    }

    function fetchUnreadCount() {
        fetch(baseUrl + '/api/notifications.php?action=count', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) updateBadge(data.count || 0);
            })
            .catch(function() {});
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(fetchUnreadCount, POLL_INTERVAL);
    }

    function updateBadge(count) {
        unreadCount = count;
        var badges = document.querySelectorAll('.notification-badge');
        badges.forEach(function(badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    function handleNewNotification(data) {
        unreadCount++;
        updateBadge(unreadCount);
        showToast(data);
        showDesktopNotification(data);
        GlobexNotificationSounds.play('notification');
    }

    function showToast(data) {
        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        var toastId = 'toast-' + Date.now();
        var html = '<div id="' + toastId + '" class="toast show" role="alert">' +
            '<div class="toast-header">' +
            '<i class="bi bi-bell-fill text-primary me-2"></i>' +
            '<strong class="me-auto">' + escapeHtml(data.title || 'Notification') + '</strong>' +
            '<small>Just now</small>' +
            '<button type="button" class="btn-close" data-bs-dismiss="toast"></button>' +
            '</div>' +
            '<div class="toast-body">' + escapeHtml(data.message || '') + '</div>' +
            '</div>';
        container.insertAdjacentHTML('beforeend', html);

        setTimeout(function() {
            var el = document.getElementById(toastId);
            if (el) el.remove();
        }, 8000);
    }

    function showDesktopNotification(data) {
        if ('Notification' in window && Notification.permission === 'granted') {
            var n = new Notification(data.title || 'GlobexSky', {
                body: data.message || '',
                icon: baseUrl + '/assets/images/logo-icon.png',
                tag: 'globexsky-notification'
            });
            n.onclick = function() {
                window.focus();
                if (data.action_url) window.location.href = data.action_url;
                n.close();
            };
            setTimeout(function() { n.close(); }, 10000);
        }
    }

    function setupDropdown() {
        var dropdownEl = document.getElementById('notificationDropdown');
        if (!dropdownEl) return;

        dropdownEl.addEventListener('show.bs.dropdown', function() {
            loadLatestNotifications();
        });
    }

    function loadLatestNotifications() {
        var list = document.getElementById('notificationList');
        if (!list) return;
        list.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';

        fetch(baseUrl + '/api/notifications.php?action=list', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data || data.data.length === 0) {
                    list.innerHTML = '<div class="text-center py-3 text-muted"><i class="bi bi-bell-slash"></i> No notifications</div>';
                    return;
                }
                var html = '';
                data.data.slice(0, 5).forEach(function(n) {
                    var isUnread = !n.is_read && !n.read_at;
                    html += '<a href="' + escapeHtml(n.action_url || baseUrl + '/pages/notifications/index.php') + '" ' +
                        'class="dropdown-item py-2 ' + (isUnread ? 'bg-light' : '') + '">' +
                        '<div class="d-flex align-items-start">' +
                        '<i class="bi ' + escapeHtml(n.icon || 'bi-bell') + ' me-2 mt-1 text-primary"></i>' +
                        '<div class="small"><div class="fw-semibold">' + escapeHtml(n.title) + '</div>' +
                        '<div class="text-muted">' + escapeHtml((n.message || '').substring(0, 60)) + '</div></div>' +
                        '</div></a>';
                });
                list.innerHTML = html;
            })
            .catch(function() { list.innerHTML = '<div class="text-center py-3 text-muted">Error loading</div>'; });
    }

    function markAllRead() {
        var fd = new FormData();
        fd.append('_csrf_token', csrfToken);
        fetch(baseUrl + '/api/notifications.php?action=mark_all_read', {
            method: 'POST', body: fd, credentials: 'same-origin'
        }).then(function() {
            updateBadge(0);
            loadLatestNotifications();
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    return {
        init: init,
        fetchUnreadCount: fetchUnreadCount,
        markAllRead: markAllRead,
        updateBadge: updateBadge,
        getUnreadCount: function() { return unreadCount; }
    };
})();
