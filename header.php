<?php
// Ensure user session data
$user_name = isset($_SESSION['user']['name']) ? htmlspecialchars($_SESSION['user']['name']) : 'Guest';
?>

<header class="flex justify-between items-center mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Club Administration</h1>
    <div class="flex items-center space-x-4">
        <span class="text-gray-600">Welcome, <?php echo $user_name; ?></span>
        <button class="p-2 bg-gray-200 rounded-full hover:bg-gray-300">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </button>
    </div>
</header>