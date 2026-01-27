<?php
session_start();

$cfg = require __DIR__ . '/../../config.php';

// verifica se o admin está logado
$isLogged = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

if (!$isLogged) {
  // evita loop se já estiver no login
  $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  if ($current !== '/login.php') {
    header("Location: /login.php");
    exit;
  }
}