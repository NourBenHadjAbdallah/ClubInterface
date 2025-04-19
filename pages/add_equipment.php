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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $image = $_FILES['image'] ?? null;

    $errors = [];
    if (empty($name)) $errors[] = "Equipment name is required";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";

    $image_mime = null;
    $image_base64 = null;
    if ($image && $image['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($image['type'], $allowed_types)) {
            $errors[] = "Image must be JPEG or PNG";
        } elseif ($image['size'] > $max_size) {
            $errors[] = "Image size must be less than 2MB";
        } else {
            $image_data = file_get_contents($image['tmp_name']);
            $image_base64 = base64_encode($image_data);
            $image_mime = $image['type'];
        }
    } elseif ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Image upload failed";
    }

    if (empty($errors)) {
        try {
            $equipment_id = uniqid('equip_');
            $stmt = $pdo->prepare("
                INSERT INTO equipment (id, name, description, quantity, image_mime, image_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$equipment_id, $name, $description, $quantity, $image_mime, $image_base64]);
            $success = "Equipment added successfully!";
            header('Location: add_equipment.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Equipment - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../styles/add_equipment.css">
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

            <div class="add-equipment-container">
                <h1 class="text-3xl font-bold mb-6">Add Equipment</h1>
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
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Equipment Name</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            class="equipment-input"
                            placeholder="e.g., Basketball"
                        >
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            class="equipment-input"
                            placeholder="e.g., Standard size basketball"
                        ></textarea>
                    </div>
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                        <input 
                            type="number" 
                            id="quantity" 
                            name="quantity" 
                            required 
                            min="1" 
                            class="equipment-input"
                            placeholder="e.g., 10"
                        >
                    </div>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">Equipment Image (Optional, JPEG/PNG, max 2MB)</label>
                        <input 
                            type="file" 
                            id="image" 
                            name="image" 
                            accept="image/jpeg,image/png" 
                            class="file-input"
                        >
                    </div>
                    <button 
                        type="submit" 
                        class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                    >
                        Add Equipment
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>