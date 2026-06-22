@php
  $chatbotEnabled = app(\App\Services\HybridChatbotService::class)->isAvailable();
  $chatbotEndpoint = route('ai.chat');
  $chatbotSuggestions = config('chatbot.suggestions', []);
  $chatbotLogo = asset('images/docutrust-logo.png');
  $chatbotLogoLight = file_exists(public_path('images/docutrust-logo-light.png'))
      ? asset('images/docutrust-logo-light.png')
      : $chatbotLogo;
@endphp

<div
  id="docutrust-chatbot"
  class="dt-chatbot"
  data-enabled="{{ $chatbotEnabled ? '1' : '0' }}"
  data-endpoint="{{ $chatbotEndpoint }}"
  data-logo="{{ $chatbotLogo }}"
  data-logo-light="{{ $chatbotLogoLight }}"
>
  <button type="button" class="dt-chatbot-toggle" id="docutrustChatbotToggle" data-chatbot-trigger aria-expanded="false" aria-controls="docutrustChatbotPanel" aria-label="{{ __('Open DocuTrust AI assistant') }}">
    <img src="{{ $chatbotLogoLight }}" alt="" class="dt-chatbot-toggle-logo" width="28" height="28" decoding="async">
    <span class="dt-chatbot-toggle-label">{{ __('Ask AI') }}</span>
  </button>

  <div class="dt-chatbot-panel" id="docutrustChatbotPanel" role="dialog" aria-label="{{ __('DocuTrust AI assistant') }}" hidden>
    <div class="dt-chatbot-header">
      <div class="dt-chatbot-header-brand">
        <img src="{{ $chatbotLogoLight }}" alt="" class="dt-chatbot-header-logo" width="36" height="36" decoding="async">
        <div>
          <div class="dt-chatbot-title">{{ __('DocuTrust Assistant') }}</div>
          <div class="dt-chatbot-subtitle">{{ __('Powered by AI · Product & compliance questions') }}</div>
        </div>
      </div>
      <button type="button" class="dt-chatbot-close" id="docutrustChatbotClose" aria-label="{{ __('Close assistant') }}">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="dt-chatbot-body">
      <div class="dt-chatbot-messages" id="docutrustChatbotMessages" aria-live="polite">
        <div class="dt-chatbot-msg-row dt-chatbot-msg-row-assistant">
          <img src="{{ $chatbotLogoLight }}" alt="" class="dt-chatbot-avatar" width="32" height="32" decoding="async">
          <div class="dt-chatbot-msg dt-chatbot-msg-assistant">
            <p>{{ __('Hi! I can answer questions about DocuTrust features, security, CSC membership, trials, and how digital signing works. How can I help?') }}</p>
          </div>
        </div>
      </div>

      @if ($chatbotEnabled && $chatbotSuggestions !== [])
        <div class="dt-chatbot-suggestions" id="docutrustChatbotSuggestions">
          <p class="dt-chatbot-suggestions-label">{{ __('Quick questions') }}</p>
          <div class="dt-chatbot-chips">
            @foreach ($chatbotSuggestions as $suggestion)
              <button type="button" class="dt-chatbot-chip" data-prompt="{{ $suggestion }}">{{ $suggestion }}</button>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <form class="dt-chatbot-composer" id="docutrustChatbotForm" @if (! $chatbotEnabled) hidden @endif>
      <label class="sr-only" for="docutrustChatbotInput">{{ __('Your question') }}</label>
      <div class="dt-chatbot-composer-inner">
        <textarea
          id="docutrustChatbotInput"
          name="message"
          rows="1"
          maxlength="2000"
          placeholder="{{ __('Ask about features, security, pricing…') }}"
          required
        ></textarea>
        <button type="submit" class="dt-chatbot-send" id="docutrustChatbotSend" aria-label="{{ __('Send message') }}">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.dt-chatbot{
  --dt-chat-teal:#2EC4B6;
  --dt-chat-teal-dark:#1a9e92;
  --dt-chat-bg:#ffffff;
  --dt-chat-surface:#f4faf9;
  --dt-chat-text:#0f172a;
  --dt-chat-muted:#64748b;
  --dt-chat-border:rgba(13,148,136,0.16);
  position:fixed;
  right:max(16px,env(safe-area-inset-right));
  bottom:max(16px,env(safe-area-inset-bottom));
  z-index:250;
  font-family:'Source Sans 3',system-ui,sans-serif;
}
html.dark-scheme .dt-chatbot{
  --dt-chat-bg:#0d1a1f;
  --dt-chat-surface:#152428;
  --dt-chat-text:#e8f4f2;
  --dt-chat-muted:#7a9e9b;
  --dt-chat-border:rgba(46,196,182,0.18);
}
.dt-chatbot-toggle{
  position:relative;
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:12px 20px 12px 12px;
  border-radius:999px;
  border:1px solid rgba(46,196,182,0.35);
  background:linear-gradient(135deg,var(--dt-chat-teal),#2d7a35);
  color:#fff;
  font-weight:600;
  font-size:.95rem;
  cursor:pointer;
  box-shadow:0 10px 32px rgba(46,196,182,0.35);
  transition:transform .2s ease,box-shadow .2s ease;
}
.dt-chatbot-toggle:hover{transform:translateY(-2px);box-shadow:0 14px 40px rgba(46,196,182,0.45)}
.dt-chatbot-toggle::after{
  content:'Ask me anything about DocuTrust →';
  position:absolute;
  right:0;
  bottom:calc(100% + 10px);
  width:max-content;
  max-width:260px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--dt-chat-border);
  background:var(--dt-chat-bg);
  color:var(--dt-chat-text);
  box-shadow:0 10px 24px rgba(0,0,0,0.16);
  font-size:.78rem;
  line-height:1.2;
  opacity:0;
  pointer-events:none;
  transform:translateY(4px);
  transition:opacity .18s ease,transform .18s ease;
}
.dt-chatbot-toggle:hover::after,
.dt-chatbot-toggle:focus-visible::after{
  opacity:1;
  transform:translateY(0);
}
@keyframes once-pulse {
  0% { transform:scale(1); box-shadow:0 0 0 0 rgba(16,185,129,0.4); }
  50% { transform:scale(1.08); box-shadow:0 0 0 10px rgba(16,185,129,0); }
  100% { transform:scale(1); box-shadow:0 0 0 0 rgba(16,185,129,0); }
}
.chat-pulse{animation:once-pulse .6s ease-out}
.dt-chatbot-toggle-logo{width:30px;height:30px;border-radius:8px;object-fit:contain;flex-shrink:0}
.dt-chatbot-toggle-label{display:none}
@media(min-width:480px){.dt-chatbot-toggle-label{display:inline}}
.dt-chatbot-panel{
  position:absolute;
  right:0;
  bottom:calc(100% + 14px);
  width:min(100vw - 24px,420px);
  max-height:min(82vh,620px);
  display:flex;
  flex-direction:column;
  background:var(--dt-chat-bg);
  border:1px solid var(--dt-chat-border);
  border-radius:22px;
  box-shadow:0 28px 72px rgba(0,0,0,0.28),0 8px 24px rgba(46,196,182,0.08);
  overflow:hidden;
}
.dt-chatbot-panel[hidden]{display:none}
.dt-chatbot-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  padding:20px 20px 18px;
  border-bottom:1px solid var(--dt-chat-border);
  background:linear-gradient(180deg,var(--dt-chat-surface) 0%,var(--dt-chat-bg) 100%);
  flex-shrink:0;
}
.dt-chatbot-header-brand{display:flex;align-items:center;gap:12px;min-width:0}
.dt-chatbot-header-logo{width:40px;height:40px;border-radius:12px;object-fit:contain;flex-shrink:0}
.dt-chatbot-title{
  font-family:'Outfit',system-ui,sans-serif;
  font-weight:700;
  font-size:1.05rem;
  color:var(--dt-chat-text);
  line-height:1.25;
}
.dt-chatbot-subtitle{font-size:.75rem;color:var(--dt-chat-muted);margin-top:3px;line-height:1.35}
.dt-chatbot-close{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:38px;
  height:38px;
  flex-shrink:0;
  border:none;
  border-radius:12px;
  background:transparent;
  color:var(--dt-chat-muted);
  cursor:pointer;
  transition:background .15s ease,color .15s ease;
}
.dt-chatbot-close:hover{background:rgba(46,196,182,0.12);color:var(--dt-chat-teal)}
.dt-chatbot-body{
  flex:1;
  min-height:0;
  display:flex;
  flex-direction:column;
  overflow-y:auto;
  overflow-x:hidden;
  overscroll-behavior:contain;
  scrollbar-width:none;
  -ms-overflow-style:none;
}
.dt-chatbot-body::-webkit-scrollbar{display:none;width:0;height:0}
.dt-chatbot-messages{
  flex:0 0 auto;
  overflow:visible;
  padding:20px 20px 8px;
  display:flex;
  flex-direction:column;
  gap:14px;
}
.dt-chatbot-msg-row{
  display:flex;
  align-items:flex-end;
  gap:10px;
  width:100%;
  max-width:100%;
}
.dt-chatbot-msg-row-assistant{justify-content:flex-start}
.dt-chatbot-msg-row-user{justify-content:flex-end}
.dt-chatbot-avatar{width:32px;height:32px;border-radius:10px;object-fit:contain;flex-shrink:0}
.dt-chatbot-msg{
  width:fit-content;
  max-width:92%;
  padding:14px 16px;
  border-radius:16px 16px 16px 4px;
  font-size:.9rem;
  line-height:1.55;
  word-wrap:break-word;
  overflow-wrap:break-word;
}
.dt-chatbot-msg-row-assistant .dt-chatbot-msg{max-width:calc(100% - 42px)}
.dt-chatbot-msg-row-user .dt-chatbot-msg{
  max-width:88%;
  border-radius:16px 16px 4px 16px;
}
.dt-chatbot-msg p{margin:0;white-space:pre-wrap}
.dt-chatbot-msg-user{
  background:rgba(46,196,182,0.18);
  border:1px solid rgba(46,196,182,0.28);
  color:var(--dt-chat-text);
}
.dt-chatbot-msg-assistant{
  background:var(--dt-chat-surface);
  border:1px solid var(--dt-chat-border);
  color:var(--dt-chat-text);
}
.dt-chatbot-msg-row-error{width:100%}
.dt-chatbot-msg-row-error .dt-chatbot-msg{max-width:100%;border-radius:14px}
.dt-chatbot-msg-error{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.28);color:#b91c1c}
@media (prefers-color-scheme:dark){.dt-chatbot-msg-error{color:#fca5a5}}
.dt-chatbot-suggestions{
  flex:0 0 auto;
  padding:12px 20px 20px;
}
.dt-chatbot-suggestions.is-hidden{display:none}
.dt-chatbot-suggestions-label{
  margin:0 0 10px;
  font-size:.7rem;
  font-weight:600;
  letter-spacing:.06em;
  text-transform:uppercase;
  color:var(--dt-chat-muted);
}
.dt-chatbot-chips{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  align-items:flex-start;
}
.dt-chatbot-chip{
  display:inline-flex;
  align-items:center;
  max-width:100%;
  padding:9px 15px;
  border-radius:999px;
  border:1px solid rgba(46,196,182,0.22);
  background:rgba(46,196,182,0.06);
  color:var(--dt-chat-teal);
  font-size:.8rem;
  font-weight:500;
  line-height:1.35;
  text-align:left;
  cursor:pointer;
  transition:background .15s ease,border-color .15s ease,transform .1s ease,box-shadow .15s ease;
}
.dt-chatbot-chip:hover{
  border-color:rgba(46,196,182,0.5);
  background:rgba(46,196,182,0.14);
  box-shadow:0 2px 8px rgba(46,196,182,0.12);
  transform:translateY(-1px);
}
.dt-chatbot-composer{
  flex-shrink:0;
  padding:0 20px 20px;
  background:var(--dt-chat-bg);
}
.dt-chatbot-composer-inner{
  display:flex;
  align-items:center;
  gap:8px;
  padding:4px 4px 4px 16px;
  border-radius:999px;
  border:1px solid var(--dt-chat-border);
  background:var(--dt-chat-surface);
  box-shadow:0 2px 12px rgba(0,0,0,0.06);
  transition:border-color .2s ease,box-shadow .2s ease;
}
.dt-chatbot-composer-inner:focus-within{
  border-color:rgba(46,196,182,0.5);
  box-shadow:0 0 0 3px rgba(46,196,182,0.14),0 4px 16px rgba(46,196,182,0.08);
}
.dt-chatbot-composer textarea{
  flex:1;
  min-width:0;
  height:44px;
  min-height:44px;
  max-height:120px;
  resize:none;
  overflow:hidden;
  overflow-y:hidden;
  scrollbar-width:none;
  -ms-overflow-style:none;
  border:none;
  background:transparent;
  padding:11px 0;
  margin:0;
  font:inherit;
  font-size:.9rem;
  line-height:1.4;
  color:var(--dt-chat-text);
}
.dt-chatbot-composer textarea::-webkit-scrollbar,
.dt-chatbot-composer textarea::-webkit-resizer{
  display:none;
  width:0;
  height:0;
}
.dt-chatbot-composer textarea:focus{outline:none}
.dt-chatbot-composer textarea::placeholder{color:var(--dt-chat-muted);opacity:.85}
.dt-chatbot-send{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  width:44px;
  height:44px;
  padding:0;
  border:none;
  border-radius:50%;
  background:linear-gradient(145deg,var(--dt-chat-teal) 0%,#238f6e 100%);
  color:#fff;
  cursor:pointer;
  box-shadow:0 4px 14px rgba(46,196,182,0.35);
  transition:opacity .15s ease,transform .15s ease,box-shadow .15s ease;
}
.dt-chatbot-send:hover:not(:disabled){transform:scale(1.04)}
.dt-chatbot-send:disabled{opacity:.5;cursor:not-allowed}
.dt-chatbot-send.is-loading{pointer-events:none;opacity:.7}
.dt-chatbot-source{
  display:block;
  margin-top:8px;
  font-size:.65rem;
  font-weight:600;
  letter-spacing:.05em;
  text-transform:uppercase;
  color:var(--dt-chat-teal-dark);
  opacity:.9;
}
@media (prefers-color-scheme:dark){.dt-chatbot-source{color:var(--dt-chat-teal)}}
.dt-chatbot-typing{display:inline-flex;gap:5px;padding:2px 0}
.dt-chatbot-typing span{
  width:7px;
  height:7px;
  border-radius:50%;
  background:var(--dt-chat-teal);
  animation:dtChatTyping 1.2s ease-in-out infinite;
}
.dt-chatbot-typing span:nth-child(2){animation-delay:.15s}
.dt-chatbot-typing span:nth-child(3){animation-delay:.3s}
@keyframes dtChatTyping{0%,60%,100%{opacity:.35;transform:translateY(0)}30%{opacity:1;transform:translateY(-4px)}}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:420px){
  .dt-chatbot-panel{width:calc(100vw - 20px);max-height:min(86vh,580px)}
  .dt-chatbot-header{padding:16px 16px 14px}
  .dt-chatbot-messages{padding:16px 16px 6px}
  .dt-chatbot-suggestions{padding:10px 16px 16px}
  .dt-chatbot-composer{padding:0 16px 16px}
}
</style>

<script>
(function () {
  var root = document.getElementById('docutrust-chatbot');
  if (!root) return;

  var enabled = root.dataset.enabled === '1';
  var endpoint = root.dataset.endpoint;
  var toggle = document.getElementById('docutrustChatbotToggle');
  var panel = document.getElementById('docutrustChatbotPanel');
  var closeBtn = document.getElementById('docutrustChatbotClose');
  var messagesEl = document.getElementById('docutrustChatbotMessages');
  var bodyEl = root.querySelector('.dt-chatbot-body');
  var form = document.getElementById('docutrustChatbotForm');
  var input = document.getElementById('docutrustChatbotInput');
  var sendBtn = document.getElementById('docutrustChatbotSend');
  var suggestions = document.getElementById('docutrustChatbotSuggestions');
  var history = [];
  var busy = false;
  var logoUrl = root.dataset.logo || '';
  var logoLightUrl = root.dataset.logoLight || logoUrl;
  var inputBaseHeight = 44;

  window.setTimeout(function () {
    var pulseTarget = document.querySelector('[data-chatbot-trigger]');
    if (!pulseTarget) return;

    pulseTarget.classList.add('chat-pulse');
    pulseTarget.addEventListener('animationend', function () {
      pulseTarget.classList.remove('chat-pulse');
    }, { once: true });
  }, 5000);

  function resetInputHeight () {
    if (!input) return;
    input.style.height = inputBaseHeight + 'px';
    input.style.overflow = 'hidden';
  }

  function resizeInput () {
    if (!input) return;
    input.style.height = inputBaseHeight + 'px';
    var next = Math.min(Math.max(input.scrollHeight, inputBaseHeight), 120);
    input.style.height = next + 'px';
    input.style.overflow = 'hidden';
  }

  function chatbotLogoSrc () {
    if (document.documentElement.classList.contains('dark-scheme')) {
      return logoUrl;
    }
    return logoLightUrl;
  }

  function getCsrfToken () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content') || '';
    var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function openPanel () {
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    if (enabled && input) input.focus();
  }

  function closePanel () {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }

  function sourceLabel (source) {
    if (source === 'faq') return 'Knowledge base';
    if (source === 'ai') return 'AI';
    if (source === 'fallback') return 'Assistant';
    return '';
  }

  function appendMessage (role, text, isError, source) {
    var row = document.createElement('div');
    row.className = 'dt-chatbot-msg-row dt-chatbot-msg-row-' + (role === 'user' ? 'user' : 'assistant');
    if (isError) row.className += ' dt-chatbot-msg-row-error';

    if (role !== 'user' && logoUrl) {
      var avatar = document.createElement('img');
      avatar.src = chatbotLogoSrc();
      avatar.alt = '';
      avatar.className = 'dt-chatbot-avatar';
      avatar.width = 32;
      avatar.height = 32;
      avatar.decoding = 'async';
      row.appendChild(avatar);
    }

    var bubble = document.createElement('div');
    bubble.className = 'dt-chatbot-msg ' + (role === 'user' ? 'dt-chatbot-msg-user' : 'dt-chatbot-msg-assistant');
    if (isError) bubble.className += ' dt-chatbot-msg-error';
    var p = document.createElement('p');
    p.textContent = text;
    bubble.appendChild(p);
    if (role !== 'user' && source && !isError) {
      var src = document.createElement('span');
      src.className = 'dt-chatbot-source';
      src.textContent = sourceLabel(source);
      bubble.appendChild(src);
    }
    row.appendChild(bubble);
    messagesEl.appendChild(row);
    if (bodyEl) bodyEl.scrollTop = bodyEl.scrollHeight;
    return row;
  }

  function showTyping () {
    var row = document.createElement('div');
    row.className = 'dt-chatbot-msg-row dt-chatbot-msg-row-assistant';
    row.id = 'docutrustChatbotTyping';

    if (logoUrl) {
      var avatar = document.createElement('img');
      avatar.src = chatbotLogoSrc();
      avatar.alt = '';
      avatar.className = 'dt-chatbot-avatar';
      avatar.width = 32;
      avatar.height = 32;
      avatar.decoding = 'async';
      row.appendChild(avatar);
    }

    var bubble = document.createElement('div');
    bubble.className = 'dt-chatbot-msg dt-chatbot-msg-assistant';
    bubble.innerHTML = '<div class="dt-chatbot-typing"><span></span><span></span><span></span></div>';
    row.appendChild(bubble);
    messagesEl.appendChild(row);
    if (bodyEl) bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function hideTyping () {
    var el = document.getElementById('docutrustChatbotTyping');
    if (el) el.remove();
  }

  function setBusy (state) {
    busy = state;
    if (sendBtn) {
      sendBtn.disabled = state;
      sendBtn.classList.toggle('is-loading', state);
      sendBtn.setAttribute('aria-busy', state ? 'true' : 'false');
    }
    if (input) input.disabled = state;
  }

  async function sendMessage (text) {
    var message = (text || '').trim();
    if (!message || busy || !enabled) return;

    appendMessage('user', message);
    history.push({ role: 'user', content: message });
    if (input) {
      input.value = '';
      resetInputHeight();
    }
    if (suggestions) suggestions.classList.add('is-hidden');
    setBusy(true);
    showTyping();

    try {
      var response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ message: message, history: history.slice(0, -1) }),
      });

      hideTyping();

      var data;
      try {
        data = await response.json();
      } catch (parseError) {
        console.error('Chatbot JSON parse error', parseError);
        appendMessage('assistant', 'Invalid response from server.', true);
        return;
      }

      var replyText = data.reply || null;
      var isSuccess = data.success === true && replyText;

      if (isSuccess) {
        appendMessage('assistant', replyText, false, data.source || null);
        history.push({ role: 'assistant', content: replyText });
        return;
      }

      var errText = data.error || data.message || 'Sorry, I could not answer that right now. Please try again.';
      console.error('Chatbot error', { status: response.status, error: errText, data: data });
      appendMessage('assistant', errText, true);
    } catch (e) {
      hideTyping();
      console.error('Chatbot network error', e);
      appendMessage('assistant', 'Connection error. Please check your connection and try again.', true);
    } finally {
      setBusy(false);
    }
  }

  toggle.addEventListener('click', function () {
    if (panel.hidden) openPanel(); else closePanel();
  });
  closeBtn.addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden) closePanel();
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      sendMessage(input ? input.value : '');
    });
    if (input) {
      input.addEventListener('input', resizeInput);
      resetInputHeight();
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          form.requestSubmit();
        }
      });
    }
  }

  if (suggestions) {
    suggestions.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-prompt]');
      if (!chip) return;
      sendMessage(chip.getAttribute('data-prompt'));
    });
  }
})();
</script>
