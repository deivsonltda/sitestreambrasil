<?php
$current = 'atendentes';
require __DIR__ . '/_layout_top.php';
?>
<div class="topbar">
  <div>
    <div class="page-title">Atendentes</div>
    <div class="page-sub">Criar, editar, ativar/desativar e excluir atendentes</div>
  </div>
  <div class="topbar-actions">
    <button class="btn" id="btnRefresh">Atualizar</button>
  </div>
</div>

<div class="panel">
  <div class="panel-title">Cadastrar novo atendente</div>

  <form id="createForm" class="form-grid">
    <div>
      <label class="lbl">Nome</label>
      <input class="input" name="name" required />
    </div>
    <div>
      <label class="lbl">Usuário</label>
      <input class="input" name="username" required />
    </div>
    <div>
      <label class="lbl">Senha</label>
      <input class="input" name="password" type="password" minlength="6" required />
    </div>
    <div>
      <label class="lbl">Role</label>
      <select class="select" name="role">
        <option value="agent" selected>agent</option>
        <option value="admin">admin</option>
      </select>
    </div>
    <div class="form-actions">
      <button class="btn primary" type="submit">Criar</button>
      <span id="createMsg" class="muted"></span>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-title">Lista de atendentes</div>
  <div class="list" id="agentsList"></div>
</div>

<!-- Modal de edição -->
<div class="modal" id="editModal" style="display:none;">
  <div class="modal-card">
    <div class="modal-head">
      <div class="modal-title">Editar atendente</div>
      <button class="btn small ghost" id="btnCloseModal">Fechar</button>
    </div>

    <form id="editForm" class="modal-body">
      <input type="hidden" name="id" />

      <label class="lbl">Nome</label>
      <input class="input" name="name" required />

      <label class="lbl">Usuário</label>
      <input class="input" name="username" required />

      <label class="lbl">Nova senha (opcional)</label>
      <input class="input" name="password" type="password" minlength="6" placeholder="Deixe vazio para não mudar" />

      <div class="rowline">
        <div class="rowblock">
          <label class="lbl">Role</label>
          <select class="select" name="role">
            <option value="agent">agent</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div class="rowblock">
          <label class="lbl">Ativo?</label>
          <select class="select" name="is_active">
            <option value="true">true</option>
            <option value="false">false</option>
          </select>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn primary" type="submit" id="btnSave">Salvar</button>
        <button class="btn danger" type="button" id="btnDelete">Excluir</button>
        <span id="editMsg" class="muted"></span>
      </div>
    </form>
  </div>
</div>

<script>
const API = {
  list: '/crm/api/agents_list.php',
  create: '/crm/api/agent_create.php',
  update: '/crm/api/agent_update.php',
  del: '/crm/api/agent_delete.php',
};

const state = { agents: [] };

