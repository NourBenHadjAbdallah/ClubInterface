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
    // Select equipment where available = TRUE (1)
    $query = "
        SELECT equipment_id, name, brand, model, specifications, created_at, available
        FROM equipment
        WHERE available = 1
        ORDER BY created_at DESC";
    $stmt = $pdo->query($query);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error fetching equipment: " . $e->getMessage();
    $equipment = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment' && $user['role'] === 'admin') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        $equipment_id = $_POST['equipment_id'] ?? '';
        if (empty($equipment_id)) {
            $error = "Invalid equipment ID";
        } else {
            try {
                // Check for active requests
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_requests WHERE equipment_id = ? AND status IN ('pending', 'approved')");
                $stmt->execute([$equipment_id]);
                $active_requests = $stmt->fetchColumn();

                if ($active_requests > 0) {
                    $error = "Cannot delete equipment with active requests.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM equipment WHERE equipment_id = ?");
                    $stmt->execute([$equipment_id]);
                    $success = "Equipment deleted successfully!";
                    header('Location: equipment_shop.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_equipment' && $user['role'] !== 'admin') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        $equipment_id = $_POST['equipment_id'] ?? '';
        $return_date = $_POST['return_date'] ?? '';

        $errors = [];
        if (empty($equipment_id)) $errors[] = "Equipment selection is required";
        if (empty($return_date)) $errors[] = "Valid return date is required";
        else {
            try {
                $return_date_time = new DateTime($return_date);
                $now = new DateTime();
                if ($return_date_time <= $now) {
                    $errors[] = "Return date must be in the future";
                }
            } catch (Exception $e) {
                $errors[] = "Invalid return date format";
            }
        }
        if (!isset($user['username']) || empty($user['username'])) {
            $errors[] = "User session invalid (missing username)";
        }

        if (empty($errors)) {
            try {
                // Insert request without changing availability
                $request_id = uniqid('req_');
                $stmt = $pdo->prepare("
                    INSERT INTO equipment_requests (request_id, equipment_id, username, return_date, status, created_at, request_date)
                    VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                $stmt->execute([$request_id, $equipment_id, $user['username'], $return_date]);

                $success = "Equipment request submitted successfully! Awaiting admin approval.";
                header('Location: equipment_requests.php');
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Shop - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/equipment_shop.css">
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
                <h1 class="text-3xl font-bold mb-6">Equipment Shop</h1>
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

                <!-- Equipment List -->
                <h2 class="text-2xl font-semibold mb-4">Available Equipment</h2>
                <?php if (empty($equipment)): ?>
                    <p class="text-gray-600">No equipment available.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($equipment as $item): ?>
                            <div class="equipment-card">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="text-sm text-gray-600">Brand: <?php echo htmlspecialchars($item['brand']); ?></p>
                                <p class="text-sm text-gray-600">Model: <?php echo htmlspecialchars($item['model']); ?></p>
                                <p class="text-sm text-gray-600">Specifications: <?php echo htmlspecialchars($item['specifications']); ?></p>
                                <p class="text-sm text-gray-600">Available: <?php echo $item['available'] ? 'Yes' : 'No'; ?></p>
                                <p class="text-sm text-gray-500">Added: <?php echo htmlspecialchars($item['created_at']); ?></p>
                                <div class="mt-3 space-x-2">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete_equipment">
                                            <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($item['equipment_id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="request_equipment">
                                            <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($item['equipment_id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <div class="flex items-center space-x-2">
                                                <input 
                                                    type="datetime-local" 
                                                    name="return_date" 
                                                    required 
                                                    class="equipment-input w-40"
                                                    placeholder="Select return date"
                                                >
                                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Request</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>