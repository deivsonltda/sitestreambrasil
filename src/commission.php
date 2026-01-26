<?php
require_once __DIR__ . '/supabase.php';

function generate_commission_for_payment(string $paymentId): array
{
  // 1) payment
  $pay = sb_request(
    'GET',
    "payments?select=id,subscription_id,amount,status,paid_at,reference_month&id=eq." . urlencode($paymentId) . "&limit=1",
    null,
    true
  );
  if (!$pay) return ['ok' => false, 'error' => 'payment_not_found'];

  $p = $pay[0];
  if (($p['status'] ?? '') !== 'paid') return ['ok' => false, 'error' => 'payment_not_paid'];

  // 2) evitar duplicidade por payment_id
  $exists = sb_request(
    'GET',
    "commissions?select=id&payment_id=eq." . urlencode($paymentId) . "&limit=1",
    null,
    true
  );
  if ($exists) return ['ok' => true, 'skipped' => true, 'reason' => 'already_exists'];

  $subscriptionId = $p['subscription_id'] ?? null;
  if (!$subscriptionId) return ['ok' => false, 'error' => 'missing_subscription_id'];

  // 3) subscription -> customer -> affiliate
  $sub = sb_request(
    'GET',
    "subscriptions?select=id,customer_id,monthly_price&id=eq." . urlencode($subscriptionId) . "&limit=1",
    null,
    true
  );
  if (!$sub) return ['ok' => false, 'error' => 'subscription_not_found'];
  $sub = $sub[0];

  $customerId = $sub['customer_id'] ?? null;
  if (!$customerId) return ['ok' => false, 'error' => 'missing_customer_id'];

  $cust = sb_request(
    'GET',
    "customers?select=id,affiliate_id&id=eq." . urlencode($customerId) . "&limit=1",
    null,
    true
  );
  if (!$cust) return ['ok' => false, 'error' => 'customer_not_found'];
  $cust = $cust[0];

  $affiliateId = $cust['affiliate_id'] ?? null;
  if (!$affiliateId) {
    return ['ok' => true, 'skipped' => true, 'reason' => 'no_affiliate'];
  }

  // 4) regra do afiliado
  $rule = sb_request(
    'GET',
    "affiliate_commission_rules?select=type,adhesion_amount,percent&affiliate_id=eq." . urlencode($affiliateId) . "&limit=1",
    null,
    true
  );
  if (!$rule) return ['ok' => false, 'error' => 'missing_rule'];
  $rule = $rule[0];

  $ruleType = $rule['type'] ?? 'adhesion_fixed';

  // 5) contar pagamentos pagos pra saber se é adesão
  $paidList = sb_request(
    'GET',
    "payments?select=id&subscription_id=eq." . urlencode($subscriptionId) . "&status=eq.paid",
    null,
    true
  );
  $paidCount = is_array($paidList) ? count($paidList) : 0;

  $amount = 0.0;

  if ($ruleType === 'adhesion_fixed') {
    if ($paidCount !== 1) return ['ok' => true, 'skipped' => true, 'reason' => 'not_first_payment'];
    $amount = (float)($rule['adhesion_amount'] ?? 10.00);
  } elseif ($ruleType === 'recurring_percent') {
    $percent = (float)($rule['percent'] ?? 0);
    if ($percent <= 0 || $percent > 1) return ['ok' => false, 'error' => 'invalid_percent'];

    $base = (float)($p['amount'] ?? 19.90);
    if ($base <= 0) $base = (float)($sub['monthly_price'] ?? 19.90);

    $amount = round($base * $percent, 2);
  } else {
    return ['ok' => false, 'error' => 'unknown_rule_type'];
  }

  if ($amount <= 0) return ['ok' => true, 'skipped' => true, 'reason' => 'amount_zero'];

  // reference_month tem que ser DATE tipo 2025-12-01
  $referenceMonth = $p['reference_month'] ?? null;
  if ($referenceMonth) $referenceMonth = substr($referenceMonth, 0, 10);

  // 6) INSERE no schema real da sua tabela commissions
  sb_request('POST', 'commissions', [
    'affiliate_id' => $affiliateId,
    'payment_id' => $paymentId,
    'rule_type' => $ruleType,
    'amount' => $amount,
    'reference_month' => $referenceMonth
  ], true);

  return ['ok' => true, 'skipped' => false, 'amount' => $amount, 'rule_type' => $ruleType];
}
