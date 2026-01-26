<?php
class Waha {
  private string $base;
  private $token;

  public function __construct(array $cfg) {
    $this->base = rtrim($cfg['waha_url'], '/');
    $this->token = $cfg['waha_token'] ?? null;
  }

  public function sendText(string $chatId, string $text, string $session = 'default') {
    $payload = [
      'session' => $session,
      'chatId' => $chatId,
      'text' => $text,
    ];

    $ch = curl_init($this->base . '/api/sendText');
    $headers = ['Content-Type: application/json'];
    if ($this->token) $headers[] = 'Authorization: Bearer ' . $this->token;

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false) throw new Exception('WAHA cURL error: ' . curl_error($ch));
    curl_close($ch);

    return ['code' => $code, 'json' => json_decode($res, true), 'raw' => $res];
  }
}