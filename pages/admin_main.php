<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
require_once '../includes/db_connect.php';

// Quick stats
$total_members = 0;
$total_equipment = 0;
$total_events = 0;
$pending_approvals = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_members = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching total members: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM equipment");
    $total_equipment = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching total equipment: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $total_events = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching total events: " . $e->getMessage());
}

$pending_users_file = '../assets/pending_users.json';
$pending_users = file_exists($pending_users_file) ? json_decode(file_get_contents($pending_users_file), true) : [];
$pending_approvals = count($pending_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/admin_main.css">
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

            <div class="dashboard-container">
                <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card">
                        <h3 class="text-lg font-semibold">Total Members</h3>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_members); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3 class="text-lg font-semibold">Total Equipment</h3>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_equipment); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3 class="text-lg font-semibold">Total Events</h3>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_events); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3 class="text-lg font-semibold">Pending Approvals</h3>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($pending_approvals); ?></p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <h2 class="text-2xl font-semibold mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">Manage Members</h3>
                        <p class="text-gray-600">View and edit member details.</p>
                        <a href="members.php" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Go to Members</a>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">Equipment Shop</h3>
                        <p class="text-gray-600">Add or manage equipment.</p>
                        <a href="add_equipment.php" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Add Equipment</a>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">Manage Events</h3>
                        <p class="text-gray-600">Create and manage events.</p>
                        <a href="events.php" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Manage Events</a>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">User Approvals</h3>
                        <p class="text-gray-600">Approve or reject new users.</p>
                        <a href="admin_approvals.php" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">View Approvals</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>