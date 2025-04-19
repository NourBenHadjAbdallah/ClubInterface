<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
require_once '../includes/db_connect.php';

$pending_users_file = '../assets/pending_users.json';
$pending_users = file_exists($pending_users_file) ? json_decode(file_get_contents($pending_users_file), true) : [];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
    $action = $_POST['action'] ?? '';

    if ($index < 0 || $index >= count($pending_users)) {
        $error = "Invalid user selection";
    } else {
        $pending_user = $pending_users[$index];
        if ($action === 'approve') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, role, name, email, phone, photo_mime, photo_data, created_at)
                    VALUES (?, ?, 'user', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pending_user['username'],
                    $pending_user['password'],
                    $pending_user['name'],
                    $pending_user['email'],
                    $pending_user['phone'],
                    $pending_user['photo_mime'],
                    $pending_user['photo_data'],
                    $pending_user['created_at']
                ]);

                $notifications_file = '../assets/notifications.json';
                $notifications = file_exists($notifications_file) ? json_decode(file_get_contents($notifications_file), true) : [];
                $notifications[] = [
                    'id' => uniqid('notif_'),
                    'user' => $pending_user['username'],
                    'type' => 'approval',
                    'message' => "Your registration has been approved!",
                    'created_at' => date('Y-m-d H:i:s'),
                    'read' => false
                ];
                file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));

                array_splice($pending_users, $index, 1);
                file_put_contents($pending_users_file, json_encode($pending_users, JSON_PRETTY_PRINT));
                $success = "User approved successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            array_splice($pending_users, $index, 1);
            file_put_contents($pending_users_file, json_encode($pending_users, JSON_PRETTY_PRINT));
            $success = "User registration rejected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Approvals - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/admin_approvals.css">
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

            <div class="approvals-container">
                <h1 class="text-3xl font-bold mb-6">Pending User Approvals</h1>
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
                <?php if (empty($pending_users)): ?>
                    <p class="text-gray-600">No pending approvals.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($pending_users as $index => $pending_user): ?>
                            <div class="approval-card">
                                <div class="flex items-center space-x-4">
                                    <?php if (!empty($pending_user['photo_data'])): ?>
                                        <img src="data:<?php echo htmlspecialchars($pending_user['photo_mime']); ?>;base64,<?php echo htmlspecialchars($pending_user['photo_data']); ?>" class="w-12 h-12 rounded-full" alt="Profile">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                            <?php echo htmlspecialchars(substr($pending_user['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($pending_user['name']); ?></h3>
                                        <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($pending_user['username']); ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">Email: <?php echo htmlspecialchars($pending_user['email']); ?></p>
                                <p class="text-sm text-gray-600">Phone: <?php echo htmlspecialchars($pending_user['phone'] ?? 'N/A'); ?></p>
                                <p class="text-sm text-gray-500">Registered: <?php echo htmlspecialchars($pending_user['created_at']); ?></p>
                                <div class="mt-3 space-x-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Approve</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Reject</button>
                                    </form>
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