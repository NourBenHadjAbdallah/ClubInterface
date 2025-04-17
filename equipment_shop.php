<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Include database connection
require_once 'db_connect.php';

// Load equipment
try {
    $stmt = $pdo->query("SELECT * FROM equipment WHERE equipment_id IS NOT NULL");
    $equipment = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $equipment[$row['equipment_id']] = $row;
    }
} catch (PDOException $e) {
    $error = "Database error fetching equipment: " . $e->getMessage();
    error_log("Fetch equipment failed: " . $e->getMessage());
    $equipment = [];
}

// Load equipment requests
try {
    $stmt = $pdo->query("
        SELECT er.*, e.name AS equipment_name, e.brand, e.model 
        FROM equipment_requests er 
        JOIN equipment e ON er.equipment_id = e.equipment_id
    ");
    $equipment_requests = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $equipment_requests[$row['request_id']] = $row;
    }
} catch (PDOException $e) {
    $error = "Database error fetching requests: " . $e->getMessage();
    error_log("Fetch requests failed: " . $e->getMessage());
    $equipment_requests = [];
}

// Load notifications
$notifications_file = 'notifications.json';
$notifications = file_exists($notifications_file) ? json_decode(file_get_contents($notifications_file), true) : [];

// Load users for notifications
try {
    $stmt = $pdo->query("SELECT username FROM users");
    $all_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Database error fetching users: " . $e->getMessage();
    error_log("Fetch users failed: " . $e->getMessage());
    $all_users = [];
}

