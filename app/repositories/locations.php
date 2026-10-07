<?php
declare(strict_types=1);

// Locations database operations.

function get_locations(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name")->fetchAll();
}

function get_location(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function deactivate_location(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

function get_or_create_location(float $lat, float $lng, ?string $landmark = null): array {
    if (!is_finite($lat) || !is_finite($lng) || !is_in_irisan($lat, $lng) || strlen((string)$landmark) > 255) {
        throw new InvalidArgumentException('Invalid Irisan location or address.');
    }
    $pdo = db();

    // Round to 5 decimal places
    $lat = round($lat, 5);
    $lng = round($lng, 5);

    // Check if exists
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE lat = ? AND lng = ?");
    $stmt->execute([$lat, $lng]);

    if ($loc = $stmt->fetch()) {
        if (!$loc['active']) {
            throw new InvalidArgumentException('This monitoring point was removed by an administrator.');
        }
        $savedAddress = trim((string)($loc['landmark'] ?? ''));
        $placeholder = $savedAddress === '' || strcasecmp($savedAddress, 'Map point') === 0 ||
            (bool)preg_match('/^(?:Map point,\s*)?Barangay Irisan,\s*Baguio City,\s*Benguet,\s*Philippines$/i', $savedAddress);
        if ($placeholder && trim((string)$landmark) !== '') {
            // Preserve an existing specific address, including one entered by a resident or administrator.
            $stmt = $pdo->prepare('UPDATE locations SET landmark = ? WHERE id = ? AND landmark <=> ?');
            $stmt->execute([trim((string)$landmark), $loc['id'], $loc['landmark']]);
            return get_location((int)$loc['id']) ?? $loc;
        }
        return $loc;
    }

    // Create new
    $name = sprintf('Irisan %.5f, %.5f', $lat, $lng);
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
         VALUES (?, NULL, ?, ?, ?, 'unknown', 1)"
    );
    $landmark = trim((string)$landmark);
    try {
        $stmt->execute([$name, $landmark !== '' ? $landmark : null, $lat, $lng]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') throw $error;
        $existing = $pdo->prepare('SELECT * FROM locations WHERE lat = ? AND lng = ?');
        $existing->execute([$lat, $lng]);
        $row = $existing->fetch();
        if (!$row || !$row['active']) throw $error;
        return $row;
    }
    return get_location((int)$pdo->lastInsertId());
}
