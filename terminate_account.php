<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
if (mysqli_query($conn, "DELETE FROM users WHERE id = $user_id")) {
    session_unset(); session_destroy();
    header("Location: register.php?status=terminated"); exit();
}
?>