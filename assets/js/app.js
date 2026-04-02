/* GlobexSky — app.js */

// Auto-dismiss flash alerts after 5 seconds
document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => { el.classList.remove('show'); }, 5000);
});

// Confirm delete/dangerous actions
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// AJAX add-to-cart feedback
document.querySelectorAll('form[data-ajax-cart]').forEach(form => {
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = form.querySelector('button[type=submit]');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) {
                btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 1500);
                // Update cart badge
                const badge = document.querySelector('.navbar .badge');
                if (badge) badge.textContent = parseInt(badge.textContent || 0) + 1;
            }
        } catch { btn.innerHTML = orig; btn.disabled = false; }
    });
});

// Number input min enforcement
document.querySelectorAll('input[type=number][min]').forEach(input => {
    input.addEventListener('blur', () => {
        const min = parseInt(input.min);
        if (!isNaN(min) && parseInt(input.value) < min) input.value = min;
    });
});

// Bootstrap tooltip init
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
