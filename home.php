<?php
session_start();
// Check if the user is logged in
$is_logged_in = isset($_SESSION["user_id"]);

// Get user info only if they are logged in
$user_name = $is_logged_in ? htmlspecialchars($_SESSION["user_name"]) : '';
$user_role = $is_logged_in ? htmlspecialchars($_SESSION["user_role"]) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Xcuria | Home</title>
    <style>
        :root {
            --primary-color: #4a4aff;
            --secondary-color: #f0f0f5;
            --text-dark: #333;
            --text-light: #fff;
            --card-bg: #fff;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            background-color: var(--secondary-color);
            color: var(--text-dark);
            line-height: 1.6;
        }
        .header {
            background: var(--primary-color);
            color: var(--text-light);
            padding: 1.5rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        .header .logout {
            background: #f44336;
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: background 0.3s;
        }
        .header .logout:hover {
            background: #d32f2f;
        }
        .role-tag {
            background: #222;
            color: var(--text-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1541339907198-e087561db154') no-repeat center center/cover;
            color: var(--text-light);
            padding: 6rem 5%;
            text-align: center;
        }
        .hero-section h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .hero-section p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 2rem;
        }
        .cta-button {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .content-section {
            padding: 4rem 5%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .content-section h2 {
            font-size: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }
        .content-section p {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card h3 {
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        .card p {
            font-size: 0.95rem;
            color: #555;
        }
        
        .contact-section {
            text-align: center;
        }
        .contact-info a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }
        .contact-info a:hover {
            text-decoration: underline;
        }
        .role-based-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto 40px;
            justify-content: center;
        }
        .role-based-links a {
            background: var(--primary-color);
            color: var(--text-light);
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            transition: transform .2s, box-shadow .2s;
        }
        .role-based-links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

    <header class="header">
        <?php if ($is_logged_in): ?>
            <h1>Welcome, <?= $user_name ?> <span class="role-tag"><?= strtoupper($user_role) ?></span></h1>
            <a href="Auth/logout.php" class="logout">Logout</a>
        <?php else: ?>
            <h1>Xcuria</h1>
            <a href="Auth/register_form.php" class="logout" style="background: #4CAF50;">Register</a>
        <?php endif; ?>
    </header>

    <main>
        <section class="hero-section" style="padding: 4rem 5%;">
            <?php if ($is_logged_in): ?>
                <h1 style="font-size: 3rem; margin: 0;">Xcuria</h1>
                <h2 style="font-size: 2rem; margin: 0.5rem 0;">Your Campus Dashboard</h2>
                <p style="margin-top: 1rem;">Click on one of the dashboards below to get started.</p>
            <?php else: ?>
                <h2>Discover and Participate in Extracurriculars</h2>
                <p>Find, join, and track your favorite campus activities. Connect with clubs and faculty to showcase your skills and express interest in upcoming events.</p>
                <a href="#about" class="cta-button">Learn More About Us</a>
            <?php endif; ?>
        </section>

        <section id="about" class="content-section">
            <h2>About Xcuria</h2>
            <p>
                Xcuria is your central hub for extracurricular life. We connect students with the activities they love and provide faculty and club leaders with the tools to manage their events and find the right participants. Our goal is to make campus engagement seamless for everyone.
            </p>
            <div class="cards-container">
                <div class="card">
                    <h3>Our Mission</h3>
                    <p>To empower educational institutions with a comprehensive digital platform that streamlines operations and enhances communication for all stakeholders.</p>
                </div>
                <div class="card">
                    <h3>Our Vision</h3>
                    <p>To be the leading campus management solution, transforming traditional campuses into dynamic, efficient, and interconnected communities.</p>
                </div>
                <div class="card">
                    <h3>Our Values</h3>
                    <p>Innovation, Integrity, Collaboration, and Community are at the core of everything we do. We are committed to building a platform that is secure, reliable, and user-friendly.</p>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2>Key Features</h2>
            <div class="cards-container">
                <div class="card">
                    <h3>Student Hub 🎓</h3>
                    <p>View upcoming events, express interest, and build a profile showcasing your skills and passions. Stay on top of your favorite activities and get noticed by clubs and faculty.</p>
                </div>
                <div class="card">
                    <h3>Faculty & Club Tools 👩‍🏫</h3>
                    <p>Upload new events and manage participant sign-ups. Easily filter and search for students based on their expressed interests and skills to find the perfect candidates for your activities.</p>
                </div>
                <div class="card">
                    <h3>Event Management 👥</h3>
                    <p>Create and manage dedicated spaces for your groups or clubs. Share event details, coordinate with members, and track participation with ease.</p>
                </div>
            </div>
        </section>

        <section class="content-section contact-section">
            <h2>Contact Us</h2>
            <p>Have questions or need support? We're here to help! Reach out to us for any inquiries.</p>
            <div class="contact-info">
                <p>Email: <a href="mailto:support@xcuria.com">support@xcuria.com</a></p>
                <p>Phone: +1 (123) 456-7890</p>
            </div>
        </section>

        <?php if ($is_logged_in): ?>
            <div class="role-based-links">
                <a href="dashboard/student.php">🎓 Student Dashboard</a>
                <a href="dashboard/faculty.php">👩‍🏫 Faculty Dashboard</a>
                <a href="dashboard/group.php">👥 Group Dashboard</a>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
