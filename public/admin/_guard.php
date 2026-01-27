<?php
session_start();

// Admin logado?
$isLogged = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

if (!$isLogged) {
  // evita loop se já estiver no login
  $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  if ($current !== '/login.php') {
    header("Location: /login.php");
    exit;
  }
}