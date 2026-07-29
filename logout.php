<?php
require_once './config/config.php';
require_once './config/database.php';
require_once './includes/auth.php';
require_once './includes/functions.php';
session_destroy();
redirect('/index.php');
exit;
