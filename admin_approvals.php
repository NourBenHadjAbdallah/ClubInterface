<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Simulated user database (same as index.php)
$users = [
    'admin' => [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin'
    ],
    'user' => [
        'username' => 'user',
        'password' => password_hash('user123', PASSWORD_DEFAULT),
        'role' => 'user'
    ]
];

// Load pending users
$pending_file = 'pending_users.json';
$pending_users = file_exists($pending_file) ? json_decode(file_get_contents($pending_file), true) : [];

// Handle approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $action = $_POST['action'] ?? '';

    if (isset($pending_users[$username])) {
        if ($action === 'approve') {
            // Add to main users (in production, update the database)
            $users[$username] = $pending_users[$username];
            // Save updated users (for demo; in production, use DB)
            // Note: This is temporary; ideally, save to a persistent DB
            unset($pending_users[$username]);
            file_put_contents($pending_file, json_encode($pending_users, JSON_PRETTY_PRINT));
            $message = "User $username approved!";
        } elseif ($action === 'deny') {
            // Remove from pending
            unset($pending_users[$username]);
            file_put_contents($pending_file, json_encode($pending_users, JSON_PRETTY_PRINT));
            $message = "User $username denied.";
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
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6B7280, #1F2937);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg text-gray-800 p-8">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Pending Member Requests</h1>
        
        <?php if (isset($message)): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pending_users)): ?>
            <p class="text-gray-600">No pending Member requests.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pending_users as $user): ?>
                    <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($user['username']); ?></p>
                            <p class="text-sm text-gray-600">Requested: <?php echo htmlspecialchars($user['created_at']); ?></p>
                        </div>
                        <div class="space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Approve</button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                <input type="hidden" name="action" value="deny">
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Deny</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="admin_main.php" class="block mt-6 text-indigo-600 hover:underline">Back to Dashboard</a>
    </div>
</body>
</html>