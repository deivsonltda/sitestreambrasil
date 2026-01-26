const CRM = (() => {
  let boardCfg = null;
  let listCfg = null;
  let custCfg = null;
  let boardTimer = null;
  let listTimer = null;

  function fmtTime(iso) {
    try {
      const d = new Date(iso);
      return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    } catch { return ''; }
  }

  function avatarLetter(nameOrId) {
    const s = (nameOrId || '?').trim();
    return s ? s[0].toUpperCase() : '?';
  }

  function ticketCard(t) {
    const c = t.customers || {};
    const name = c.name || c.wa_chat_id || 'Cliente';
    const sub = c.wa_chat_id || '';
    const time = fmtTime(t.last_message_at);

    return `
      <div class="card" data-id="${t.id}">
        <div class="card-top">
          <div class="card-avatar">${avatarLetter(name)}</div>
          <div class="card-meta">
            <div class="card-name">${escapeHtml(name)}</div>
            <div class="card-sub">${escapeHtml(sub)}</div>
          </div>
          <div class="card-time">${time}</div>
        </div>
        <div class="card-actions">
          <button class="btn small" data-action="claim">Iniciar atendimento</button>
          <button class="btn small ghost" data-action="open">Abrir</button>
        </div>
      </div>
    `;
  }

  function listItem(t) {
    const c = t.customers || {};
    const name = c.name || c.wa_chat_id || 'Cliente';
    const sub = `${c.wa_chat_id || ''} • ${t.priority} • ${t.status}`;
    const time = fmtTime(t.last_message_at);

    return `
      <div class="row" data-id="${t.id}">
        <div class="row-avatar">${avatarLetter(name)}</div>
        <div class="row-main">
          <div class="row-title">${escapeHtml(name)}</div>
          <div class="row-sub">${escapeHtml(sub)}</div>
        </div>
        <div class="row-right">
          <div class="row-time">${time}</div>
          <button class="btn small" data-action="open">Abrir</button>
        </div>
      </div>
    `;
  }

  function customerRow(c) {
    const name = c.name || c.wa_chat_id || 'Cliente';
    return `
      <div class="row">
        <div class="row-avatar">${avatarLetter(name)}</div>
        <div class="row-main">
          <div class="row-title">${escapeHtml(name)}</div>
          <div class="row-sub">${escapeHtml(c.wa_chat_id || '')}</div>
        </div>
        <div class="row-right">
          <select class="select" data-id="${c.id}">
            ${['BOT','SUPPORT','HUMAN'].map(s => `<option ${c.step===s?'selected':''}>${s}</option>`).join('')}
          </select>
          <button class="btn small" data-action="save" data-id="${c.id}">Salvar</button>
        </div>
      </div>
    `;
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
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

  // ------- BOARD -------
  async function refreshBoard() {
    if (!boardCfg) return;
    const res = await fetch(boardCfg.listUrl);
    const data = await res.json().catch(()=>({ok:false}));
    if (!data.ok) return;

    const human = data.human || [];
    const support = data.support || [];

    document.getElementById('countHuman').textContent = human.length;
    document.getElementById('countSupport').textContent = support.length;

    const colHuman = document.getElementById('colHuman');
    const colSupport = document.getElementById('colSupport');

    colHuman.innerHTML = human.map(ticketCard).join('') || `<div class="empty">Sem HUMAN</div>`;
    colSupport.innerHTML = support.map(ticketCard).join('') || `<div class="empty">Sem SUPPORT</div>`;

    // bind actions
    document.querySelectorAll('.card').forEach(el => {
      const id = el.getAttribute('data-id');
      el.querySelectorAll('button').forEach(btn => {
        btn.onclick = async () => {
          const act = btn.getAttribute('data-action');
          if (act === 'open') return boardCfg.openChat(id);

          if (act === 'claim') {
            btn.disabled = true;
            const {res, json} = await postJson(boardCfg.claimUrl, {ticket_id:id});
            if (res.status === 409) {
              alert('Esse ticket já foi pego por outro atendente.');
              btn.disabled = false;
              return;
            }
            if (!json.ok) {
              alert('Falha ao iniciar atendimento.');
              btn.disabled = false;
              return;
            }
            boardCfg.openChat(id);
          }
        };
      });
    });
  }

  function initBoard(cfg) {
    boardCfg = cfg;
    refreshBoard();
    if (boardTimer) clearInterval(boardTimer);
    boardTimer = setInterval(refreshBoard, cfg.autoRefreshMs || 2500);
  }

  // ------- LIST -------
  async function refreshList() {
    if (!listCfg) return;
    const res = await fetch(listCfg.url);
    const data = await res.json().catch(()=>({ok:false}));
    if (!data.ok) return;

    const tickets = data.tickets || [];
    const container = document.getElementById(listCfg.containerId);
    container.innerHTML = tickets.map(listItem).join('') || `<div class="empty">Nada aqui</div>`;

    container.querySelectorAll('.row').forEach(el => {
      const id = el.getAttribute('data-id');
      const btn = el.querySelector('button[data-action="open"]');
      if (btn) btn.onclick = () => listCfg.onOpen(id);
      el.onclick = (e) => {
        if (e.target.tagName.toLowerCase() === 'button') return;
        listCfg.onOpen(id);
      };
    });
  }

  function initList(cfg) {
    listCfg = cfg;
    refreshList();
    if (listTimer) clearInterval(listTimer);
    listTimer = setInterval(refreshList, cfg.autoRefreshMs || 2500);
  }

  // ------- CUSTOMERS -------
  async function searchCustomers() {
    if (!custCfg) return;
    const qEl = document.getElementById('q');
    const q = qEl ? qEl.value.trim() : '';
    const url = custCfg.listUrl + (q ? ('?q=' + encodeURIComponent(q)) : '');
    const res = await fetch(url);
    const data = await res.json().catch(()=>({ok:false}));
    if (!data.ok) return;

    const list = document.getElementById(custCfg.containerId);
    const customers = data.customers || [];
    list.innerHTML = customers.map(customerRow).join('') || `<div class="empty">Nenhum cliente</div>`;

    list.querySelectorAll('button[data-action="save"]').forEach(btn => {
      btn.onclick = async (e) => {
        e.stopPropagation();
        const id = btn.getAttribute('data-id');
        const sel = list.querySelector(`select[data-id="${id}"]`);
        const step = sel.value;

        btn.disabled = true;
        const {json} = await postJson(custCfg.updateUrl, {customer_id:id, step});
        btn.disabled = false;

        if (!json.ok) return alert('Falha ao salvar step.');
        btn.textContent = 'Salvo!';
        setTimeout(()=>btn.textContent='Salvar', 800);
      };
    });
  }

  function initCustomers(cfg) {
    custCfg = cfg;
  }

  return {
    initBoard,
    refreshBoard,
    initList,
    refreshList,
    initCustomers,
    searchCustomers,
  };
})();