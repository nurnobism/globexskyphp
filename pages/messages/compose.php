<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];

$pageTitle = 'New Message';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5" style="max-width:680px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/messages/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>New Message</h4>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="composeForm" novalidate>
                <?= csrfField() ?>

                <!-- Recipient search -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="text" id="recipientSearch" class="form-control"
                               placeholder="Search by name or email…" autocomplete="off" required>
                        <div id="recipientDropdown"
                             class="dropdown-menu w-100 shadow-sm"
                             style="display:none; max-height:220px; overflow-y:auto; position:absolute; z-index:1050; top:100%;"></div>
                    </div>
                    <input type="hidden" id="recipientId" name="recipient_id">
                    <div id="recipientChosen" class="mt-2"></div>
                    <div class="invalid-feedback" id="recipientError">Please select a recipient.</div>
                </div>

                <!-- Subject (optional) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject <span class="text-muted small">(optional)</span></label>
                    <input type="text" id="subjectInput" name="subject" class="form-control"
                           placeholder="What is this about?" maxlength="255">
                </div>

                <!-- Message body -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                    <textarea id="bodyInput" name="body" class="form-control" rows="6"
                              placeholder="Write your message…" required maxlength="5000"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-muted"><span id="charCount">0</span>/5000</small>
                    </div>
                    <div class="invalid-feedback" id="bodyError">Please enter a message.</div>
                </div>

                <!-- Actions -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= APP_URL ?>/pages/messages/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </a>
                    <button type="submit" id="sendBtn" class="btn btn-primary px-4">
                        <span id="sendBtnText"><i class="bi bi-send-fill me-1"></i>Send Message</span>
                        <span id="sendBtnSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Sending…
                        </span>
                    </button>
                </div>
            </form>

            <!-- Error/success feedback -->
            <div id="formAlert" class="alert mt-3 d-none" role="alert"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const APP_URL  = <?= json_encode(APP_URL) ?>;
    const CSRF     = <?= json_encode(csrfToken()) ?>;
    const SELF_ID  = <?= $currentUserId ?>;

    let selectedRecipient = null;
    let searchTimer = null;

    const recipientSearch   = document.getElementById('recipientSearch');
    const recipientDropdown = document.getElementById('recipientDropdown');
    const recipientId       = document.getElementById('recipientId');
    const recipientChosen   = document.getElementById('recipientChosen');
    const recipientError    = document.getElementById('recipientError');
    const bodyInput         = document.getElementById('bodyInput');
    const charCount         = document.getElementById('charCount');
    const composeForm       = document.getElementById('composeForm');
    const sendBtn           = document.getElementById('sendBtn');
    const sendBtnText       = document.getElementById('sendBtnText');
    const sendBtnSpinner    = document.getElementById('sendBtnSpinner');
    const formAlert         = document.getElementById('formAlert');

    // Character count
    bodyInput.addEventListener('input', function () {
        charCount.textContent = this.value.length;
    });

    // Recipient search with debounce
    recipientSearch.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 2) {
            recipientDropdown.style.display = 'none';
            return;
        }
        searchTimer = setTimeout(() => fetchUsers(q), 300);
    });

    recipientSearch.addEventListener('blur', function () {
        setTimeout(() => { recipientDropdown.style.display = 'none'; }, 200);
    });

    recipientSearch.addEventListener('focus', function () {
        if (this.value.trim().length >= 2 && recipientDropdown.children.length > 0) {
            recipientDropdown.style.display = 'block';
        }
    });

    function fetchUsers(q) {
        fetch(APP_URL + '/api/chat.php?action=search_users&q=' + encodeURIComponent(q), {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => renderDropdown(data.users || []))
        .catch(() => renderDropdown([]));
    }

    function renderDropdown(users) {
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
            item.innerHTML = `
                <div style="width:32px;height:32px;border-radius:50%;background:#6c757d;
                            color:#fff;display:flex;align-items:center;justify-content:center;
                            font-weight:600;font-size:.85rem;flex-shrink:0;">
                    ${initial}
                </div>
                <div>
                    <div class="fw-semibold small">${escHtml(u.name || '')}</div>
                    <div class="text-muted" style="font-size:.75rem;">${escHtml(u.email || '')}</div>
                </div>`;
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
        recipientId.value = u.id;
        recipientSearch.value = '';
        recipientDropdown.style.display = 'none';
        recipientError.style.display = 'none';

        const initial = (u.name || u.email || '?').charAt(0).toUpperCase();
        recipientChosen.innerHTML = `
            <span class="badge bg-primary d-inline-flex align-items-center gap-1 px-2 py-2">
                <span style="width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.25);
                             display:flex;align-items:center;justify-content:center;font-size:.7rem;">
                    ${initial}
                </span>
                ${escHtml(u.name || u.email)}
                <button type="button" class="btn-close btn-close-white ms-1"
                        style="font-size:.55rem;" id="clearRecipient"></button>
            </span>`;

        document.getElementById('clearRecipient').addEventListener('click', function () {
            selectedRecipient = null;
            recipientId.value = '';
            recipientChosen.innerHTML = '';
            recipientSearch.focus();
        });
    }

    // Form submit
    composeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();

        let valid = true;

        if (!recipientId.value) {
            recipientError.style.display = 'block';
            valid = false;
        } else {
            recipientError.style.display = 'none';
        }

        const body = bodyInput.value.trim();
        const bodyErr = document.getElementById('bodyError');
        if (!body) {
            bodyErr.style.display = 'block';
            bodyInput.classList.add('is-invalid');
            valid = false;
        } else {
            bodyErr.style.display = 'none';
            bodyInput.classList.remove('is-invalid');
        }

        if (!valid) return;

        setLoading(true);

        const payload = {
            _csrf_token:  CSRF,
            recipient_id: recipientId.value,
            subject:      document.getElementById('subjectInput').value.trim(),
            body:         body
        };

        fetch(APP_URL + '/api/chat.php?action=create_room', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body:    JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.room_id) {
                window.location.href =
                    APP_URL + '/pages/messages/conversation.php?room_id=' + data.room_id;
            } else {
                showAlert('danger', data.message || 'Failed to send message. Please try again.');
                setLoading(false);
            }
        })
        .catch(() => {
            showAlert('danger', 'Network error. Please check your connection and try again.');
            setLoading(false);
        });
    });

    function setLoading(on) {
        sendBtn.disabled = on;
        sendBtnText.classList.toggle('d-none', on);
        sendBtnSpinner.classList.toggle('d-none', !on);
    }

    function showAlert(type, msg) {
        formAlert.className = 'alert alert-' + type + ' mt-3';
        formAlert.textContent = msg;
    }

    function hideAlert() {
        formAlert.className = 'alert mt-3 d-none';
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
