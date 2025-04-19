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
    $stmt = $pdo->prepare("SELECT id, username, NAME, birthday, email, phone, description, photo_mime, photo_data, role, join_date, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error fetching members: " . $e->getMessage();
    $members = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_member') {
    $member_id = $_POST['member_id'] ?? '';
    $name = trim($_POST['NAME'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $role = $_POST['role'] ?? 'user';

    $errors = [];
    if (empty($member_id)) $errors[] = "Invalid member ID";
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (!empty($phone) && !preg_match('/^\+?[\d\s-]{10,15}$/', $phone)) $errors[] = "Invalid phone number format";
    if (!empty($birthday)) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            $errors[] = "Invalid birthday format (YYYY-MM-DD)";
        } else {
            // Optional: Validate birthday range (e.g., not in future, not too old)
            $birthday_date = DateTime::createFromFormat('Y-m-d', $birthday);
            $today = new DateTime();
            $min_date = (new DateTime())->modify('-120 years'); // Max age 120
            if ($birthday_date > $today) {
                $errors[] = "Birthday cannot be in the future";
            } elseif ($birthday_date < $min_date) {
                $errors[] = "Birthday is too far in the past";
            }
        }
    }
    if ($role !== 'user') $errors[] = "Invalid role";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET NAME = ?, email = ?, phone = ?, birthday = ?, description = ?, role = ?
                WHERE id = ? AND role != 'admin'
            ");
            $stmt->execute([$name, $email, $phone, $birthday ?: null, $description, $role, $member_id]);
            $success = "Member updated successfully!";
            header('Location: members.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_member') {
    $member_id = $_POST['member_id'] ?? '';
    if (empty($member_id)) {
        $error = "Invalid member ID";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$member_id]);
            $success = "Member deleted successfully!";
            header('Location: members.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$edit_member = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT id, username, NAME, birthday, email, phone, description, photo_mime, photo_data, role, join_date, created_at FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$edit_id]);
        $edit_member = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_member) {
            $error = "Member not found for editing";
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
    <link rel="stylesheet" href="../styles/members.css">
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

            <div class="members-container">
                <h1 class="text-3xl font-bold mb-6">Manage Members</h1>
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

                <!-- Edit Member Form -->
                <?php if ($edit_member): ?>
                    <div class="member-form mb-12">
                        <h2 class="text-2xl font-semibold mb-4">Edit Member</h2>
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="edit_member">
                            <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($edit_member['id']); ?>">
                            <div>
                                <label for="NAME" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input 
                                    type="text" 
                                    id="NAME" 
                                    name="NAME" 
                                    required 
                                    value="<?php echo htmlspecialchars($edit_member['NAME'] ?? 'Unknown'); ?>"
                                    class="member-input w-full p-2 border rounded"
                                    placeholder="Enter full name"
                                >
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required 
                                    value="<?php echo htmlspecialchars($edit_member['email']); ?>"
                                    class="member-input w-full p-2 border rounded"
                                    placeholder="Enter email"
                                >
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input 
                                    type="text" 
                                    id="phone" 
                                    name="phone" 
                                    value="<?php echo htmlspecialchars($edit_member['phone'] ?? ''); ?>"
                                    class="member-input w-full p-2 border rounded"
                                    placeholder="e.g., +1234567890"
                                >
                            </div>
                            <div>
                                <label for="birthday" class="block text-sm font-medium text-gray-700">Birthday</label>
                                <input 
                                    type="date" 
                                    id="birthday" 
                                    name="birthday" 
                                    value="<?php echo htmlspecialchars($edit_member['birthday'] ?? ''); ?>"
                                    class="date-input w-full p-2 border rounded"
                                    placeholder="Select birthday"
                                >
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea 
                                    id="description" 
                                    name="description" 
                                    class="member-input w-full p-2 border rounded"
                                    placeholder="Enter description"
                                ><?php echo htmlspecialchars($edit_member['description'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select id="role" name="role" class="member-input w-full p-2 border rounded">
                                    <option value="user" <?php echo $edit_member['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                            <button 
                                type="submit" 
                                class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                            >
                                Update Member
                            </button>
                            <a href="members.php" class="block text-center mt-4 text-indigo-600 hover:underline">Cancel Edit</a>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Members Table -->
                <h2 class="text-2xl font-semibold mb-4">Current Members</h2>
                <?php if (empty($members)): ?>
                    <p class="text-gray-600">No members found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border rounded-lg">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Photo</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Name</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Username</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Email</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Phone</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Birthday</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Description</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Role</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Joined</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Created</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr class="border-t">
                                        <td class="py-3 px-4">
                                            <?php if (!empty($member['photo_data']) && !empty($member['photo_mime'])): ?>
                                                <img src="data:<?php echo htmlspecialchars($member['photo_mime']); ?>;base64,<?php echo htmlspecialchars($member['photo_data']); ?>" class="w-10 h-10 rounded-full" alt="Profile">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                                    <?php echo htmlspecialchars(substr($member['NAME'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['NAME'] ?? 'Unknown'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['username']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['birthday'] ?? 'N/A'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['description'] ?? 'N/A'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['role']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['join_date'] === '0000-00-00 00:00:00' ? 'N/A' : $member['join_date']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($member['created_at']); ?></td>
                                        <td class="py-3 px-4">
                                            <a href="members.php?edit_id=<?php echo htmlspecialchars($member['id']); ?>" class="inline-block bg-yellow-500 text-white px-3 py-1 rounded-lg hover:bg-yellow-600">Edit</a>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_member">
                                                <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>