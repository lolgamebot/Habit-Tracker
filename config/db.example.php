<?php
/**
 * Database configuration for Habit Tracker.
 *
 * LOCAL (XAMPP):
 *   host = "localhost"
 *   dbname = "habittracker"
 *   dbuser = "root"
 *   dbpass = ""
 *
 * INFINITYFREE:
 *   Log in to your InfinityFree control panel -> MySQL Databases.
 *   The panel gives you a MySQL hostname (e.g. sqlXXX.infinityfree.com),
 *   a database name (e.g. if0_XXXXX_habittracker), a username and password.
 *   "localhost" does NOT work on InfinityFree - you must use the exact
 *   hostname from the panel.
 *
 *   host = "sqlXXX.infinityfree.com"   (from the panel)
 *   dbname = "if0_XXXXX_habittracker"  (from the panel)
 *   dbuser = "if0_XXXXX"               (from the panel)
 *   dbpass = "YOUR_DB_PASSWORD"        (from the panel)
 */

$host = "localhost";
$dbname = "habittracker";
$dbuser = "root";
$dbpass = "";

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Connection failed: " . $e->getMessage());
}
