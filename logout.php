<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';
start_session();

$_SESSION = [];
session_destroy();
redirect('index.php');
