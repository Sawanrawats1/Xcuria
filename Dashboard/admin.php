<?php
session_start();
require_once('../Includes/db_connect.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../Auth/login_form.html");
    exit();
}

// Get filter parameters safely
$filterDept = $_GET['department'] ?? '';
$filterYear = $_GET['year'] ?? '';

// --- START: NEW NOTIFICATION LOGIC ---
$unreadNotifications = [];
$unreadCount = 0;

// Prepare and execute the query to get unread notifications for the current user
$stmt_notifications = $conn->prepare("SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt_notifications->bind_param("i", $_SESSION['user_id']);
$stmt_notifications->execute();
$result_notifications = $stmt_notifications->get_result();

if ($result_notifications->num_rows > 0) {
    $unreadCount = $result_notifications->num_rows;
    while ($row = $result_notifications->fetch_assoc()) {
        $unreadNotifications[] = $row;
    }
}
$stmt_notifications->close();
// --- END: NEW NOTIFICATION LOGIC ---


// Fetch counts for dashboard (total counts without filters)
$totalUsers = $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'];
$totalEvents = $conn->query("SELECT COUNT(*) AS count FROM events")->fetch_assoc()['count'];
$totalGroups = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='group'")->fetch_assoc()['count'];

// NEW: Fetch count for total students and total faculty
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='student'")->fetch_assoc()['count'];
$totalFaculty = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='faculty'")->fetch_assoc()['count'];

// --- Analytics dashboard data ---
// Most popular categories by event count and participation count
$popularCategoriesResult = $conn->query("
    SELECT i.name AS interest_name,
           COUNT(DISTINCT e.id) AS event_count,
           COUNT(p.event_id) AS participation_count
    FROM interests i
    LEFT JOIN events e ON e.interest_id = i.id
    LEFT JOIN participation p ON p.event_id = e.id
    GROUP BY i.id, i.name
    ORDER BY participation_count DESC
");
$popularCategories = [];
while ($row = $popularCategoriesResult->fetch_assoc()) {
    $popularCategories[] = $row;
}

// Department-wise engagement (participation counts by student department)
$deptEngagementResult = $conn->query("
    SELECT u.department, COUNT(p.event_id) AS participation_count
    FROM participation p
    JOIN users u ON p.user_id = u.id
    WHERE u.department IS NOT NULL AND u.department != ''
    GROUP BY u.department
    ORDER BY participation_count DESC
");
$deptEngagement = [];
while ($row = $deptEngagementResult->fetch_assoc()) {
    $deptEngagement[] = $row;
}

// Overall attendance rate (present / total marked) — gracefully handles the
// case where schema_updates.sql hasn't been run yet (attendance table absent)
$attendanceRate = null;
$attendanceTableCheck = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($attendanceTableCheck && $attendanceTableCheck->num_rows > 0) {
    $attRow = $conn->query("
        SELECT
            SUM(status = 'present') AS present_count,
            COUNT(*) AS total_marked
        FROM attendance
    ")->fetch_assoc();
    if ($attRow && $attRow['total_marked'] > 0) {
        $attendanceRate = round(($attRow['present_count'] / $attRow['total_marked']) * 100, 1);
    }
}


// Build interests query with filters on student department/year
$interestsQuery = "
    SELECT interests.id, interests.name, COUNT(user_interests.user_id) AS total_students
    FROM interests
    LEFT JOIN user_interests ON interests.id = user_interests.interest_id
    LEFT JOIN users ON users.id = user_interests.user_id AND users.role = 'student'
";

if ($filterDept || $filterYear) {
    $interestsQuery .= " WHERE 1=1";
    if ($filterDept) {
        $interestsQuery .= " AND users.department = '" . $conn->real_escape_string($filterDept) . "'";
    }
    if ($filterYear) {
        $interestsQuery .= " AND users.year = '" . $conn->real_escape_string($filterYear) . "'";
    }
}

$interestsQuery .= "
    GROUP BY interests.id, interests.name
    ORDER BY total_students DESC;
";
$interestsResult = $conn->query($interestsQuery);

// Build students query with filters
$studentsQuery = "
    SELECT interests.id AS interest_id, users.name AS student_name, users.department, users.year
    FROM user_interests
    JOIN users ON users.id = user_interests.user_id AND users.role = 'student'
    JOIN interests ON interests.id = user_interests.interest_id
";

$whereConditions = [];
if ($filterDept) {
    $whereConditions[] = "users.department = '" . $conn->real_escape_string($filterDept) . "'";
}
if ($filterYear) {
    $whereConditions[] = "users.year = '" . $conn->real_escape_string($filterYear) . "'";
}

if (count($whereConditions) > 0) {
    $studentsQuery .= " WHERE " . implode(' AND ', $whereConditions);
}

$studentsQuery .= " ORDER BY interests.name, users.name;";
$studentsResult = $conn->query($studentsQuery);

$studentsByInterest = [];
while ($row = $studentsResult->fetch_assoc()) {
    $studentsByInterest[$row['interest_id']][] = $row;
}

// Fetch all feedback for the admin panel
$feedbackQuery = "SELECT feedback.*, users.name as user_name FROM feedback JOIN users ON feedback.user_id = users.id ORDER BY feedback.created_at DESC";
$feedbackResult = $conn->query($feedbackQuery);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard - Xcuria</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<style>
    /* ===== Reset & Global ===== */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f6fb;
        display: flex;
        min-height: 100vh;
    }

    /* ===== Sidebar ===== */
    .sidebar {
        width: 220px;
        background-color: #1f2937;
        color: white;
        padding: 30px 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .sidebar h2 {
        font-size: 20px;
        margin-bottom: 0;
        text-align: center;
    }
    .sidebar p {
        color: #bbb;
        font-size: 14px;
        text-align: center;
        margin-top: 5px;
    }

    .sidebar a {
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        background-color: #374151;
        border-radius: 8px;
        transition: background-color 0.2s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }

    .sidebar a:hover {
        background-color: #4b5563;
    }

    /* ===== Main Content ===== */
    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #f4f6fb;
    }

    /* ===== Navbar ===== */
    .navbar {
        background-color: #ffffff;
        padding: 20px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar h2 {
        font-size: 22px;
        color: #1f2937;
        margin: 0;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
    }

   /* Notification bell */
.notification {
    position: relative;
    cursor: pointer;
}
.notification i {
    font-size: 20px;
    color: #333;
    transition: color 0.3s ease; /* Added for a smooth hover effect */
}

/* --- NEW: Hover effect for the bell icon --- */
.notification i:hover {
    color: #007bff; /* Changed color on hover */
}

/* --- NEW: Notification badge style --- */
.notification .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ff3b30;
    color: white;
    font-size: 10px;
    font-weight: bold;
    border-radius: 50%;
    padding: 3px 6px;
    line-height: 1;
    display: block; /* Display only if there are notifications */
}

#notificationDropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 30px;
    background: #fff;
    color: #333;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    padding: 10px;
    border-radius: 6px;
    min-width: 220px;
    z-index: 1000;
    font-size: 14px;
}
#notificationDropdown strong {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 15px;
}
#notificationDropdown ul {
    list-style: none;
    padding-left: 0;
    max-height: 150px;
    overflow-y: auto;
}
#notificationDropdown ul li {
    padding: 6px 0;
    border-bottom: 1px solid #ddd;
    transition: background-color 0.3s ease; /* Added for a smooth hover effect */
}

