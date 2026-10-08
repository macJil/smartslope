<?php
// Compatibility route; the page logic now lives at the project root.
header('Location: ../dashboard.php?action=select_location', true, 307);
exit;
