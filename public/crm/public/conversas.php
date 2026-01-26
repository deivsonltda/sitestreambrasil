<?php
$current = 'conversas';
require __DIR__ . '/_layout_top.php';
?>
<div class="topbar">
  <div>
    <div class="page-title">Conversas</div>
    <div class="page-sub">Tickets em atendimento</div>
  </div>
  <div class="topbar-actions">
    <button class="btn" id="btnRefresh">Atualizar</button>
  </div>
</div>

<div class="list" id="list"></div>

<script src="/crm/public/assets/app.js"></script>
<script>
CRM.initList({
  url: '/crm/api/tickets_inprogress.php',
  containerId: 'list',
  onOpen: (ticketId) => location.href = '/crm/public/chat.php?ticket=' + encodeURIComponent(ticketId),
  autoRefreshMs: 2500
});
document.getElementById('btnRefresh').onclick = () => CRM.refreshList();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>