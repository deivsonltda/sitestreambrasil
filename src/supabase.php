<?php

function sb_request($method, $path, $data = null, $useService = true) {
  $cfg = require __DIR__ . '/../config.php';
  $url = rtrim($cfg['SUPABASE_URL'], '/') . '/rest/v1/' . ltrim($path, '/');

  $key = $useService ? $cfg['SUPABASE_SERVICE_KEY'] : $cfg['SUPABASE_ANON_KEY'];

  $headers = [
    "apikey: {$key}",
    "Authorization: Bearer {$key}",
    "Content-Type: application/json",
    "Prefer: return=representation"
  ];

  $ch = curl_init($url);
  $url = preg_replace('/\s+/', '', $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) throw new Exception("Curl error: " . $err);
  $json = json_decode($resp, true);

  if ($code >= 400) throw new Exception("Supabase {$code}: " . $resp);

  return $json;
}