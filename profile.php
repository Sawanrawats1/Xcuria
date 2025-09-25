<?php
// This script creates a user profile page by fetching data from your 'users' table.
// The database connection details have been updated based on your 'db_connect.php' file.

// Start the session to access user data.
session_start();

// --- Database Configuration (UPDATED FROM YOUR DETAILS) ---
$host = 'localhost';
$dbname = 'xcuria';      // Your database name
$user = 'root';          // Your username
$pass = '';              // Your password (blank)
$port = 3308;            // Your specific port number

// --- Database Connection ---
try {
    // The DSN string now includes the specific port.
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In a production environment, you should log this error and show a generic message.
    die("Database connection failed: " . $e->getMessage());
}

// --- Determine which user to display ---
$userId = null;
$loggedInUserId = $_SESSION['user_id'] ?? null;

// Check if a user ID is in the URL. This takes priority.
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];
} elseif ($loggedInUserId) {
    // If no ID is in the URL, use the logged-in user's ID from the session.
    $userId = $loggedInUserId;
}

// --- Retrieve User Data from Database ---
$user = null;
if ($userId) {
    // Use a prepared statement to prevent SQL injection attacks.
    // The SQL query also fetches the 'role' column.
    $stmt = $pdo->prepare("SELECT name, email, role, department, year FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

// --- Determine the correct dashboard link based on user role ---
$dashboardLink = 'dashboard/student.php'; // Default dashboard for unknown roles or admins
if ($user) {
    switch ($user['role']) {
        case 'faculty':
            $dashboardLink = 'dashboard/faculty.php';
            break;
        case 'group':
            $dashboardLink = 'dashboard/group.php';
            break;
        case 'admin':
            $dashboardLink = 'dashboard/admin.php';
            break;
        case 'student':
        default:
            $dashboardLink = 'dashboard/student.php';
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }
        .profile-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        .profile-title {
            font-size: 2.5rem;
            color: #4a90e2;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0.5rem;
        }
        .profile-info {
            margin-bottom: 1.5rem;
        }
        .profile-info p {
            font-size: 1.2rem;
            line-height: 1.6;
            margin: 0.8rem 0;
        }
        .profile-info strong {
            color: #555;
        }
        .not-found {
            font-size: 1.5rem;
            color: #d9534f;
        }
        .profile-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 1rem;
            color: #fff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .dashboard-btn {
            background-color: #4a90e2;
        }
        .dashboard-btn:hover {
            background-color: #357bd8;
        }
        .logout-btn {
            background-color: #d9534f;
        }
        .logout-btn:hover {
            background-color: #c9302c;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1.5rem;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <?php if ($user): ?>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random&color=fff&size=128&rounded=true" alt="User Avatar" class="profile-avatar">
            <h1 class="profile-title"><?php echo htmlspecialchars($user['name']); ?>'s Profile</h1>
            <div class="profile-info">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>

                <?php
                // Conditionally display department and year only if the user is a student.
                if ($user['role'] === 'student'): ?>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
                    <p><strong>Year:</strong> <?php echo htmlspecialchars($user['year']); ?></p>
                <?php endif; ?>

            </div>
            <!-- Add a row for both action buttons -->
            <div class="profile-actions">
                <a href="<?php echo htmlspecialchars($dashboardLink); ?>" class="btn dashboard-btn">Back to Dashboard</a>
                <a href="Auth/logout.php" class="btn logout-btn">Logout</a>
            </div>
        <?php else: ?>
            <h1 class="profile-title">Profile Not Found</h1>
            <p class="not-found">
                <?php
                if (isset($_GET['id'])) {
                    echo "The user you are looking for does not exist.";
                } else {
                    echo "Please provide a user ID in the URL, e.g., <code>profile.php?id=1</code>";
                }
                ?>
            </p>
        <?php endif; ?>
    </div>

</body>
</html>