/* --- NEW: Hover effect for list items --- */
#notificationDropdown ul li:hover {
    background-color: #f0f0f0; /* Change background color on hover */
    cursor: pointer;
}

/* --- NEW: Style for the "Mark All as Read" link --- */
.notification-footer {
    text-align: center;
    border-top: 1px solid #eee;
    padding-top: 8px;
}

.notification-footer a {
    text-decoration: none;
    color: #007bff;
    font-weight: bold;
}

.notification-footer a:hover {
    text-decoration: underline;
}
    /* --- END: NEW --- */

    /* Profile dropdown */
    .profile-dropdown {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        user-select: none;
    }
    .profile-dropdown img {
        border-radius: 50%;
        width: 35px;
        height: 35px;
        object-fit: cover;
        border: 1.5px solid #4a4aff;
    }
    .profile-dropdown span {
        color: #333;
        font-weight: 600;
    }
    .profile-dropdown i {
        color: #333;
        font-size: 12px;
    }
    #profileDropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 45px;
        background-color: #fff;
        color: #000;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        border-radius: 6px;
        padding: 10px;
        min-width: 160px;
        z-index: 1000;
    }
    #profileDropdown a {
        display: block;
        padding: 8px 0;
        text-decoration: none;
        color: #333;
    }
    #profileDropdown a:hover {
        background-color: #f2f2f2;
    }
    .profile-dropdown.show #profileDropdown {
        display: block;
    }

    /* ===== Container & Cards ===== */
    .container {
        padding: 30px 40px;
        max-width: 1100px;
        margin: 0 auto;
        flex: 1;
        overflow-y: auto;
    }

    .cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .card {
        flex: 1;
        min-width: 250px;
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        text-align: center;
    }

    .card h3 {
        margin-bottom: 10px;
        font-size: 20px;
    }

    .card p {
        font-size: 26px;
        font-weight: bold;
        color: #4a4aff;
    }

    /* ===== Interests Section ===== */
    #interestsSection {
        margin-top: 40px;
        display: none;
    }

    #interestsSection table {
        width: 100%;
        border-collapse: collapse;
    }

    #interestsSection th, #interestsSection td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    #interestsSection thead tr {
        background-color: #4a4aff;
        color: white;
    }

    .student-list {
        display: none;
        background: #f9fafb;
        padding: 10px;
        border-radius: 6px;
        text-align: left;
    }

    .student-entry {
        padding: 5px 0;
    }

    button.toggle-students {
        background: #4a4aff;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
    }

    button.toggle-students:hover {
        background: #3333cc;
    }

    /* ===== Filter Form ===== */
    .filters {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .filters label {
        font-weight: 600;
        color: #333;
    }

    .filters select, .filters button {
        padding: 10px;
        font-size: 16px;
    }
     .btn-view {
            padding: 8px 14px;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-view:hover {
            background-color: #0f9f75;
        }

    /* New CSS for the Feedback section table and button */
    #feedbackSection {
        margin-top: 40px;
    }
    #feedbackSection h2 {
        margin-bottom: 20px;
        color: #1f2937;
    }
    #feedbackSection table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        border-radius: 8px;
    }
    #feedbackSection th, #feedbackSection td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }
    #feedbackSection thead tr {
        background-color: #1f2937;
        color: white;
    }
    .action-btn {
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
    }
    .delete-btn {
        background-color: #ef4444; /* Red color for delete */
        color: white;
    }
    .delete-btn:hover {
        background-color: #dc2626;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 400px;
        text-align: center;
        border-radius: 10px;
    }

    .modal-content h3 {
        margin-top: 0;
        color: #333;
    }

    .modal-content p {
        color: #666;
        margin-bottom: 20px;
    }

    .modal-content button {
        padding: 10px 20px;
        font-size: 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin: 0 5px;
        transition: background-color 0.3s;
    }

    .cancel-btn {
        background-color: #ccc;
        color: #333;
    }

    .cancel-btn:hover {
        background-color: #bbb;
    }
