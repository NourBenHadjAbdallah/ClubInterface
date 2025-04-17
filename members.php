<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Include database connection
require_once 'db_connect.php';

// Initialize messages
$error = '';
$success = '';
$debug_info = []; // Store invalid records for debugging

// Load users with strict filtering
try {
    $stmt = $pdo->query("
        SELECT id, username, role, name, email, phone, photo_mime, photo_data, created_at 
        FROM users 
        WHERE id IS NOT NULL AND name IS NOT NULL AND name != ''
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Log raw users data for debugging
    error_log("Raw users data: " . json_encode($users));
} catch (PDOException $e) {
    $error = "Database error fetching users: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
    $users = [];
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) && in_array($_POST['role'], ['admin', 'user']) ? $_POST['role'] : 'user';
    $photo = $_FILES['photo'] ?? null;

    // Validation
    $errors = [];
    if ($user_id <= 0) {
        $errors[] = "Invalid user ID";
    }
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username must contain only letters, numbers, or underscores";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if (!empty($phone) && !preg_match('/^\+?[\d\s-]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    // Check for duplicate username or email
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists";
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already registered";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }

    // Handle photo (optional)
    $photo_mime = null;
    $photo_base64 = null;
    if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($photo['type'], $allowed_types)) {
            $errors[] = "Photo must be JPEG or PNG";
        } elseif ($photo['size'] > $max_size) {
            $errors[] = "Photo size must be less than 2MB";
        } else {
            $photo_data = file_get_contents($photo['tmp_name']);
            $photo_base64 = base64_encode($photo_data);
            $photo_mime = $photo['type'];
        }
    } elseif ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Photo upload failed";
    }

    if (empty($errors)) {
        try {
            if (empty($password) && !$photo_base64) {
                // Update without changing password or photo
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$username, $role, $name, $email, $phone, $user_id]);
            } elseif (empty($password)) {
                // Update with new photo
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, name = ?, email = ?, phone = ?, photo_mime = ?, photo_data = ? WHERE id = ?");
                $stmt->execute([$username, $role, $name, $email, $phone, $photo_mime, $photo_base64, $user_id]);
            } elseif (!$photo_base64) {
                // Update with new password
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $name, $email, $phone, $user_id]);
            } else {
                // Update with new password and photo
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, name = ?, email = ?, phone = ?, photo_mime = ?, photo_data = ? WHERE id = ?");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $name, $email, $phone, $photo_mime, $photo_base64, $user_id]);
            }
            $success = "User updated successfully!";
            header('Location: members.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($user_id <= 0) {
        $error = "Invalid user ID provided";
    } elseif ($user_id == $user['id']) {
        $error = "You cannot delete your own account";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $error = "User not found";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = "User deleted successfully!";
                error_log("User ID $user_id deleted by admin ID {$user['id']}");
                header('Location: members.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error during deletion: " . $e->getMessage();
            error_log("Deletion failed for user ID $user_id: " . $e->getMessage());
        }
    }
}

