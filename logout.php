<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
session_start();

$_SESSION = [];
session_destroy();
redirect('index.php');
