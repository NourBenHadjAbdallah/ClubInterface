<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db_connect.php';

// Handle become a member form submission
$member_error = null;
$member_success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'become_member') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $new_username = $_POST['new_username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $photo = $_FILES['photo'] ?? null;

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if (!empty($phone) && !preg_match('/^\+?[\d\s-]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    if (empty($new_username)) {
        $errors[] = "Username is required";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
        $errors[] = "Username must contain only letters, numbers, or underscores";
    }
    if (empty($new_password) || strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    // Check if username or email exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$new_username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists";
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already registered";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }

    // Validate photo (optional)
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
            // Insert into users
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, password, role, name, email, phone, 
                    photo_mime, photo_data, created_at
                ) VALUES (?, ?, 'user', ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $new_username,
                password_hash($new_password, PASSWORD_DEFAULT),
                $name,
                $email,
                $phone,
                $photo_mime,
                $photo_base64
            ]);

            $member_success = "Registration successful! Please log in.";
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    if (!empty($errors)) {
        $member_error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Member - Club Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="min-h-screen gradient-bg text-gray-800 flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="card bg-white rounded-2xl shadow-lg p-6 md:p-8">
            <h1 class="text-3xl md:text-4xl font-bold mb-4 text-gray-800 text-center">Join Our Club</h1>
            <p class="text-gray-600 mb-6 text-center">
                Become a member to enjoy exclusive benefits, including access to premium equipment, 
                community events, and personalized support. Fill out the form below to get started!
            </p>

            <?php if (isset($member_error)): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 text-center font-medium">
                    <?php echo $member_error; ?>
                </div>
            <?php elseif (isset($member_success)): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 text-center font-medium">
                    <?php echo htmlspecialchars($member_success); ?>
                    <p><a href="index.php" class="text-indigo-600 hover:underline">Go to Login</a></p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="become_member">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus bg-gray-50 text-gray-800 placeholder-gray-400"
                        placeholder="Enter your full name"
                    >
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus bg-gray-50 text-gray-800 placeholder-gray-400"
                        placeholder="Enter your email"
                    >
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number (Optional)</label>
                    <input 
                        type="text" 
                        id="phone" 
                        name="phone" 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus bg-gray-50 text-gray-800 placeholder-gray-400"
                        placeholder="e.g., +1234567890"
                    >
                </div>
                <div>
                    <label for="new_username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input 
                        type="text" 
                        id="new_username" 
                        name="new_username" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus bg-gray-50 text-gray-800 placeholder-gray-400"
                        placeholder="Choose a username"
                    >
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg input-focus bg-gray-50 text-gray-800 placeholder-gray-400"
                        placeholder="Choose a password"
                    >
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700">Profile Photo (Optional, JPEG/PNG, max 2MB)</label>
                    <input 
                        type="file" 
                        id="photo" 
                        name="photo" 
                        accept="image/jpeg,image/png" 
                        class="mt-1 w-full file-input"
                        onchange="previewPhoto(event)"
                    >
                    <img id="photo-preview" class="hidden w-24 h-24 rounded-full mt-2 object-cover" alt="Photo preview">
                </div>
                <button 
                    type="submit" 
                    class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold"
                >
                    Register
                </button>
            </form>
            <p class="mt-4 text-center text-gray-600">
                Already have an account? <a href="index.php" class="text-indigo-600 hover:underline">Login</a>
            </p>
        </div>
    </div>

    <script>
        function previewPhoto(event) {
            const input = event.target;
            const preview = document.getElementById('photo-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }
    </script>
</body>
</html>