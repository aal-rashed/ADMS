<?php
session_start();
include "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: alumni.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM alumni WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: alumni.php");
exit();
?>
