<?php
session_start();
require_once('../Includes/db_connect.php'); // adjust path if needed

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../Auth/login_form.html");
    exit();
}

$success = '';
$error = '';

// Handle Add or Edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? null;
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $date        = $_POST['date'] ?? '';
    $time        = $_POST['time'] ?? '00:00:00';
    $location    = trim($_POST['location']);
    $interest_id = $_POST['interest_id'] ?? null;

    if (empty($title) || empty($date) || empty($time) || empty($interest_id)) {
        $error = "Please fill in all required fields (title, date, time, interest).";
    } else {
        if ($id) {
            // Update event
            $stmt = $conn->prepare("UPDATE events SET title=?, description=?, date=?, time=?, location=?, interest_id=? WHERE id=?");
            $stmt->bind_param("sssssii", $title, $description, $date, $time, $location, $interest_id, $id);
            if ($stmt->execute()) {
                $success = "Event updated successfully.";
            } else {
                $error = "Error updating event: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Insert new event
            $stmt = $conn->prepare("INSERT INTO events (title, description, date, time, location, interest_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $title, $description, $date, $time, $location, $interest_id);
            if ($stmt->execute()) {
                $success = "New event added successfully.";
            } else {
                $error = "Error adding event: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    if ($stmt->execute()) {
        $success = "Event deleted successfully.";
    } else {
        $error = "Error deleting event: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch event for editing (AJAX or GET edit id)
$editEvent = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $editEvent = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch interests list for dropdown
$interestsResult = $conn->query("SELECT id, name FROM interests ORDER BY name ASC");

// Fetch all events joined with interest name
$eventsResult = $conn->query("
    SELECT events.*, interests.name AS interest_name 
    FROM events 
    LEFT JOIN interests ON events.interest_id = interests.id
    ORDER BY events.date DESC, events.time DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Manage Events - Admin</title>
<style>
    body { font-family: Arial, sans-serif; background:#f9f9f9; margin:0; padding:20px; }
    h1 { text-align:center; margin-bottom: 30px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
    th { background: #4a4aff; color: white; }
    a.button, button.button {
        background: #4a4aff;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        margin-right: 5px;
    }
    a.button:hover, button.button:hover { background: #3333cc; }
    form {
        max-width: 600px;
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
    input[type="text"], input[type="date"], input[type="time"], select, textarea {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }
    textarea {
        resize: vertical;
        min-height: 80px;
    }
    input[type="submit"], button.cancel-btn {
        background: #4a4aff;
        color: white;
        border: none;
        padding: 10px 18px;
        cursor: pointer;
        border-radius: 4px;
        font-size: 16px;
    }
    input[type="submit"]:hover, button.cancel-btn:hover {
        background: #3333cc;
    }
    button.cancel-btn {
        background: #6c757d;
        margin-left: 10px;
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
    #deleteModal, #editModal {
        display: none;
        position: fixed;
        top:0; left:0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    #deleteModal .modal-content, #editModal .modal-content {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 500px;
        margin: auto;
        box-shadow: 0 0 10px rgba(0,0,0,0.25);
    }
    #deleteModal p, #editModal h3 {
        margin-bottom: 20px;
        text-align: center;
    }
    #deleteModal button, #editModal button {
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
    #cancelDelete, #editCancelBtn {
        background: #6c757d;
    }
    #editForm div {
        margin-bottom: 12px;
    }
</style>
</head>
<body>

<h1>Manage Events</h1>

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

<!-- Add Event Form -->
<form method="post" action="">
    <input type="hidden" name="id" value="" id="form_id" />
    <div>
        <label for="interest_id">Interest *</label>
        <select name="interest_id" id="form_interest_id" required>
            <option value="">-- Select Interest --</option>
            <?php
            if ($interestsResult && $interestsResult->num_rows > 0) {
                $interestsResult->data_seek(0); // reset pointer
                while ($interest = $interestsResult->fetch_assoc()) {
                    echo '<option value="' . $interest['id'] . '">' . htmlspecialchars($interest['name']) . '</option>';
                }
            }
            ?>
        </select>
    </div>
    <div>
        <label for="title">Title *</label>
        <input type="text" name="title" id="form_title" required />
    </div>
    <div>
        <label for="date">Date *</label>
        <input type="date" name="date" id="form_date" required />
    </div>
    <div>
        <label for="time">Time *</label>
        <input type="time" name="time" id="form_time" required />
    </div>
    <div>
        <label for="location">Location</label>
        <input type="text" name="location" id="form_location" />
    </div>
     <div>
        <label for="description">Description</label>
        <textarea name="description" id="form_description"></textarea>
    </div>
    
    <div>
        <input type="submit" value="Add Event" />
    </div>
</form>

<!-- Events List -->
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
    <tbody>
        <?php if ($eventsResult && $eventsResult->num_rows > 0): ?>
            <?php while ($ev = $eventsResult->fetch_assoc()): ?>
                <tr id="event-<?= $ev['id'] ?>">
    <td data-interest-id="<?= $ev['interest_id'] ?>"><?= htmlspecialchars($ev['interest_name']) ?></td>
    <td><?= htmlspecialchars($ev['title']) ?></td>
    <td><?= htmlspecialchars($ev['date']) ?></td>
    <td><?= htmlspecialchars($ev['time']) ?></td>
    <td><?= htmlspecialchars($ev['location']) ?></td>
    <td><?= nl2br(htmlspecialchars($ev['description'])) ?></td>
    <td>
        <button class="button" onclick="openEditModal(<?= $ev['id'] ?>)">✏️ Edit</button>
        <button class="button" onclick="openDeleteModal(<?= $ev['id'] ?>)">🗑 Delete</button>
    </td>
</tr>

            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No events found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Edit Event Modal -->
<div id="editModal">
  <div class="modal-content">
    <h3>Edit Event</h3>
    <form id="editForm" method="POST" action="">
      <input type="hidden" name="id" id="edit_id" />
      <div>
          <label for="edit_title">Title *</label>
          <input type="text" name="title" id="edit_title" required />
      </div>
      <div>
          <label for="edit_description">Description</label>
          <textarea name="description" id="edit_description"></textarea>
      </div>
      <div>
          <label for="edit_date">Date *</label>
          <input type="date" name="date" id="edit_date" required />
      </div>
      <div>
          <label for="edit_time">Time *</label>
          <input type="time" name="time" id="edit_time" required />
      </div>
      <div>
          <label for="edit_location">Location</label>
          <input type="text" name="location" id="edit_location" />
      </div>
      <div>
          <label for="edit_interest_id">Interest *</label>
          <select name="interest_id" id="edit_interest_id" required>
            <option value="">-- Select Interest --</option>
            <?php
            if ($interestsResult && $interestsResult->num_rows > 0) {
                $interestsResult->data_seek(0);
                while ($interest = $interestsResult->fetch_assoc()) {
                    echo '<option value="' . $interest['id'] . '">' . htmlspecialchars($interest['name']) . '</option>';
                }
            }
            ?>
          </select>
      </div>
      <div style="text-align: right;">
        <button type="submit" style="
  background-color: #28a745; 
  color: white; 
  border: none; 
  padding: 10px 20px; 
  border-radius: 5px; 
  font-weight: bold; 
  cursor: pointer;
  transition: background-color 0.3s ease;
"
>Save Changes</button>

        <button type="button" id="editCancelBtn">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal">
  <div class="modal-content">
    <p>Are you sure you want to delete this event?</p>
    <button id="confirmDelete">Yes, Delete</button>
    <button id="cancelDelete">Cancel</button>
  </div>
</div>
<script>
  // Auto fade messages
  window.onload = function() {
    const msg = document.getElementById('msg');
    if (msg) {
      setTimeout(() => {
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 500);
      }, 4000);
    }
  };

  // Modal elements
  const editModal = document.getElementById('editModal');
  const editForm = document.getElementById('editForm');
  const editCancelBtn = document.getElementById('editCancelBtn');

  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDelete');
  const cancelDeleteBtn = document.getElementById('cancelDelete');

  let deleteEventId = null;

  // Open Edit Modal and populate fields
  function openEditModal(eventId) {
    const row = document.getElementById('event-' + eventId);
    if (!row) return;

    const cells = row.getElementsByTagName('td');
    document.getElementById('edit_id').value = eventId;
    document.getElementById('edit_interest_id').value = cells[0].getAttribute('data-interest-id') || '';
    document.getElementById('edit_title').value = cells[1].innerText;
    document.getElementById('edit_date').value = cells[2].innerText;
    document.getElementById('edit_time').value = cells[3].innerText;
    document.getElementById('edit_location').value = cells[4].innerText;
    document.getElementById('edit_description').value = cells[5].innerText;

    editModal.style.display = 'flex';
  }

  // Close edit modal
  editCancelBtn.addEventListener('click', () => {
    editModal.style.display = 'none';
    editForm.reset();
  });

  // Open Delete Modal
  function openDeleteModal(eventId) {
    deleteEventId = eventId;
    deleteModal.style.display = 'flex';
  }

  // Confirm Delete
  confirmDeleteBtn.addEventListener('click', () => {
    if (deleteEventId !== null) {
      window.location.href = '?delete=' + deleteEventId;
    }
  });

  cancelDeleteBtn.addEventListener('click', () => {
    deleteModal.style.display = 'none';
    deleteEventId = null;
  });

  // Close modals if clicking outside modal content
  window.addEventListener('click', (e) => {
    if (e.target === editModal) {
      editModal.style.display = 'none';
      editForm.reset();
    } else if (e.target === deleteModal) {
      deleteModal.style.display = 'none';
      deleteEventId = null;
    }
  });
</script>


</body>
</html>
