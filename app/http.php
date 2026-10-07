<?php
declare(strict_types=1);

// End a JSON response using the same encoding options as both API endpoints.
function json_response(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}
