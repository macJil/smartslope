<?php
require_once __DIR__ . '/app/config.php';
start_session();

session_destroy();
header('Location: index.php');
exit;
