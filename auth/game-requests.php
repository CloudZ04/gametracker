<?php
// Prevent any output buffering issues
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Custom error handler to prevent HTML error output
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    return true;
}
set_error_handler("handleError");

// Custom exception handler
function handleException($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit(1);
}
set_exception_handler("handleException");

require_once '../includes/db.php';
session_start();

// Security check: Verify user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
    
        $action = $_POST['action'];
        error_log("Received action: " . $action);
        
        $response = null;
    
        switch ($action) {
            case 'get_requests':
                $response = getRequests();
                break;
            case 'review_request':
                $response = reviewRequest();
                break;
            case 'bulk_review':
                $response = bulkReviewRequests();
                break;
            case 'get_stats':
                $response = getRequestStats();
                break;
            default:
                throw new Exception("Invalid action: " . $action);
        }
        
        if ($response === null) {
            throw new Exception("No response generated");
        }
        
        if (ob_get_length()) ob_clean();
        echo json_encode($response);
        
    } catch (Throwable $e) {
        error_log("Error processing request: " . $e->getMessage());
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit();
}

function getRequests() {
    global $conn;
    
    $page = (int)($_POST['page'] ?? 1);
    $status = $_POST['status'] ?? 'pending';
    
    // Get all requests for client-side pagination
    $whereConditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $whereConditions[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM game_requests $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($params) {
        $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $countStmt->execute();
    $totalRequests = $countStmt->get_result()->fetch_row()[0];
    
    // Get requests with user information
    $sql = "SELECT gr.*, u.username 
            FROM game_requests gr 
            LEFT JOIN users u ON gr.user_id = u.id 
            $whereClause 
            ORDER BY gr.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    return [
        'success' => true,
        'requests' => $requests,
        'total_requests' => $totalRequests
    ];
}

function reviewRequest() {
    global $conn;
    
    $requestId = (int)$_POST['id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE game_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $status, $requestId);
    $success = $stmt->execute();
    
    return ['success' => $success];
}

function bulkReviewRequests() {
    global $conn;
    
    $requestIds = json_decode($_POST['request_ids'], true);
    $status = $_POST['status'];
    
    if (!is_array($requestIds) || empty($requestIds)) {
        return ['success' => false, 'error' => 'No requests selected'];
    }
    
    $placeholders = str_repeat('?,', count($requestIds) - 1) . '?';
    $params = array_merge([$status], $requestIds);
    $types = 's' . str_repeat('i', count($requestIds));
    
    $stmt = $conn->prepare("UPDATE game_requests SET status = ? WHERE request_id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $success = $stmt->execute();
    
    return ['success' => $success];
}

function getRequestStats() {
    global $conn;
    
    $stats = [];
    
    // Pending count
    $result = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'pending'");
    $stats['pending'] = $result->fetch_row()[0];
    
    // Approved today
    $result = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'approved' AND DATE(updated_at) = CURDATE()");
    $stats['approved_today'] = $result->fetch_row()[0];
    
    // Rejected today
    $result = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'rejected' AND DATE(updated_at) = CURDATE()");
    $stats['rejected_today'] = $result->fetch_row()[0];
    
    // Total requests
    $result = $conn->query("SELECT COUNT(*) FROM game_requests");
    $stats['total'] = $result->fetch_row()[0];
    
    return $stats;
}

// Get initial stats for display
$pendingCount = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'pending'")->fetch_row()[0];
$approvedToday = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'approved' AND DATE(updated_at) = CURDATE()")->fetch_row()[0];
$rejectedToday = $conn->query("SELECT COUNT(*) FROM game_requests WHERE status = 'rejected' AND DATE(updated_at) = CURDATE()")->fetch_row()[0];
$totalRequests = $conn->query("SELECT COUNT(*) FROM game_requests")->fetch_row()[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Requests - GameTracker Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/styles.css">
    
    <style>
        :root {
            --primary-color: #b200ff;
        }

        .hero-section {
            position: relative;
            padding: 6rem 0 4rem;
            background: linear-gradient(to right, rgba(21, 21, 30, 0.9), rgba(21, 21, 30, 0.7));
            border-bottom: 1px solid rgba(127, 0, 255, 0.3);
            margin-bottom: 2rem;
        }

        .hero-section h1 {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(127, 0, 255, 0.5);
            box-shadow: 0 0 20px rgba(178, 0, 255, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #a8a8b3;
            font-size: 0.95rem;
        }

        .request-card {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 1.5rem;
        }

        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(178, 0, 255, 0.5);
            border-color: rgba(127, 0, 255, 0.5);
        }

        .request-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 250px;
        }

        .request-info {
            padding: 1rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .request-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.25rem;
            color: #ffffff;
            margin: 0;
            line-height: 1.4;
        }

        .request-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin: 0;
        }

        .meta-item {
            background: rgba(30, 30, 47, 0.5);
            padding: 0.35rem;
            border-radius: 6px;
            text-align: center;
            border: 1px solid rgba(127, 0, 255, 0.1);
        }

        .meta-label {
            font-size: 0.75rem;
            color: #a8a8b3;
            margin-bottom: 0.15rem;
        }

        .meta-value {
            font-weight: bold;
            color: #ffffff;
            font-size: 0.85rem;
        }

        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }

        .request-description {
            color: #a8a8b3;
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 0;
            max-height: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .request-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0;
        }

        .tag {
            background: rgba(178, 0, 255, 0.1);
            border: 1px solid rgba(178, 0, 255, 0.2);
            color: #ffffff;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .bulk-actions {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .bulk-actions .form-check {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            margin: 0;
            height: 38px;
        }

        .bulk-actions .form-check-input {
            margin: 0;
        }

        .bulk-actions .form-check-label {
            margin: 0 0 0 0.5rem;
            color: #ffffff;
            font-size: 0.9rem;
        }

        #selectedCount {
            padding: 0.5rem 1rem;
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
        }

        .bulk-actions .d-flex {
            gap: 1rem !important;
        }

        .game-select-area {
            background: rgba(30, 30, 47, 0.5);
            border-left: 1px solid rgba(127, 0, 255, 0.1);
            margin: 0;
            height: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 250px; /* Match the image height */
        }

        .game-select-area:hover {
            background: rgba(30, 30, 47, 0.8);
        }

        .game-select-area .form-check-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .game-select-area::after {
            content: '\F26B';
            font-family: "bootstrap-icons";
            font-size: 2rem;
            color: rgba(178, 0, 255, 0.4);
            pointer-events: none;
            transition: all 0.2s ease;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .game-select-area.checked {
            background: rgba(178, 0, 255, 0.3);
        }

        .game-select-area.checked::after {
            color: white;
        }

        .game-select-area:hover::after {
            color: rgba(178, 0, 255, 0.7);
        }

        @keyframes subtlePulse {
            0% { opacity: 0.5; }
            50% { opacity: 0.7; }
            100% { opacity: 0.5; }
        }

        .game-select-area:not(.checked)::after {
            animation: subtlePulse 2s infinite;
        }

        .pagination {
            margin: 0;
            gap: 0.25rem;
        }

        .pagination .page-link {
            background: rgba(127, 0, 255, 0.1);
            border: 1px solid rgba(127, 0, 255, 0.2);
            color: #ffffff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background: rgba(127, 0, 255, 0.2);
            border-color: rgba(127, 0, 255, 0.3);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
        }

        .pagination .page-item.disabled .page-link {
            background: rgba(127, 0, 255, 0.05);
            border-color: rgba(127, 0, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
        }

        .additional-info {
            background: rgba(30, 30, 47, 0.5);
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
        }

        .additional-info-text {
            color: #a8a8b3;
            font-size: 0.9rem;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .empty-state {
            background: #1e1e2f;
            border: 1px solid rgba(127, 0, 255, 0.1);
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            margin: 2rem 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            font-family: 'Orbitron', sans-serif;
            color: #ffffff;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 4rem 0 2rem;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 2.5rem;
            }

            .request-meta {
                grid-template-columns: repeat(2, 1fr);
            }

            .request-image {
                height: 200px;
                min-height: auto;
            }

            .bulk-actions {
                padding: 1rem;
            }

            .bulk-actions .d-flex {
                flex-direction: column;
            }

            .bulk-actions .form-check,
            .bulk-actions button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto text-center">
                <h1 class="display-4 mb-3">Game Request Center</h1>
                <p class="lead">Review and manage user-submitted game requests. Approve quality submissions to expand our game database.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <!-- Stats Dashboard -->
    <div class="row stats-row g-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="pendingCount"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="approvedCount"><?= $approvedToday ?></div>
                <div class="stat-label">Approved Today</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="rejectedCount"><?= $rejectedToday ?></div>
                <div class="stat-label">Rejected Today</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" id="totalCount"><?= $totalRequests ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <div class="d-flex align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label class="form-check-label" for="selectAll">Select All</label>
            </div>
            <button type="button" class="btn btn-success" onclick="window.bulkApprove()">
                <i class="bi bi-check-circle me-2"></i>Approve Selected
            </button>
            <button type="button" class="btn btn-danger" onclick="window.bulkReject()">
                <i class="bi bi-x-circle me-2"></i>Reject Selected
            </button>
            <span id="selectedCount" class="ms-auto me-3">0 selected</span>
            <div class="btn-group" role="group" aria-label="Status filters">
                <button type="button" class="btn btn-outline-warning active" onclick="filterByStatus('pending')">
                    <i class="bi bi-clock-history me-1"></i>Pending
                </button>
                <button type="button" class="btn btn-outline-success" onclick="filterByStatus('approved')">
                    <i class="bi bi-check-circle me-1"></i>Approved
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('rejected')">
                    <i class="bi bi-x-circle me-1"></i>Rejected
                </button>
            </div>
        </div>
    </div>

    <!-- Requests Container -->
    <div id="requestsContainer">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading requests...</p>
        </div>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
</div>

<?php include '../includes/footer.php'; ?>


<script>
// Wrap all JavaScript in try-catch
try {
    console.log('Main script starting');
    
    // Global variables
    let currentPage = 1;
    let totalPages = 1;
    let selectedRequests = new Set();

    // Make functions globally available
    window.bulkApprove = async function() {
        await bulkAction('approved');
    }

    window.bulkReject = async function() {
        await bulkAction('rejected');
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }

    async function bulkAction(status) {
        const selectedIds = Array.from(selectedRequests);
        if (selectedIds.length === 0) {
            showNotification('Please select at least one request', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'bulk_review');
            formData.append('request_ids', JSON.stringify(selectedIds));
            formData.append('status', status);

            const response = await fetch('game-requests.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showNotification(`Successfully ${status} ${selectedIds.length} requests`, 'success');
                selectedRequests.clear();
                updateSelectedCount();
                loadRequests(1);
                updateStats();
            } else {
                throw new Error(data.error || 'Failed to update requests');
            }
        } catch (error) {
            console.error('Error in bulk action:', error);
            showNotification(error.message, 'error');
        }
    }

    async function handleSingleAction(requestId, status) {
        try {
            const formData = new FormData();
            formData.append('action', 'review_request');
            formData.append('id', requestId);
            formData.append('status', status);

            const response = await fetch('game-requests.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showNotification(`Successfully ${status} request`, 'success');
                loadRequests(1);
                updateStats();
            } else {
                throw new Error(data.error || 'Failed to update request');
            }
        } catch (error) {
            console.error('Error in single action:', error);
            showNotification(error.message, 'error');
        }
    }

    async function loadRequests(page = 1) {
        console.log('loadRequests called with page:', page);
        const container = document.getElementById('requestsContainer');
        const requestsPerPage = 10;

        try {
            const formData = new FormData();
            formData.append('action', 'get_requests');
            formData.append('page', 1);
            formData.append('status', document.querySelector('.btn-group .btn.active').textContent.trim().toLowerCase());

            console.log('Fetching requests...');
            const response = await fetch('game-requests.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('Requests data received:', data);

            if (data.success && data.requests && data.requests.length > 0) {
                const allRequests = data.requests;
                const totalRequests = allRequests.length;
                const totalPages = Math.ceil(totalRequests / requestsPerPage);
                const startIndex = (page - 1) * requestsPerPage;
                const endIndex = Math.min(startIndex + requestsPerPage, totalRequests);
                const currentPageRequests = allRequests.slice(startIndex, endIndex);

                container.innerHTML = currentPageRequests.map(request => `
                    <div class="request-card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <img src="${request.image_path ? '../' + request.image_path : '../images/logo.png'}" 
                                     class="request-image" alt="${request.game_title}"
                                     onerror="this.src='../images/logo.png'">
                            </div>
                            <div class="col-md-7">
                                <div class="request-info">
                                    <h3 class="request-title">${request.game_title}</h3>
                                    <div class="request-meta">
                                        <div class="meta-item">
                                            <div class="meta-label">Requested By</div>
                                            <div class="meta-value">${request.username}</div>
                                        </div>
                                        <div class="meta-item">
                                            <div class="meta-label">Date</div>
                                            <div class="meta-value">${new Date(request.created_at).toLocaleDateString()}</div>
                                        </div>
                                        <div class="meta-item">
                                            <div class="meta-label">Status</div>
                                            <div class="meta-value status-${request.status}">${capitalizeFirst(request.status)}</div>
                                        </div>
                                    </div>
                                    <div class="request-description">${request.description || ''}</div>
                                    ${request.additional_info ? `
                                        <div class="additional-info">
                                            <div class="additional-info-text">${request.additional_info}</div>
                                        </div>
                                    ` : ''}
                                    <div class="request-tags">
                                        ${request.platforms ? request.platforms.split(',').map(platform => 
                                            `<span class="tag">${platform.trim()}</span>`
                                        ).join('') : ''}
                                    </div>
                                    ${request.release_year ? `
                                        <div class="request-tags">
                                            <span class="tag">Release Year: ${request.release_year}</span>
                                        </div>
                                    ` : ''}
                                    ${request.status === 'pending' ? `
                                        <div class="request-actions mt-3">
                                            <button class="btn btn-success btn-sm me-2" onclick="handleSingleAction(${request.request_id}, 'approved')">
                                                <i class="bi bi-check-circle me-1"></i>Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="handleSingleAction(${request.request_id}, 'rejected')">
                                                <i class="bi bi-x-circle me-1"></i>Reject
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="col-md-1 p-0">
                                <div class="form-check game-select-area h-100 m-0 ${selectedRequests.has(request.request_id) ? 'checked' : ''}">
                                    <input class="form-check-input request-checkbox" 
                                           type="checkbox" 
                                           value="${request.request_id}" 
                                           id="request-${request.request_id}"
                                           onchange="handleCheckboxChange(this)"
                                           ${selectedRequests.has(request.request_id) ? 'checked' : ''}>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                const paginationHtml = `
                    <nav aria-label="Request list navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item ${page === 1 ? 'disabled' : ''}">
                                <button class="page-link" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            </li>
                            ${Array.from({length: totalPages}, (_, i) => i + 1).map(pageNum => `
                                <li class="page-item ${pageNum === page ? 'active' : ''}">
                                    <button class="page-link" data-page="${pageNum}">
                                        ${pageNum}
                                    </button>
                                </li>
                            `).join('')}
                            <li class="page-item ${page === totalPages ? 'disabled' : ''}">
                                <button class="page-link" data-page="${page + 1}" ${page === totalPages ? 'disabled' : ''}>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </li>
                        </ul>
                        <div class="text-center mt-2">
                            Showing ${startIndex + 1}-${endIndex} of ${totalRequests} requests
                        </div>
                    </nav>
                `;
                
                const paginationContainer = document.getElementById('pagination');
                if (paginationContainer) {
                    paginationContainer.innerHTML = paginationHtml;

                    paginationContainer.querySelectorAll('.page-link').forEach(button => {
                        button.addEventListener('click', (e) => {
                            if (!button.disabled) {
                                const pageNum = parseInt(button.dataset.page);
                                loadRequests(pageNum);
                                
                                document.getElementById('requestsContainer').scrollIntoView({ 
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }
                        });
                    });
                }

                document.querySelectorAll('.request-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const requestId = parseInt(this.value);
                        if (this.checked) {
                            selectedRequests.add(requestId);
                        } else {
                            selectedRequests.delete(requestId);
                        }
                        updateSelectedCount();
                    });
                });
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-inbox-fill"></i>
                        <h3>No requests found</h3>
                        <p>There are currently no user requested games to review in this category.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error in loadRequests:', error);
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-exclamation-circle"></i>
                    <h3>Error loading requests</h3>
                    <p>${error.message}</p>
                    <button class="btn btn-primary mt-3" onclick="loadRequests()">Retry</button>
                </div>
            `;
        }
    }

    async function updateStats() {
        try {
            console.log('Updating stats...');
            const formData = new FormData();
            formData.append('action', 'get_stats');

            const response = await fetch('game-requests.php', {
                method: 'POST',
                body: formData
            });

            const stats = await response.json();
            console.log('Stats received:', stats);

            document.getElementById('pendingCount').textContent = stats.pending || 0;
            document.getElementById('approvedCount').textContent = stats.approved_today || 0;
            document.getElementById('rejectedCount').textContent = stats.rejected_today || 0;
            document.getElementById('totalCount').textContent = stats.total || 0;
        } catch (error) {
            console.error('Error updating stats:', error);
        }
    }

    function capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    function updateSelectedCount() {
        const count = selectedRequests.size;
        document.getElementById('selectedCount').textContent = `${count} selected`;
        document.getElementById('selectAll').checked = count > 0 && count === document.querySelectorAll('.request-checkbox').length;
    }

    function toggleSelectAll() {
        const isChecked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.request-checkbox').forEach(checkbox => {
            checkbox.checked = isChecked;
            const requestId = parseInt(checkbox.value);
            const selectArea = checkbox.closest('.game-select-area');
            
            if (isChecked) {
                selectedRequests.add(requestId);
                selectArea.classList.add('checked');
            } else {
                selectedRequests.delete(requestId);
                selectArea.classList.remove('checked');
            }
        });
        updateSelectedCount();
    }

    function handleCheckboxChange(checkbox) {
        const requestId = parseInt(checkbox.value);
        const selectArea = checkbox.closest('.game-select-area');
        
        if (checkbox.checked) {
            selectedRequests.add(requestId);
            selectArea.classList.add('checked');
        } else {
            selectedRequests.delete(requestId);
            selectArea.classList.remove('checked');
        }
        
        updateSelectedCount();
    }

    function filterByStatus(status) {
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
            if(btn.textContent.toLowerCase().includes(status)) {
                btn.classList.add('active');
            }
        });
        
        selectedRequests.clear();
        updateSelectedCount();
        
        loadRequests(1);
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        loadRequests();
        updateStats();
    });

    console.log('Main script completed setup');
} catch (error) {
    console.error('Error in main script:', error);
}
</script>
</body>
</html>