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

// Load announcements
$announcements_file = 'announcements.json';
$announcements = file_exists($announcements_file) ? json_decode(file_get_contents($announcements_file), true) : [];

// Load notifications
$notifications_file = 'notifications.json';
$notifications = file_exists($notifications_file) ? json_decode(file_get_contents($notifications_file), true) : [];

// Load users for notifications
$stmt = $pdo->query("SELECT username FROM users");
$all_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');

    // Validation
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($content)) {
        $error = "Content is required";
    } elseif (strtotime($date) === false) {
        $error = "Invalid date format";
    } else {
        $id = uniqid('ann_');
        $announcements[$id] = [
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'date' => $date,
            'author' => $user['username'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($announcements_file, json_encode($announcements, JSON_PRETTY_PRINT));

        // Create notifications for all users
        foreach ($all_users as $username) {
            if ($username !== $user['username']) { // Skip the author
                $notifications[] = [
                    'id' => uniqid('notif_'),
                    'user' => $username,
                    'type' => 'announcement',
                    'item_id' => $id,
                    'message' => "New announcement: $title",
                    'created_at' => date('Y-m-d H:i:s'),
                    'read' => false
                ];
            }
        }
        file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));
        $success = "Announcement created successfully!";
    }
}

// Handle edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');

    // Validation
    if (!isset($announcements[$id])) {
        $error = "Announcement not found";
    } elseif (empty($title)) {
        $error = "Title is required";
    } elseif (empty($content)) {
        $error = "Content is required";
    } elseif (strtotime($date) === false) {
        $error = "Invalid date format";
    } else {
        $announcements[$id]['title'] = $title;
        $announcements[$id]['content'] = $content;
        $announcements[$id]['date'] = $date;
        $announcements[$id]['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($announcements_file, json_encode($announcements, JSON_PRETTY_PRINT));
        $success = "Announcement updated successfully!";
    }
}

// Handle delete announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? '';
    if (isset($announcements[$id])) {
        unset($announcements[$id]);
        file_put_contents($announcements_file, json_encode($announcements, JSON_PRETTY_PRINT));
        // Remove related notifications
        $notifications = array_filter($notifications, function($notif) use ($id) {
            return !($notif['type'] === 'announcement' && $notif['item_id'] === $id);
        });
        file_put_contents($notifications_file, json_encode(array_values($notifications), JSON_PRETTY_PRINT));
        $success = "Announcement deleted successfully!";
    } else {
        $error = "Announcement not found";
    }
}

// Sort announcements by created_at (newest first)
usort($announcements, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Check if editing an announcement
$edit_id = $_GET['edit_id'] ?? '';
$edit_announcement = $edit_id && isset($announcements[$edit_id]) ? $announcements[$edit_id] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6B7280, #1F2937);
        }
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-4px);
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
                <h1 class="text-3xl font-bold mb-6">Club Announcements</h1>

                <!-- Admin: Create/Edit Announcement Form -->
                <?php if ($user['role'] === 'admin'): ?>
                    <h2 class="text-2xl font-semibold mb-4"><?php echo $edit_announcement ? 'Edit Announcement' : 'Create New Announcement'; ?></h2>
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
                        <input type="hidden" name="action" value="<?php echo $edit_announcement ? 'edit' : 'create'; ?>">
                        <?php if ($edit_announcement): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_announcement['id']); ?>">
                        <?php endif; ?>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['title']) : ''; ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                placeholder="Enter announcement title"
                            >
                        </div>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                            <textarea 
                                id="content" 
                                name="content" 
                                required 
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                placeholder="Enter announcement content"
                            ><?php echo $edit_announcement ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                            <input 
                                type="date" 
                                id="date" 
                                name="date" 
                                value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['date']) : date('Y-m-d'); ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            >
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                        >
                            <?php echo $edit_announcement ? 'Update Announcement' : 'Create Announcement'; ?>
                        </button>
                        <?php if ($edit_announcement): ?>
                            <a href="announcements.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <!-- Announcement List -->
                <h2 class="text-2xl font-semibold mb-4">All Announcements</h2>
                <?php if (empty($announcements)): ?>
                    <p class="text-gray-600">No announcements available.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="card bg-gray-50 p-4 rounded-lg shadow-md">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <p class="text-sm text-gray-600">Posted by: <?php echo htmlspecialchars($announcement['author']); ?></p>
                                <p class="text-sm text-gray-600">Date: <?php echo htmlspecialchars($announcement['date']); ?></p>
                                <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                <p class="text-sm text-gray-500 mt-2">Created: <?php echo htmlspecialchars($announcement['created_at']); ?></p>
                                <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                                    <p class="text-sm text-gray-500">Updated: <?php echo htmlspecialchars($announcement['updated_at']); ?></p>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <div class="mt-3 space-x-2">
                                        <a href="announcements.php?edit_id=<?php echo htmlspecialchars($announcement['id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($announcement['id']); ?>">
                                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>