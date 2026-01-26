const Chat = (() => {
  let cfg = null;
  let timer = null;
  let lastCount = 0;

  function fmtTime(iso) {
    try {
      const d = new Date(iso);
      return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    } catch { return ''; }
  }

  function esc(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function msgBubble(m) {
    const side = m.direction === 'OUT' ? 'out' : 'in';
    return `
      <div class="msg ${side}">
        <div class="bubble">${esc(m.text || '')}</div>
        <div class="meta">${fmtTime(m.sent_at)}</div>
      </div>
    `;
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(body)
    });
    const json = await res.json().catch(()=>({ok:false}));
    return {res, json};
  }

  async function loadHeader() {
    const res = await fetch(cfg.ticketGetUrl + '?ticket_id=' + encodeURIComponent(cfg.ticketId));
    const data = await res.json().catch(()=>({ok:false}));
    if (!data.ok) {
      document.getElementById('custName').textContent = 'Ticket não encontrado';
      return;
    }
    const t = data.ticket;
    const c = t.customers || {};
    document.getElementById('custName').textContent = c.name || c.wa_chat_id || 'Cliente';
    document.getElementById('custSub').textContent = `${c.wa_chat_id || ''} • ${t.priority} • ${t.status}`;
    document.getElementById('custAvatar').textContent = (c.name || c.wa_chat_id || '?')[0].toUpperCase();
  }

  async function refreshMessages(scrollIfNew=true) {
    const res = await fetch(cfg.messagesListUrl + '?ticket_id=' + encodeURIComponent(cfg.ticketId));
    const data = await res.json().catch(()=>({ok:false}));
    if (!data.ok) return;

    const messages = data.messages || [];
    const body = document.getElementById('chatBody');

    // render only if changed
    if (messages.length !== lastCount) {
      body.innerHTML = messages.map(msgBubble).join('');
      if (scrollIfNew) body.scrollTop = body.scrollHeight;
      lastCount = messages.length;
    }
  }

  async function send(text) {
    const {res, json} = await postJson(cfg.sendUrl, {ticket_id: cfg.ticketId, text});
    if (!json.ok) {
      if (res.status === 403) alert('Você não é o atendente desse ticket (ou não está IN_PROGRESS).');
      else alert('Falha ao enviar.');
      return false;
    }
    return true;
  }

  async function done() {
    const ok = confirm('Marcar como CONCLUÍDO?');
    if (!ok) return;
    const {res, json} = await postJson(cfg.doneUrl, {ticket_id: cfg.ticketId});
    if (!json.ok) {
      if (res.status === 409) alert('Não foi possível concluir (talvez já tenha sido concluído).');
      else alert('Falha ao concluir.');
      return;
    }
    location.href = '/crm/public/concluidas.php';
  }

  function init(_cfg) {
    cfg = _cfg;
    loadHeader();
    refreshMessages(true);

    if (timer) clearInterval(timer);
    timer = setInterval(()=>refreshMessages(false), cfg.pollMs || 1500);

    document.getElementById('sendForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = document.getElementById('msgInput');
      const text = input.value.trim();
      if (!text) return;
      input.value = '';
      const ok = await send(text);
      if (ok) await refreshMessages(true);
    });

    document.getElementById('btnDone').onclick = done;
  }

  return { init };
})();