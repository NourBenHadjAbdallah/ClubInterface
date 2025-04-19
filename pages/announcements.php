<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
require_once '../includes/db_connect.php';

$error = '';
$success = '';

try {
    $stmt = $pdo->query("SELECT a.*, u.NAME AS posted_by_name FROM announcements a JOIN users u ON a.posted_by = u.username ORDER BY a.created_at DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error fetching announcements: " . $e->getMessage();
    $announcements = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_announcement') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($content)) $errors[] = "Content is required";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, posted_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $content, $user['username']]);
            $success = "Announcement created successfully!";
            header('Location: announcements.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_announcement') {
    $announcement_id = $_POST['announcement_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $errors = [];
    if (empty($announcement_id)) $errors[] = "Invalid announcement ID";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($content)) $errors[] = "Content is required";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET title = ?, content = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $announcement_id]);
            $success = "Announcement updated successfully!";
            header('Location: announcements.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    $announcement_id = $_POST['announcement_id'] ?? '';
    if (empty($announcement_id)) {
        $error = "Invalid announcement ID";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$announcement_id]);
            $success = "Announcement deleted successfully!";
            header('Location: announcements.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$edit_announcement = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_announcement) {
            $error = "Announcement not found for editing";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/announcements.css">
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

            <div class="announcements-container">
                <h1 class="text-3xl font-bold mb-6">Manage Announcements</h1>
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

                <!-- Create/Edit Announcement Form -->
                <div class="announcement-form mb-12">
                    <h2 class="text-2xl font-semibold mb-4"><?php echo $edit_announcement ? 'Edit Announcement' : 'Create New Announcement'; ?></h2>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="<?php echo $edit_announcement ? 'edit_announcement' : 'create_announcement'; ?>">
                        <?php if ($edit_announcement): ?>
                            <input type="hidden" name="announcement_id" value="<?php echo htmlspecialchars($edit_announcement['id']); ?>">
                        <?php endif; ?>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                value="<?php echo htmlspecialchars($edit_announcement['title'] ?? ''); ?>"
                                class="announcement-input"
                                placeholder="Enter announcement title"
                            >
                        </div>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                            <textarea 
                                id="content" 
                                name="content" 
                                required 
                                class="announcement-input"
                                placeholder="Enter announcement content"
                            ><?php echo htmlspecialchars($edit_announcement['content'] ?? ''); ?></textarea>
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                        >
                            <?php echo $edit_announcement ? 'Update Announcement' : 'Post Announcement'; ?>
                        </button>
                        <?php if ($edit_announcement): ?>
                            <a href="announcements.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Announcements List -->
                <h2 class="text-2xl font-semibold mb-4">Current Announcements</h2>
                <?php if (empty($announcements)): ?>
                    <p class="text-gray-600">No announcements found.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                <p class="text-sm text-gray-500 mt-2">Posted by: <?php echo htmlspecialchars($announcement['posted_by_name'] . ' (@' . $announcement['posted_by'] . ')'); ?></p>
                                <p class="text-sm text-gray-500">Posted on: <?php echo htmlspecialchars($announcement['created_at']); ?></p>
                                <?php if (!empty($announcement['updated_at'])): ?>
                                    <p class="text-sm text-gray-500">Updated on: <?php echo htmlspecialchars($announcement['updated_at']); ?></p>
                                <?php endif; ?>
                                <div class="mt-3 space-x-2">
                                    <a href="announcements.php?edit_id=<?php echo htmlspecialchars($announcement['id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo htmlspecialchars($announcement['id']); ?>">
                                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Delete</button>
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