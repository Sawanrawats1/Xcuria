<?php
session_start();
require_once('../Includes/db_connect.php'); // adjust path as needed

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../Auth/login_form.html");
    exit();
}

// Initialize message variables
$success = '';
$error = '';

// Handle form submission for Add or Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'] ?? null; // for edit
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($role)) {
        $error = "Name, Email, and Role are required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists (except current user if editing)
        if ($id) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $id);
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email already in use.";
        } else {
            if ($id) {
                // Update existing user
                if (!empty($password)) {
                    // Hash new password
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssi", $name, $email, $role, $hashed, $id);
                } else {
                    // Update without password
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                    $stmt->bind_param("sssi", $name, $email, $role, $id);
                }
                if ($stmt->execute()) {
                    $success = "User updated successfully.";
                } else {
                    $error = "Error updating user.";
                }
            } else {
                // Insert new user - password required
                if (empty($password)) {
                    $error = "Password is required for new users.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $role, $hashed);
                    if ($stmt->execute()) {
                        $success = "New user added successfully.";
                    } else {
                        $error = "Error adding user.";
                    }
                }
            }
        }
        $stmt->close();
    }
}

// Handle delete user
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Prevent deleting self
    if ($del_id === $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            $success = "User deleted successfully.";
        } else {
            $error = "Error deleting user.";
        }
        $stmt->close();
    }
}

// If editing, fetch user data
$editUser = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $editUser = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all users
$users = $conn->query("SELECT id, name, email, role FROM users ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Manage Users - Admin</title>
<style>
    body { font-family: Arial, sans-serif; background:#f9f9f9; margin:0; padding:20px; }
    h1 { text-align:center; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #4a4aff; color: white; }
    a.button {
        background: #4a4aff;
        color: white;
        padding: 6px 12px;
        text-decoration: none;
        border-radius: 4px;
        margin-right: 5px;
    }
    a.button:hover { background: #3333cc; }
    form {
        max-width: 500px;
        background: white;
        padding: 20px;
        border-radius: 6px;
        margin: 0 auto 50px auto;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    form div {
        margin-bottom: 12px;
    }
    label {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
    }
    input[type="text"], input[type="email"], input[type="password"], select {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
    }
    input[type="submit"] {
        background: #4a4aff;
        color: white;
        border: none;
        padding: 10px 18px;
        cursor: pointer;
        border-radius: 4px;
        font-size: 16px;
    }
    input[type="submit"]:hover {
        background: #3333cc;
    }
    .message {
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 250px;
        padding: 15px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        font-weight: bold;
        z-index: 9999;
        opacity: 1;
        transition: opacity 0.5s ease;
        text-align: center;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .topnav {
        text-align: center;
        margin-bottom: 20px;
    }
    .topnav a {
        background: #4a4aff;
        color: white;
        padding: 10px 15px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        margin: 0 5px;
        display: inline-block;
    }
    .topnav a:hover {
        background: #3333cc;
    }
    /* Modal styles */
    #deleteModal {
        display: none;
        position: fixed;
        top:0; left:0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    #deleteModal .modal-content {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 400px;
        margin: auto;
        text-align: center;
        box-shadow: 0 0 10px rgba(0,0,0,0.25);
    }
    #deleteModal p {
        font-size: 18px;
        margin-bottom: 20px;
    }
    #deleteModal button {
        border: none;
        padding: 10px 20px;
        margin: 0 10px;
        cursor: pointer;
        border-radius: 4px;
        color: #fff;
        font-weight: bold;
    }
    #confirmDelete {
        background: #d9534f;
    }
    #cancelDelete {
        background: #6c757d;
    }
</style>
</head>
<body>

<h1>Manage Users</h1>

<div class="topnav">
    <a href="admin.php">Back to Dashboard</a>
    <a href="../Auth/logout.php">Logout</a>
</div>

<?php if ($success): ?>
    <div id="msg" class="message success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div id="msg" class="message error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- User Form -->
<form method="post" action="">
    <input type="hidden" name="id" value="<?php echo $editUser ? $editUser['id'] : ''; ?>" />
    <div>
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>" />
    </div>
    <div>
        <label for="email">Email *</label>
        <input type="email" name="email" id="email" required value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" />
    </div>
    <div>
        <label for="role">Role *</label>
        <select name="role" id="role" required>
            <?php
            $roles = ['student', 'faculty', 'admin', 'group'];
            foreach ($roles as $role) {
                $selected = ($editUser && $editUser['role'] === $role) ? 'selected' : '';
                echo "<option value=\"$role\" $selected>" . ucfirst($role) . "</option>";
            }
            ?>
        </select>
    </div>
    <div>
        <label for="password"><?php echo $editUser ? 'New Password (leave blank to keep current)' : 'Password *'; ?></label>
        <input type="password" name="password" id="password" <?php echo $editUser ? '' : 'required'; ?> />
    </div>
    <div>
        <input type="submit" value="<?php echo $editUser ? 'Update User' : 'Add User'; ?>" />
        <?php if ($editUser): ?>
            <a href="manage_users.php" style="margin-left: 10px;">Cancel</a>
        <?php endif; ?>
    </div>
</form>

<!-- Users Table -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($users && $users->num_rows > 0): ?>
            <?php while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo ucfirst($row['role']); ?></td>
                    <td>
                        <a class="button" href="manage_users.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <a href="#" class="button delete-btn" data-id="<?php echo $row['id']; ?>">Delete</a>
                        <?php else: ?>
                            <span style="color: gray;">(You)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No users found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Delete Confirmation Modal -->
<div id="deleteModal">
  <div class="modal-content">
    <p>Are you sure you want to delete this user?</p>
    <button id="confirmDelete">Yes, Delete</button>
    <button id="cancelDelete">Cancel</button>
  </div>
</div>

<script>
// Auto fade message popup
window.onload = function() {
    const msg = document.getElementById('msg');
    if (msg) {
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => {
                if (msg.parentNode) {
                    msg.parentNode.removeChild(msg);
                }
            }, 500);
        }, 4000);
    }
};

// Modal delete confirmation
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDelete');
    const cancelBtn = document.getElementById('cancelDelete');
    let deleteUserId = null;

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            deleteUserId = this.getAttribute('data-id');
            modal.style.display = 'flex';
        });
    });

    confirmBtn.addEventListener('click', function() {
        if (deleteUserId) {
            window.location.href = 'manage_users.php?delete=' + deleteUserId;
        }
    });

    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        deleteUserId = null;
    });

    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            deleteUserId = null;
        }
    });
});
</script>

</body>
</html>
