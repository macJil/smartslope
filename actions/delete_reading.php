<?php
// Compatibility route; the page logic now lives at the project root.
header('Location: ../admin.php?action=archive_reading', true, 307);
exit;
