<?php
$current = 'concluidas';
require __DIR__ . '/_layout_top.php';
?>
<div class="topbar">
  <div>
    <div class="page-title">Concluídas</div>
    <div class="page-sub">Histórico de tickets encerrados</div>
  </div>
  <div class="topbar-actions">
    <button class="btn" id="btnRefresh">Atualizar</button>
  </div>
</div>

<div class="list" id="list"></div>

<script src="/public/assets/app.js"></script>
<script>
CRM.initList({
  url: '/api/tickets_done.php',
  containerId: 'list',
  onOpen: (ticketId) => location.href = '/public/chat.php?ticket=' + encodeURIComponent(ticketId),
  autoRefreshMs: 5000
});
document.getElementById('btnRefresh').onclick = () => CRM.refreshList();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>