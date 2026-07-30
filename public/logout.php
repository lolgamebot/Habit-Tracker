<?php
require "../includes/helpers.php";
initSecureSession();

$_SESSION = [];
session_destroy();

header("Location: login.php");
exit;