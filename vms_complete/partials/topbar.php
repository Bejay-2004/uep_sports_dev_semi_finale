<?php
// partials/topbar.php

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/../auth/guard.php";
require_once __DIR__ . "/../config/db.php";

require_role("coach");

if (!isset($_SESSION['user'])) {
  return; // ❗ do NOT redirect from here
}

$full_name = $_SESSION['user']['full_name'];
$role      = $_SESSION['user']['user_role'];
$sports_id = (int)$_SESSION['user']['sports_id'];

// Get the actual sport name from database
$sports_name = 'Unknown Sport';
try {
  $stmt = $pdo->prepare("SELECT sports_name FROM tbl_sports WHERE sports_id = :sports_id LIMIT 1");
  $stmt->execute(['sports_id' => $sports_id]);
  $sport = $stmt->fetch();
  if ($sport) {
    $sports_name = $sport['sports_name'];
  }
} catch (PDOException $e) {
  // If query fails, just show the ID
  $sports_name = "Sport #$sports_id";
}
?>

<header class="topbar">
  <strong><?= htmlspecialchars($full_name) ?></strong>
  <span>Role: <?= htmlspecialchars(ucfirst($role)) ?></span>
  <span>Sport: <?= htmlspecialchars($sports_name) ?></span>

  <form action="<?= BASE_URL ?>/auth/logout.php" method="post">
    <button type="submit">Logout</button>
  </form>
</header>