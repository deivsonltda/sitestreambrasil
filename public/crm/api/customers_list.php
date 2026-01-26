<?php
session_start();
if (empty($_SESSION['agent_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$cfg = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/supabase.php';
$sb = new Supabase($cfg);

$q = trim($_GET['q'] ?? '');
$params = [
  'select' => 'id,wa_chat_id,name,avatar_url,step,updated_at',
  'order' => 'updated_at.desc',
  'limit' => 50
];

if ($q !== '') {
  // busca simples por name/wa_chat_id (duas requests)
  $r1 = $sb->request('GET', '/rest/v1/customers', $params + ['name' => 'ilike.*'.$q.'*']);
  $r2 = $sb->request('GET', '/rest/v1/customers', $params + ['wa_chat_id' => 'ilike.*'.$q.'*']);
  $merged = array_merge($r1['json'] ?? [], $r2['json'] ?? []);
  // dedupe por id
  $map = [];
  foreach ($merged as $c) $map[$c['id']] = $c;
  echo json_encode(['ok'=>true,'customers'=>array_values($map)]);
  exit;
}

$r = $sb->request('GET', '/rest/v1/customers', $params);
echo json_encode(['ok'=>true,'customers'=>$r['json'] ?? []]);