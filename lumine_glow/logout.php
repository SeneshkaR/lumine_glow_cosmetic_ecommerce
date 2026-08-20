<?php
session_start();
require_once 'includes/functions.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

setFlashMessage('success', 'You have been logged out successfully.');
header('Location: login.php');
exit;