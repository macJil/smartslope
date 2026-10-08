<?php
// Compatibility route; the page logic now lives at the project root.
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../dashboard.php' . ($query !== '' ? '?' . $query : ''), true, 307);
exit;
