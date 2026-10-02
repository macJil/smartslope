<?php
require_once __DIR__ . '/app/config.php';
start_session();

require_post_csrf();
$_SESSION = [];
session_destroy();
redirect('index.php');
