<?php
$id = (int)($_POST['reading_id'] ?? $_GET['reading_id'] ?? 0);
header('Location: ../admin.php?action=edit_reading&reading_id=' . $id, true, 307);
exit;
