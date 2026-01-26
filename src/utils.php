<?php

function slugify($text)
{
  $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  $text = trim($text, '-');
  return $text ?: 'afiliado';
}

function fmt_br_datetime($iso)
{
  if (!$iso) return '-';
  $dt = new DateTime($iso);                 // lê UTC do Supabase
  $dt->setTimezone(new DateTimeZone('America/Recife')); // ou America/Sao_Paulo
  return $dt->format('d/m/Y H:i');
}

function random_code($len = 4)
{
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out = '';
  for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
  return $out;
}

function app_tz(): DateTimeZone
{
  return new DateTimeZone('America/Recife');
}

function month_floor(DateTime $d)
{
  $tz = $d->getTimezone() ?: app_tz();
  return new DateTime($d->format('Y-m-01 00:00:00'), $tz);
}

/**
 * Retorna [start, end) (end exclusivo) em timezone America/Recife.
 *
 * Presets suportados:
 * - today
 * - yesterday
 * - last_7_days
 * - last_30_days
 * - custom (precisa de $customStart, $customEnd no formato YYYY-MM-DD)
 * - this_month / this_quarter / this_semester (mantidos)
 */
function get_period_range($preset, ?string $customStart = null, ?string $customEnd = null)
{
  $tz = app_tz();
  $now = new DateTime('now', $tz);

  $start = null;
  $end = null;

  if ($preset === 'today') {
    $start = (clone $now)->setTime(0, 0, 0);
    $end   = (clone $start)->modify('+1 day');
  } elseif ($preset === 'yesterday') {
    $start = (clone $now)->setTime(0, 0, 0)->modify('-1 day');
    $end   = (clone $start)->modify('+1 day');
  } elseif ($preset === 'last_7_days') {
    // inclui hoje + 6 dias anteriores (7 dias no total)
    $end   = (clone $now)->setTime(0, 0, 0)->modify('+1 day');
    $start = (clone $end)->modify('-7 day');
  } elseif ($preset === 'last_30_days') {
    $end   = (clone $now)->setTime(0, 0, 0)->modify('+1 day');
    $start = (clone $end)->modify('-30 day');
  } elseif ($preset === 'custom') {
    // customStart e customEnd (YYYY-MM-DD)
    if (!$customStart || !$customEnd) {
      // fallback seguro
      $start = month_floor($now);
      $end = (clone $start)->modify('+1 month');
      return [$start, $end];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEnd)) {
      $start = month_floor($now);
      $end = (clone $start)->modify('+1 month');
      return [$start, $end];
    }

    $start = new DateTime($customStart . ' 00:00:00', $tz);
    $end   = new DateTime($customEnd . ' 00:00:00', $tz);

    // end exclusivo: inclui o dia final inteiro
    $end = $end->modify('+1 day');

    // se o usuário inverter, corrige
    if ($end <= $start) {
      $tmp = $start;
      $start = (clone $end)->modify('-1 day');
      $end = (clone $tmp)->modify('+1 day');
    }
  } elseif ($preset === 'this_month') {
    $start = month_floor($now);
    $end = (clone $start)->modify('+1 month');
  } elseif ($preset === 'this_quarter') {
    $m = (int)$now->format('n');
    $qStartMonth = $m - (($m - 1) % 3);
    $start = new DateTime($now->format("Y-{$qStartMonth}-01 00:00:00"), $tz);
    $end = (clone $start)->modify('+3 month');
  } elseif ($preset === 'this_semester') {
    $m = (int)$now->format('n');
    $sStartMonth = ($m <= 6) ? 1 : 7;
    $start = new DateTime($now->format("Y-{$sStartMonth}-01 00:00:00"), $tz);
    $end = (clone $start)->modify('+6 month');
  } else {
    $start = month_floor($now);
    $end = (clone $start)->modify('+1 month');
  }

  return [$start, $end];
}

function pin_hash($pin)
{
  return password_hash($pin, PASSWORD_BCRYPT);
}

function pin_verify($pin, $hash)
{
  if (!$hash) return false;
  return password_verify($pin, $hash);
}

function normalize_phone_digits($input)
{
  $d = preg_replace('/\D+/', '', (string)$input);
  // remove 00 se vier internacional
  $d = preg_replace('/^00/', '', $d);

  // Se vier com 55, ok. Se vier só DDD+numero, prefixa 55.
  if (strpos($d, '55') === 0) return $d;
  if (strlen($d) >= 10 && strlen($d) <= 11) return '55' . $d;

  return $d; // fallback
}

function br_phone_display($input)
{
  $d = preg_replace('/\D+/', '', (string)$input);
  // tira o 55 pra exibir
  if (strpos($d, '55') === 0) $d = substr($d, 2);

  // 11 dígitos: (DD) 9XXXX-XXXX
  if (strlen($d) === 11) {
    $ddd = substr($d, 0, 2);
    $p1  = substr($d, 2, 5);
    $p2  = substr($d, 7, 4);
    return "($ddd) $p1-$p2";
  }

  // 10 dígitos: (DD) XXXX-XXXX
  if (strlen($d) === 10) {
    $ddd = substr($d, 0, 2);
    $p1  = substr($d, 2, 4);
    $p2  = substr($d, 6, 4);
    return "($ddd) $p1-$p2";
  }

  return $input;
}


function whatsapp_chat_link($phoneInput)
{
  $digits = normalize_phone_digits($phoneInput);
  return "https://wa.me/" . $digits;
}

function format_br_phone_display(?string $raw): string {
  if (!$raw) return '';

  // remove tudo que não for dígito
  $digits = preg_replace('/\D+/', '', $raw);

  // se vier com 55 na frente, remove
  if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
    $digits = substr($digits, 2);
  }

  // precisa ter DDD + número
  if (strlen($digits) < 10) return $digits;

  $ddd = substr($digits, 0, 2);
  $num = substr($digits, 2);

  // celular BR (11 dígitos: 9 + 8)
  if (strlen($num) === 9) {
    $p1 = substr($num, 0, 5); // 9xxxx
    $p2 = substr($num, 5, 4);
    return "($ddd) $p1-$p2";
  }

  // fixo BR (8 dígitos)
  if (strlen($num) === 8) {
    $p1 = substr($num, 0, 4);
    $p2 = substr($num, 4, 4);
    return "($ddd) $p1-$p2";
  }

  // fallback
  return "($ddd) $num";
}

if (!function_exists('format_br_phone')) {
  function format_br_phone(?string $phone): string {
    $digits = preg_replace('/\D+/', '', (string)$phone);

    // remove 55 se vier com DDI
    if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
      $digits = substr($digits, 2);
    }

    // (11) 91234-5678
    if (strlen($digits) === 11) {
      return sprintf('(%s) %s-%s',
        substr($digits, 0, 2),
        substr($digits, 2, 5),
        substr($digits, 7, 4)
      );
    }

    // (11) 1234-5678
    if (strlen($digits) === 10) {
      return sprintf('(%s) %s-%s',
        substr($digits, 0, 2),
        substr($digits, 2, 4),
        substr($digits, 6, 4)
      );
    }

    return $digits; // fallback
  }
}