// Handle edit equipment (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'edit_equipment') {
    $equipment_id = $_POST['equipment_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

    // Validation
    $errors = [];
    if (!isset($equipment[$equipment_id])) {
        $errors[] = "Equipment not found";
    }
    if (empty($name)) {
        $errors[] = "Equipment name is required";
    }
    if (empty($brand)) {
        $errors[] = "Brand is required";
    }
    if (empty($model)) {
        $errors[] = "Model is required";
    }
    if (!is_numeric($quantity) || $quantity < 0) {
        $errors[] = "Valid quantity (0 or more) is required";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE equipment 
                SET name = ?, brand = ?, model = ?, specifications = ?, quantity = ?, available_quantity = ?, updated_at = NOW()
                WHERE equipment_id = ?
            ");
            // Adjust available_quantity: can't exceed new quantity
            $current_requests = array_filter($equipment_requests, function($req) use ($equipment_id) {
                return $req['equipment_id'] === $equipment_id && $req['status'] === 'approved';
            });
            $allocated = array_sum(array_column($current_requests, 'quantity'));
            $new_available = max(0, (int)$quantity - $allocated);
            $stmt->execute([$name, $brand, $model, $specifications, (int)$quantity, $new_available, $equipment_id]);
            $success = "AV equipment updated successfully!";
            header('Location: equipment_shop.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Edit equipment failed: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Handle delete equipment (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    $equipment_id = $_POST['equipment_id'] ?? '';
    if (isset($equipment[$equipment_id])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE equipment_id = ?");
            $stmt->execute([$equipment_id]);
            $success = "AV equipment deleted successfully!";
            header('Location: equipment_shop.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Delete equipment failed: " . $e->getMessage());
        }
    } else {
        $error = "Equipment not found";
    }
}

// Handle request equipment (all users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_equipment') {
    $equipment_id = $_POST['equipment_id'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

    // Validation
    $errors = [];
    if (!isset($equipment[$equipment_id])) {
        $errors[] = "Equipment not found";
    }
    if (!is_numeric($quantity) || $quantity < 1) {
        $errors[] = "Valid quantity (1 or more) is required";
    }
    if (isset($equipment[$equipment_id]) && $quantity > $equipment[$equipment_id]['available_quantity']) {
        $errors[] = "Requested quantity exceeds available stock";
    }

    if (empty($errors)) {
        try {
            $request_id = uniqid('req_');
            $stmt = $pdo->prepare("
                INSERT INTO equipment_requests (request_id, equipment_id, username, quantity, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$request_id, $equipment_id, $user['username'], (int)$quantity]);
            
            // Notify admins
            $equipment_name = $equipment[$equipment_id]['name'];
            $brand = $equipment[$equipment_id]['brand'];
            $model = $equipment[$equipment_id]['model'];
            $equipment_full_name = "$brand $model $equipment_name";
            foreach ($all_users as $username) {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() === 'admin' && $username !== $user['username']) {
                    $notifications[] = [
                        'id' => uniqid('notif_'),
                        'user' => $username,
                        'type' => 'equipment_request',
                        'item_id' => $request_id,
                        'message' => "{$user['username']} requested $quantity x $equipment_full_name",
                        'created_at' => date('Y-m-d H:i:s'),
                        'read' => false
                    ];
                }
            }
            file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));
            $success = "Equipment request submitted!";
            header('Location: equipment_shop.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Request equipment failed: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Handle approve/deny equipment request (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && in_array($_POST['action'], ['approve_request', 'deny_request'])) {
    $request_id = $_POST['request_id'] ?? '';
    if (!isset($equipment_requests[$request_id])) {
        $error = "Request not found";
    } else {
        $request = $equipment_requests[$request_id];
        $equipment_id = $request['equipment_id'];
        $quantity = $request['quantity'];
        $requester = $request['username'];
        $equipment_name = $request['equipment_name'];
        $brand = $request['brand'];
        $model = $request['model'];
        $equipment_full_name = "$brand $model $equipment_name";

        try {
            if ($_POST['action'] === 'approve_request') {
                if ($quantity > $equipment[$equipment_id]['available_quantity']) {
                    $error = "Not enough equipment available to approve request";
                } else {
                    $stmt = $pdo->prepare("UPDATE equipment_requests SET status = 'approved', updated_at = NOW() WHERE request_id = ?");
                    $stmt->execute([$request_id]);
                    $stmt = $pdo->prepare("UPDATE equipment SET available_quantity = available_quantity - ? WHERE equipment_id = ?");
                    $stmt->execute([(int)$quantity, $equipment_id]);
                    
                    // Notify requester
                    $notifications[] = [
                        'id' => uniqid('notif_'),
                        'user' => $requester,
                        'type' => 'equipment_request',
                        'item_id' => $request_id,
                        'message' => "Your request for $quantity x $equipment_full_name was approved",
                        'created_at' => date('Y-m-d H:i:s'),
                        'read' => false
                    ];
                    file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));
                    $success = "Request approved!";
                }
            } else { // deny_request
                $stmt = $pdo->prepare("UPDATE equipment_requests SET status = 'denied', updated_at = NOW() WHERE request_id = ?");
                $stmt->execute([$request_id]);
                
                // Notify requester
                $notifications[] = [
                    'id' => uniqid('notif_'),
                    'user' => $requester,
                    'type' => 'equipment_request',
                    'item_id' => $request_id,
                    'message' => "Your request for $quantity x $equipment_full_name was denied",
                    'created_at' => date('Y-m-d H:i:s'),
                    'read' => false
                ];
                file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));
                $success = "Request denied.";
            }
            header('Location: equipment_shop.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Approve/deny request failed: " . $e->getMessage());
        }
    }
}