</style>
</head>
<body>

<div class="sidebar">
    <h2>Admin Panel - Xcuria</h2>
    <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
    <a href="../home.php"><i class="fa-solid fa-house"></i> Home</a>
    <a href="manage_users.php"><i class="fa-solid fa-users"></i> Manage Users</a>
    <a href="manage_events.php"><i class="fa-solid fa-calendar"></i> Manage Events</a>
    <a href="#" onclick="showSection('feedbackSection')"><i class="fa-solid fa-comment"></i> Student Feedback</a>
    <a href="#" onclick="showSection('analyticsSection')"><i class="fa-solid fa-chart-column"></i> Analytics</a>
    <a href="../Auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="navbar">
        <h2>Admin Dashboard</h2>
        <div class="navbar-right">
            <div class="notification" onclick="toggleNotifications(event)" title="Notifications">
                <i class="fas fa-bell"></i>
                <!-- --- Display the unread count badge --- -->
                <?php if ($unreadCount > 0): ?>
                    <span class="badge" id="notification-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
                <!-- --- END: Notification badge --- -->

                <div id="notificationDropdown">
                    <strong>Notifications</strong>
                    <ul>
                        <!-- --- Dynamic list of notifications --- -->
                        <?php if (!empty($unreadNotifications)): ?>
                            <?php foreach ($unreadNotifications as $notification): ?>
                                <li>
                                    <?= htmlspecialchars($notification['message']) ?><br>
                                    <small><?= htmlspecialchars($notification['created_at']) ?></small>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No new notifications</li>
                        <?php endif; ?>
                        <!-- --- END: Dynamic list --- -->
                        <!-- --- NEW: "Mark All as Read" link --- -->
                        <li class="notification-footer">
                            <a href="#" onclick="markAllAsRead(event)">Mark all as read</a>
                        </li>
                        <!-- --- END: NEW --- -->
                    </ul>
                </div>
            </div>

            <div class="profile-dropdown" id="profileDropdownContainer" onclick="toggleProfileDropdown(event)">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=random" alt="Profile" />
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <i class="fas fa-chevron-down"></i>

                <div id="profileDropdown">
                    <a href="../profile.php">My Profile</a>
                    <a href="../Auth/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="card">
                <h3>Total Events</h3>
                <p><?php echo $totalEvents; ?></p>
            </div>
            <div class="card">
                <h3>Total Groups</h3>
                <p><?php echo $totalGroups; ?></p>
            </div>
            <div class="card">
                <h3>Total Students</h3>
                <p><?php echo $totalStudents; ?></p>
            </div>
            <div class="card">
                <h3>Total Faculty</h3>
                <p><?php echo $totalFaculty; ?></p>
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center;">
            <button id="toggleInterestsBtn" class="toggle-students">👀 See Students by Interest</button>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <label for="department">Filter by Department:</label>
                <select name="department" id="department">
                    <option value="">All</option>
                    <option value="CSE" <?= ($filterDept ?? '') == "CSE" ? "selected" : "" ?>>CSE</option>
                    <option value="ECE" <?= ($filterDept ?? '') == "ECE" ? "selected" : "" ?>>ECE</option>
                    <option value="ME"  <?= ($filterDept ?? '') == "ME"  ? "selected" : "" ?>>ME</option>
                </select>

                <label for="year">Filter by Year:</label>
                <select name="year" id="year">
                    <option value="">All</option>
                    <option value="1st" <?= ($filterYear ?? '') == "1st" ? "selected" : "" ?>>1st</option>
                    <option value="2nd" <?= ($filterYear ?? '') == "2nd" ? "selected" : "" ?>>2nd</option>
                    <option value="3rd" <?= ($filterYear ?? '') == "3rd" ? "selected" : "" ?>>3rd</option>
                    <option value="4th" <?= ($filterYear ?? '') == "4th" ? "selected" : "" ?>>4th</option>
                </select>

                <button type="submit">Apply Filters</button>
            </form>
        </div>

        <section id="interestsSection">
            <h2>📊 Student Interests Overview</h2>
            <table>
                <thead>
                    <tr>
                        <th>Interest</th>
                        <th>Total Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $interestsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['total_students'] ?></td>
                        <td>
                            <button class="btn-view" onclick="toggleStudentList(<?= $row['id'] ?>)">👥 View Students</button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <div id="students-<?= $row['id'] ?>" class="student-list">
                                <?php if (!empty($studentsByInterest[$row['id']])): ?>
                                    <?php foreach ($studentsByInterest[$row['id']] as $student): ?>
                                    <div class="student-entry">
                                        🔹 <strong><?= htmlspecialchars($student['student_name']) ?></strong>
                                        (<?= htmlspecialchars($student['department']) ?>, <?= htmlspecialchars($student['year']) ?>)
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="student-entry">No students found for this interest.</div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <section id="feedbackSection" style="display: none;">
            <h2>💬 Student Feedback</h2>
            <?php if (isset($_GET['message'])): ?>
                <p style="color: green; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($_GET['message']) ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <p style="color: red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Message</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($feedbackResult->num_rows > 0): ?>
                        <?php while ($fb = $feedbackResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fb['user_name']) ?></td>
                                <td><?= nl2br(htmlspecialchars($fb['comment'])) ?></td>
                                <td><?= htmlspecialchars($fb['created_at']) ?></td>
                                <td>
                                    <button class="action-btn delete-btn" onclick="openDeleteFeedbackModal(<?= $fb['id'] ?>)">🗑️ Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No feedback found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="analyticsSection" style="display: none;">
            <h2>📈 Engagement Analytics</h2>

            <div class="cards">
                <div class="card">
                    <h3>Overall Attendance Rate</h3>
                    <p>
                        <?php if ($attendanceRate !== null): ?>
                            <?= $attendanceRate ?>%
                        <?php else: ?>
                            <span style="font-size: 0.9rem;">No attendance data yet</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
                <div style="flex: 1; min-width: 320px; max-width: 500px;">
                    <h3>Most Popular Activity Categories</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                <div style="flex: 1; min-width: 320px; max-width: 500px;">
                    <h3>Department-wise Engagement</h3>
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const categoryLabels = <?= json_encode(array_column($popularCategories, 'interest_name')) ?>;
const categoryEventCounts = <?= json_encode(array_map('intval', array_column($popularCategories, 'event_count'))) ?>;
const categoryParticipationCounts = <?= json_encode(array_map('intval', array_column($popularCategories, 'participation_count'))) ?>;

