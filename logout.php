<?php
require_once './includes/bootstrap.php';
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
