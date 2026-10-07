<?php
// Simple Logout
require_once __DIR__ . '/functions.php';

start_session();
clear_session();
redirect('index.php');
