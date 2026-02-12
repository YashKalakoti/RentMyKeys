<?php
require_once "includes/functions.php";

$_SESSION = array();

session_destroy();

header("Location: login.php");
exit();
?>