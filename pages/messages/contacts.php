<?php
require_once __DIR__ . '/../../includes/middleware.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];

$pageTitle = 'Contacts';
include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chat.css">

<div class="container py-4" style="max-width:820px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= APP_URL ?>/pages/messages/inbox.php"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-people text-primary me-2"></i>Contacts
        </h4>
        <div class="ms-auto">
            <a href="<?= APP_URL ?>/pages/messages/new.php" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil-square me-1"></i>New Message
            </a>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-3">
        <input type="text" id="contactSearch" class="form-control"
               placeholder="Search by name or email…" autocomplete="off">
    </div>

    <!-- Contact categories -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="btn btn-primary btn-sm contact-tab active" data-group="all">All</button>
        <button class="btn btn-outline-secondary btn-sm contact-tab" data-group="recent">Recent</button>
        <button class="btn btn-outline-secondary btn-sm contact-tab" data-group="suppliers">Suppliers</button>
        <button class="btn btn-outline-secondary btn-sm contact-tab" data-group="buyers">Buyers</button>
        <button class="btn btn-outline-secondary btn-sm contact-tab" data-group="support">Support</button>
    </div>

    <!-- Contact list -->
    <div class="card border-0 shadow-sm">
        <div id="contactList" class="list-group list-group-flush rounded">
            <div class="text-center py-5 text-muted" id="contactLoading">
                <div class="spinner-border spinner-border-sm"></div>
                <p class="small mt-2">Loading contacts…</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const APP_URL  = <?= json_encode(APP_URL) ?>;
    const CSRF     = <?= json_encode(csrfToken()) ?>;
    const SELF_ID  = <?= $currentUserId ?>;

    let allContacts  = [];
    let activeGroup  = 'all';
    let searchTimer  = null;

    const contactList   = document.getElementById('contactList');
    const contactSearch = document.getElementById('contactSearch');
    const contactLoading = document.getElementById('contactLoading');

    // ── Load contacts ──────────────────────────────────────────
    function loadContacts() {
        fetch(APP_URL + '/api/chat.php?action=contacts', {
            headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => {
            allContacts = data.data || [];
            render();
        })
        .catch(() => {
            contactList.innerHTML =
                '<div class="text-center py-4 text-danger small">' +
                '<i class="bi bi-exclamation-circle me-1"></i>Failed to load contacts.</div>';
        });
    }

    // ── Render filtered list ───────────────────────────────────
    function render() {
        if (contactLoading) contactLoading.remove();

        let items = allContacts.filter(c => c.id !== SELF_ID);

        if (activeGroup !== 'all') {
            items = items.filter(c => (c.group || 'all') === activeGroup);
        }

        const q = (contactSearch ? contactSearch.value.trim().toLowerCase() : '');
        if (q) {
            items = items.filter(c =>
                (c.name || '').toLowerCase().includes(q) ||
                (c.email || '').toLowerCase().includes(q)
            );
        }

        if (items.length === 0) {
            contactList.innerHTML =
                '<div class="text-center py-5 text-muted">' +
                '<i class="bi bi-person-x display-4 opacity-25"></i>' +
                '<p class="small mt-2">No contacts found.</p></div>';
            return;
        }

        contactList.innerHTML = '';
        items.forEach(c => contactList.appendChild(buildContactItem(c)));
    }

    function buildContactItem(c) {
        const initial = (c.name || c.email || '?').charAt(0).toUpperCase();
        const isOnline = !!c.is_online;

        const item = document.createElement('div');
        item.className = 'contact-item list-group-item list-group-item-action';

        item.innerHTML =
            '<div class="chat-avatar flex-shrink-0" style="width:44px;height:44px;">' +
              escHtml(initial) +
              '<span class="online-dot ' + (isOnline ? 'pulse' : 'offline') + '"></span>' +
            '</div>' +
            '<div class="flex-grow-1 overflow-hidden">' +
              '<div class="fw-semibold text-truncate">' + escHtml(c.name || c.email || '—') + '</div>' +
              '<div class="text-muted small text-truncate">' +
                (c.role ? '<span class="badge bg-light text-secondary me-1">' + escHtml(c.role) + '</span>' : '') +
                (isOnline ? '<span class="text-success">Online</span>'
                          : '<span>Last seen ' + escHtml(c.last_seen || 'a while ago') + '</span>') +
              '</div>' +
            '</div>' +
            '<a href="' + APP_URL + '/pages/messages/new.php?to=' + encodeURIComponent(c.id) + '"' +
               ' class="btn btn-sm btn-outline-primary flex-shrink-0">' +
              '<i class="bi bi-chat-dots me-1"></i>Message' +
            '</a>';

        return item;
    }

    // ── Events ─────────────────────────────────────────────────
    document.querySelectorAll('.contact-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.contact-tab').forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary', 'active');
            activeGroup = this.dataset.group || 'all';
            render();
        });
    });

    if (contactSearch) {
        contactSearch.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(render, 250);
        });
    }

    // ── Utility ────────────────────────────────────────────────
    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    loadContacts();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
