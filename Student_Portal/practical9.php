
<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "studenthub"; 

$message = "";
$messageType = "";

try {
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed. Check your database settings.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (!is_string($username) || !is_string($email) ||
            !is_string($password) || !is_string($confirmPassword)) {
            $message = "Invalid input.";
            $messageType = "error";
        } elseif ($username === "" || strlen($username) < 3 ||
            strlen($username) > 50 ||
            !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $message = "Username must be 3–50 characters and contain only letters, numbers, and underscores.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 100) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } elseif (strlen($password) < 8) {
            $message = "Password must contain at least 8 characters.";
            $messageType = "error";
        } elseif ($password !== $confirmPassword) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1"
                );
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $message = "Username or email already exists.";
                    $messageType = "error";
                } else {
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $insert = $conn->prepare(
                        "INSERT INTO users (username, email, password)
                         VALUES (?, ?, ?)"
                    );
                    $insert->bind_param(
                        "sss",
                        $username,
                        $email,
                        $hashedPassword
                    );
                    $insert->execute();

                    $message = "Registration successful!";
                    $messageType = "success";

                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                }
            
            } catch (mysqli_sql_exception $e) {
                error_log("Practical 9 registration error: " . $e->getMessage());

                if ($e->getCode() === 1062) {
                    $message = "Username or email already exists.";
                } else {
                    $message = "Registration failed. Check the Apache error log.";
                }

                $messageType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practical 9 Registration</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 460px;
            margin: 50px auto;
            padding: 28px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #0b40ef;
        }

        label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            border: 0;
            border-radius: 6px;
            color: white;
            background: #0b40ef;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #0832bd;
        }

        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        <p>StudentHub Portal — Practical 9</p>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="practical9.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8') ?>">

            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   minlength="3" maxlength="50"
                   pattern="[A-Za-z0-9_]+"
                   title="Use letters, numbers, and underscores only."
                   autocomplete="username" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   maxlength="100" autocomplete="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password"
                   name="confirm_password"
                   minlength="8" autocomplete="new-password" required>

            <button type="submit">Register</button>
        </form>

        <a class="back" href="index.php">Back to StudentHub</a>
    </div>
</body>
</html>
