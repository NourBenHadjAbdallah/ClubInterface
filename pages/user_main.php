<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/user_main.css">
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
                <h2 class="text-2xl font-semibold mb-4">User Dashboard</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">Equipment Shop</h3>
                        <p class="text-gray-600">Browse and request equipment.</p>
                        <a href="equipment_shop.php" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Go to Shop</a>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="text-lg font-semibold">Upcoming Events</h3>
                        <p class="text-gray-600">Check out upcoming club events.</p>
                        <a href="#" class="mt-3 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">View Events</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>