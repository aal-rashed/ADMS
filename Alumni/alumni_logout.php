<?php
require_once __DIR__ . '/adms_session.php';
adms_session_start_alumni();
session_destroy();
header("Location: index.php");
exit();
?>
