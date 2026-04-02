/**
 * GlobexSky — AI Chatbot Widget
 */
(function () {
  'use strict';

  const ENDPOINT = '/api/ai/chatbot.php';
  let isOpen = false;
  let history = JSON.parse(sessionStorage.getItem('gs_chat_history') || '[]');

  const trigger = document.getElementById('gsChatTrigger');
  const window_ = document.getElementById('gsChatWindow');
  const closeBtn = document.getElementById('gsChatClose');
  const messages = document.getElementById('gsChatMessages');
  const input    = document.getElementById('gsChatInput');
  const sendBtn  = document.getElementById('gsChatSend');
  const quickReplies = document.getElementById('gsQuickReplies');

  if (!trigger || !window_) return;

  const defaultReplies = ['Track my order', 'Find suppliers', 'Get a quote', 'Shipping rates'];

  function toggle() {
    isOpen = !isOpen;
    window_.classList.toggle('open', isOpen);
    trigger.querySelector('i').className = isOpen ? 'fa fa-times' : 'fa fa-comment-dots';
    if (isOpen && !messages.children.length) {
      appendMessage('bot', 'Hi! I\'m SkyBot 🤖 — your GlobexSky assistant. How can I help you today?');
      renderQuickReplies(defaultReplies);
    }
  }

  function appendMessage(role, text) {
    const div = document.createElement('div');
    div.className = `gs-chat-msg ${role}`;
    div.textContent = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  function showTyping() {
    const el = document.createElement('div');
    el.className = 'gs-chat-typing';
    el.id = 'gsTyping';
    el.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(el);
    messages.scrollTop = messages.scrollHeight;
  }

  function hideTyping() {
    const el = document.getElementById('gsTyping');
    if (el) el.remove();
  }

  function renderQuickReplies(replies) {
    if (!quickReplies) return;
    quickReplies.innerHTML = replies.map(r =>
      `<button class="gs-quick-reply-btn" type="button">${r}</button>`
    ).join('');
  }

  async function sendMessage(text) {
    if (!text.trim()) return;
    if (input) input.value = '';
    if (quickReplies) quickReplies.innerHTML = '';
    appendMessage('user', text);
    history.push({ role: 'user', content: text });
    showTyping();
    if (sendBtn) sendBtn.disabled = true;

    try {
      const res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ message: text, history: history.slice(-10) }),
      });
      const data = await res.json();
      hideTyping();
      const reply = data.reply || 'Sorry, I could not process that. Please try again.';
      appendMessage('bot', reply);
      history.push({ role: 'assistant', content: reply });
      sessionStorage.setItem('gs_chat_history', JSON.stringify(history.slice(-20)));
      if (data.quick_replies?.length) renderQuickReplies(data.quick_replies);
      playNotificationSound();
    } catch {
      hideTyping();
      appendMessage('bot', 'Connection error. Please try again later.');
    } finally {
      if (sendBtn) sendBtn.disabled = false;
      if (input) input.focus();
    }
  }

  function playNotificationSound() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.1, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
      osc.start(); osc.stop(ctx.currentTime + 0.3);
    } catch { /* audio not supported */ }
  }

  trigger.addEventListener('click', toggle);
  if (closeBtn) closeBtn.addEventListener('click', toggle);
  if (sendBtn) sendBtn.addEventListener('click', () => sendMessage(input?.value || ''));
  if (input) input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(input.value); } });
  document.addEventListener('click', e => {
    if (e.target.classList.contains('gs-quick-reply-btn')) sendMessage(e.target.textContent);
  });
})();
