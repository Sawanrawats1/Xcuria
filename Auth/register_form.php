<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Xcuria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #495057;
        }

        .container {
            background-color: #ffffff;
            padding: 40px 50px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h2 {
            color: #4a4aff;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-sizing: border-box;
            transition: all 0.3s ease-in-out;
            font-size: 16px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #4a4aff;
            box-shadow: 0 0 0 3px rgba(74, 74, 255, 0.25);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
        }

        .password-wrapper .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .student-fields {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
        }

        button {
            background-color: #4a4aff;
            color: #ffffff;
            padding: 14px;
            width: 100%;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        button:hover {
            background-color: #3b3bff;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }

        .login-link a {
            color: #4a4aff;
            text-decoration: none;
            font-weight: bold;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
  <h2>Register</h2>

  <form id="registerForm" method="POST">
    <div class="form-group">
      <label>Name:</label>
      <input type="text" name="name" required>
    </div>

    <div class="form-group">
      <label>Email:</label>
      <input type="email" name="email" required>
      <div class="error" id="emailError"></div>
    </div>

    <div class="form-group">
      <label>Password:</label>
      <div class="password-wrapper">
        <input type="password" name="password" id="password" required>
        <i class="fa-solid fa-eye toggle-password" toggle="#password"></i>
      </div>
      <div class="error" id="passwordError"></div>
    </div>

    <div class="form-group">
      <label>Confirm Password:</label>
      <div class="password-wrapper">
        <input type="password" name="confirm_password" id="confirm_password" required>
        <i class="fa-solid fa-eye toggle-password" toggle="#confirm_password"></i>
      </div>
      <div class="error" id="confirmPasswordError"></div>
    </div>


    <div class="form-group">
      <label>Role:</label>
      <select name="role" id="role" required>
        <option value="student">Student</option>
        <option value="faculty">Faculty</option>
        <option value="group">Group</option>
      </select>
    </div>
    
    <div id="student-fields" class="student-fields">
        <div class="form-group">
            <label>Department:</label>
            <select name="department" id="department">
                <option value="">Select Department</option>
                <option value="CSE">CSE</option>
                <option value="ECE">ECE</option>
                <option value="ME">ME</option>
            </select>
        </div>
        <div class="form-group">
            <label>Year:</label>
            <select name="year" id="year">
                <option value="">Select Year</option>
                <option value="1st">1st</option>
                <option value="2nd">2nd</option>
                <option value="3rd">3rd</option>
                <option value="4th">4th</option>
            </select>
        </div>
    </div>


    <div class="error" id="message"></div>

    <button type="submit">Register</button>
  </form>

  <div class="login-link">
    Already have an account? <a href="login_form.html">Login here</a>
  </div>
</div>

<script>
  document.querySelectorAll(".toggle-password").forEach(function (icon) {
    icon.addEventListener("click", function () {
      const input = document.querySelector(this.getAttribute("toggle"));
      if (input.type === "password") {
        input.type = "text";
        this.classList.remove("fa-eye");
        this.classList.add("fa-eye-slash");
      } else {
        input.type = "password";
        this.classList.remove("fa-eye-slash");
        this.classList.add("fa-eye");
      }
    });
  });
  
  const roleSelect = document.getElementById("role");
  const studentFields = document.getElementById("student-fields");

  function toggleStudentFields() {
      if (roleSelect.value === "student") {
          studentFields.style.display = "block";
      } else {
          studentFields.style.display = "none";
      }
  }

  roleSelect.addEventListener("change", toggleStudentFields);
  
  document.addEventListener("DOMContentLoaded", toggleStudentFields);


  document.getElementById("registerForm").addEventListener("submit", async function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    document.getElementById("emailError").textContent = "";
    document.getElementById("passwordError").textContent = "";
    document.getElementById("confirmPasswordError").textContent = "";
    document.getElementById("message").textContent = "";

    const password = formData.get("password");
    const confirmPassword = formData.get("confirm_password");

    const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;
    if (!passwordRegex.test(password)) {
      document.getElementById("passwordError").textContent =
        "Password must be at least 6 characters long and contain both letters and numbers.";
      return;
    }

    if (password !== confirmPassword) {
      document.getElementById("confirmPasswordError").textContent = "Passwords do not match.";
      return;
    }

    const role = formData.get("role");
    if (role === 'student') {
        if (!formData.get("department") || formData.get("department") === "") {
            document.getElementById("message").textContent = "Please select a department.";
            return;
        }
        if (!formData.get("year") || formData.get("year") === "") {
            document.getElementById("message").textContent = "Please select a year.";
            return;
        }
    }

    try {
      const response = await fetch("register.php", {
        method: "POST",
        body: formData
      });

      const result = await response.json();

      if (result.success) {
        window.location.href = result.redirect;
      } else {
        const message = result.message.toLowerCase();
        if (message.includes("email")) {
          document.getElementById("emailError").textContent = result.message;
        } else if (message.includes("password")) {
          document.getElementById("passwordError").textContent = result.message;
        } else {
          document.getElementById("message").textContent = result.message;
        }
      }
    } catch (error) {
      console.error("Error:", error);
      document.getElementById("message").textContent = "An unexpected error occurred.";
    }
  });
</script>

</body>
</html>