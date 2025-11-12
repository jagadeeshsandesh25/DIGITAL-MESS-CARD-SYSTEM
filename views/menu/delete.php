<?php
// views/menu/delete.php

$script_path = $_SERVER['SCRIPT_NAME'];
$project_root = preg_replace('#/views/.*$#', '', $script_path) . '/';

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $db->prepare("DELETE FROM menu WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
    }
}

header("Location: " . $project_root . "views/menu/index.php");
exit;
?>