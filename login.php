<?php
session_start();
require "db.php";

function uuidv4(): string {
    $data = random_bytes(16);
    // Set version to 0100
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        die("Email and Password required");
    }

    // 🔍 User check by email
    $sql = "SELECT user_id, password_hash FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // 🔐 Password verify
        if (password_verify($password, $user["password_hash"])) {

            // 🔄 Regenerate session (security)
            session_regenerate_id(true);

            // 🧠 Session variables
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["email"]   = $email;
            $_SESSION["logged"]  = true;

            // Load roles
            $roles = [];
            $rSql = "SELECT r.role_name
                     FROM user_roles ur
                     JOIN roles r ON r.role_id = ur.role_id
                     WHERE ur.user_id = ?";
            $rStmt = $conn->prepare($rSql);
            $rStmt->bind_param("i", $user["user_id"]);
            $rStmt->execute();
            $rRes = $rStmt->get_result();
            while ($rRes && ($row = $rRes->fetch_assoc())) {
                $roles[] = $row['role_name'];
            }
            if (count($roles) === 0) {
                $roles = ['user'];
            }
            $_SESSION['roles'] = $roles;
            $_SESSION['is_support'] = in_array('support', $roles, true);

            // 🆔 Generate session_id (UUIDv4, 36 char)
            $session_id = uuidv4();

            // 📝 Insert into login_sessions
            $insert = "INSERT INTO login_sessions (session_id, user_id) VALUES (?, ?)";
            $stmt2 = $conn->prepare($insert);
            $stmt2->bind_param("si", $session_id, $user["user_id"]);
            $stmt2->execute();

            $_SESSION["login_session_id"] = $session_id;

            // 🚀 Redirect by role
            if (!empty($_SESSION['is_support'])) {
                header("Location: support_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();

        } else {
            echo "❌ Invalid password";
        }

    } else {
        echo "❌ Email not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetTracker - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-gradient-1: #faf9fc;
            --bg-gradient-2: #f5f3ff;
            --bg-gradient-3: #ede9fe;
            --primary-purple: #a855f7;
            --primary-violet: #9333ea;
            --accent-pink: #ec4899;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --glass-bg: rgba(255, 255, 255, 1);
            --glass-border: rgba(167, 139, 250, 0.2);
            --input-border: #e5e7eb;
            --input-focus: #a855f7;
            --navbar-bg: rgba(255, 255, 255, 0.8);
        }

        [data-theme="dark"] {
            --bg-gradient-1: #0a0118;
            --bg-gradient-2: #1a0b3e;
            --bg-gradient-3: #0f0628;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --glass-bg: rgba(15, 6, 40, 0.95);
            --glass-border: rgba(139, 92, 246, 0.15);
            --input-border: rgba(139, 92, 246, 0.25);
            --input-focus: #a78bfa;
            --navbar-bg: rgba(10, 1, 24, 0.95);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color 0.3s ease;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--bg-gradient-1) 0%, var(--bg-gradient-2) 50%, var(--bg-gradient-3) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Overlay gradient for purple accent */
        .animated-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 50%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(139, 92, 246, 0.3);
            border-radius: 50%;
            animation: float 20s infinite;
        }

        .particle:nth-child(1) { width: 80px; height: 80px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 60px; height: 60px; left: 20%; animation-delay: 2s; background: rgba(236, 72, 153, 0.3); }
        .particle:nth-child(3) { width: 100px; height: 100px; left: 60%; animation-delay: 4s; }
        .particle:nth-child(4) { width: 70px; height: 70px; left: 80%; animation-delay: 6s; background: rgba(236, 72, 153, 0.3); }
        .particle:nth-child(5) { width: 90px; height: 90px; left: 40%; animation-delay: 8s; }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.3; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
            background: var(--navbar-bg);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .navbar-logo {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .navbar-logo svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .navbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
        }

        .nav-btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }

        .nav-btn-ghost:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary-purple);
            transform: translateY(-2px);
        }

        .nav-btn-primary {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .nav-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
        }

        .theme-toggle {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            display: flex;
            min-height: 100vh;
            padding-top: 72px;
            position: relative;
        }

        /* Left Side - Login Form with Glass Effect */
        .login-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25),
                        0 0 0 1px var(--glass-border);
            width: 100%;
            max-width: 480px;
            animation: slideInLeft 0.8s ease-out;
            border: 1px solid var(--glass-border);
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .header h1 {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            animation: fadeIn 1.2s ease-out;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.05rem;
            margin-bottom: 2.5rem;
            animation: fadeIn 1.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
            animation: fadeInUp 1s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.2s; }
        .form-group:nth-child(2) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--input-border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--glass-bg);
            color: var(--text-primary);
        }

        input:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1),
                        0 10px 25px -5px rgba(139, 92, 246, 0.2);
            transform: translateY(-2px);
        }

        input::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .password-container {
            position: relative;
        }

        .password-container input {
            padding-right: 3.5rem;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
            color: white;
        }

        .submit-btn {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px -5px rgba(139, 92, 246, 0.5);
            margin-top: 1.5rem;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -5px rgba(139, 92, 246, 0.6);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Toggle Auth */
        .toggle-auth {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.95rem;
            color: var(--text-secondary);
            animation: fadeIn 2s ease-out;
        }

        .toggle-auth a {
            background: none;
            border: none;
            color: var(--primary-purple);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            text-decoration: none;
        }

        .toggle-auth a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .toggle-auth a:hover::after {
            transform: scaleX(1);
        }

        /* Right Side - Hero Section */
        .hero-section {
            flex: 1;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hero-content {
            max-width: 500px;
            position: relative;
            z-index: 1;
            animation: slideInRight 0.8s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-content h2 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--accent-pink) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .hero-content > p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        /* Features */
        .features {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .feature {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.5rem;
            background: rgba(139, 92, 246, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
            animation: fadeInRight 1s ease-out;
            animation-fill-mode: both;
        }

        .feature:nth-child(1) { animation-delay: 0.2s; }
        .feature:nth-child(2) { animation-delay: 0.4s; }
        .feature:nth-child(3) { animation-delay: 0.6s; }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .feature:hover {
            transform: translateX(10px);
            background: rgba(139, 92, 246, 0.15);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.2);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-violet) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .feature-content h3 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .feature-content p {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .hidden {
            display: none;
        }

        /* Stats Section */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3rem;
            animation: fadeInUp 1s ease-out 0.8s both;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--accent-pink) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (min-width: 1024px) {
            .hero-section {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            .navbar-content {
                padding: 1rem 1.5rem;
            }

            .navbar-title {
                font-size: 1.1rem;
            }

            .nav-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .nav-btn span {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .login-section {
                padding: 1rem;
            }

            .glass-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .header h1 {
                font-size: 1.75rem;
            }

            .hero-content h2 {
                font-size: 2rem;
            }

            .stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }

            .stat-number {
                font-size: 1.75rem;
            }
        }

        /* Loading Animation */
        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="home.php" class="navbar-brand">
                <div class="navbar-logo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9"></path>
                    </svg>
                </div>
                <span class="navbar-title">BudgetTracker</span>
            </a>
            
            <div class="navbar-actions">
                <button class="nav-btn nav-btn-ghost theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                    <svg id="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2"/>
                        <path d="M12 20v2"/>
                        <path d="m4.93 4.93 1.41 1.41"/>
                        <path d="m17.66 17.66 1.41 1.41"/>
                        <path d="M2 12h2"/>
                        <path d="M20 12h2"/>
                        <path d="m6.34 17.66-1.41 1.41"/>
                        <path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                    <svg id="moon-icon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>
                
                <a href="home.php" class="nav-btn nav-btn-ghost">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span>Home</span>
                </a>
                
                <a href="signup.php" class="nav-btn nav-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span>Sign Up</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Left Side - Login Form -->
        <div class="login-section">
            <div class="glass-card">
                <div class="header">
                    <h1>Welcome Back</h1>
                    <p>Start your journey to financial freedom</p>
                </div>

                <!-- Form -->
                <form id="auth-form" method="POST" action="login.php">
                    <!-- Email Input -->
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" placeholder="you@example.com" name="email" required>
                    </div>

                    <!-- Password Input -->
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-container">
                            <input type="password" id="password" placeholder="••••••••" name="password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eye-off-icon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-btn" id="submit-btn">Login</button>
                </form>

                <!-- Toggle to Sign Up -->
                <div class="toggle-auth">
                    <span>Don't have an account? </span>
                    <a href="signup.php">Sign Up</a>
                </div>
            </div>
        </div>

        <!-- Right Side - Hero Section -->
        <div class="hero-section">
            <div class="hero-content">
                <h2>Take control of your finances</h2>
                <p>Track your expenses, manage budgets, and achieve your financial goals with our intelligent platform.</p>
                
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="feature-content">
                            <h3>Smart Budget Planning</h3>
                            <p>AI-powered insights to help you make better financial decisions</p>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="feature-content">
                            <h3>Real-time Analytics</h3>
                            <p>Monitor your spending patterns with beautiful visualizations</p>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div class="feature-content">
                            <h3>Bank-level Security</h3>
                            <p>Your financial data is encrypted and completely secure</p>
                        </div>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-item">
                        <span class="stat-number">50K+</span>
                        <span class="stat-label">Active Users</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">$2M+</span>
                        <span class="stat-label">Saved</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">4.9★</span>
                        <span class="stat-label">Rating</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Toggle icons
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');
            
            if (newTheme === 'dark') {
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            } else {
                sunIcon.classList.remove('hidden');
                moonIcon.classList.add('hidden');
            }
        }

        // Load theme from localStorage
        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');
            
            if (savedTheme === 'dark') {
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            }
        });

        // Password Toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // Add parallax effect to particles on mouse move
        document.addEventListener('mousemove', (e) => {
            const particles = document.querySelectorAll('.particle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            particles.forEach((particle, index) => {
                const speed = (index + 1) * 0.05;
                const xMove = (x - 0.5) * 50 * speed;
                const yMove = (y - 0.5) * 50 * speed;
                particle.style.transform = `translate(${xMove}px, ${yMove}px)`;
            });
        });
    </script>
</body>
</html>