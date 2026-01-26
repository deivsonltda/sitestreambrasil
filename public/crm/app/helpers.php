<?php
function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function rel_time(string $iso): string {
  // ISO timestamptz -> HH:MM (simples)
  try {
    $dt = new DateTime($iso);
    return $dt->format('H:i');
  } catch (Throwable $e) {
    return '';
  }
}