<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "group") {
    header("Location: ../Auth/login_form.html");
    exit();
}
require_once('../Includes/db_connect.php');

$group_id = $_SESSION['user_id'];

// Filters
$filterDept = $_GET['department'] ?? '';
$filterYear = $_GET['year'] ?? '';

// Query to fetch interests with filtered student count
$interestsQuery = "
    SELECT 
        interests.id, 
        interests.name, 
        COUNT(user_interests.user_id) AS total_students
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

// Fetch students grouped by interest
$studentsQuery = "
    SELECT 
        interests.id AS interest_id,
        interests.name AS interest_name,
        users.name AS student_name,
        users.department,
        users.year
    FROM user_interests
    JOIN interests ON interests.id = user_interests.interest_id
    JOIN users ON users.id = user_interests.user_id
    WHERE users.role = 'student'
";
if ($filterDept) {
    $studentsQuery .= " AND users.department = '" . $conn->real_escape_string($filterDept) . "'";
}
if ($filterYear) {
    $studentsQuery .= " AND users.year = '" . $conn->real_escape_string($filterYear) . "'";
}
$studentsQuery .= " ORDER BY interests.name, users.name;";
$studentsResult = $conn->query($studentsQuery);
$studentsByInterest = [];
while ($row = $studentsResult->fetch_assoc()) {
    $interestId = $row['interest_id'];
    if (!isset($studentsByInterest[$interestId])) {
        $studentsByInterest[$interestId] = [];
    }
    $studentsByInterest[$interestId][] = $row;
}

// Query for upcoming events created by the current group - MODIFIED
$eventsResult = $conn->prepare("
    SELECT events.*, interests.name AS interest_name 
    FROM events 
    JOIN interests ON events.interest_id = interests.id 
    WHERE events.created_by = ?
    AND (
        events.date > CURDATE() 
        OR (events.date = CURDATE() AND events.time >= CURTIME())
    )
    ORDER BY events.date ASC, events.time ASC
");
$eventsResult->bind_param("i", $group_id);
$eventsResult->execute();
$events = $eventsResult->get_result();

// Query for participants in group-created events
$participantsQuery = $conn->prepare("
    SELECT 
        e.title AS event_title,
        u.name AS student_name,
        u.department,
        u.year
    FROM participation p
    JOIN events e ON p.event_id = e.id
    JOIN users u ON p.user_id = u.id
    WHERE e.created_by = ? 
    ORDER BY e.title, u.name
");
$participantsQuery->bind_param("i", $group_id);
$participantsQuery->execute();
$participantsResult = $participantsQuery->get_result();
$participantsByEvent = [];
while ($row = $participantsResult->fetch_assoc()) {
    $eventTitle = $row['event_title'];
    if (!isset($participantsByEvent[$eventTitle])) {
        $participantsByEvent[$eventTitle] = [];
    }
    $participantsByEvent[$eventTitle][] = $row;
}

// Query for feedback on group-created events
$feedbackQuery = $conn->prepare("
    SELECT 
        e.title AS event_title,
        u.name AS student_name,
        f.rating,
        f.comment,
        f.created_at
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    JOIN users u ON f.user_id = u.id
    WHERE e.created_by = ? 
    ORDER BY e.title, f.created_at DESC
");
$feedbackQuery->bind_param("i", $group_id);
$feedbackQuery->execute();
$feedbackResult = $feedbackQuery->get_result();
$feedbackByEvent = [];
while ($row = $feedbackResult->fetch_assoc()) {
    $eventTitle = $row['event_title'];
    if (!isset($feedbackByEvent[$eventTitle])) {
        $feedbackByEvent[$eventTitle] = [];
    }
    $feedbackByEvent[$eventTitle][] = $row;
}

// Fetch all interests for the create event form
$allInterests = $conn->query("SELECT id, name FROM interests ORDER BY name ASC");


// --- NEW CODE FOR NOTIFICATIONS ---
// This assumes a 'notifications' table exists in your database.
// Table structure: id, user_id, message, link, is_read, created_at
$notificationsQuery = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = FALSE
    ORDER BY created_at DESC
    LIMIT 5
");
$notificationsQuery->bind_param("i", $group_id);
$notificationsQuery->execute();
$notificationsResult = $notificationsQuery->get_result();
$notifications = [];
while ($row = $notificationsResult->fetch_assoc()) {
    $notifications[] = $row;
}
// --- END NEW CODE ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Dashboard - Xcuria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        /* ... existing CSS ... */
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
            margin-bottom: 20px;
            text-align: center;
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
        }
        .sidebar a:hover {
            background-color: #4b5563;
        }
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
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
}

.navbar-right .notification {
    position: relative; /* This is essential for positioning the dropdown */
}

.navbar-right .notification i {
    font-size: 20px;
    cursor: pointer;
    color: #333;
    transition: color 0.2s ease;
}

.navbar-right .notification i:hover {
    color: #3b82f6; /* A subtle hover effect */
}

/* --- Notification Dropdown Styles --- */
#notificationDropdown {
    display: none;
    position: absolute;
    top: 55px;
    right: 0;
    width: 300px;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    font-size: 14px;
    padding: 10px;
    animation: fadeIn 0.3s ease-in-out;
}

