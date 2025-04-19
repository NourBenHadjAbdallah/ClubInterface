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
    $stmt = $pdo->query("SELECT e.*, u.NAME AS posted_by_name FROM events e JOIN users u ON e.posted_by = u.username ORDER BY e.event_date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error fetching events: " . $e->getMessage();
    $events = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($event_date) || !strtotime($event_date)) $errors[] = "Valid event date is required";
    if (empty($location)) $errors[] = "Location is required";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, event_date, location, posted_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $description, $event_date, $location, $user['username']]);
            $success = "Event created successfully!";
            header('Location: events.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_event') {
    $event_id = $_POST['event_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $errors = [];
    if (empty($event_id)) $errors[] = "Invalid event ID";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($event_date) || !strtotime($event_date)) $errors[] = "Valid event date is required";
    if (empty($location)) $errors[] = "Location is required";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE events 
                SET title = ?, description = ?, event_date = ?, location = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $event_date, $location, $event_id]);
            $success = "Event updated successfully!";
            header('Location: events.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $event_id = $_POST['event_id'] ?? '';
    if (empty($event_id)) {
        $error = "Invalid event ID";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $success = "Event deleted successfully!";
            header('Location: events.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$edit_event = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_event) {
            $error = "Event not found for editing";
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
    <title>Events - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/events.css">
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

            <div class="events-container">
                <h1 class="text-3xl font-bold mb-6">Manage Events</h1>
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

                <!-- Create/Edit Event Form -->
                <div class="event-form mb-12">
                    <h2 class="text-2xl font-semibold mb-4"><?php echo $edit_event ? 'Edit Event' : 'Create New Event'; ?></h2>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="<?php echo $edit_event ? 'edit_event' : 'create_event'; ?>">
                        <?php if ($edit_event): ?>
                            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($edit_event['id']); ?>">
                        <?php endif; ?>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                value="<?php echo htmlspecialchars($edit_event['title'] ?? ''); ?>"
                                class="event-input"
                                placeholder="Enter event title"
                            >
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea 
                                id="description" 
                                name="description" 
                                required 
                                class="event-input"
                                placeholder="Enter event description"
                            ><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="event_date" class="block text-sm font-medium text-gray-700">Event Date & Time</label>
                            <input 
                                type="datetime-local" 
                                id="event_date" 
                                name="event_date" 
                                required 
                                value="<?php echo htmlspecialchars($edit_event['event_date'] ?? ''); ?>"
                                class="event-input"
                            >
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input 
                                type="text" 
                                id="location" 
                                name="location" 
                                required 
                                value="<?php echo htmlspecialchars($edit_event['location'] ?? ''); ?>"
                                class="event-input"
                                placeholder="Enter event location"
                            >
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                        >
                            <?php echo $edit_event ? 'Update Event' : 'Create Event'; ?>
                        </button>
                        <?php if ($edit_event): ?>
                            <a href="events.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Events List -->
                <h2 class="text-2xl font-semibold mb-4">Upcoming Events</h2>
                <?php if (empty($events)): ?>
                    <p class="text-gray-600">No events found.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                <p class="text-sm text-gray-600 mt-2">Date: <?php echo htmlspecialchars($event['event_date']); ?></p>
                                <p class="text-sm text-gray-600">Location: <?php echo htmlspecialchars($event['location']); ?></p>
                                <p class="text-sm text-gray-500">Posted by: <?php echo htmlspecialchars($event['posted_by_name'] . ' (@' . $event['posted_by'] . ')'); ?></p>
                                <p class="text-sm text-gray-500">Posted on: <?php echo htmlspecialchars($event['created_at']); ?></p>
                                <?php if (!empty($event['updated_at'])): ?>
                                    <p class="text-sm text-gray-500">Updated on: <?php echo htmlspecialchars($event['updated_at']); ?></p>
                                <?php endif; ?>
                                <div class="mt-3 space-x-2">
                                    <a href="events.php?edit_id=<?php echo htmlspecialchars($event['id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
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