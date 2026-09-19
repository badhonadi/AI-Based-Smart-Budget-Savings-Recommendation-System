<?php
require "db.php";

// React থেকে আসা JSON ডাটা হ্যান্ডেল করার জন্য
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    // যদি আপনি fetch() ব্যবহার করেন, তবে নিচের লাইনটি লাগবে
    $data = json_decode(file_get_contents("php://input"), true);
    
    // যদি সাধারণ ফর্ম সাবমিট হয় তবে $_POST ব্যবহার হবে
    $first_name = trim($data['firstName'] ?? ($_POST['firstName'] ?? ''));
    $last_name  = trim($data['lastName'] ?? ($_POST['lastName'] ?? ''));
    $username   = trim($data['username'] ?? ($_POST['username'] ?? ''));
    $email      = trim($data['email'] ?? ($_POST['email'] ?? ''));
    $birth_date = trim($data['birthday'] ?? ($_POST['birthday'] ?? ''));
    $gender     = trim($data['gender'] ?? ($_POST['gender'] ?? ''));
    $pass_raw   = (string)($data['password'] ?? ($_POST['password'] ?? ''));

    if ($first_name === '' || $last_name === '' || $email === '' || $pass_raw === '') {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        exit;
    }

    $password_hash = password_hash($pass_raw, PASSWORD_DEFAULT);

    // Check duplicate email/username
    $checkSql = "SELECT 1 FROM users WHERE email = ? OR username = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ss", $email, $username);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();

    if ($checkRes && $checkRes->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email or Username already exists!"]);
        exit;
    }

    // Insert user (matches spendeeapp.sql: password_hash)
    $insertSql = "INSERT INTO users (first_name, last_name, username, birth_date, gender, email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("sssssss", $first_name, $last_name, $username, $birth_date, $gender, $email, $password_hash);

    if ($stmt->execute()) {
        $newUserId = (int)$conn->insert_id;

        // Ensure base roles exist, then assign default role to new user
        $conn->query("INSERT IGNORE INTO roles (role_name) VALUES ('user'), ('support')");

        $roleId = null;
        $rStmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'user' LIMIT 1");
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        if ($rRes && $rRes->num_rows === 1) {
            $roleId = (int)$rRes->fetch_assoc()['role_id'];
        }

        if ($roleId) {
            $urStmt = $conn->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $urStmt->bind_param("ii", $newUserId, $roleId);
            $urStmt->execute();
        }

        echo json_encode(["status" => "success", "message" => "Account created successfully!"]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Database error"]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - BudgetTracker</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- React and ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    
    <!-- Babel Standalone for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Framer Motion -->
    <script src="https://unpkg.com/framer-motion@11/dist/framer-motion.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
                'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background-color 0.3s ease;
        }
        
        #root {
            width: 100%;
            min-height: 100vh;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.7);
        }

        /* Password strength bar animation */
        .strength-bar {
            transition: width 0.3s ease, background-color 0.3s ease;
        }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;
        const { motion, AnimatePresence } = Motion;
        
        // Icon Components
        const HomeIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        );
        
        const MailIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
        );
        
        const LockIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        );
        
        const UserIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
        );
        
        const EyeIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        );
        
        const EyeOffIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                <line x1="2" x2="22" y1="2" y2="22"/>
            </svg>
        );

        const AtSignIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="4"/>
                <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>
            </svg>
        );

        const CalendarIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                <line x1="16" x2="16" y1="2" y2="6"/>
                <line x1="8" x2="8" y1="2" y2="6"/>
                <line x1="3" x2="21" y1="10" y2="10"/>
            </svg>
        );

        const SunIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
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
        );

        const MoonIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
            </svg>
        );

        const UsersIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        );

        const ShieldCheckIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        );

        const AlertIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" x2="12" y1="8" y2="12"/>
                <line x1="12" x2="12.01" y1="16" y2="16"/>
            </svg>
        );

        const CheckCircleIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        );

        const XIcon = () => (
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        );

        // Logo Component matching the image
        const Logo = ({ isDark }) => (
            <div className="w-12 h-12 bg-gradient-to-br from-indigo-500 via-purple-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/40">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="8" width="16" height="12" rx="2" stroke="white" strokeWidth="2" fill="none"/>
                    <path d="M8 8V6C8 4.89543 8.89543 4 10 4H14C15.1046 4 16 4.89543 16 6V8" stroke="white" strokeWidth="2" strokeLinecap="round"/>
                    <circle cx="12" cy="14" r="1.5" fill="white"/>
                </svg>
            </div>
        );

        // Toast Notification Component
        const Toast = ({ message, onClose, isDark }) => {
            useEffect(() => {
                const timer = setTimeout(() => {
                    onClose();
                }, 3000);
                return () => clearTimeout(timer);
            }, [onClose]);

            return (
                <motion.div
                    initial={{ opacity: 0, x: 100, y: 0 }}
                    animate={{ opacity: 1, x: 0, y: 0 }}
                    exit={{ opacity: 0, x: 100 }}
                    transition={{ duration: 0.3, type: "spring" }}
                    className={`fixed top-24 right-6 z-[60] flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl backdrop-blur-xl border ${
                        isDark 
                            ? 'bg-gradient-to-r from-emerald-900/90 to-green-900/90 border-emerald-500/30' 
                            : 'bg-gradient-to-r from-emerald-50/95 to-green-50/95 border-emerald-300/50'
                    }`}
                    style={{ minWidth: '320px', maxWidth: '400px' }}
                >
                    <div className={`flex-shrink-0 ${isDark ? 'text-emerald-400' : 'text-emerald-600'}`}>
                        <CheckCircleIcon />
                    </div>
                    <div className="flex-1">
                        <h4 className={`font-semibold ${isDark ? 'text-white' : 'text-gray-900'}`}>
                            Success!
                        </h4>
                        <p className={`text-sm ${isDark ? 'text-emerald-200' : 'text-gray-700'}`}>
                            {message}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className={`flex-shrink-0 p-1 rounded-lg transition-colors ${
                            isDark 
                                ? 'hover:bg-white/10 text-emerald-200 hover:text-white' 
                                : 'hover:bg-gray-200 text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        <XIcon />
                    </button>
                </motion.div>
            );
        };

        // Password Strength Calculator
        const calculatePasswordStrength = (password) => {
            if (!password) return { strength: 0, label: '', color: '', percentage: 0 };
            
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 20;
            if (password.length >= 12) strength += 10;
            
            // Contains lowercase
            if (/[a-z]/.test(password)) strength += 15;
            
            // Contains uppercase
            if (/[A-Z]/.test(password)) strength += 15;
            
            // Contains numbers
            if (/[0-9]/.test(password)) strength += 15;
            
            // Contains special characters
            if (/[^a-zA-Z0-9]/.test(password)) strength += 25;
            
            // Determine label and color
            let label = '';
            let color = '';
            let percentage = 0;
            
            if (strength <= 40) {
                label = 'Weak';
                color = 'bg-red-500';
                percentage = 33;
            } else if (strength <= 70) {
                label = 'Medium';
                color = 'bg-yellow-500';
                percentage = 66;
            } else {
                label = 'Strong';
                color = 'bg-green-500';
                percentage = 100;
            }
            
            return { strength, label, color, percentage };
        };

        // Navbar Component
        const Navbar = ({ isDark, toggleDarkMode }) => {
            return (
                <motion.nav 
                    initial={{ y: -100, opacity: 0 }}
                    animate={{ y: 0, opacity: 1 }}
                    transition={{ duration: 0.6 }}
                    className={`fixed top-0 left-0 right-0 z-50 px-6 py-4 backdrop-blur-md border-b transition-colors ${
                        isDark 
                            ? 'bg-black/20 border-white/10' 
                            : 'bg-white/20 border-gray-200/50'
                    }`}
                >
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        {/* Logo and Name */}
                        <div className="flex items-center gap-3">
                            <Logo isDark={isDark} />
                            <span className={`text-2xl font-bold ${
                                isDark 
                                    ? 'bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent'
                                    : 'bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent'
                            }`}>
                                BudgetTracker
                            </span>
                        </div>

                        {/* Navigation Links */}
                        <div className="flex items-center gap-3">
                            {/* Dark/Light Mode Toggle */}
                            <button
                                onClick={toggleDarkMode}
                                className={`p-2.5 rounded-xl transition-all ${
                                    isDark
                                        ? 'bg-white/10 hover:bg-white/20 text-yellow-300'
                                        : 'bg-purple-100 hover:bg-purple-200 text-purple-600'
                                }`}
                            >
                                {isDark ? <SunIcon /> : <MoonIcon />}
                            </button>
                            
                            <a 
                                href="home.php"
                                className={`px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 group ${
                                    isDark
                                        ? 'text-white/90 hover:text-white hover:bg-white/10'
                                        : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'
                                }`}
                            >
                                <div className="group-hover:scale-110 transition-transform">
                                    <HomeIcon />
                                </div>
                            </a>
                            <a 
                                href="login.php"
                                className={`px-6 py-2.5 rounded-xl transition-all shadow-lg ${
                                    isDark
                                        ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white hover:from-purple-600 hover:to-indigo-700 shadow-purple-500/30 hover:shadow-purple-500/50'
                                        : 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white hover:from-purple-600 hover:to-indigo-600 shadow-purple-400/30 hover:shadow-purple-400/50'
                                } hover:scale-105`}
                            >
                                Login
                            </a>
                        </div>
                    </div>
                </motion.nav>
            );
        };

        // Password Strength Indicator Component
        const PasswordStrengthIndicator = ({ password, isDark }) => {
            const { label, color, percentage } = calculatePasswordStrength(password);
            
            if (!password) return null;
            
            return (
                <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="mt-2 space-y-2"
                >
                    {/* Strength Bar */}
                    <div className={`h-2 rounded-full overflow-hidden ${isDark ? 'bg-slate-700' : 'bg-gray-200'}`}>
                        <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${percentage}%` }}
                            transition={{ duration: 0.3 }}
                            className={`h-full strength-bar ${color}`}
                        />
                    </div>
                    
                    {/* Strength Label */}
                    <div className="flex items-center gap-2">
                        {label === 'Strong' && (
                            <div className="text-green-500">
                                <ShieldCheckIcon />
                            </div>
                        )}
                        {label === 'Medium' && (
                            <div className="text-yellow-500">
                                <AlertIcon />
                            </div>
                        )}
                        {label === 'Weak' && (
                            <div className="text-red-500">
                                <AlertIcon />
                            </div>
                        )}
                        <span className={`text-sm font-medium ${
                            label === 'Weak' ? 'text-red-500' : 
                            label === 'Medium' ? 'text-yellow-500' : 
                            'text-green-500'
                        }`}>
                            Password strength: {label}
                        </span>
                    </div>
                    
                    {/* Password Requirements */}
                    {label !== 'Strong' && (
                        <div className={`text-xs ${isDark ? 'text-slate-400' : 'text-gray-500'} space-y-1`}>
                            <p>Password should contain:</p>
                            <ul className="list-disc list-inside space-y-0.5 ml-2">
                                {password.length < 8 && <li>At least 8 characters</li>}
                                {!/[A-Z]/.test(password) && <li>Uppercase letter</li>}
                                {!/[a-z]/.test(password) && <li>Lowercase letter</li>}
                                {!/[0-9]/.test(password) && <li>Number</li>}
                                {!/[^a-zA-Z0-9]/.test(password) && <li>Special character (!@#$%^&*)</li>}
                            </ul>
                        </div>
                    )}
                </motion.div>
            );
        };

        // Main App Component
        const App = () => {
            const [isDark, setIsDark] = useState(true);
            const [showPassword, setShowPassword] = useState(false);
            const [showToast, setShowToast] = useState(false);
            const [formData, setFormData] = useState({
                firstName: "",
                lastName: "",
                username: "",
                email: "",
                birthday: "",
                gender: "",
                password: "",
                confirmPassword: ""
            });

            const toggleDarkMode = () => {
                setIsDark(!isDark);
            };

           const handleSubmit = async (e) => {
    e.preventDefault();
    
    // পাসওয়ার্ড ম্যাচিং চেক
    if (formData.password !== formData.confirmPassword) {
        alert("Passwords do not match!");
        return;
    }
    
    // পাসওয়ার্ড স্ট্রেন্থ চেক
    const { strength } = calculatePasswordStrength(formData.password);
    if (strength <= 40) {
        alert("Please use a stronger password for better security!");
        return;
    }

    try {
        // ডাটাবেসে ডাটা পাঠানোর চেষ্টা
        const response = await fetch('signup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.status === 'success') {
            // যদি ডাটাবেসে সফলভাবে সেভ হয়, তবেই টোস্ট দেখাবে
            setShowToast(true);
            
            // ২ সেকেন্ড পর লগইন পেজে নিয়ে যাবে
            setTimeout(() => {
                window.location.href = "login.php";
            }, 1000);
            } else {
            // যদি ইমেইল আগে থেকেই থাকে বা অন্য কোনো এরর হয়
            alert(result.message);
               }
               } catch (error) {
                 console.error("Error:", error);
                 alert("Something went wrong. Please try again later.");
            }
            };
            const handleChange = (e) => {
                setFormData({
                    ...formData,
                    [e.target.name]: e.target.value
                });
            };

            return (
                <div className={`min-h-screen relative overflow-hidden transition-colors duration-500 ${
                    isDark 
                        ? 'bg-gradient-to-br from-slate-950 via-purple-950 to-slate-900' 
                        : 'bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50'
                }`}>
                    {/* Toast Notification */}
                    <AnimatePresence>
                        {showToast && (
                            <Toast 
                                message="Account created successfully! Redirecting to login..." 
                                onClose={() => setShowToast(false)}
                                isDark={isDark}
                            />
                        )}
                    </AnimatePresence>

                    {/* Complex Background */}
                    <div className="absolute inset-0 overflow-hidden">
                        {/* Animated geometric shapes */}
                        <motion.div
                            animate={{
                                rotate: [0, 360],
                                scale: [1, 1.1, 1],
                            }}
                            transition={{
                                duration: 25,
                                repeat: Infinity,
                                ease: "linear"
                            }}
                            className="absolute -top-40 -left-40 w-96 h-96"
                        >
                            <div className={`w-full h-full rounded-full blur-3xl ${
                                isDark 
                                    ? 'bg-gradient-to-br from-purple-600/20 to-transparent' 
                                    : 'bg-gradient-to-br from-purple-300/30 to-transparent'
                            }`} />
                        </motion.div>
                        
                        <motion.div
                            animate={{
                                rotate: [360, 0],
                                scale: [1.1, 1, 1.1],
                            }}
                            transition={{
                                duration: 20,
                                repeat: Infinity,
                                ease: "linear"
                            }}
                            className="absolute -bottom-40 -right-40 w-[500px] h-[500px]"
                        >
                            <div className={`w-full h-full rounded-full blur-3xl ${
                                isDark 
                                    ? 'bg-gradient-to-tl from-indigo-600/20 to-transparent' 
                                    : 'bg-gradient-to-tl from-blue-300/30 to-transparent'
                            }`} />
                        </motion.div>

                        {/* Hexagon shapes */}
                        <motion.div
                            animate={{
                                y: [0, -30, 0],
                                rotate: [0, 5, 0],
                            }}
                            transition={{
                                duration: 8,
                                repeat: Infinity,
                                ease: "easeInOut"
                            }}
                            className="absolute top-1/4 left-1/4 w-64 h-64 opacity-10"
                        >
                            <svg viewBox="0 0 100 100" className="w-full h-full">
                                <polygon points="50 1 95 25 95 75 50 99 5 75 5 25" fill="url(#grad1)" stroke={isDark ? "rgba(139, 92, 246, 0.3)" : "rgba(139, 92, 246, 0.5)"} strokeWidth="0.5"/>
                                <defs>
                                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style={{ stopColor: 'rgb(139, 92, 246)', stopOpacity: isDark ? 0.3 : 0.4 }} />
                                        <stop offset="100%" style={{ stopColor: 'rgb(99, 102, 241)', stopOpacity: isDark ? 0.1 : 0.2 }} />
                                    </linearGradient>
                                </defs>
                            </svg>
                        </motion.div>

                        <motion.div
                            animate={{
                                y: [0, 40, 0],
                                rotate: [0, -5, 0],
                            }}
                            transition={{
                                duration: 10,
                                repeat: Infinity,
                                ease: "easeInOut"
                            }}
                            className="absolute bottom-1/4 right-1/4 w-72 h-72 opacity-10"
                        >
                            <svg viewBox="0 0 100 100" className="w-full h-full">
                                <polygon points="50 1 95 25 95 75 50 99 5 75 5 25" fill="url(#grad2)" stroke={isDark ? "rgba(99, 102, 241, 0.3)" : "rgba(99, 102, 241, 0.5)"} strokeWidth="0.5"/>
                                <defs>
                                    <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style={{ stopColor: 'rgb(99, 102, 241)', stopOpacity: isDark ? 0.3 : 0.4 }} />
                                        <stop offset="100%" style={{ stopColor: 'rgb(139, 92, 246)', stopOpacity: isDark ? 0.1 : 0.2 }} />
                                    </linearGradient>
                                </defs>
                            </svg>
                        </motion.div>

                        {/* Grid pattern overlay */}
                        <div className={`absolute inset-0 ${isDark ? 'opacity-40' : 'opacity-20'}`} style={{
                            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3Cpattern id='grid' width='60' height='60' patternUnits='userSpaceOnUse'%3E%3Cpath d='M 10 0 L 0 0 0 10' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='100%25' height='100%25' fill='url(%23grid)'/%3E%3C/svg%3E")`
                        }} />
                        
                        {/* Radial gradient overlay */}
                        <div className={`absolute inset-0 ${isDark ? 'bg-gradient-to-r from-transparent via-transparent to-black/50' : 'bg-gradient-to-r from-transparent via-transparent to-white/30'}`} />
                    </div>

                    <Navbar isDark={isDark} toggleDarkMode={toggleDarkMode} />

                    <div className="relative z-10 min-h-screen flex items-center justify-center px-4 py-20">
                        <motion.div
                            initial={{ opacity: 0, y: 50 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.8, delay: 0.2 }}
                            className="w-full max-w-2xl"
                        >
                            {/* Header */}
                            <motion.div 
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                                className="text-center mb-8"
                            >
                                <h1 className={`text-5xl font-bold mb-3 ${
                                    isDark 
                                        ? 'bg-gradient-to-r from-purple-400 via-fuchsia-400 to-purple-400 bg-clip-text text-transparent'
                                        : 'bg-gradient-to-r from-purple-600 via-fuchsia-600 to-purple-600 bg-clip-text text-transparent'
                                }`}>
                                    Create Account
                                </h1>
                                <p className={`text-lg ${isDark ? 'text-slate-400' : 'text-gray-600'}`}>
                                    Start managing your budget efficiently
                                </p>
                            </motion.div>

                            {/* Signup Form */}
                            <motion.div
                                initial={{ opacity: 0, scale: 0.95 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.6, delay: 0.6 }}
                                className={`relative backdrop-blur-2xl rounded-3xl p-8 shadow-2xl overflow-hidden ${
                                    isDark 
                                        ? 'bg-gradient-to-b from-slate-900/90 to-slate-950/90 border border-purple-500/20' 
                                        : 'bg-white/80 border border-purple-200/50'
                                }`}
                            >
                                {/* Card glow effect */}
                                <div className={`absolute -top-40 -right-40 w-80 h-80 rounded-full blur-3xl ${
                                    isDark ? 'bg-purple-500/20' : 'bg-purple-300/20'
                                }`} />
                                <div className={`absolute -bottom-40 -left-40 w-80 h-80 rounded-full blur-3xl ${
                                    isDark ? 'bg-indigo-500/20' : 'bg-indigo-300/20'
                                }`} />
                                
                                <div className="relative z-10">
                                    <form method="POST" action="signup.php" onSubmit={handleSubmit} className="space-y-5">
                                        {/* Two Column Layout for First Name and Last Name */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            {/* First Name Field */}
                                            <motion.div
                                                initial={{ opacity: 0, x: -20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.5, delay: 0.7 }}
                                            >
                                                <label className={`block text-sm font-semibold mb-2 ${
                                                    isDark ? 'text-slate-300' : 'text-gray-700'
                                                }`}>
                                                    First Name
                                                </label>
                                                <div className="relative group">
                                                    <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                        isDark 
                                                            ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                            : 'text-gray-400 group-focus-within:text-purple-500'
                                                    }`}>
                                                        <UserIcon />
                                                    </div>
                                                    <input
                                                        type="text"
                                                        name="firstName"
                                                        value={formData.firstName}
                                                        onChange={handleChange}
                                                        placeholder="Enter first name"
                                                        className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                            isDark 
                                                                ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                                : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                        }`}
                                                        required
                                                    />
                                                </div>
                                            </motion.div>

                                            {/* Last Name Field */}
                                            <motion.div
                                                initial={{ opacity: 0, x: 20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.5, delay: 0.7 }}
                                            >
                                                <label className={`block text-sm font-semibold mb-2 ${
                                                    isDark ? 'text-slate-300' : 'text-gray-700'
                                                }`}>
                                                    Last Name
                                                </label>
                                                <div className="relative group">
                                                    <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                        isDark 
                                                            ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                            : 'text-gray-400 group-focus-within:text-purple-500'
                                                    }`}>
                                                        <UserIcon />
                                                    </div>
                                                    <input
                                                        type="text"
                                                        name="lastName"
                                                        value={formData.lastName}
                                                        onChange={handleChange}
                                                        placeholder="Enter last name"
                                                        className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                            isDark 
                                                                ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                                : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                        }`}
                                                        required
                                                    />
                                                </div>
                                            </motion.div>
                                        </div>

                                        {/* Username Field */}
                                        <motion.div
                                            initial={{ opacity: 0, x: -20 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ duration: 0.5, delay: 0.8 }}
                                        >
                                            <label className={`block text-sm font-semibold mb-2 ${
                                                isDark ? 'text-slate-300' : 'text-gray-700'
                                            }`}>
                                                Username
                                            </label>
                                            <div className="relative group">
                                                <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                    isDark 
                                                        ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                        : 'text-gray-400 group-focus-within:text-purple-500'
                                                }`}>
                                                    <AtSignIcon />
                                                </div>
                                                <input
                                                    type="text"
                                                    name="username"
                                                    value={formData.username}
                                                    onChange={handleChange}
                                                    placeholder="Choose a username"
                                                    className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                        isDark 
                                                            ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                            : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                    }`}
                                                    required
                                                />
                                            </div>
                                        </motion.div>

                                        {/* Email Field */}
                                        <motion.div
                                            initial={{ opacity: 0, x: -20 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ duration: 0.5, delay: 0.9 }}
                                        >
                                            <label className={`block text-sm font-semibold mb-2 ${
                                                isDark ? 'text-slate-300' : 'text-gray-700'
                                            }`}>
                                                Email Address
                                            </label>
                                            <div className="relative group">
                                                <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                    isDark 
                                                        ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                        : 'text-gray-400 group-focus-within:text-purple-500'
                                                }`}>
                                                    <MailIcon />
                                                </div>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    value={formData.email}
                                                    onChange={handleChange}
                                                    placeholder="Enter your email"
                                                    className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                        isDark 
                                                            ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                            : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                    }`}
                                                    required
                                                />
                                            </div>
                                        </motion.div>

                                        {/* Two Column Layout for Birthday and Gender */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            {/* Birthday Field */}
                                            <motion.div
                                                initial={{ opacity: 0, x: -20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.5, delay: 1.0 }}
                                            >
                                                <label className={`block text-sm font-semibold mb-2 ${
                                                    isDark ? 'text-slate-300' : 'text-gray-700'
                                                }`}>
                                                    Birthday
                                                </label>
                                                <div className="relative group">
                                                    <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                        isDark 
                                                            ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                            : 'text-gray-400 group-focus-within:text-purple-500'
                                                    }`}>
                                                        <CalendarIcon />
                                                    </div>
                                                    <input
                                                        type="date"
                                                        name="birthday"
                                                        value={formData.birthday}
                                                        onChange={handleChange}
                                                        className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                            isDark 
                                                                ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                                : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                        }`}
                                                        required
                                                    />
                                                </div>
                                            </motion.div>

                                            {/* Gender Field */}
                                            <motion.div
                                                initial={{ opacity: 0, x: 20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.5, delay: 1.0 }}
                                            >
                                                <label className={`block text-sm font-semibold mb-2 ${
                                                    isDark ? 'text-slate-300' : 'text-gray-700'
                                                }`}>
                                                    Gender
                                                </label>
                                                <div className="relative group">
                                                    <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                        isDark 
                                                            ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                            : 'text-gray-400 group-focus-within:text-purple-500'
                                                    }`}>
                                                        <UsersIcon />
                                                    </div>
                                                    <select
                                                        name="gender"
                                                        value={formData.gender}
                                                        onChange={handleChange}
                                                        className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                            isDark 
                                                                ? 'bg-slate-800/50 border border-slate-700 text-white hover:border-slate-600'
                                                                : 'bg-white border border-gray-200 text-gray-900 hover:border-gray-300'
                                                        }`}
                                                        required
                                                    >
                                                        <option value="">Select gender</option>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                        <option value="other">Other</option>
                                                        <option value="prefer-not-to-say">Prefer not to say</option>
                                                    </select>
                                                </div>
                                            </motion.div>
                                        </div>

                                        {/* Password Field with Strength Indicator */}
                                        <motion.div
                                            initial={{ opacity: 0, x: -20 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ duration: 0.5, delay: 1.1 }}
                                        >
                                            <label className={`block text-sm font-semibold mb-2 ${
                                                isDark ? 'text-slate-300' : 'text-gray-700'
                                            }`}>
                                                Password
                                            </label>
                                            <div className="relative group">
                                                <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                    isDark 
                                                        ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                        : 'text-gray-400 group-focus-within:text-purple-500'
                                                }`}>
                                                    <LockIcon />
                                                </div>
                                                <input
                                                    type={showPassword ? "text" : "password"}
                                                    name="password"
                                                    value={formData.password}
                                                    onChange={handleChange}
                                                    placeholder="Create a password"
                                                    className={`w-full pl-12 pr-12 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                        isDark 
                                                            ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                            : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                    }`}
                                                    required
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowPassword(!showPassword)}
                                                    className={`absolute right-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                        isDark 
                                                            ? 'text-slate-500 hover:text-purple-400' 
                                                            : 'text-gray-400 hover:text-purple-500'
                                                    }`}
                                                >
                                                    {showPassword ? <EyeOffIcon /> : <EyeIcon />}
                                                </button>
                                            </div>
                                            
                                            {/* Password Strength Indicator */}
                                            <PasswordStrengthIndicator password={formData.password} isDark={isDark} />
                                        </motion.div>

                                        {/* Confirm Password Field */}
                                        <motion.div
                                            initial={{ opacity: 0, x: -20 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ duration: 0.5, delay: 1.2 }}
                                        >
                                            <label className={`block text-sm font-semibold mb-2 ${
                                                isDark ? 'text-slate-300' : 'text-gray-700'
                                            }`}>
                                                Confirm Password
                                            </label>
                                            <div className="relative group">
                                                <div className={`absolute left-4 top-1/2 -translate-y-1/2 transition-colors ${
                                                    isDark 
                                                        ? 'text-slate-500 group-focus-within:text-purple-400' 
                                                        : 'text-gray-400 group-focus-within:text-purple-500'
                                                }`}>
                                                    <LockIcon />
                                                </div>
                                                <input
                                                    type={showPassword ? "text" : "password"}
                                                    name="confirmPassword"
                                                    value={formData.confirmPassword}
                                                    onChange={handleChange}
                                                    placeholder="Confirm your password"
                                                    className={`w-full pl-12 pr-4 py-3.5 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent ${
                                                        isDark 
                                                            ? 'bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 hover:border-slate-600'
                                                            : 'bg-white border border-gray-200 text-gray-900 placeholder-gray-400 hover:border-gray-300'
                                                    }`}
                                                    required
                                                />
                                            </div>
                                            
                                            {/* Password Match Indicator */}
                                            {formData.confirmPassword && (
                                                <motion.div
                                                    initial={{ opacity: 0, y: -10 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    className="mt-2 flex items-center gap-2"
                                                >
                                                    {formData.password === formData.confirmPassword ? (
                                                        <>
                                                            <div className="text-green-500">
                                                                <ShieldCheckIcon />
                                                            </div>
                                                            <span className="text-sm font-medium text-green-500">
                                                                Passwords match
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <div className="text-red-500">
                                                                <AlertIcon />
                                                            </div>
                                                            <span className="text-sm font-medium text-red-500">
                                                                Passwords do not match
                                                            </span>
                                                        </>
                                                    )}
                                                </motion.div>
                                            )}
                                        </motion.div>

                                        {/* Submit Button */}
                                        <motion.button
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.5, delay: 1.3 }}
                                            whileHover={{ scale: 1.02, y: -2 }}
                                            whileTap={{ scale: 0.98 }}
                                            type="submit"
                                            className={`w-full py-4 font-semibold rounded-xl transition-all shadow-lg mt-2 relative overflow-hidden group ${
                                                isDark 
                                                    ? 'bg-gradient-to-r from-purple-600 via-fuchsia-600 to-purple-600 text-white hover:from-purple-700 hover:via-fuchsia-700 hover:to-purple-700 shadow-purple-500/50 hover:shadow-purple-500/70'
                                                    : 'bg-gradient-to-r from-purple-500 via-fuchsia-500 to-purple-500 text-white hover:from-purple-600 hover:via-fuchsia-600 hover:to-purple-600 shadow-purple-400/50 hover:shadow-purple-400/70'
                                            }`}
                                        >
                                            <span className="relative z-10">Create Account</span>
                                            <div className="absolute inset-0 bg-gradient-to-r from-purple-400 to-fuchsia-400 opacity-0 group-hover:opacity-20 transition-opacity" />
                                        </motion.button>

                                        {/* Login Link */}
                                        <motion.p
                                            initial={{ opacity: 0 }}
                                            animate={{ opacity: 1 }}
                                            transition={{ duration: 0.5, delay: 1.4 }}
                                            className={`text-center mt-4 ${isDark ? 'text-slate-400' : 'text-gray-600'}`}
                                        >
                                            Already have an account?{" "}
                                            <a href="login.php" className={`font-semibold transition-colors ${
                                                isDark 
                                                    ? 'text-purple-400 hover:text-purple-300' 
                                                    : 'text-purple-600 hover:text-purple-500'
                                            }`}>
                                                Login here
                                            </a>
                                        </motion.p>
                                    </form>
                                </div>
                            </motion.div>

                            {/* Trust Badge */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 1.5 }}
                                className={`mt-8 flex items-center justify-center gap-4 ${isDark ? 'text-slate-400' : 'text-gray-600'}`}
                            >
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                                    <span className="text-sm">Secure & Encrypted</span>
                                </div>
                                <div className={`w-1 h-1 rounded-full ${isDark ? 'bg-slate-600' : 'bg-gray-400'}`} />
                                <div className="flex items-center gap-2">
                                    <span className="text-sm">4.9★ Rated</span>
                                </div>
                            </motion.div>
                        </motion.div>
                    </div>
                </div>
            );
        };

        // Render the app
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>