function esc(s){
  return String(s ?? '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;')
    .replaceAll('>','&gt;').replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

async function post(url, body){
  const res = await fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  const json = await res.json().catch(()=>({ok:false}));
  return {res, json};
}

function renderList(){
  const el = document.getElementById('agentsList');
  if (!state.agents.length){
    el.innerHTML = `<div class="empty">Nenhum atendente</div>`;
    return;
  }

  el.innerHTML = state.agents.map(a => `
    <div class="row agent-row" data-id="${a.id}">
      <div class="row-avatar">${(a.name||'?')[0].toUpperCase()}</div>
      <div class="row-main">
        <div class="row-title">${esc(a.name)} <span class="pill">${esc(a.role)}</span></div>
        <div class="row-sub">@${esc(a.username)} • ${a.is_active ? 'ativo' : 'inativo'}</div>
      </div>
      <div class="row-right">
        <button class="btn small" data-action="edit">Editar</button>
      </div>
    </div>
  `).join('');

  el.querySelectorAll('button[data-action="edit"]').forEach(btn => {
    btn.onclick = (e) => {
      e.stopPropagation();
      const row = btn.closest('.agent-row');
      openEdit(row.getAttribute('data-id'));
    };
  });

  el.querySelectorAll('.agent-row').forEach(row => {
    row.onclick = () => openEdit(row.getAttribute('data-id'));
  });
}

async function loadAgents(){
  const res = await fetch(API.list);
  const data = await res.json().catch(()=>({ok:false}));
  if (!data.ok) {
    document.getElementById('agentsList').innerHTML = `<div class="empty">Erro ao carregar</div>`;
    return;
  }
  state.agents = data.agents || [];
  renderList();
}

function openEdit(id){
  const a = state.agents.find(x => x.id === id);
  if (!a) return;

  const modal = document.getElementById('editModal');
  modal.style.display = 'flex';

  const f = document.getElementById('editForm');
  f.id.value = a.id;
  f.name.value = a.name || '';
  f.username.value = a.username || '';
  f.password.value = '';
  f.role.value = a.role || 'agent';
  f.is_active.value = String(!!a.is_active);

  document.getElementById('editMsg').textContent = '';
  document.getElementById('btnDelete').dataset.id = a.id;
}

function closeEdit(){
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('btnCloseModal').onclick = closeEdit;
document.getElementById('editModal').addEventListener('click', (e)=>{
  if (e.target.id === 'editModal') closeEdit();
});

document.getElementById('btnRefresh').onclick = loadAgents;

// CREATE
document.getElementById('createForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);

  const payload = {
    name: fd.get('name'),
    username: fd.get('username'),
    password: fd.get('password'),
    role: fd.get('role'),
  };

  const msg = document.getElementById('createMsg');
  msg.textContent = 'Criando...';

  const {res, json} = await post(API.create, payload);

  if (!json.ok) {
    msg.textContent = res.status === 400 ? 'Erro ao criar (usuário pode existir / senha fraca).' : 'Falha ao criar.';
    return;
  }

  msg.textContent = 'Criado!';
  e.target.reset();
  setTimeout(()=>msg.textContent='', 900);
  loadAgents();
});

// UPDATE
document.getElementById('editForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const f = e.target;

  const payload = {
    id: f.id.value,
    name: f.name.value,
    username: f.username.value,
    password: f.password.value,     // vazio = não muda
    role: f.role.value,
    is_active: (f.is_active.value === 'true'),
  };

  const msg = document.getElementById('editMsg');
  msg.textContent = 'Salvando...';

  const {res, json} = await post(API.update, payload);

  if (!json.ok) {
    msg.textContent = 'Falha ao salvar (username pode já existir).';
    return;
  }

  msg.textContent = 'Salvo!';
  setTimeout(()=>msg.textContent='', 900);
  await loadAgents();
  closeEdit();
});

// DELETE
document.getElementById('btnDelete').addEventListener('click', async ()=>{
  const id = document.getElementById('btnDelete').dataset.id;
  if (!id) return;

  const ok = confirm('Excluir este atendente? Isso não tem volta.');
  if (!ok) return;

  const msg = document.getElementById('editMsg');
  msg.textContent = 'Excluindo...';

  const {res, json} = await post(API.del, {id});

  if (!json.ok) {
    if (json.error === 'cannot_delete_self') msg.textContent = 'Você não pode excluir você mesmo.';
    else if (json.error === 'cannot_delete_last_admin') msg.textContent = 'Não pode excluir o último admin.';
    else msg.textContent = 'Falha ao excluir.';
    return;
  }

  msg.textContent = 'Excluído!';
  setTimeout(()=>msg.textContent='', 900);
  await loadAgents();
  closeEdit();
});

loadAgents();
</script>

<style>
.panel{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:16px;padding:12px;margin-bottom:12px}
.panel-title{font-weight:900;margin:4px 0 10px 2px}
.form-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr .8fr;gap:10px;align-items:end}
.lbl{display:block;font-size:12px;color:var(--muted);margin:0 0 6px 2px}
.form-actions{display:flex;gap:10px;align-items:center}
.muted{color:var(--muted);font-size:12px}
.pill{display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.06);font-size:12px;color:var(--muted)}
@media (max-width: 980px){ .form-grid{grid-template-columns:1fr} }

/* modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:14px;z-index:50}
.modal-card{width:min(520px, 96vw);background:linear-gradient(180deg,var(--panel),var(--panel2));border:1px solid var(--border);border-radius:16px;overflow:hidden}
.modal-head{display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid var(--border)}
.modal-title{font-weight:900}
.modal-body{display:flex;flex-direction:column;gap:10px;padding:12px}
.rowline{display:flex;gap:10px}
.rowblock{flex:1}
.modal-actions{display:flex;gap:10px;align-items:center;margin-top:6px}
@media (max-width: 560px){ .rowline{flex-direction:column} .modal-actions{flex-wrap:wrap} }
</style>

<?php require __DIR__ . '/_layout_bottom.php'; ?>