// Check if editing a user
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$edit_user = null;
if ($edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, role, name, email, phone, photo_mime, photo_data FROM users WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_user) {
            $error = "User not found for editing";
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
    <title>Members - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="memberStyle.css">
</head>
<body class="min-h-screen gradient-bg text-gray-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64 md:p-8">
            <!-- Header with Notification Bell -->
            <?php include 'header.php'; ?>

            <!-- Mobile Menu Button -->
            <button id="openSidebar" class="md:hidden mb-6 p-2 bg-indigo-600 text-white rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="bg-white rounded-2xl shadow-lg p-8">
                <h1 class="text-3xl font-bold mb-6">Members</h1>

                <!-- Debug Section (Remove after resolving issue) -->
                <?php if (!empty($debug_info)): ?>
                    <div class="debug-section">
                        <h3 class="font-semibold">Debug: Invalid Records Detected</h3>
                        <pre><?php echo htmlspecialchars(json_encode($debug_info, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php endif; ?>

                <!-- Edit User Form (only shown when editing) -->
                <?php if ($edit_user): ?>
                    <h2 class="text-2xl font-semibold mb-4">Edit User</h2>
                    <?php if ($error): ?>
                        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="member-form space-y-6 mb-12">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id'] ?? ''); ?>">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                required 
                                value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>"
                                class="member-input input-focus"
                                placeholder="Enter full name"
                            >
                        </div>
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required 
                                value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>"
                                class="member-input input-focus"
                                placeholder="Enter username"
                            >
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required 
                                value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>"
                                class="member-input input-focus"
                                placeholder="Enter email"
                            >
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number (Optional)</label>
                            <input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>"
                                class="member-input input-focus"
                                placeholder="e.g., +1234567890"
                            >
                        </div>
                        <div>
                            <label for="photo" class="block text-sm font-medium text-gray-700">Profile Photo (Optional, JPEG/PNG, max 2MB)</label>
                            <?php if (!empty($edit_user['photo_data'])): ?>
                                <img src="data:<?php echo htmlspecialchars($edit_user['photo_mime']); ?>;base64,<?php echo htmlspecialchars($edit_user['photo_data']); ?>" class="w-16 h-16 rounded-full mb-2" alt="Current photo">
                            <?php endif; ?>
                            <input 
                                type="file" 
                                id="photo" 
                                name="photo" 
                                accept="image/jpeg,image/png" 
                                class="file-input"
                            >
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password (leave blank to keep current)</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password"
                                class="member-input input-focus"
                                placeholder="Enter new password"
                            >
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select 
                                id="role" 
                                name="role" 
                                class="member-input input-focus"
                            >
                                <option value="user" <?php echo ($edit_user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                        >
                            Update User
                        </button>
                        <a href="members.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                    </form>
                <?php endif; ?>

                <!-- Current Members -->
                <h2 class="text-2xl font-semibold mb-4">Current Members</h2>
                <?php if ($error && !$edit_user): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success && !$edit_user): ?>
                    <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($users)): ?>
                    <p class="text-gray-600">No members found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($users as $index => $member): ?>
                            <?php
                            // Robust validation
                            if (!is_array($member) || !isset($member['id']) || !is_numeric($member['id']) || !isset($member['name']) || empty(trim($member['name']))) {
                                $debug_info[] = [
                                    'index' => $index,
                                    'record' => $member,
                                    'error' => 'Invalid record: Missing or invalid id or name'
                                ];
                                error_log("Skipping invalid user record at index $index: " . json_encode($member));
                                continue;
                            }
                            ?>
                            <div class="member-card">
                                <div class="flex items-center space-x-4">
                                    <?php if (!empty($member['photo_data'])): ?>
                                        <img src="data:<?php echo htmlspecialchars($member['photo_mime'] ?? 'image/png'); ?>;base64,<?php echo htmlspecialchars($member['photo_data']); ?>" class="w-12 h-12 rounded-full" alt="Profile">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                            <?php echo htmlspecialchars(substr($member['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($member['name']); ?></h3>
                                        <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($member['username'] ?? 'Unknown'); ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">Email: <?php echo htmlspecialchars($member['email'] ?? 'Unknown'); ?></p>
                                <p class="text-sm text-gray-600">Phone: <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></p>
                                <p class="text-sm text-gray-600">Role: <?php echo ucfirst(htmlspecialchars($member['role'] ?? 'Unknown')); ?></p>
                                <p class="text-sm text-gray-500">Joined: <?php echo htmlspecialchars($member['created_at'] ?? 'N/A'); ?></p>
                                <div class="mt-3 space-x-2">
                                    <a href="members.php?edit_id=<?php echo htmlspecialchars($member['id']); ?>" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">Edit</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700" <?php echo ($member['id'] == $user['id']) ? 'disabled' : ''; ?>>Delete</button>
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