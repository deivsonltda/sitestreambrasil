<?php
class Supabase {
  private string $url;
  private string $key;

  public function __construct(array $cfg) {
    $this->url = rtrim($cfg['supabase_url'], '/');
    $this->key = $cfg['supabase_service_role'];
  }

  public function request(string $method, string $path, array $query = [], $body = null) {
    $u = $this->url . $path;
    if (!empty($query)) $u .= '?' . http_build_query($query);

    $ch = curl_init($u);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => array_filter([
        'apikey: ' . $this->key,
        'Authorization: Bearer ' . $this->key,
        'Content-Type: application/json',
        'Prefer: return=representation',
      ]),
    ]);

    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    curl_close($ch);

    $json = json_decode($res, true);
    return ['code' => $code, 'json' => $json, 'raw' => $res];
  }

  public function rpc(string $fn, array $args) {
    return $this->request('POST', "/rest/v1/rpc/{$fn}", [], $args);
  }
}