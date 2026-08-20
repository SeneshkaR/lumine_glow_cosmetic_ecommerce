<?php
session_start();
require_once 'includes/functions.php';

// Clear session
$_SESSION = [];
session_destroy();

setFlashMessage('success', 'You have been logged out.');
header('Location: login.php');
exit;