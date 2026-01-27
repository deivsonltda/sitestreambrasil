<?php
require __DIR__ . '/../src/supabase.php';

$code = $_GET['c'] ?? '';
if (!$code) { http_response_code(400); echo "missing code"; exit; }

try {
  $links = sb_request('GET', "short_links?select=id,destination_url,is_active&code=eq." . urlencode($code) . "&limit=1", null, true);
  if (!$links || !$links[0]['is_active']) { http_response_code(404); echo "not found"; exit; }

  $linkId = $links[0]['id'];
  $dest   = $links[0]['destination_url'];

  // clique opcional
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ipHash = $ip ? hash('sha256', $ip) : null;

  sb_request('POST', 'clicks', [
    'short_link_id' => $linkId,
    'ip_hash' => $ipHash,
    'user_agent' => substr($ua, 0, 250),
  ], true);

  header("Location: {$dest}", true, 302);
  exit;
} catch (Exception $e) {
  http_response_code(500);
  echo "error";
}