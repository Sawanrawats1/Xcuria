<?php
session_start();
require_once('../Includes/db_connect.php');

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {
    header("Location: ../Auth/login_form.html");
    exit();
}

$user_name = $_SESSION["user_name"];
$user_email = $_SESSION["user_email"];
$user_id = $_SESSION['user_id'];

// Get all available interests
$all_interests = [];
$result = $conn->query("SELECT id, name FROM interests");
while ($row = $result->fetch_assoc()) {
    $all_interests[] = $row;
}

// Get selected interests of user
$interests = [];
$interest_ids = [];
$stmt = $conn->prepare("SELECT i.id, i.name FROM interests i
                         JOIN user_interests ui ON i.id = ui.interest_id
                         WHERE ui.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $interests[] = $row;
    $interest_ids[] = $row['id'];
}
$stmt->close();

// Fetch upcoming events
// Fetch upcoming events, excluding past events
$eventsQuery = "
    SELECT
        e.id,
        e.title,
        e.date,
        e.time,
        e.location,
        e.description,
        i.name AS interest_name
    FROM events e
    LEFT JOIN interests i ON e.interest_id = i.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE u.role IN ('faculty', 'group', 'admin')
      AND CONCAT(e.date, ' ', e.time) > NOW()
    ORDER BY e.date ASC, e.time ASC
";
$eventsResult = $conn->query($eventsQuery);
// Fetch completed events with feedback status
$completedEventsQuery = "
    SELECT
        e.id,
        e.title,
        e.date,
        e.time,
        e.location,
        e.description,
        i.name AS interest_name,
        f.rating,
        f.comment,
        f.created_at AS feedback_date
    FROM events e
    JOIN participation p ON e.id = p.event_id
    LEFT JOIN interests i ON e.interest_id = i.id
    LEFT JOIN feedback f ON e.id = f.event_id AND f.user_id = ?
    WHERE p.user_id = ? AND CONCAT(e.date, ' ', e.time) < NOW()
    ORDER BY e.date DESC, e.time DESC
