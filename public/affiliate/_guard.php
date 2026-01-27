<?php
// public/affiliate/_guard.php
session_start();

// Afiliado logado?
$isLogged = !empty($_SESSION['affiliate_id']);

if (!$isLogged) {
  $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  if ($current !== '/login.php') {
    header("Location: /login.php");
    exit;
  }
}