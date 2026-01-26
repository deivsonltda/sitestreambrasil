<?php
$current = 'solicitacoes';
require __DIR__ . '/_layout_top.php';
?>

<div class="topbar">
  <div class="topbar-left">
    <i class="fa-regular fa-comment-dots"></i>
    <span>Solicitações</span>
  </div>

  <div class="topbar-search">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input id="q" placeholder="Pesquisar." />
  </div>

  <div class="topbar-right">
    <div class="tb-icon" title="Atualizar" id="btnReload"><i class="fa-solid fa-rotate"></i></div>
    <div class="tb-icon" title="Perfil"><i class="fa-solid fa-user"></i></div>
  </div>
</div>

<div class="board">
  <div class="columns">
    <div class="col">
      <div class="col-head">
        <div class="col-title"><span class="dot"></span> HUMAN</div>
        <div class="col-count" id="countH">0</div>
      </div>
      <div id="colH"></div>
    </div>

    <div class="col">
      <div class="col-head">
        <div class="col-title"><span class="dot red"></span> SUPPORT</div>
        <div class="col-count" id="countS">0</div>
      </div>
      <div id="colS"></div>
    </div>
  </div>
</div>

<div class="actions">
  <button class="fab secondary" title="Filtro"><i class="fa-solid fa-filter"></i></button>
  <button class="fab" title="Novo (futuro)"><i class="fa-solid fa-plus"></i></button>
</div>

<script>
const elH = document.getElementById('colH');
const elS = document.getElementById('colS');
const cH  = document.getElementById('countH');
const cS  = document.getElementById('countS');
const q   = document.getElementById('q');

function esc(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

function cardHtml(t){
  const name = esc(t.customer_name || t.wa_chat_id || 'Cliente');
  const msg  = esc(t.last_message || '');
  const time = esc(t.last_time || '');
  const avatar = t.avatar_url ? `<img src="${esc(t.avatar_url)}" alt="">` : '';
  return `
    <div class="ticket" data-id="${esc(t.id)}">
      <div class="row">
        <div class="avatar">${avatar}</div>
        <div style="min-width:0">
          <div class="title">${name}</div>
          <div class="sub">${msg}</div>
        </div>
      </div>

      <div class="meta">
        <div class="row" style="gap:8px">
          <span class="pill">${esc(t.priority)}</span>
        </div>
        <span class="sub">${time}</span>
      </div>

      <button class="btn-start" data-claim="${esc(t.id)}">
        INICIAR ATENDIMENTO
      </button>
    </div>
  `;
}

async function load(){
  const res = await fetch('/crm/api/tickets_open.php?q=' + encodeURIComponent(q.value || ''), {cache:'no-store'});
  const json = await res.json();
  if (!json.ok) return;

  elH.innerHTML = '';
  elS.innerHTML = '';
  cH.textContent = json.human.length;
  cS.textContent = json.support.length;

  json.human.forEach(t => elH.insertAdjacentHTML('beforeend', cardHtml(t)));
  json.support.forEach(t => elS.insertAdjacentHTML('beforeend', cardHtml(t)));

  // bind claim
  document.querySelectorAll('[data-claim]').forEach(btn => {
    btn.onclick = async () => {
      const id = btn.getAttribute('data-claim');
      btn.disabled = true;
      btn.textContent = 'INICIANDO...';

      const r = await fetch('/crm/api/ticket_claim.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ticket_id:id})
      });
      const j = await r.json().catch(()=>({ok:false}));

      if (!j.ok) {
        btn.disabled = false;
        btn.textContent = 'INICIAR ATENDIMENTO';
        alert(j.error || 'Não foi possível iniciar (provavelmente outro atendente pegou).');
        load();
        return;
      }

      location.href = '/crm/public/chat.php?ticket=' + encodeURIComponent(id);
    };
  });
}

document.getElementById('btnReload').onclick = load;
q.addEventListener('input', () => { clearTimeout(window.__tt); window.__tt=setTimeout(load, 250); });

load();
setInterval(load, 2500);
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>