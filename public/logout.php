<?php
require "../includes/bootstrap.php";

$_SESSION = [];
session_destroy();

header("Location: login.php");
exit;
