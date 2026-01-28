<?php
$current = 'clientes';
require __DIR__ . '/_layout_top.php';
?>
<div class="topbar">
  <div>
    <div class="page-title">Clientes</div>
    <div class="page-sub">Buscar e alterar step (BOT / SUPPORT / HUMAN)</div>
  </div>
  <div class="topbar-actions">
    <input id="q" class="input" placeholder="Buscar por nome ou chatId..." />
    <button class="btn" id="btnSearch">Buscar</button>
  </div>
</div>

<div class="list" id="list"></div>

<script src="/public/assets/app.js"></script>
<script>
CRM.initCustomers({
  listUrl: '/api/customers_list.php',
  updateUrl: '/api/customer_update_step.php',
  containerId: 'list'
});
document.getElementById('btnSearch').onclick = () => CRM.searchCustomers();
document.getElementById('q').addEventListener('keydown', (e)=>{ if(e.key==='Enter') CRM.searchCustomers(); });
CRM.searchCustomers();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>