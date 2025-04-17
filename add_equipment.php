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

// Handle create equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_equipment') {
    $name = $_POST['name'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $specifications = $_POST['specifications'] ?? '';

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Equipment name is required";
    }
    if (empty($brand)) {
        $errors[] = "Brand is required";
    }
    if (empty($model)) {
        $errors[] = "Model is required";
    }

    if (empty($errors)) {
        try {
            $equipment_id = uniqid('equip_');
            $stmt = $pdo->prepare("
                INSERT INTO equipment (equipment_id, name, brand, model, specifications, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$equipment_id, $name, $brand, $model, $specifications]);
            $success = "AV equipment added successfully!";
            header('Location: equipment_shop.php');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Add equipment failed: " . $e->getMessage());
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
    <title>Add New Equipment - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
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
                <h1 class="text-3xl font-bold mb-6">Add New Equipment</h1>

                <?php if ($error): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php elseif ($success): ?>
                    <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 text-center">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6 mb-12">
                    <input type="hidden" name="action" value="create_equipment">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Equipment Type</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            placeholder="e.g., DSLR Camera"
                        >
                    </div>
                    <div>
                        <label for="brand" class="block text-sm font-medium text-gray-700">Brand</label>
                        <input 
                            type="text" 
                            id="brand" 
                            name="brand" 
                            required 
                            value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>"
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            placeholder="e.g., Canon"
                        >
                    </div>
                    <div>
                        <label for="model" class="block text-sm font-medium text-gray-700">Model</label>
                        <input 
                            type="text" 
                            id="model" 
                            name="model" 
                            required 
                            value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>"
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            placeholder="e.g., EOS 5D Mark IV"
                        >
                    </div>
                    <div>
                        <label for="specifications" class="block text-sm font-medium text-gray-700">Specifications</label>
                        <textarea 
                            id="specifications" 
                            name="specifications" 
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus"
                            placeholder="e.g., 30.4MP, 4K video, 61-point AF"
                        ><?php echo isset($_POST['specifications']) ? htmlspecialchars($_POST['specifications']) : ''; ?></textarea>
                    </div>
                    <button 
                        type="submit" 
                        class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                    >
                        Add Equipment
                    </button>
                    <a href="equipment_shop.php" class="block text-center mt-4 text-indigo-600 hover:underline">Back to Equipment Shop</a>
                </form>
            </div>
        </main>
    </div>
</body>
</html>