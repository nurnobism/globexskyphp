/**
 * assets/js/ai-content.js — AI Content Generator UI (Phase 8)
 */
(function() {
    'use strict';

    let currentType = 'product_description';
    let lastGenerationId = null;

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    // Tab switching
    document.querySelectorAll('#content-tabs .nav-link').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#content-tabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentType = this.dataset.type;
            updatePlaceholder();
        });
    });

    function updatePlaceholder() {
        const input = document.getElementById('content-input');
        if (!input) return;
        const placeholders = {
            product_description: 'Enter product name, features, specifications...',
            seo_title: 'Enter product name and key features for SEO title...',
            ad_copy: 'Enter product details for ad copy generation...',
            translation: 'Enter text to translate...',
            improve: 'Enter text you want to improve...',
        };
        input.placeholder = placeholders[currentType] || 'Enter text...';
    }

    // Generate button
    const generateBtn = document.getElementById('generate-btn');
    if (generateBtn) {
        generateBtn.addEventListener('click', generateContent);
    }

    async function generateContent() {
        const input     = document.getElementById('content-input');
        const output    = document.getElementById('content-output');
        const copyBtn   = document.getElementById('copy-btn');
        const actionsEl = document.getElementById('content-actions');
        const style     = document.getElementById('style-select')?.value || 'professional';
        const language  = document.getElementById('language-select')?.value || 'en';
        const text      = input?.value.trim();

        if (!text) { alert('Please enter some text first.'); return; }

        generateBtn.disabled = true;
        generateBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Generating...';
        output.innerHTML = '<span class="text-muted"><i class="spinner-border spinner-border-sm me-2"></i>AI is generating content...</span>';

        const actionMap = {
            product_description: 'generate_description',
            seo_title: 'generate_seo',
            ad_copy: 'generate_ad_copy',
            translation: 'translate',
            improve: 'improve',
        };

        const payloadMap = {
            product_description: { action: 'generate_description', product_data: { name: text }, style, language },
            seo_title:           { action: 'generate_seo', product_data: { name: text } },
            ad_copy:             { action: 'generate_ad_copy', product_data: { name: text }, platform: 'google' },
            translation:         { action: 'translate', text, target_language: language },
            improve:             { action: 'improve', text, instructions: 'Make it more professional, clear, and compelling.' },
        };

        try {
            const res  = await fetch('/api/ai/content.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payloadMap[currentType] || { action: 'improve', text }),
            });
            const data = await res.json();

            if (data.success) {
                const content = data.data?.generated_text || data.data?.improved_text || data.data?.translated_text
                              || data.data?.seo_title || JSON.stringify(data.data, null, 2);
                output.innerHTML = content.replace(/\n/g, '<br>');
                if (copyBtn) copyBtn.classList.remove('d-none');
                if (actionsEl) actionsEl.classList.remove('d-none');
                typewriterEffect(output, content);
                loadHistory();
            } else {
                output.innerHTML = '<span class="text-danger">Error: ' + escapeHtml(data.error || 'Generation failed') + '</span>';
            }
        } catch (err) {
            output.innerHTML = '<span class="text-danger">Connection error. Please try again.</span>';
        } finally {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="bi bi-stars me-2"></i>Generate';
        }
    }

    function typewriterEffect(el, text) {
        el.innerHTML = '';
        let i = 0;
        function typeChar() {
            if (i < text.length) {
                el.innerHTML = escapeHtml(text.substring(0, i + 1)).replace(/\n/g, '<br>');
                i++;
                setTimeout(typeChar, 10);
            }
        }
        typeChar();
    }

    // Copy button
    const copyBtn = document.getElementById('copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const output = document.getElementById('content-output');
            navigator.clipboard.writeText(output.innerText).then(() => {
                copyBtn.textContent = '✓ Copied!';
                setTimeout(() => { copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
            });
        });
    }

    // Load generation history
    function loadHistory() {
        const list = document.getElementById('history-list');
        if (!list) return;
        // Use the content API isn't available, show placeholder
        list.innerHTML = '<div class="text-muted text-center py-3 small">Recent generations appear here</div>';
    }

    updatePlaceholder();
    loadHistory();
})();
