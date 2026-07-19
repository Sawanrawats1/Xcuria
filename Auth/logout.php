<?php
session_start();
session_unset();
session_destroy();

// If logout.php is inside Auth/, and login_form.html is also inside Auth/
header("Location: login_form.html");
exit();
