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

// Load events
$events_file = 'events.json';
$events = file_exists($events_file) ? json_decode(file_get_contents($events_file), true) : [];

// Load notifications
$notifications_file = 'notifications.json';
$notifications = file_exists($notifications_file) ? json_decode(file_get_contents($notifications_file), true) : [];

// Load users for notifications
$stmt = $pdo->query("SELECT username FROM users");
$all_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle create event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $location = $_POST['location'] ?? '';

    // Validation
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($description)) {
        $error = "Description is required";
    } elseif (empty($date) || strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = "Valid future date is required";
    } elseif (empty($time) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        $error = "Valid time (HH:MM) is required";
    } elseif (empty($location)) {
        $error = "Location is required";
    } else {
        $id = uniqid('event_');
        $events[$id] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'date' => $date,
            'time' => $time,
            'location' => $location,
            'author' => $user['username'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($events_file, json_encode($events, JSON_PRETTY_PRINT));

        // Create notifications for all users
        foreach ($all_users as $username) {
            if ($username !== $user['username']) { // Skip the author
                $notifications[] = [
                    'id' => uniqid('notif_'),
                    'user' => $username,
                    'type' => 'event',
                    'item_id' => $id,
                    'message' => "New event: $title on $date",
                    'created_at' => date('Y-m-d H:i:s'),
                    'read' => false
                ];
            }
        }
        file_put_contents($notifications_file, json_encode($notifications, JSON_PRETTY_PRINT));
        $success = "Event created successfully!";
    }
}

// Handle edit event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $location = $_POST['location'] ?? '';

    // Validation
    if (!isset($events[$id])) {
        $error = "Event not found";
    } elseif (empty($title)) {
        $error = "Title is required";
    } elseif (empty($description)) {
        $error = "Description is required";
    } elseif (empty($date) || strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = "Valid future date is required";
    } elseif (empty($time) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        $error = "Valid time (HH:MM) is required";
    } elseif (empty($location)) {
        $error = "Location is required";
    } else {
        $events[$id]['title'] = $title;
        $events[$id]['description'] = $description;
        $events[$id]['date'] = $date;
        $events[$id]['time'] = $time;
        $events[$id]['location'] = $location;
        $events[$id]['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($events_file, json_encode($events, JSON_PRETTY_PRINT));
        $success = "Event updated successfully!";
    }
}

// Handle delete event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? '';
    if (isset($events[$id])) {
        unset($events[$id]);
        file_put_contents($events_file, json_encode($events, JSON_PRETTY_PRINT));
        // Remove related notifications
        $notifications = array_filter($notifications, function($notif) use ($id) {
            return !($notif['type'] === 'event' && $notif['item_id'] === $id);
        });
        file_put_contents($notifications_file, json_encode(array_values($notifications), JSON_PRETTY_PRINT));
        $success = "Event deleted successfully!";
    } else {
        $error = "Event not found";
    }
}

// Sort events by date (upcoming first)
usort($events, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Check if editing an event
$edit_id = $_GET['edit_id'] ?? '';
$edit_event = $edit_id && isset($events[$edit_id]) ? $events[$edit_id] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Club Administration</title>
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
                <h1 class="text-3xl font-bold mb-6">Club Events</h1>

                <!-- Admin: Create/Edit Event Form -->
                <?php if ($user['role'] === 'admin'): ?>
                    <h2 class="text-2xl font-semibold mb-4"><?php echo $edit_event ? 'Edit Event' : 'Create New Event'; ?></h2>
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
                        <input type="hidden" name="action" value="<?php echo $edit_event ? 'edit' : 'create'; ?>">
                        <?php if ($edit_event): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_event['id']); ?>">
                        <?php endif; ?>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                required 
                                value="<?php echo $edit_event ? htmlspecialchars($edit_event['title']) : ''; ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                placeholder="Enter event title"
                            >
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea 
                                id="description" 
                                name="description" 
                                required 
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                placeholder="Enter event description"
                            ><?php echo $edit_event ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                            <input 
                                type="date" 
                                id="date" 
                                name="date" 
                                required 
                                min="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo $edit_event ? htmlspecialchars($edit_event['date']) : ''; ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            >
                        </div>
                        <div>
                            <label for="time" class="block text-sm font-medium text-gray-700">Time (HH:MM)</label>
                            <input 
                                type="time" 
                                id="time" 
                                name="time" 
                                required 
                                value="<?php echo $edit_event ? htmlspecialchars($edit_event['time']) : ''; ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                            >
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input 
                                type="text" 
                                id="location" 
                                name="location" 
                                required 
                                value="<?php echo $edit_event ? htmlspecialchars($edit_event['location']) : ''; ?>"
                                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
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
                <?php endif; ?>

                <!-- Event List -->
                <h2 class="text-2xl font-semibold mb-4">Upcoming Events</h2>
                <?php if (empty($events)): ?>
                    <p class="text-gray-600">No events available.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($events as $event): ?>
                            <div class="card bg-gray-50 p-4 rounded-lg shadow-md">
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-sm text-gray-600">Posted by: <?php echo htmlspecialchars($event['author']); ?></p>
                                <p class="text-sm text-gray-600">Date: <?php echo htmlspecialchars($event['date']); ?></p>
                                <p class="text-sm text-gray-600">Time: <?php echo htmlspecialchars($event['time']); ?></p>
                                <p class="text-sm text-gray-600">Location: <?php echo htmlspecialchars($event['location']); ?></p>
                                <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                <p class="text-sm text-gray-500 mt-2">Created: <?php echo htmlspecialchars($event['created_at']); ?></p>
                                <?php if ($event['updated_at'] !== $event['created_at']): ?>
                                    <p class="text-sm text-gray-500">Updated: <?php echo htmlspecialchars($event['updated_at']); ?></p>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <div class="mt-3 space-x-2">
                                        <a href="events.php?edit_id=<?php echo htmlspecialchars($event['id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($event['id']); ?>">
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