";
$stmt = $conn->prepare($completedEventsQuery);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$completedEventsResult = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Xcuria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* General Body and Container Styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: #ecf0f1;
            height: 100vh;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: -250px;
            transition: left 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar.open {
            left: 0;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        
        .sidebar a:hover {
            background-color: #34495e;
            transform: translateX(5px);
        }

        .sidebar a i {
            margin-right: 15px;
            font-size: 18px;
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 35px;
            left: 5px;
            font-size: 24px;
            color: #333;
            cursor: pointer;
            z-index: 1001;
            background-color: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .sidebar-toggle:hover {
            transform: scale(1.1);
        }

        .main-content {
            margin-left: 0;
            flex-grow: 1;
            padding: 20px;
            transition: margin-left 0.3s ease;
            width: 100%;
        }

        .sidebar.open + .main-content {
            margin-left: 250px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 10px;
            margin-left: 10px; /* Adjusted for sidebar */
        }

        .navbar .left h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .navbar .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .bell-container, .profile {
    position: relative;
    cursor: pointer;
}

.bell-container i, .profile img {
    font-size: 24px;
    color: #555;
    transition: color 0.3s ease;
}

.bell-container i:hover, .profile img:hover {
    color: #3498db;
}

.bell-dropdown, .profile-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background-color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-radius: 8px;
    min-width: 250px;
    padding: 10px;
    margin-top: 10px;
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s ease-in-out;
    transform: translateY(-10px);
    z-index: 999;
}
 
/* Added for a scrollable notification list */
.bell-dropdown {
    max-height: 400px;
    overflow-y: auto;
}

.bell-dropdown.show, .profile-dropdown.show {
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}

.bell-dropdown p, .profile-dropdown a {
    padding: 10px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.mark-read-btn {
    display: block;
    width: 100%;
    padding: 8px;
    margin-top: 10px;
    background-color: #f0f0f0;
    border: none;
    border-top: 1px solid #ccc;
    color: #000000ff;
    cursor: pointer;
    font-size: 14px;
    text-align: center;
    transition: background-color 0.2s;
}

.mark-read-btn:hover {
    background-color: #2d8e98ff;
    color: #ffffff; /* Added for better contrast on hover */
}
        .profile-dropdown a {
            display: block;
            color: #333;
            text-decoration: none;
        }
        
        .profile-dropdown a:hover {
            background-color: #f0f2f5;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome {
            background-color: #e3f2fd;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 5px solid #2196f3;
            animation: fadeIn 0.8s ease-in-out;
        }

        .welcome h1 {
            color: #1e88e5;
            margin-top: 0;
            font-size: 36px;
        }

        .collapsible-section {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .collapsible-header {
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-size: 20px;
            font-weight: 500;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        .collapsible-header:hover {
            background-color: #f9f9f9;
        }
        
        .collapsible-header i {
            margin-right: 15px;
            color: #555;
        }

        .arrow {
            font-size: 14px;
            transition: transform 0.3s ease;
        }
        
        .collapsible-header.active .arrow {
            transform: rotate(90deg);
        }
        
        .collapsible-content {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.4s ease-out;
            background-color: #f9f9f9;
        }
        
        .collapsible-content.show {
            padding: 25px;
            max-height: 1000px; /* Adjust as needed */
        }

        /* Interest Section Styling */
        .interest-selection-section {
            margin-top: 20px;
        }

        .interests {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .interest-btn {
            background-color: #e0e0e0;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 500;
        }
        
        .interest-btn:hover {
            background-color: #d0d0d0;
            transform: translateY(-2px);
        }
        
        .interest-btn.selected {
            background-color: #28a745;
            color: #fff;
            cursor: not-allowed;
        }

        .your-interests {
            margin-top: 20px;
        }

        .interest-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .interest-badge {
            background-color: #17a2b8;
            color: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .interest-badge .remove-btn {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s ease;
        }
        
        .interest-badge .remove-btn:hover {
            transform: scale(1.2);
        }

        /* Event Table Styling */
        .event-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
            overflow-x: auto;
            display: block; /* Makes it a block to enable horizontal scrolling */
        }
        
        .event-table thead {
            background-color: #3498db;
            color: #fff;
        }
        
        .event-table th, .event-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .event-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .event-table th {
            font-weight: 600;
        }
/* General style for the button */
.btn-participate {
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 20px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: inline-flex; /* Use flex to align icon and text */
    align-items: center;
    gap: 8px; /* Space between icon and text */
}

/* Style for the default 'Participate' button (Green) */
.btn-participate {
    background-color: #28a745;
}
.btn-participate:hover {
    background-color: #218838;
}

/* Style for the 'Cancel Participation' button (Red) */
.btn-participate.participated {
    background-color: #dc3545;
}
.btn-participate.participated:hover {
    background-color: #c82333;
}
        /* Feedback Section Styling */
        .feedback-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
        }
        
        .feedback-card h4 {
            margin-top: 0;
            color: #34495e;
        }
        
        .feedback-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        /* Add this to your existing CSS */
.rating-container, .comments-container {
    margin-bottom: 15px; /* Adds space below the rating and comments sections */
}

.comments-container label {
    display: block; /* Forces the label to its own line */
    margin-bottom: 5px; /* Adds a small gap between the label and the textarea */
}

        .feedback-form button, .delete-feedback-btn {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .feedback-form button:hover, .delete-feedback-btn:hover {
            background-color: #0056b3;
        }

        .delete-feedback-btn {
            background-color: #dc3545;
        }
        
        .delete-feedback-btn:hover {
            background-color: #c82333;
        }

        .star-rating {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .star-rating i {
            color: #ccc;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star-rating i.hover {
            color: #ffc107;
        }
        
        .star-rating i.selected {
            color: #ffc107;
        }
        
        .existing-feedback {
            margin-top: 15px;
        }
        
        .existing-feedback p {
            margin: 5px 0;
        }
        
        .existing-feedback .fa-star {
            color: gold;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 12px;
            border-left: 5px solid #dc3545;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation: scaleIn 0.3s ease;
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-btn:hover, .close-btn:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons .confirm-btn, .modal-buttons .cancel-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .modal-buttons .confirm-btn {
            background-color: #dc3545;
            color: white;
        }

        .modal-buttons .cancel-btn {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>

<i class="fas fa-bars sidebar-toggle"></i>

<div class="sidebar">
    <h2>📘 Xcuria</h2>
    <a href="../home.php"><i class="fas fa-home"></i> Home</a>
    <a href="#"><i class="fas fa-bolt"></i> Quick Links</a>
    <a href="#"><i class="fas fa-bell"></i> Notifications</a>
    <a href="../Auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="navbar">
        <div class="left">
            <h2 style="margin-left: 20px;">Student Dashboard</h2>
        </div>
        <div class="right">
            <div class="bell-container">
                <i class="fas fa-bell" title="Notifications"></i>
                <div class="bell-dropdown">
                    <p>No new notifications</p>
                </div>
            </div>
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=random" alt="Profile">
                <div>
                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user_name); ?></div>
                    <div style="font-size: 12px;">Student</div>
                </div>
                <div id="profile-dropdown" class="profile-dropdown">
                    <a href="../profile.php">My Profile</a>
                    <a href="../Auth/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
            <p>Email: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
        </div>

        <div class="collapsible-section">
            <div class="collapsible-header">
                <div><i class="fas fa-star"></i> Your Interests</div>
                <span class="arrow">▶</span>
            </div>
            <div class="collapsible-content">
                <p>Choose what excites you. Selected ones are highlighted.</p>
                <div class="interest-selection-section">
                    <div class="interests">
                        <?php foreach ($all_interests as $interest):
                            $selected = in_array($interest['id'], $interest_ids);
                        ?>
                            <button class="interest-btn <?= $selected ? 'selected' : '' ?> animate-in"
                                    data-id="<?= $interest['id'] ?>"
                                    <?= $selected ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($interest['name']) ?>
                                <?= $selected ? '✔️' : '' ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="your-interests" style="margin-top: 30px;">
                        <?php if (!empty($interests)): ?>
                            <h4>Your Interests:</h4>
                            <div class="interest-badges">
                                <?php foreach ($interests as $i): ?>
                                    <div class="interest-badge animate-in">
                                        <span><?= htmlspecialchars($i['name']) ?></span>
                                        <button class="remove-btn" data-id="<?= $i['id'] ?>">❌</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-interests-msg">You haven't selected any interests yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapsible-section">
            <div class="collapsible-header">
                <div><i class="fas fa-calendar-alt"></i> Upcoming Events</div>
                <span class="arrow">▶</span>
            </div>
            <div class="collapsible-content">
                <!-- Add a wrapper for horizontal scrolling on mobile -->
                <div style="overflow-x: auto;">
                    <table class="event-table">
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
                        <tbody>
                        <?php if ($eventsResult && $eventsResult->num_rows > 0): ?>
                            <?php while ($ev = $eventsResult->fetch_assoc()): ?>
                                <?php
                                $check = $conn->prepare("SELECT 1 FROM participation WHERE event_id = ? AND user_id = ?");
                                $check->bind_param("ii", $ev['id'], $_SESSION['user_id']);
                                $check->execute();
                                $already = $check->get_result()->num_rows > 0;
                                $check->close();
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($ev['interest_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($ev['title']) ?></td>
                                    <td><?= htmlspecialchars($ev['date']) ?></td>
                                    <td><?= htmlspecialchars($ev['time'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($ev['location']) ?></td>
                                    <td><?= htmlspecialchars($ev['description']) ?></td>
                                    <td>
    <?php
    $check = $conn->prepare("SELECT 1 FROM participation WHERE event_id = ? AND user_id = ?");
    $check->bind_param("ii", $ev['id'], $_SESSION['user_id']);
    $check->execute();
    $already = $check->get_result()->num_rows > 0;
    $check->close();
    ?>
    
    <?php if ($already): ?>
        <button
            class="btn-participate participated"
            data-event-id="<?= $ev['id'] ?>">
            <i class="fas fa-check"></i> <span>Cancel Participation</span>
        </button>
    <?php else: ?>
        <button
            class="btn-participate"
            data-event-id="<?= $ev['id'] ?>">
            <i class="fas fa-plus"></i> <span>Participate</span>
        </button>
    <?php endif; ?>
</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No upcoming events found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
<div class="collapsible-section">
    <div class="collapsible-header">
        <div><i class="fas fa-comments"></i> Share Your Feedback</div>
        <span class="arrow">▶</span>
    </div>
    <div class="collapsible-content">
        <?php if ($completedEventsResult && $completedEventsResult->num_rows > 0): ?>
            <?php while ($ev = $completedEventsResult->fetch_assoc()): ?>
                <div class="feedback-card">
                    <h4>Feedback for: **<?= htmlspecialchars($ev['title']) ?>**</h4>
                    <p>on <?= htmlspecialchars($ev['date']) ?> at <?= htmlspecialchars($ev['location']) ?></p>
                    <?php if ($ev['rating']): ?>
                        <div class="existing-feedback">
                            <h4>Your Submitted Feedback</h4>
                            <p><strong>Rating:</strong>
                                <?php
                                $rating = htmlspecialchars($ev['rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </p>
                            <p><strong>Comments:</strong> <?= htmlspecialchars($ev['comment']) ?></p>
                            <p><strong>Submitted on:</strong> <?= htmlspecialchars($ev['feedback_date']) ?></p>
                            <button class="delete-feedback-btn" data-event-id="<?= $ev['id'] ?>">Delete Feedback</button>
                        </div>
                    <?php else: ?>
                        <div class="feedback-form">
                            <form id="feedback-form-<?= $ev['id'] ?>" class="feedback-form-actual">
                                <input type="hidden" name="event_id" value="<?= htmlspecialchars($ev['id']) ?>">
                                <div class="rating-container">
                                    <label>Rating:</label>
                                    <div class="star-rating" id="rating-stars-<?= $ev['id'] ?>">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="far fa-star" data-rating="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="rating" id="rating-input-<?= $ev['id'] ?>" required>
                                <div class="comments-container">
                                    <label for="comment-<?= $ev['id'] ?>">Comments:</label>
                                    <textarea name="comment" id="comment-<?= $ev['id'] ?>" rows="4" placeholder="Write your feedback here..." required></textarea>
                                </div>
                                <button type="submit">Submit Feedback</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No completed events to provide feedback for.</p>
        <?php endif; ?>
    </div>
</div>


        <div class="collapsible-section">
            <div class="collapsible-header">
                <div><i class="fas fa-users-viewfinder"></i> What Other Students Said</div>
                <span class="arrow">▶</span>
            </div>
            <div class="collapsible-content">
                <p>See what others thought about events you participated in.</p>
                <div id="other-students-feedback">
                    <p>Loading feedback...</p>
                </div>
            </div>
        </div>

    </div>
</div>


<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h4 style="color: #333;">Confirm Deletion</h4>
        <p>Are you sure you want to delete your feedback? This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Global variable to store the eventId to be deleted.
    let eventIdToDelete = null;

    // This function shows a toast notification message.
    function showToast(message) {
        const toast = document.createElement("div");
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            opacity: 0.95;
            z-index: 9999;
            transition: all 0.3s ease-in-out;
        `;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // This function performs the actual AJAX call to delete feedback.
    function performDeletion(eventId) {
        fetch('../feedback/delete_feedback.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'event_id=' + encodeURIComponent(eventId)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Feedback deleted successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Could not delete feedback.');
            }
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            showToast('Could not delete feedback.');
        });
    }

    // Helper function to generate star ratings HTML
    function generateStarRating(rating) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<i class="${i <= rating ? 'fas' : 'far'} fa-star" style="color: gold;"></i>`;
        }
        return stars;
    }

    // Event listener for collapsible sections
    document.querySelectorAll('.collapsible-header').forEach(header => {
        header.addEventListener('click', () => {
            const content = header.nextElementSibling;
            const arrow = header.querySelector('.arrow');
            
            header.classList.toggle('active');
            arrow.classList.toggle('active');

            if (content.style.maxHeight) {
                content.style.maxHeight = null;
                content.style.padding = '0 25px';
            } else {
                content.style.padding = '25px';
                content.style.maxHeight = content.scrollHeight + 'px';
            }
        });
    });

    // -------- Sidebar, Bell, and Profile Dropdown Functionality --------
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const bellContainer = document.querySelector('.bell-container');
    const bellDropdown = document.querySelector('.bell-dropdown');
    const profileDiv = document.querySelector('.profile');
    const profileDropdown = document.getElementById('profile-dropdown');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    // Corrected logic for bell and profile dropdowns
    bellContainer.addEventListener('click', (e) => {
        e.stopPropagation();
        bellDropdown.classList.toggle('show');
        profileDropdown.classList.remove('show');
        // Fetch notifications when the bell dropdown is about to be shown
        if(bellDropdown.classList.contains('show')) {
            fetchAndRenderNotifications();
        }
    });

    profileDiv.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
        bellDropdown.classList.remove('show');
    });

    document.addEventListener('click', (e) => {
        if (!bellContainer.contains(e.target)) {
            bellDropdown.classList.remove('show');
        }
        if (!profileDiv.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });

    // -------- Interest Management Functionality --------
    document.querySelectorAll(".interest-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-id");
            fetch("save_interest.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "interest_id=" + encodeURIComponent(id)
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message);
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => showToast("Error: " + error.message));
        });
    });

    document.querySelectorAll('.remove-btn').forEach(button => {
        button.addEventListener('click', function () {
            const interestId = this.dataset.id;
            const badge = this.closest('.interest-badge');
            fetch('remove_interest.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'interest_id=' + encodeURIComponent(interestId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    badge.style.transition = "opacity 0.4s ease, transform 0.4s ease";
                    badge.style.opacity = "0";
                    badge.style.transform = "translateY(10px)";
                    setTimeout(() => location.reload(), 400); // Reload after removal
                } else {
                    showToast("Error: " + data.message);
                }
            })
            .catch(error => showToast("Fetch failed: " + error.message));
        });
    });

    // -------- Event Participation Functionality --------
    document.querySelectorAll('.btn-participate').forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.dataset.eventId;
            const isParticipated = this.classList.contains('participated');

            const url = 'participate.php';
            const action = isParticipated ? 'cancel' : 'add';
            const buttonText = this.querySelector('span');
            const buttonIcon = this.querySelector('i');

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `event_id=${eventId}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (isParticipated) {
                        button.classList.remove('participated');
                        buttonText.textContent = 'Participate';
                        buttonIcon.className = 'fas fa-plus';
                        showToast('Participation cancelled.');
                    } else {
                        button.classList.add('participated');
                        buttonText.textContent = 'Cancel Participation';
                        buttonIcon.className = 'fas fa-check';
                        showToast('Successfully joined event!');
                    }
                } else {
                    showToast(data.message || 'An error occurred.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error, please try again.');
            });
        });
    });

    // -------- Feedback Submission Functionality --------
    document.querySelectorAll('.feedback-form-actual').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../feedback/submit_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Feedback submitted successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message);
                }
            })
            .catch(error => {
                console.error('Error submitting feedback:', error);
                showToast('Failed to submit feedback.');
            });
        });
    });

    // -------- Delete Feedback Modal and Functionality --------
    const modal = document.getElementById("deleteModal");
    const closeBtn = document.querySelector(".close-btn");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const cancelBtn = document.getElementById("cancelDeleteBtn");
    const feedbackContainers = document.querySelectorAll('.delete-feedback-btn');

    feedbackContainers.forEach(btn => {
        btn.addEventListener('click', (e) => {
            eventIdToDelete = e.target.dataset.eventId;
            modal.style.display = "block";
        });
    });
    
    closeBtn.onclick = () => { modal.style.display = "none"; };
    cancelBtn.onclick = () => { modal.style.display = "none"; };
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };
    
    confirmBtn.onclick = () => {
        if (eventIdToDelete) {
            performDeletion(eventIdToDelete);
            eventIdToDelete = null; // Reset the global variable
        }
        modal.style.display = "none";
    };

    // -------- Star Rating Script --------
    document.querySelectorAll('.star-rating').forEach(starContainer => {
        const stars = starContainer.querySelectorAll('i');
        const inputId = 'rating-input-' + starContainer.id.split('-').pop();
        const hiddenInput = document.getElementById(inputId);

        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                resetStars(stars);
                highlightStars(star, stars);
            });
            star.addEventListener('click', () => {
                const ratingValue = star.getAttribute('data-rating');
                hiddenInput.value = ratingValue;
                selectStars(star, stars);
            });
        });

        starContainer.addEventListener('mouseout', () => {
            resetStars(stars);
            if (hiddenInput.value) {
                selectStarsByValue(hiddenInput.value, stars);
            }
        });

        function resetStars(stars) {
            stars.forEach(s => s.classList.remove('fas', 'selected', 'hover'));
            stars.forEach(s => s.classList.add('far'));
        }

        function highlightStars(star, stars) {
            const rating = star.getAttribute('data-rating');
            stars.forEach(s => {
                if (s.getAttribute('data-rating') <= rating) {
                    s.classList.add('hover');
                }
            });
        }

        function selectStars(selectedStar, stars) {
            const rating = selectedStar.getAttribute('data-rating');
            stars.forEach(s => {
                if (s.getAttribute('data-rating') <= rating) {
                    s.classList.remove('far', 'hover');
                    s.classList.add('fas', 'selected');
                } else {
                    s.classList.remove('fas', 'selected');
                    s.classList.add('far');
                }
            });
        }
        
        function selectStarsByValue(ratingValue, stars) {
            stars.forEach(star => {
                if (star.getAttribute('data-rating') <= ratingValue) {
                    star.classList.remove('far');
                    star.classList.add('fas', 'selected');
                }
            });
        }
    });

   // -------- Mark All Notifications as Read --------
function markNotificationsAsRead() {
    fetch('/xcuria/notifications/mark_notifications_read.php', {
        method: 'POST',
        headers: {
            // Note: Content-Type is not strictly needed since you're not sending a body,
            // but it's harmless to leave it in.
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Corrected condition: Check for the 'success' key returned by PHP
        if (data.success) { 
            const bellDropdown = document.querySelector('.bell-dropdown');
            bellDropdown.innerHTML = '<p class="p-2 text-gray-500">No new notifications</p>';
        } else {
            // Corrected error key to 'error' to match the PHP response
            console.error('Error from server:', data.error); 
            alert('Failed to mark notifications as read.');
        }
    })
    .catch(error => {
        console.error('Error marking notifications as read:', error);
        alert('Failed to mark notifications as read.');
    });
}

    // -------- Notification Fetching and Rendering --------
    function fetchAndRenderNotifications() {
        fetch('/xcuria/notifications/fetch_notifications.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const bellDropdown = document.querySelector('.bell-dropdown');
                bellDropdown.innerHTML = '';
                if (data.status === 'success') {
                    if (data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationItem = document.createElement('p');
                            notificationItem.textContent = notification.message;
                            notificationItem.classList.add('notification-item');
                            if (notification.is_read == 0) {
                                notificationItem.classList.add('unread');
                            }
                            bellDropdown.appendChild(notificationItem);
                        });
                        
                        // Add the "Mark All Read" button only if there are notifications
                        const markAllReadBtn = document.createElement('button');
                        markAllReadBtn.textContent = 'Mark All Read';
                        markAllReadBtn.classList.add('mark-read-btn');
                        markAllReadBtn.addEventListener('click', markNotificationsAsRead);
                        bellDropdown.appendChild(markAllReadBtn);
                        
                    } else {
                        bellDropdown.innerHTML = '<p class="p-2 text-gray-500">No new notifications</p>';
                    }
                } else {
                    console.error('Error from server:', data.message);
                    bellDropdown.innerHTML = `<p class="p-2 text-red-500">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                const bellDropdown = document.querySelector('.bell-dropdown');
                bellDropdown.innerHTML = '<p class="p-2 text-red-500">Failed to load notifications.</p>';
            });
    }
// -------- Public Feedback Fetcher --------
    function fetchOtherStudentsFeedback() {
        fetch('../feedback/fetch_public_feedback.php')
            .then(response => response.json())
            .then(data => {
                const feedbackContainer = document.getElementById('other-students-feedback');
                feedbackContainer.innerHTML = ''; // Clear the "Loading..." message
                if (data.status === 'success' && data.feedback.length > 0) {
                    data.feedback.forEach(item => {
                        const feedbackHtml = `
                            <div class="existing-feedback" style="margin-bottom: 15px;">
                                <p><strong>Event:</strong> ${item.title}</p>
                                <p><strong>Student:</strong> ${item.student_name}</p>
                                <p>
                                    <strong>Rating:</strong>
                                    ${generateStarRating(item.rating)}
                                </p>
                                <p><strong>Comment:</strong> ${item.comment}</p>
                            </div>
                        `;
                        feedbackContainer.innerHTML += feedbackHtml;
                    });
                } else {
                    feedbackContainer.innerHTML = '<p>No public feedback for your events yet.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching public feedback:', error);
                document.getElementById('other-students-feedback').innerHTML = '<p>Failed to load feedback.</p>';
            });
    }
    
    fetchOtherStudentsFeedback();
});
</script>
</body>
</html>