<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) < 2) {
    echo json_encode(['games' => []]);
    exit;
}

$like = '%' . $q . '%';
$stmt = $conn->prepare("
    SELECT id, title, image_url, release_date
    FROM games
    WHERE title LIKE ?
    ORDER BY
        CASE WHEN title LIKE ? THEN 0 ELSE 1 END,
        title ASC
    LIMIT 15
");
$starts = $q . '%';
$stmt->bind_param("ss", $like, $starts);
$stmt->execute();
$result = $stmt->get_result();

$games = [];
while ($row = $result->fetch_assoc()) {
    $games[] = $row;
}

echo json_encode(['games' => $games]);
