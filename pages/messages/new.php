<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];

// Pre-fill recipient from query string (used from contacts.php)
$preRecipientId = (int)get('to', 0);
$preRecipient   = null;
if ($preRecipientId > 0 && $preRecipientId !== $currentUserId) {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT id, CONCAT(first_name, " ", last_name) AS name, email
               FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$preRecipientId]);
        $preRecipient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $preRecipient = null;
    }
}

$pageTitle = 'New Message';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5" style="max-width:680px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/messages/inbox.php"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-pencil-square text-primary me-2"></i>New Message
        </h4>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="newMsgForm" novalidate>
                <?= csrfField() ?>

                <!-- Recipient search -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        To <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        <input type="text" id="recipientSearch" class="form-control"
                               placeholder="Search by name or email…"
                               autocomplete="off"
                               <?= $preRecipient ? 'style="display:none;"' : '' ?>>
                        <div id="recipientDropdown"
                             class="dropdown-menu w-100 shadow-sm"
                             style="display:none; max-height:220px; overflow-y:auto;
                                    position:absolute; z-index:1050; top:100%;"></div>
                    </div>
                    <input type="hidden" id="recipientId" name="recipient_id"
                           value="<?= $preRecipient ? (int)$preRecipient['id'] : '' ?>">
                    <div id="recipientChosen" class="mt-2">
                        <?php if ($preRecipient): ?>
                        <span class="badge bg-primary d-inline-flex align-items-center gap-1 px-2 py-2">
                            <span style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.25);
                                         display:flex;align-items:center;justify-content:center;font-size:.7rem;">
                                <?= htmlspecialchars(
                                    strtoupper(substr($preRecipient['name'] ?? $preRecipient['email'] ?? '?', 0, 1)),
                                    ENT_QUOTES, 'UTF-8'
                                ) ?>
                            </span>
                            <?= htmlspecialchars($preRecipient['name'] ?? $preRecipient['email'], ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close btn-close-white ms-1"
                                    style="font-size:.55rem;" id="clearRecipient"></button>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="invalid-feedback" id="recipientError">
                        Please select a recipient.
                    </div>
                </div>

                <!-- Subject -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Subject <span class="text-muted small">(optional)</span>
                    </label>
                    <input type="text" id="subjectInput" name="subject"
                           class="form-control"
                           placeholder="What is this about?"
                           maxlength="255">
                </div>

                <!-- Message body -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        Message <span class="text-danger">*</span>
                    </label>
                    <textarea id="bodyInput" name="body" class="form-control"
                              rows="6" placeholder="Write your message…"
                              required maxlength="5000"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-muted"><span id="charCount">0</span>/5000</small>
                    </div>
                    <div class="invalid-feedback" id="bodyError">
                        Please enter a message.
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= APP_URL ?>/pages/messages/inbox.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </a>
                    <button type="submit" id="sendBtn" class="btn btn-primary px-4">
                        <span id="sendBtnText">
                            <i class="bi bi-send-fill me-1"></i>Create &amp; Send
                        </span>
                        <span id="sendBtnSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Sending…
                        </span>
                    </button>
                </div>
            </form>

            <div id="formAlert" class="alert mt-3 d-none" role="alert"></div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const APP_URL = <?= json_encode(APP_URL) ?>;
    const CSRF    = <?= json_encode(csrfToken()) ?>;
    const SELF_ID = <?= $currentUserId ?>;

    let selectedRecipient = <?= $preRecipient
        ? json_encode(['id' => $preRecipient['id'], 'name' => $preRecipient['name'] ?? '', 'email' => $preRecipient['email'] ?? ''])
        : 'null' ?>;
    let searchTimer = null;

    const recipientSearch   = document.getElementById('recipientSearch');
    const recipientDropdown = document.getElementById('recipientDropdown');
    const recipientIdInput  = document.getElementById('recipientId');
    const recipientChosen   = document.getElementById('recipientChosen');
    const recipientError    = document.getElementById('recipientError');
    const bodyInput         = document.getElementById('bodyInput');
    const charCount         = document.getElementById('charCount');
    const newMsgForm        = document.getElementById('newMsgForm');
    const sendBtn           = document.getElementById('sendBtn');
    const sendBtnText       = document.getElementById('sendBtnText');
    const sendBtnSpinner    = document.getElementById('sendBtnSpinner');
    const formAlert         = document.getElementById('formAlert');

    // Character count
    if (bodyInput) {
        bodyInput.addEventListener('input', function () {
            if (charCount) charCount.textContent = this.value.length;
        });
    }

    // Recipient search (debounced)
    if (recipientSearch) {
        recipientSearch.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(searchTimer);
            if (q.length < 2) {
                if (recipientDropdown) recipientDropdown.style.display = 'none';
                return;
            }
            searchTimer = setTimeout(() => fetchUsers(q), 300);
        });

        recipientSearch.addEventListener('blur', function () {
            setTimeout(() => {
                if (recipientDropdown) recipientDropdown.style.display = 'none';
            }, 200);
        });

        recipientSearch.addEventListener('focus', function () {
            if (this.value.trim().length >= 2 &&
                recipientDropdown && recipientDropdown.children.length > 0) {
                recipientDropdown.style.display = 'block';
            }
        });
    }

    // Clear pre-filled recipient
    const clearBtn = document.getElementById('clearRecipient');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            selectedRecipient = null;
            if (recipientIdInput) recipientIdInput.value = '';
            if (recipientChosen) recipientChosen.innerHTML = '';
            if (recipientSearch) recipientSearch.style.display = '';
            if (recipientSearch) recipientSearch.focus();
        });
    }

    function fetchUsers(q) {
        fetch(APP_URL + '/api/chat.php?action=search_users&q=' + encodeURIComponent(q), {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => renderDropdown(data.data || data.users || []))
        .catch(() => renderDropdown([]));
    }

    function renderDropdown(users) {
        if (!recipientDropdown) return;
        recipientDropdown.innerHTML = '';
        if (users.length === 0) {
            recipientDropdown.innerHTML =
                '<div class="dropdown-item text-muted small">No users found.</div>';
            recipientDropdown.style.display = 'block';
            return;
        }
        users.forEach(u => {
            if (u.id === SELF_ID) return;
            const item = document.createElement('a');
            item.href = '#';
            item.className = 'dropdown-item d-flex align-items-center gap-2 py-2';
            const initial = (u.name || u.email || '?').charAt(0).toUpperCase();
            item.innerHTML =
                '<div style="width:32px;height:32px;border-radius:50%;background:#6c757d;' +
                'color:#fff;display:flex;align-items:center;justify-content:center;' +
                'font-weight:600;font-size:.85rem;flex-shrink:0;">' +
                escHtml(initial) + '</div>' +
                '<div>' +
                '<div class="fw-semibold small">' + escHtml(u.name || '') + '</div>' +
                '<div class="text-muted" style="font-size:.75rem;">' + escHtml(u.email || '') + '</div>' +
                '</div>';
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectRecipient(u);
            });
            recipientDropdown.appendChild(item);
        });
        recipientDropdown.style.display = 'block';
    }

    function selectRecipient(u) {
        selectedRecipient = u;
        if (recipientIdInput) recipientIdInput.value = u.id;
        if (recipientSearch)  recipientSearch.style.display = 'none';
        if (recipientDropdown) recipientDropdown.style.display = 'none';
        if (recipientError) recipientError.style.display = 'none';

        const initial = (u.name || u.email || '?').charAt(0).toUpperCase();
        if (recipientChosen) {
            recipientChosen.innerHTML =
                '<span class="badge bg-primary d-inline-flex align-items-center gap-1 px-2 py-2">' +
                '<span style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.25);' +
                'display:flex;align-items:center;justify-content:center;font-size:.7rem;">' +
                escHtml(initial) + '</span>' +
                escHtml(u.name || u.email) +
                '<button type="button" class="btn-close btn-close-white ms-1"' +
                ' style="font-size:.55rem;" id="clearRecipient2"></button>' +
                '</span>';

            const cb = document.getElementById('clearRecipient2');
            if (cb) {
                cb.addEventListener('click', function () {
                    selectedRecipient = null;
                    if (recipientIdInput) recipientIdInput.value = '';
                    if (recipientChosen) recipientChosen.innerHTML = '';
                    if (recipientSearch) recipientSearch.style.display = '';
                    if (recipientSearch) recipientSearch.focus();
                });
            }
        }
    }

    // Form submit
    if (newMsgForm) {
        newMsgForm.addEventListener('submit', function (e) {
            e.preventDefault();
            hideAlert();

            let valid = true;

            if (!recipientIdInput || !recipientIdInput.value) {
                if (recipientError) recipientError.style.display = 'block';
                valid = false;
            } else {
                if (recipientError) recipientError.style.display = 'none';
            }

            const body = bodyInput ? bodyInput.value.trim() : '';
            const bodyErr = document.getElementById('bodyError');
            if (!body) {
                if (bodyErr)   bodyErr.style.display = 'block';
                if (bodyInput) bodyInput.classList.add('is-invalid');
                valid = false;
            } else {
                if (bodyErr)   bodyErr.style.display = 'none';
                if (bodyInput) bodyInput.classList.remove('is-invalid');
            }

            if (!valid) return;

            setLoading(true);

            const subjectEl = document.getElementById('subjectInput');

            // POST as form-encoded (API accepts both form and JSON)
            const fd = new FormData();
            fd.append('_csrf_token',  CSRF);
            fd.append('recipient_id', recipientIdInput.value);
            fd.append('subject',      subjectEl ? subjectEl.value.trim() : '');
            fd.append('body',         body);
            fd.append('type',         'direct');
            fd.append('participants', JSON.stringify([parseInt(recipientIdInput.value, 10)]));

            fetch(APP_URL + '/api/chat.php?action=create_room', {
                method:  'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body:    fd
            })
            .then(r => r.json())
            .then(data => {
                const room = data.data || {};
                const roomId = room.id || data.room_id;
                if ((data.success || data.ok) && roomId) {
                    // Send first message
                    const fd2 = new FormData();
                    fd2.append('_csrf_token', CSRF);
                    fd2.append('room_id',     roomId);
                    fd2.append('message',     body);
                    return fetch(APP_URL + '/api/chat.php?action=send', {
                        method:  'POST',
                        headers: { 'X-CSRF-Token': CSRF },
                        body:    fd2
                    })
                    .then(() => {
                        window.location.href =
                            APP_URL + '/pages/messages/conversation.php?room_id=' + roomId;
                    });
                } else {
                    showAlert('danger', data.message || 'Failed to create conversation.');
                    setLoading(false);
                }
            })
            .catch(() => {
                showAlert('danger', 'Network error. Please check your connection and try again.');
                setLoading(false);
            });
        });
    }

    function setLoading(on) {
        if (sendBtn) sendBtn.disabled = on;
        if (sendBtnText)    sendBtnText.classList.toggle('d-none', on);
        if (sendBtnSpinner) sendBtnSpinner.classList.toggle('d-none', !on);
    }

    function showAlert(type, msg) {
        if (formAlert) {
            formAlert.className = 'alert alert-' + type + ' mt-3';
            formAlert.textContent = msg;
        }
    }

    function hideAlert() {
        if (formAlert) formAlert.className = 'alert mt-3 d-none';
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
