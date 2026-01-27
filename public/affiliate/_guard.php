<?php
session_start();

$isLogged = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

if (!$isLogged) {
  header("Location: /login.php");
  exit;
}