<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
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
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6B7280, #1F2937);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg text-gray-800">
    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header with Notification Bell -->
            <?php include 'header.php'; ?>

            <!-- Mobile Menu Button -->
            <button id="openSidebar" class="md:hidden mb-6 p-2 bg-indigo-600 text-white rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="bg-white rounded-2xl shadow-lg p-8">
                <h1 class="text-3xl font-bold mb-6">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <p class="text-gray-600">This is your user dashboard. Use the sidebar to navigate to Events, Announcements, Tool Shop, or Members.</p>
            </div>
        </main>
    </div>
</body>
</html>