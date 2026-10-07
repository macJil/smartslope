<?php
/**
 * SmartSlope - Logout Handler
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
clear_session();
redirect('index.php');
