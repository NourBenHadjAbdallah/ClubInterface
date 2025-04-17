<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin_main.php');
    } else {
        header('Location: user_main.php');
    }
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Initialize error message
$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user'] = [
                'username' => $user['username'],
                'role' => $user['role']
            ];
            if ($user['role'] === 'admin') {
                header('Location: admin_main.php');
            } else {
                header('Location: user_main.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6B7280, #1F2937);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Club Administration</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="Enter username"
                >
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    placeholder="Enter password"
                >
            </div>
            <button 
                type="submit" 
                class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
            >
                Login
            </button>
        </form>
        <p class="mt-4 text-center text-gray-600">
            Not a member? <a href="become_member.php" class="text-indigo-600 hover:underline">Sign up</a>
        </p>
    </div>
</body>
</html>