#notificationDropdown strong {
    display: block;
    padding: 5px 0 10px 0; /* Adjusting padding for top and bottom */
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #eee; /* This replaces the <hr> tag */
    margin-bottom: 0; /* This is the key change to remove space */
}

#notificationDropdown ul {
    list-style: none;
    padding: 0;
    margin: 0; /* This is also a key change */
    max-height: 250px;
    overflow-y: auto;
}

#notificationDropdown li {
    padding: 1px; /* Added padding to make items more spacious */
    border-bottom: 1px solid #f0f0f0;
}

#notificationDropdown li:last-child {
    border-bottom: none;
}

#notificationDropdown li a {
    text-decoration: none;
    color: #333;
    display: block;
    transition: background-color 0.2s ease;
}

#notificationDropdown li a:hover {
    background-color: #f7f7f7;
}

#notificationDropdown .mark-all-read-btn {
    width: 40%;
    padding: 10px;
    margin-top: 10px;
    margin-left: auto;
    background-color: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

#notificationDropdown .mark-all-read-btn:hover {
    background-color: #2563eb;
}

#notificationDropdown li.no-notifications {
    color: #6c757d;
    text-align: center;
    padding: 20px;
}

        .profile-dropdown {
            position: relative;
        }
        .profile {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .profile img {
            border-radius: 50%;
            width: 35px;
            height: 35px;
            object-fit: cover;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background-color: #fff;
            color: #000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            border-radius: 6px;
            padding: 10px;
            min-width: 160px;
            z-index: 1000;
        }
        .dropdown-content ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .dropdown-content li {
            padding: 5px 0;
            font-size: 14px;
        }
        .dropdown-content a {
            display: block;
            padding: 8px 0;
            text-decoration: none;
            color: #333;
        }
        .dropdown-content a:hover {
            background-color: #f2f2f2;
        }
        .container {
            padding: 30px 40px;
            max-width: 1100px;
            margin: 0 auto;
        }
        h1, h2 {
            color: #1f2937;
            margin-bottom: 20px;
        }
        .navbar-title {
            color: white;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .filters select, .filters button {
            padding: 10px;
            font-size: 16px;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 40px;
        }
        th, td {
            padding: 14px;
            text-align: center;
            border-bottom: 1px solid #eaeaea;
        }
        th {
            background-color: #3b82f6;
            color: white;
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
        .event-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .event-form input, .event-form select, .event-form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .event-form button {
            padding: 12px 20px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .event-form button:hover {
            background-color: #2563eb;
        }
        .card {
            background: #fff;
            padding: 20px;
            margin-top: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .success-message, .error-message {
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold;
            width: 100%;
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
        }
        #editModal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px 30px; /* Reduced top/bottom padding */
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            width: 400px;
            max-width: 90%;
            font-family: 'Segoe UI', sans-serif;
        }

        #editModal h3 {
            margin-top: 0;
            font-size: 20px; /* Slightly smaller font size */
            color: #333;
            text-align: center;
            margin-bottom: 15px; /* Reduced bottom margin */
        }

        #editModal form {
            display: flex;
            flex-direction: column;
        }

        #editModal input,
        #editModal textarea,
        #editModal select {
            padding: 8px 10px; /* Slightly reduced padding */
            font-size: 14px; /* Slightly smaller font size */
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 10px; /* Reduced bottom margin */
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        #editModal input:focus,
        #editModal textarea:focus,
        #editModal select:focus {
            border-color: #007bff;
            outline: none;
        }

        #editModal textarea {
            resize: vertical;
            min-height: 60px; /* Slightly reduced minimum height */
        }

        #editModal button {
            padding: 9px; /* Slightly reduced padding */
            font-size: 14px; /* Slightly smaller font size */
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 3px; /* Reduced top margin */
            color: white;
        }

        #editModal button:hover {
            opacity: 0.95;
        }

        #editModal button:first-of-type { /* Style for the Save Changes button */
            background-color: #28a745;
        }

        #editModal button:last-of-type { /* Style for the Cancel button */
            background-color: #dc3545;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 350px;
            text-align: center;
            border-radius: 8px;
        }
        .modal-content h3 {
            margin-top: 0;
        }
        .modal .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* New styles from your provided body structure */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .dashboard-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        .dashboard-card-icon {
            font-size: 48px;
            color: #3b82f6;
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            font-size: 18px;
            color: #1f2937;
            margin: 0;
        }
        .content-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-top: 20px;
            display: none;
        }
        .edit, .delete {
            padding: 8px 14px;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .edit { background-color: #28a745; }
        .delete { background-color: #dc3545; }
        hr {
            border: 0;
            border-top: 1px solid #e0e0e0;
            margin: 40px 0;
        }
        #deleteModal .modal-content button {
            padding: 10px 20px;
            font-size: 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 5px;
            color: white;
        }
        /* Style for the notification dropdown header */
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        /* Style for the Mark All Read button inside the dropdown */
        .notification-header #mark-all-read-btn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }
        .notification-header #mark-all-read-btn:hover {
            background-color: #f0f0f0;
        }
        .notification-dropdown {
            width: 300px;
        }
        .notification-list li {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .notification-list li:last-child {
            border-bottom: none;
        }
        .notification-list li.unread {
            background-color: #e6f7ff;
            font-weight: bold;
        }
    </style>
    </head>


<body>
    <div class="sidebar">
        <h2 class="navbar-title">📘 Xcuria</h2>
        <a href="../home.php"><i class="fas fa-home"></i> Home</a>
        <a href="#"><i class="fas fa-users"></i> Students</a>
        <a href="#"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="../Auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="navbar">
            <h2>Group Dashboard</h2>
            <div class="navbar-right">
                <div class="notification">
    <i class="fas fa-bell" onclick="toggleNotifications()"></i>
    <div id="notificationDropdown" class="dropdown-content">
        <strong>Notifications</strong>
        <hr>
        <ul>
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($notification['link'] ?? '#'); ?>">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li style="text-align: center; margin-top: 10px;">
                   <button class="mark-all-read-btn" onclick="markAllNotificationsAsRead()">Mark All Read</button>
                </li>
            <?php else: ?>
                <li class="no-notifications">No new notifications</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
  <div class="profile-dropdown">
                    <div onclick="toggleProfileDropdown()" class="profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=random" alt="Profile">
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                    <div id="profileDropdown" class="dropdown-content">
                        <a href="../profile.php">My Profile</a>
                        <a href="../Auth/logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</h1>

            <div class="dashboard-grid">
                <div class="dashboard-card" data-target="interests-section">
                    <i class="fas fa-chart-pie dashboard-card-icon"></i>
                    <h3>Student Interests</h3>
                </div>
                <div class="dashboard-card" data-target="create-event-section">
                    <i class="fas fa-plus-circle dashboard-card-icon"></i>
                    <h3>Create Event</h3>
                </div>
                <div class="dashboard-card" data-target="my-events-section">
                    <i class="fas fa-calendar-alt dashboard-card-icon"></i>
                    <h3>My Events</h3>
                </div>
                <div class="dashboard-card" data-target="participants-section">
                    <i class="fas fa-users dashboard-card-icon"></i>
                    <h3>Event Participants</h3>
                </div>
                <div class="dashboard-card" data-target="feedback-section">
                    <i class="fas fa-comments dashboard-card-icon"></i>
                    <h3>Student Feedback</h3>
                </div>
            </div>

            <div id="interests-section" class="content-section">
                <h2>📊 Student Interests Overview</h2>
                <div class="filters">
                    <form method="GET" action="">
                        <label for="department">Filter by Department:</label>
                        <select name="department" id="department">
                            <option value="">All</option>
                            <option value="CSE" <?= $filterDept == "CSE" ? "selected" : "" ?>>CSE</option>
                            <option value="ECE" <?= $filterDept == "ECE" ? "selected" : "" ?>>ECE</option>
                            <option value="ME" <?= $filterDept == "ME" ? "selected" : "" ?>>ME</option>
                        </select>
                        <label for="year">Filter by Year:</label>
                        <select name="year" id="year">
                            <option value="">All</option>
                            <option value="1st" <?= $filterYear == "1st" ? "selected" : "" ?>>1st</option>
                            <option value="2nd" <?= $filterYear == "2nd" ? "selected" : "" ?>>2nd</option>
                            <option value="3rd" <?= $filterYear == "3rd" ? "selected" : "" ?>>3rd</option>
                            <option value="4th" <?= $filterYear == "4th" ? "selected" : "" ?>>4th</option>
                        </select>
                        <button type="submit">Apply Filters</button>
                    </form>
                </div>
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
            </div>

            <div id="create-event-section" class="content-section">
                <?php if (isset($_SESSION['event_success'])): ?>
                    <div class="success-message">
                        <?= $_SESSION['event_success']; ?>
                    </div>
                    <?php unset($_SESSION['event_success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['event_error'])): ?>
                    <div class="error-message">
                        <?= $_SESSION['event_error']; ?>
                    </div>
                    <?php unset($_SESSION['event_error']); ?>
                <?php endif; ?>
                <div class="event-form">
                    <h2>📅 Create Event for an Interest</h2>
                    <form id="createEventForm"> 
                        <label>Interest</label>
                        <select name="interest_id" required>
                            <?php
                            $allInterests->data_seek(0);
                            while ($int = $allInterests->fetch_assoc()) {
                                echo "<option value='{$int['id']}'>" . htmlspecialchars($int['name']) . "</option>";
                            }
                            ?>
                        </select>
                        <label>Event Title</label>
                        <input type="text" name="title" required>
                        <label>Date</label>
                        <input type="date" name="date" required>
                        <label>Time</label>
                        <input type="time" name="time" required>
                        <label>Location</label>
                        <input type="text" name="location" required>
                        <label>Description</label>
                        <textarea name="description" rows="4" required></textarea>
                        <input type="hidden" name="created_by_role" value="group">
                        <div id="createEventMessage"></div> 
                        <button type="submit">Create Event</button>
                    </form>
                </div>
            </div>

            <div id="my-events-section" class="content-section">
                <div class="card">
                    <h2>📅 Events Created</h2>
                    <p id="deleteMessage" style="color: green; font-weight: bold;"></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Interest</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="events-table-body">
                            <?php $events->data_seek(0); while ($event = $events->fetch_assoc()): ?>
                                <tr id="event-<?= $event['id'] ?>">
                                    <td><?= htmlspecialchars($event['interest_name']) ?></td>
                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                    <td><?= htmlspecialchars($event['date']) ?></td>
                                    <td><?= htmlspecialchars($event['time']) ?></td>
                                    <td><?= htmlspecialchars($event['location']) ?></td>
                                    <td><?= htmlspecialchars($event['description']) ?></td>
                                    <td>
                                        <button class="edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($event)) ?>)">✏️ Edit</button>
                                        <button class="delete" onclick="openDeleteModal(<?= htmlspecialchars($event['id']) ?>)">🗑️ Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="participants-section" class="content-section">
                <h2>👥 Participants by Event</h2>
                <div class="card">
                    <?php if (!empty($participantsByEvent)): ?>
                        <?php foreach ($participantsByEvent as $eventTitle => $participants): ?>
                            <div style="margin-bottom: 20px;">
                                <h3><?= htmlspecialchars($eventTitle) ?></h3>
                                <ul>
                                    <?php foreach ($participants as $student): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($student['student_name']) ?></strong> 
                                            (<?= htmlspecialchars($student['department']) ?>, <?= htmlspecialchars($student['year']) ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No students have participated in your events yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="feedback-section" class="content-section">
                <h2>💬 Student Feedback</h2>
                <div class="card">
                    <?php if (!empty($feedbackByEvent)): ?>
                        <?php foreach ($feedbackByEvent as $eventTitle => $feedbackList): ?>
                            <div style="margin-bottom: 20px;">
                                <h3><?= htmlspecialchars($eventTitle) ?></h3>
                                <?php foreach ($feedbackList as $feedback): ?>
                                    <p><strong><?= htmlspecialchars($feedback['student_name']) ?></strong> (Rating: <?= $feedback['rating'] ?>/5):</p>
                                    <p style="margin-top: 5px;"><?= nl2br(htmlspecialchars($feedback['comment'])) ?></p>
                                    <small><em>- <?= date('F j, Y', strtotime($feedback['created_at'])) ?></em></small>
                                    <hr>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No feedback has been submitted for your events yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>Edit Event</h3>
            <form id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <label>Interest</label>
                <select name="interest_id" id="edit_interest_id" required>
                    <?php
                    $allInterests->data_seek(0);
                    while ($int = $allInterests->fetch_assoc()) {
                        echo "<option value='{$int['id']}'>" . htmlspecialchars($int['name']) . "</option>";
                    }
                    ?>
                </select>
                <label>Event Title</label>
                <input type="text" name="title" id="edit_title" required>
                <label>Date</label>
                <input type="date" name="date" id="edit_date" required>
                <label>Time</label>
                <input type="time" name="time" id="edit_time" required>
                <label>Location</label>
                <input type="text" name="location" id="edit_location" required>
                <label>Description</label>
                <textarea name="description" id="edit_description" rows="4" required></textarea>
                <p id="editMessage" style="text-align: center; font-weight: bold;"></p>
                <button type="submit">Save Changes</button>
                <button type="button" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this event?</p>
            <input type="hidden" id="deleteEventId">
            <button style="background-color:#dc3545;" onclick="confirmDelete()">Delete</button>
            <button style="background-color:#6c757d;" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>


<script>
    // Toggle dropdowns
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            dropdown.style.display = 'block';
        }
    }

    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
            dropdown.style.display = 'block';
        }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.navbar-right')) {
            document.getElementById('notificationDropdown').style.display = 'none';
            document.getElementById('profileDropdown').style.display = 'none';
        }
    });

    // Toggle student list visibility
    function toggleStudentList(id) {
        const el = document.getElementById('students-' + id);
        el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    }

    // Hide success/error messages after a delay
    setTimeout(() => {
        const msg = document.querySelector('.success-message, .error-message');
        if (msg) msg.style.display = 'none';
    }, 4000);

    // --- NEW FUNCTION TO MARK NOTIFICATIONS AS READ ---
    function markAllNotificationsAsRead() {
        // Send a POST request to the PHP script
       fetch('../notifications/mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If successful, update the UI
                const notificationList = document.getElementById('notificationDropdown').querySelector('ul');
                notificationList.innerHTML = '<li>No new notifications</li>';
            } else {
                alert('Failed to mark notifications as read: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
// Make sure all the code runs after the page is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // --- Dashboard Card Toggles ---
        const dashboardCards = document.querySelectorAll('.dashboard-card');
        dashboardCards.forEach(card => {
            card.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetSection = document.getElementById(targetId);
                document.querySelectorAll('.content-section').forEach(section => {
                    if (section.id !== targetId) {
                        section.style.display = 'none';
                    }
                });
                if (targetSection) {
                    targetSection.style.display = targetSection.style.display === 'block' ? 'none' : 'block';
                }
            });
        });

        // AJAX for creating a new event
        const createEventForm = document.getElementById('createEventForm');
        const createEventMessage = document.getElementById('createEventMessage');
        const eventsTableBody = document.getElementById('events-table-body');

        if (createEventForm) {
            createEventForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission (page reload)

                const formData = new FormData(createEventForm);
                
                createEventMessage.textContent = "Creating event...";
                createEventMessage.style.color = "blue";

                fetch('create_event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes("<tr")) { // Check if the response is an HTML row
                        // Success! Display message, append new row, and then reload the page
                        createEventMessage.textContent = "Event created successfully!";
                        createEventMessage.style.color = "green";
                        eventsTableBody.innerHTML += data;
                        createEventForm.reset(); 
                        
                        // The fix: Set a timeout to reload the page after a short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500); // Reloads the page after 1.5 seconds
                        
                    } else {
                        // Display error message from server
                        createEventMessage.textContent = data; 
                        createEventMessage.style.color = "red";
                        
                        // Clear the error message after a few seconds
                        setTimeout(() => {
                            createEventMessage.textContent = "";
                        }, 5000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    createEventMessage.textContent = "An error occurred. Please try again.";
                    createEventMessage.style.color = "red";
                    
                    setTimeout(() => {
                        createEventMessage.textContent = "";
                    }, 5000);
                });
            });
        }

        // --- Student List Toggles ---
        window.toggleStudentList = function(id) {
            const el = document.getElementById('students-' + id);
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
        }

        // --- Edit Modal Logic ---
        window.openEditModal = function(event) {
            document.getElementById('edit_id').value = event.id;
            document.getElementById('edit_interest_id').value = event.interest_id;
            document.getElementById('edit_title').value = event.title;
            document.getElementById('edit_date').value = event.date;
            document.getElementById('edit_time').value = event.time;
            document.getElementById('edit_location').value = event.location;
            document.getElementById('edit_description').value = event.description;
            document.getElementById('editModal').style.display = 'block';
        }
        window.closeEditModal = function() {
            document.getElementById('editModal').style.display = 'none';
        }

        // The core fix: Attach listener directly inside DOMContentLoaded
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(editForm);
                fetch('edit_event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(data => {
                    const editMessage = document.getElementById('editMessage');
                    if (data.trim() === "success") {
                        editMessage.textContent = "Event updated successfully!";
                        editMessage.style.color = "green";
                        setTimeout(() => {
                            closeEditModal();
                            location.reload();
                        }, 1000);
                    } else {
                        editMessage.textContent = data;
                        editMessage.style.color = "red";
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    const editMessage = document.getElementById('editMessage');
                    editMessage.textContent = "Something went wrong!";
                    editMessage.style.color = "red";
                });
            });
        }
        
        // --- Delete Modal Logic ---
        window.openDeleteModal = function(id) {
            document.getElementById("deleteEventId").value = id;
            document.getElementById("deleteModal").style.display = "block";
        }
        window.closeDeleteModal = function() {
            document.getElementById("deleteModal").style.display = "none";
        }
        window.confirmDelete = function() {
            const id = document.getElementById("deleteEventId").value;
            fetch('delete_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
            .then(response => response.text())
            .then(data => {
                const deleteMessage = document.getElementById('deleteMessage');
                if (data.trim() === "success") {
                    document.getElementById('event-' + id).remove();
                    deleteMessage.textContent = "Event deleted successfully!";
                    deleteMessage.style.color = "green";
                } else {
                    deleteMessage.textContent = "Failed to delete event: " + data;
                    deleteMessage.style.color = "red";
                }
                closeDeleteModal();
                setTimeout(() => { deleteMessage.textContent = ""; }, 4000);
            })
            .catch(error => {
                console.error('Error:', error);
                const deleteMessage = document.getElementById('deleteMessage');
                deleteMessage.textContent = "Error deleting event.";
                deleteMessage.style.color = "red";
                closeDeleteModal();
                setTimeout(() => { deleteMessage.textContent = ""; }, 4000);
            });
        }
        
    });
</script>
</body>
</html>