// Check if editing equipment
$edit_id = $_GET['edit_id'] ?? '';
$edit_equipment = $edit_id && isset($equipment[$edit_id]) ? $equipment[$edit_id] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AV Equipment Shop - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="min-h-screen gradient-bg text-gray-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64 md:p-8">
            <!-- Header with Notification Bell -->
            <?php include 'header.php'; ?>

            <!-- Mobile Menu Button -->
            <button id="openSidebar" class="md:hidden mb-6 p-2 bg-indigo-600 text-white rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="bg-white rounded-2xl shadow-lg p-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold">AV Equipment Shop</h1>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="add_equipment.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold">
                            Add New Equipment
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Admin: Edit Equipment Form -->
                <?php if ($user['role'] === 'admin' && $edit_equipment): ?>
                    <h2 class="text-2xl font-semibold mb-4">Edit Equipment</h2>
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php elseif (isset($success)): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-6 mb-12">
                        <input type="hidden" name="action" value="edit_equipment">
                        <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($edit_equipment['equipment_id']); ?>">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Equipment Type</label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                required 
                                value="<?php echo htmlspecialchars($edit_equipment['name']); ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            >
                        </div>
                        <div>
                            <label for="brand" class="block text-sm font-medium text-gray-700">Brand</label>
                            <input 
                                type="text" 
                                id="brand" 
                                name="brand" 
                                required 
                                value="<?php echo htmlspecialchars($edit_equipment['brand']); ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            >
                        </div>
                        <div>
                            <label for="model" class="block text-sm font-medium text-gray-700">Model</label>
                            <input 
                                type="text" 
                                id="model" 
                                name="model" 
                                required 
                                value="<?php echo htmlspecialchars($edit_equipment['model']); ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            >
                        </div>
                        <div>
                            <label for="specifications" class="block text-sm font-medium text-gray-700">Specifications</label>
                            <textarea 
                                id="specifications" 
                                name="specifications" 
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            ><?php echo htmlspecialchars($edit_equipment['specifications']); ?></textarea>
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                        >
                            Update Equipment
                        </button>
                        <a href="equipment_shop.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                    </form>
                <?php endif; ?>

                <!-- Equipment List -->
                <h2 class="text-2xl font-semibold mb-4">Available AV Equipment</h2>
                <?php if (isset($error) && !$edit_equipment): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php elseif (isset($success) && !$edit_equipment): ?>
                    <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($equipment)): ?>
                    <p class="text-gray-600">No equipment available.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($equipment as $equip): ?>
                            <div class="card bg-gray-50 p-4 rounded-lg shadow-md">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($equip['brand'] . ' ' . $equip['model'] . ' ' . $equip['name']); ?></h3>
                                <p class="text-sm text-gray-600">Specifications: <?php echo htmlspecialchars($equip['specifications'] ?: 'N/A'); ?></p>
                                <p class="text-sm text-gray-500">Created: <?php echo htmlspecialchars($equip['created_at']); ?></p>
                                <?php if ($equip['updated_at'] !== $equip['created_at']): ?>
                                    <p class="text-sm text-gray-500">Updated: <?php echo htmlspecialchars($equip['updated_at']); ?></p>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <div class="mt-3 space-x-2">
                                        <a href="equipment_shop.php?edit_id=<?php echo htmlspecialchars($equip['equipment_id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete_equipment">
                                            <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($equip['equipment_id']); ?>">
                                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Delete</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <?php if ($equip['available_quantity'] > 0): ?>
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="action" value="request_equipment">
                                            <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($equip['equipment_id']); ?>">
                                            <div class="flex space-x-2">
                                                <input 
                                                    type="number" 
                                                    name="quantity" 
                                                    min="1" 
                                                    max="<?php echo htmlspecialchars($equip['available_quantity']); ?>" 
                                                    value="1" 
                                                    class="w-20 px-2 py-1 border border-gray-300 rounded-lg"
                                                >
                                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Request</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-sm text-red-600 mt-3">Out of stock</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Equipment Requests -->
                <h2 class="text-2xl font-semibold mt-12 mb-4">Equipment Requests</h2>
                <?php if (empty($equipment_requests)): ?>
                    <p class="text-gray-600">No equipment requests.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($equipment_requests as $request): ?>
                            <?php if ($user['role'] === 'admin' || $request['username'] === $user['username']): ?>
                                <div class="card bg-gray-50 p-4 rounded-lg shadow-md">
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($request['brand'] . ' ' . $request['model'] . ' ' . $request['equipment_name']); ?></h3>
                                    <p class="text-sm text-gray-600">Requested by: <?php echo htmlspecialchars($request['username']); ?></p>
                                    <p class="text-sm text-gray-600">Quantity: <?php echo htmlspecialchars($request['quantity']); ?></p>
                                    <p class="text-sm text-gray-600">Status: <?php echo ucfirst(htmlspecialchars($request['status'])); ?></p>
                                    <p class="text-sm text-gray-500">Requested: <?php echo htmlspecialchars($request['created_at']); ?></p>
                                    <?php if ($request['updated_at'] !== $request['created_at']): ?>
                                        <p class="text-sm text-gray-500">Updated: <?php echo htmlspecialchars($request['updated_at']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($user['role'] === 'admin' && $request['status'] === 'pending'): ?>
                                        <div class="mt-3 space-x-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="approve_request">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Approve</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="deny_request">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Deny</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>