const deptLabels = <?= json_encode(array_column($deptEngagement, 'department')) ?>;
const deptCounts = <?= json_encode(array_map('intval', array_column($deptEngagement, 'participation_count'))) ?>;

if (categoryLabels.length > 0) {
    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [
                { label: 'Events Created', data: categoryEventCounts, backgroundColor: '#6c5ce7' },
                { label: 'Student Participations', data: categoryParticipationCounts, backgroundColor: '#00b894' }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
} else {
    document.getElementById('categoryChart').replaceWith('No category data yet.');
}

if (deptLabels.length > 0) {
    new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: {
            labels: deptLabels,
            datasets: [{ label: 'Participations', data: deptCounts, backgroundColor: '#0984e3' }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
} else {
    document.getElementById('deptChart').replaceWith('No department engagement data yet.');
}
</script>

<div id="deleteFeedbackModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this feedback?</p>
        <input type="hidden" id="deleteFeedbackId">
        <button onclick="confirmDeleteFeedback()" class="delete-btn">Yes, Delete</button>
        <button onclick="closeDeleteFeedbackModal()" class="cancel-btn">Cancel</button>
    </div>
</div>
<script>
    // Profile dropdown toggle
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const container = document.getElementById('profileDropdownContainer');
        container.classList.toggle('show');

        // Hide notifications if open
        document.getElementById('notificationDropdown').style.display = 'none';
    }

    // --- START: UPDATED NOTIFICATION LOGIC ---
    function toggleNotifications(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        const profileContainer = document.getElementById('profileDropdownContainer');

        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
            profileContainer.classList.remove('show');
        }
    }

    function markAllAsRead(e) {
        e.preventDefault(); // Prevent the default link behavior
        e.stopPropagation(); // Stop the event from propagating to the parent div

        const notificationBadge = document.getElementById('notification-badge');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationBadge) {
            fetch('../notifications/mark_notifications_read.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationBadge.style.display = 'none';
                        const notificationList = notificationDropdown.querySelector('ul');
                        notificationList.innerHTML = '<li>No new notifications</li>';
                    }
                    notificationDropdown.style.display = 'none'; // Close dropdown after action
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                    notificationDropdown.style.display = 'none'; // Close dropdown even on error
                });
        } else {
            notificationDropdown.style.display = 'none'; // Close dropdown if no badge exists
        }
    }

    // Close dropdowns when clicking outside
    window.addEventListener('click', function (event) {
        const notificationDropdown = document.getElementById('notificationDropdown');
        const profileDropdownContainer = document.getElementById('profileDropdownContainer');

        // Check if the click is outside both dropdowns
        if (!event.target.closest('.notification') && !event.target.closest('.profile-dropdown')) {
            if (notificationDropdown && notificationDropdown.style.display === 'block') {
                notificationDropdown.style.display = 'none';
            }
            if (profileDropdownContainer && profileDropdownContainer.classList.contains('show')) {
                profileDropdownContainer.classList.remove('show');
            }
        }
    });

    // Modified function to show/hide a specific content section
    function showSection(sectionId) {
        const sectionToShow = document.getElementById(sectionId);
        const sections = document.querySelectorAll('section');

        // If the section is already visible, hide it. Otherwise, show it.
        if (sectionToShow && sectionToShow.style.display === 'block') {
            sectionToShow.style.display = 'none';
        } else {
            // Hide all sections first
            sections.forEach(section => {
                section.style.display = 'none';
            });
            // Show the requested section
            if (sectionToShow) {
                sectionToShow.style.display = 'block';
            }
        }

        // Handle the interests button text for consistency
        const interestsSection = document.getElementById('interestsSection');
        const toggleInterestsBtn = document.getElementById('toggleInterestsBtn');
        if (interestsSection.style.display === 'block') {
            toggleInterestsBtn.textContent = '🙈 Hide Students by Interest';
        } else {
            toggleInterestsBtn.textContent = '👀 See Students by Interest';
        }
    }

    // Toggle interests section
    const toggleInterestsBtn = document.getElementById('toggleInterestsBtn');
    const interestsSection = document.getElementById('interestsSection');
    
    toggleInterestsBtn.addEventListener('click', () => {
        if (interestsSection.style.display === 'block') {
            interestsSection.style.display = 'none';
            toggleInterestsBtn.textContent = '👀 See Students by Interest';
        } else {
            // Hide other sections when showing interests
            document.getElementById('feedbackSection').style.display = 'none';
            interestsSection.style.display = 'block';
            toggleInterestsBtn.textContent = '🙈 Hide Students by Interest';
        }
    });
    
    // Toggle student list visibility
    function toggleStudentList(id) {
        const list = document.getElementById('students-' + id);
        if (list.style.display === 'block') {
            list.style.display = 'none';
        } else {
            list.style.display = 'block';
        }
    }
    
    // Modal functions for feedback deletion
    function openDeleteFeedbackModal(id) {
        document.getElementById("deleteFeedbackId").value = id;
        document.getElementById("deleteFeedbackModal").style.display = "block";
    }

    function closeDeleteFeedbackModal() {
        document.getElementById("deleteFeedbackModal").style.display = "none";
    }

    function confirmDeleteFeedback() {
        const id = document.getElementById("deleteFeedbackId").value;
        window.location.href = `admin_delete_feedback.php?id=${id}`;
    }
</script>

</body>
</html>
