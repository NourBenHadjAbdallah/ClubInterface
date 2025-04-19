<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
require_once '../includes/db_connect.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

try {
    // Fetch requests: admins see all, users see their own
    if ($user['role'] === 'admin') {
        $query = "
            SELECT er.request_id, er.equipment_id, er.username, er.return_date, er.status, er.created_at, e.name AS equipment_name
            FROM equipment_requests er
            JOIN equipment e ON er.equipment_id = e.equipment_id
            ORDER BY er.created_at DESC";
        $stmt = $pdo->query($query);
    } else {
        $query = "
            SELECT er.request_id, er.equipment_id, er.username, er.return_date, er.status, er.created_at, e.name AS equipment_name
            FROM equipment_requests er
            JOIN equipment e ON er.equipment_id = e.equipment_id
            WHERE er.username = ?
            ORDER BY er.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user['username']]);
    }
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error fetching requests: " . $e->getMessage();
    $requests = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $user['role'] === 'admin') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        $request_id = $_POST['request_id'] ?? '';
        if (empty($request_id)) {
            $error = "Invalid request ID";
        } else {
            try {
                $pdo->beginTransaction();

                if ($_POST['action'] === 'approve_request') {
                    // Update request status to approved
                    $stmt = $pdo->prepare("UPDATE equipment_requests SET status = 'approved' WHERE request_id = ?");
                    $stmt->execute([$request_id]);

                    // Get equipment_id
                    $stmt = $pdo->prepare("SELECT equipment_id FROM equipment_requests WHERE request_id = ?");
                    $stmt->execute([$request_id]);
                    $equipment_id = $stmt->fetchColumn();

                    if ($equipment_id) {
                        // Set equipment as unavailable
                        $stmt = $pdo->prepare("UPDATE equipment SET available = 0 WHERE equipment_id = ?");
                        $stmt->execute([$equipment_id]);
                    } else {
                        throw new PDOException("Equipment ID not found for request");
                    }

                    $success = "Request approved successfully!";
                } elseif ($_POST['action'] === 'deny_request') {
                    // Update request status to denied
                    $stmt = $pdo->prepare("UPDATE equipment_requests SET status = 'denied' WHERE request_id = ?");
                    $stmt->execute([$request_id]);

                    // Get equipment_id
                    $stmt = $pdo->prepare("SELECT equipment_id FROM equipment_requests WHERE request_id = ?");
                    $stmt->execute([$request_id]);
                    $equipment_id = $stmt->fetchColumn();

                    if ($equipment_id) {
                        // Check for other active requests
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_requests WHERE equipment_id = ? AND status IN ('pending', 'approved')");
                        $stmt->execute([$equipment_id]);
                        $active_requests = $stmt->fetchColumn();

                        if ($active_requests == 0) {
                            // No active requests, set available = TRUE
                            $stmt = $pdo->prepare("UPDATE equipment SET available = 1 WHERE equipment_id = ?");
                            $stmt->execute([$equipment_id]);
                        }
                    }

                    $success = "Request denied successfully!";
                }

                $pdo->commit();
                header('Location: equipment_requests.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Requests - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/equipment_shop.css"> <!-- Reuse same CSS for consistency -->
</head>
<body class="gradient-bg min-h-screen text-gray-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64 md:p-8">
            <!-- Header -->
            <?php include '../includes/header.php'; ?>

            <!-- Mobile Menu Button -->
            <button id="openSidebar" class="md:hidden mb-6 p-2 bg-indigo-600 text-white rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="shop-container">
                <h1 class="text-3xl font-bold mb-6">Equipment Requests</h1>
                <?php if ($error): ?>
                    <div class="error-message bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Requests List -->
                <h2 class="text-2xl font-semibold mb-4"><?php echo $user['role'] === 'admin' ? 'All Requests' : 'My Requests'; ?></h2>
                <?php if (empty($requests)): ?>
                    <p class="text-gray-600">No requests found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border rounded-lg">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Equipment</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Requested By</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Return Date</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Requested On</th>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr class="border-t">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($request['equipment_name']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($request['username']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($request['return_date']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars(ucfirst($request['status'])); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($request['created_at']); ?></td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <td class="py-3 px-4 space-x-2">
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="approve_request">
                                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700">Approve</button>
                                                    </form>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="deny_request">
                                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700">Deny</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>