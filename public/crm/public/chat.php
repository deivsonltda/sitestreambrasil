<?php
$current = 'chat';
require __DIR__ . '/_layout_top.php';

$ticketId = $_GET['ticket'] ?? $_GET['id'] ?? '';
$ticketId = trim($ticketId);
?>
<div class="chat-wrap" data-ticket="<?= h($ticketId) ?>">
  <div class="chat-header">
    <div class="chat-head-left">
      <div class="avatar" id="chatAvatar"></div>
      <div>
        <div class="chat-title" id="chatTitle">Cliente</div>
        <div class="chat-sub" id="chatSub">...</div>
      </div>
    </div>

    <div class="row" style="gap:10px">
      <button class="btn danger" id="btnDone">Concluído</button>
    </div>
  </div>

  <div class="chat-body" id="chatBody"></div>

  <div class="chat-footer">
    <input class="chat-input" id="msg" placeholder="Digite uma mensagem..." />
    <button class="btn primary" id="btnSend">Enviar</button>
  </div>
</div>

<script>
  window.TICKET_ID = <?= json_encode($ticketId) ?>;

  function esc(s) {
    return (s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    } [m]));
  }

  async function loadTicket() {
    const id = window.TICKET_ID;
    if (!id) {
      alert('Ticket não informado na URL. Use ?ticket=UUID');
      return;
    }

    const r = await fetch('/crm/api/ticket_get.php?ticket=' + encodeURIComponent(id), {
      cache: 'no-store'
    });
    const j = await r.json();
    if (!j.ok) {
      alert('ticket_get: ' + (j.error || 'erro'));
      return;
    }

    const t = j.ticket || {};
    document.getElementById('chatTitle').textContent = t.customer_name || t.customerName || t.name || 'Cliente';
    const lm = t.last_message_at || t.lastMessageAt;
    let lmTxt = '';
    if (lm) {
      const ms = Date.parse(lm);
      if (isFinite(ms)) lmTxt = ' • ' + new Date(ms).toLocaleString([], {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
    document.getElementById('chatSub').textContent =
      (t.priority ? (t.priority + ' • ') : '') + (t.status || '') + lmTxt;


    const av = t.avatar_url || t.avatarUrl || t.avatar;
    const avBox = document.getElementById('chatAvatar');
    avBox.innerHTML = av ? `<img src="${esc(av)}" alt="">` : '';
  }

  function bubbleHtml(m) {
    const dir = String(m.direction || '').trim().toUpperCase();
    const fromMe = (dir === 'OUT') || !!(m.fromMe ?? m.from_me);

    const cls = fromMe ? 'bubble me' : 'bubble';

    const ms =
      m.timestampMs ?? m.ms ??
      (m.t ? (m.t * 1000) : null) ??
      Date.parse(m.created_at || m.createdAt || m.ts || '');

    const dt = isFinite(ms) ? new Date(ms) : null;
    const hh = dt ? dt.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    }) : '';

    return `<div class="${cls}">
    <div class="bubble-text">${esc(m.text || m.body || '')}</div>
    <div class="bubble-time">${esc(hh)}</div>
  </div>`;
  }

  async function loadMessages() {
    const id = window.TICKET_ID;
    const r = await fetch('/crm/api/messages_list.php?ticket=' + encodeURIComponent(id), {
      cache: 'no-store'
    });
    const j = await r.json();
    if (!j.ok) {
      return;
    }

    const box = document.getElementById('chatBody');
    box.innerHTML = '';
    (j.messages || []).forEach(m => box.insertAdjacentHTML('beforeend', bubbleHtml(m)));
    box.scrollTop = box.scrollHeight;
  }

  async function sendMsg() {
    const id = window.TICKET_ID;
    const input = document.getElementById('msg');
    const text = (input.value || '').trim();
    if (!text) return;

    const r = await fetch('/crm/api/message_send.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ticket: id,
        text
      })
    });
    const j = await r.json().catch(() => ({
      ok: false
    }));

    if (!j.ok) {
      alert('erro ao enviar: ' + (j.error || ''));
      return;
    }

    input.value = '';
    loadMessages();
  }

  async function done() {
    const id = window.TICKET_ID;
    const r = await fetch('/crm/api/ticket_done.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ticket: id
      })
    });
    const j = await r.json().catch(() => ({
      ok: false
    }));
    if (!j.ok) {
      alert('erro ao concluir');
      return;
    }
    location.href = '/crm/public/conversas.php';
  }

  document.getElementById('btnSend').onclick = sendMsg;
  document.getElementById('msg').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      sendMsg();
    }
  });
  document.getElementById('btnDone').onclick = done;

  loadTicket();
  loadMessages();
  setInterval(loadMessages, 2000);
</script>

<style>
  /* bolhas do chat */
  .bubble {
    max-width: 70%;
    background: #fff;
    border: 1px solid rgba(0, 0, 0, .08);
    padding: 10px 12px;
    border-radius: 14px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
    margin-bottom: 10px;
  }

  .bubble.me {
    margin-left: auto;
    /* <-- joga pra direita */
    background: #dcf8c6;
  }

  .bubble-text {
    white-space: pre-wrap;
  }

  .bubble-time {
    font-size: 11px;
    opacity: .6;
    margin-top: 6px;
    text-align: right;
  }

  /* garante alinhamento correto das bolhas */
  #chatBody {
    display: block !important;
  }

  .bubble.me {
    margin-left: auto !important;
  }
</style>

<?php require __DIR__ . '/_layout_bottom.php'; ?>