<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetTracker - Take Control of Your Finances</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Primary Colors - Purple/Violet Gradient */
            --primary-400: #a78bfa;
            --primary-500: #8b5cf6;
            --primary-600: #7c3aed;
            --primary-700: #6d28d9;
            --primary-800: #5b21b6;
            
            /* Secondary Colors */
            --secondary-500: #a855f7;
            --secondary-600: #9333ea;
            --secondary-700: #7e22ce;
            
            /* Accent Colors */
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --accent-yellow: #f59e0b;
            --accent-blue: #3b82f6;
            
            /* Neutral Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Background Colors */
            --bg-primary: rgba(10, 10, 15, 0.98);
            --bg-secondary: rgba(20, 15, 35, 0.95);
            --bg-tertiary: rgba(30, 20, 50, 0.9);
            
            /* Text Colors */
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            --text-inverse: #111827;
            
            /* Gradients */
            --gradient-primary: linear-gradient(135deg, #a78bfa 0%, #7c3aed 50%, #5b21b6 100%);
            --gradient-secondary: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            --gradient-hero: linear-gradient(135deg, #7c3aed 0%, #5b21b6 25%, #6d28d9 50%, #8b5cf6 75%, #a78bfa 100%);
            
            /* Spacing */
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;
            --spacing-2xl: 4rem;
            --spacing-3xl: 6rem;
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition-fast: 150ms ease-in-out;
            --transition-base: 300ms ease-in-out;
            --transition-slow: 500ms ease-in-out;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        [data-theme="light"] {
            --bg-primary: rgba(255, 255, 255, 1);
            --bg-secondary: rgba(255, 255, 255, 0.98);
            --bg-tertiary: rgba(250, 250, 250, 1);
            
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-tertiary: #6b7280;
            --text-inverse: #ffffff;
        }

        [data-theme="light"] .animated-bg {
            background: linear-gradient(to right, #ffffff 0%, #faf5ff 40%, #f5f3ff 100%);
        }

        [data-theme="light"] .particle {
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, transparent 70%);
        }

        [data-theme="light"] .particle:nth-child(2) {
            background: radial-gradient(circle, rgba(167, 139, 250, 0.06) 0%, transparent 70%);
        }

        [data-theme="light"] .particle:nth-child(4) {
            background: radial-gradient(circle, rgba(124, 58, 237, 0.05) 0%, transparent 70%);
        }

        [data-theme="light"] .particle:nth-child(6) {
            background: radial-gradient(circle, rgba(147, 51, 234, 0.04) 0%, transparent 70%);
        }

        [data-theme="light"] .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(139, 92, 246, 0.15);
            box-shadow: 0 4px 20px rgba(91, 33, 182, 0.08);
        }

        [data-theme="light"] .dashboard-card {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.15), 0 0 60px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        [data-theme="light"] .card-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(139, 92, 246, 0.15);
        }

        [data-theme="light"] .ie-item {
            background: rgba(255, 255, 255, 0.95);
        }

        [data-theme="light"] .feature-card {
            background: rgba(255, 255, 255, 1);
            border: 1px solid rgba(139, 92, 246, 0.15);
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08);
        }

        [data-theme="light"] .feature-card:hover {
            box-shadow: 0 20px 40px -5px rgba(139, 92, 246, 0.2);
            border-color: rgba(139, 92, 246, 0.3);
        }

        [data-theme="light"] .theme-toggle {
            background: rgba(255, 255, 255, 1);
            border: 2px solid rgba(139, 92, 246, 0.2);
        }

        [data-theme="light"] .btn-secondary {
            background: rgba(139, 92, 246, 0.08);
            border: 2px solid rgba(139, 92, 246, 0.2);
        }

        [data-theme="light"] .btn-secondary:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.3);
        }

        [data-theme="light"] .btn-outline {
            border: 2px solid rgba(139, 92, 246, 0.3);
        }

        [data-theme="light"] .btn-outline:hover {
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.5);
        }

        [data-theme="light"] .footer {
            background: rgba(255, 255, 255, 0.98);
            border-top: 1px solid rgba(139, 92, 246, 0.15);
        }

        [data-theme="light"] .modal-content {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 25px 50px rgba(139, 92, 246, 0.2), 0 0 60px rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        [data-theme="light"] .modal-header {
            border-bottom: 1px solid rgba(139, 92, 246, 0.15);
        }

        [data-theme="light"] .form-input,
        [data-theme="light"] .form-textarea {
            background: rgba(255, 255, 255, 1);
            border: 2px solid rgba(139, 92, 246, 0.15);
        }

        [data-theme="light"] .btn-cancel {
            border: 2px solid rgba(139, 92, 246, 0.15);
        }

        [data-theme="light"] .btn-cancel:hover {
            background: rgba(139, 92, 246, 0.08);
            border-color: rgba(139, 92, 246, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
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
            background: linear-gradient(to right, #0a0a0f 0%, #1a0f2e 40%, #0f0a1f 100%);
            z-index: -2;
            transition: background 0.5s ease;
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
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 8s ease-in-out infinite;
            filter: blur(40px);
            transition: background 0.5s ease;
        }

        .particle:nth-child(1) { 
            width: 600px; 
            height: 600px; 
            left: -15%; 
            top: -10%;
            animation-delay: 0s; 
        }
        .particle:nth-child(2) { 
            width: 400px; 
            height: 400px; 
            left: 5%; 
            top: 30%;
            animation-delay: 2s;
            background: radial-gradient(circle, rgba(167, 139, 250, 0.12) 0%, transparent 70%);
        }
        .particle:nth-child(3) { 
            width: 500px; 
            height: 500px; 
            right: -10%; 
            top: 20%;
            animation-delay: 4s;
        }
        .particle:nth-child(4) { 
            width: 350px; 
            height: 350px; 
            right: 15%; 
            bottom: 10%;
            animation-delay: 1s;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
        }
        .particle:nth-child(5) { 
            width: 450px; 
            height: 450px; 
            left: 40%; 
            bottom: -15%;
            animation-delay: 3s;
        }
        .particle:nth-child(6) { 
            width: 380px; 
            height: 380px; 
            left: 60%; 
            top: 50%;
            animation-delay: 5s;
            background: radial-gradient(circle, rgba(147, 51, 234, 0.08) 0%, transparent 70%);
        }
        .particle:nth-child(7) { 
            width: 420px; 
            height: 420px; 
            right: 30%; 
            top: 60%;
            animation-delay: 6s;
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 0.3;
                transform: scale(1);
            }
            50% { 
                opacity: 0.6;
                transform: scale(1.1);
            }
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            padding: var(--spacing-sm) 0;
            box-shadow: 0 4px 20px rgba(91, 33, 182, 0.2);
            transition: all 0.3s ease;
        }

        .nav-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 var(--spacing-lg);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            text-decoration: none;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px -5px rgba(139, 92, 246, 0.6);
            transition: all 0.3s ease;
        }

        .logo:hover .logo-icon {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 12px 28px -5px rgba(139, 92, 246, 0.8);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .logo-text {
            font-weight: 800;
            font-size: 1.25rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: var(--spacing-lg);
            align-items: center;
        }

        .nav-links a,
        .nav-link-btn {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: color var(--transition-fast);
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .nav-links a:hover,
        .nav-link-btn:hover {
            color: var(--text-primary);
        }

        .nav-links a.active {
            color: var(--primary-500);
        }

        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
        }

        .nav-actions {
            display: flex;
            gap: var(--spacing-sm);
            align-items: center;
        }

        /* Theme Toggle */
        .theme-toggle {
            background: var(--bg-secondary);
            border: 2px solid rgba(139, 92, 246, 0.3);
            padding: 0.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .theme-toggle:hover {
            background: var(--gradient-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .theme-toggle svg {
            width: 20px;
            height: 20px;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }

        .theme-toggle:hover svg {
            color: white;
        }

        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.6);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary.btn-large {
            padding: 0.875rem 2rem;
            font-size: 1rem;
        }

        .btn-secondary {
            background: rgba(139, 92, 246, 0.15);
            color: var(--text-primary);
            border: 2px solid rgba(139, 92, 246, 0.3);
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            background: rgba(139, 92, 246, 0.25);
            border-color: rgba(139, 92, 246, 0.5);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid rgba(139, 92, 246, 0.5);
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
            backdrop-filter: blur(10px);
        }

        .btn-outline:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: rgba(139, 92, 246, 0.8);
            transform: translateY(-2px);
        }

        .btn-outline.btn-large {
            padding: 0.875rem 2rem;
            font-size: 1rem;
        }

        /* Hero Section */
        .hero {
            padding: var(--spacing-3xl) var(--spacing-lg);
            position: relative;
            overflow: hidden;
            min-height: 90vh;
            display: flex;
            align-items: center;
        }

        .hero-content {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-3xl);
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            text-shadow: 0 2px 20px rgba(139, 92, 246, 0.3);
        }

        .gradient-text {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
        }

        .hero-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 540px;
        }

        .hero-actions {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        .hero-stats {
            display: flex;
            gap: var(--spacing-xl);
            margin-top: var(--spacing-md);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Dashboard Preview */
        .hero-visual {
            position: relative;
        }

        .dashboard-preview {
            position: relative;
            transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
            transition: transform var(--transition-slow);
        }

        .dashboard-preview:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }

        .dashboard-card {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl), 0 0 60px rgba(139, 92, 246, 0.3);
            overflow: hidden;
            border: 1px solid rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(20px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            background: var(--bg-secondary);
        }

        .card-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
        }

        .card-menu {
            color: var(--text-tertiary);
            font-size: 1.25rem;
            cursor: pointer;
        }

        .card-content {
            padding: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }

        .balance-display {
            text-align: center;
            padding: var(--spacing-md) 0;
        }

        .balance-label {
            font-size: 0.875rem;
            color: var(--text-tertiary);
            margin-bottom: var(--spacing-xs);
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .income-expense {
            display: flex;
            gap: var(--spacing-md);
        }

        .ie-item {
            flex: 1;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-md);
            border-radius: var(--radius-lg);
            background: var(--bg-secondary);
        }

        .ie-item.income {
            border-left: 3px solid var(--accent-green);
        }

        .ie-item.expense {
            border-left: 3px solid var(--accent-red);
        }

        .ie-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .ie-item.income .ie-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
        }

        .ie-item.expense .ie-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
        }

        .ie-details {
            flex: 1;
        }

        .ie-label {
            font-size: 0.75rem;
            color: var(--text-tertiary);
            margin-bottom: 0.25rem;
        }

        .ie-amount {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .chart-placeholder {
            display: flex;
            align-items: flex-end;
            gap: var(--spacing-xs);
            height: 120px;
            padding: var(--spacing-md) 0;
        }

        .chart-bar {
            flex: 1;
            background: var(--gradient-primary);
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            min-height: 20px;
            opacity: 0.8;
            transition: opacity var(--transition-fast), transform var(--transition-fast);
        }

        .chart-bar:hover {
            opacity: 1;
            transform: scaleY(1.05);
        }

        /* Features Section */
        .features {
            padding: var(--spacing-3xl) var(--spacing-lg);
            position: relative;
        }

        .section-header {
            text-align: center;
            max-width: 640px;
            margin: 0 auto var(--spacing-3xl);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            letter-spacing: -0.02em;
            text-shadow: 0 2px 20px rgba(139, 92, 246, 0.3);
        }

        .section-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        .features-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: var(--spacing-lg);
        }

        .feature-card {
            padding: var(--spacing-lg);
            border-radius: var(--radius-xl);
            background: var(--bg-primary);
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all var(--transition-base);
            cursor: pointer;
            backdrop-filter: blur(20px);
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -5px rgba(139, 92, 246, 0.4);
            border-color: rgba(139, 92, 246, 0.5);
        }

        .feature-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-lg);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: var(--spacing-md);
            box-shadow: 0 8px 20px -5px rgba(139, 92, 246, 0.5);
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta {
            padding: var(--spacing-3xl) var(--spacing-lg);
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            letter-spacing: -0.02em;
            text-shadow: 0 2px 20px rgba(139, 92, 246, 0.3);
        }

        .cta-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }

        .cta-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: rgba(15, 12, 41, 0.95);
            color: var(--gray-300);
            padding: var(--spacing-3xl) var(--spacing-lg) var(--spacing-lg);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(139, 92, 246, 0.2);
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .footer-logo-container {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            margin-bottom: var(--spacing-sm);
        }

        .footer-logo-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px -5px rgba(139, 92, 246, 0.5);
        }

        .footer-logo-icon svg {
            width: 20px;
            height: 20px;
            color: white;
        }

        .footer-logo-text {
            font-weight: 800;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .footer-description {
            color: var(--gray-400);
            line-height: 1.6;
            max-width: 280px;
        }

        .footer-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            font-size: 0.9375rem;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color var(--transition-fast);
        }

        .footer-links a:hover {
            color: var(--text-primary);
        }

        .footer-bottom {
            max-width: 1280px;
            margin: 0 auto;
            padding-top: var(--spacing-lg);
            border-top: 1px solid rgba(139, 92, 246, 0.2);
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: var(--spacing-md);
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-2xl);
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-2xl), 0 0 60px rgba(139, 92, 246, 0.4);
            border: 1px solid rgba(139, 92, 246, 0.3);
            animation: slideUp 0.3s ease-out;
            /* Hide scrollbar but keep functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .modal-content::-webkit-scrollbar {
            display: none;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-lg);
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-tertiary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--text-primary);
        }

        /* Alert Styles */
        .alert {
            display: none;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin: var(--spacing-lg);
            margin-bottom: 0;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert.active {
            display: flex;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Form Styles */
        .report-form {
            padding: var(--spacing-lg);
        }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .required {
            color: var(--accent-red);
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            border: 2px solid rgba(139, 92, 246, 0.2);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.9375rem;
            font-family: inherit;
            transition: all var(--transition-fast);
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid rgba(139, 92, 246, 0.2);
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-cancel:hover {
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: var(--spacing-xl);
            }
            
            .hero-visual {
                order: -1;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero {
                min-height: auto;
                padding: var(--spacing-xl) var(--spacing-sm);
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-description {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .nav-content {
                padding: 0 var(--spacing-sm);
            }
            
            .nav-actions {
                gap: var(--spacing-xs);
            }
            
            .btn-primary,
            .btn-secondary,
            .btn-outline {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            
            .btn-primary.btn-large,
            .btn-outline.btn-large {
                padding: 0.75rem 1.5rem;
                font-size: 0.9375rem;
            }
            
            .features,
            .cta {
                padding: var(--spacing-xl) var(--spacing-sm);
            }
        }

        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Selection */
        ::selection {
            background: rgba(139, 92, 246, 0.3);
            color: var(--text-primary);
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
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="container">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="nav-content">
                <a href="#" class="logo">
                    <div class="logo-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9"></path>
                        </svg>
                    </div>
                    <span class="logo-text">BudgetTracker</span>
                </a>
                <ul class="nav-links">
                    <li><a href="#" class="active">Home</a></li>
                    <li><button class="nav-link-btn" onclick="navigateTo('dashboard')">Dashboard</button></li>
                    <li><button class="nav-link-btn" onclick="navigateTo('transactions')">Transactions</button></li>
                    <li><button class="nav-link-btn" onclick="openReportModal()">Reports</button></li>
                    <li><a href="#">Settings</a></li>
                </ul>
                <div class="nav-actions">
                    <button class="theme-toggle" id="themeToggle">
                        <svg id="sunIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg id="moonIcon" style="display: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                    <button class="btn-secondary" onclick="window.location.href='signup.php'">Sign Up</button>
                    <button class="btn-primary" onclick="window.location.href='login.php'">Login</button>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">
                        Take Control of Your
                        <span class="gradient-text">Finances</span>
                    </h1>
                    <p class="hero-description">
                        Track your income, expenses, and savings with our intuitive budget tracker. 
                        Make smarter financial decisions and achieve your goals faster.
                    </p>
                    <div class="hero-actions">
                        <button class="btn-primary btn-large" onclick="window.location.href='login.php'">Start Free Trial</button>
                        <button class="btn-outline btn-large">Watch Demo</button>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-number">50K+</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">$2M+</div>
                            <div class="stat-label">Tracked</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">4.9★</div>
                            <div class="stat-label">Rating</div>
                        </div>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="dashboard-preview">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <div class="card-title">Monthly Overview</div>
                                <div class="card-menu">⋯</div>
                            </div>
                            <div class="card-content">
                                <div class="balance-display">
                                    <div class="balance-label">Total Balance</div>
                                    <div class="balance-amount">$12,450.00</div>
                                </div>
                                <div class="income-expense">
                                    <div class="ie-item income">
                                        <div class="ie-icon">↑</div>
                                        <div class="ie-details">
                                            <div class="ie-label">Income</div>
                                            <div class="ie-amount">$8,500</div>
                                        </div>
                                    </div>
                                    <div class="ie-item expense">
                                        <div class="ie-icon">↓</div>
                                        <div class="ie-details">
                                            <div class="ie-label">Expenses</div>
                                            <div class="ie-amount">$3,200</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="chart-placeholder">
                                    <div class="chart-bar" style="height: 60%"></div>
                                    <div class="chart-bar" style="height: 80%"></div>
                                    <div class="chart-bar" style="height: 45%"></div>
                                    <div class="chart-bar" style="height: 90%"></div>
                                    <div class="chart-bar" style="height: 70%"></div>
                                    <div class="chart-bar" style="height: 55%"></div>
                                    <div class="chart-bar" style="height: 85%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features">
            <div class="section-header">
                <h2 class="section-title">Why Choose BudgetTracker?</h2>
                <p class="section-description">Everything you need to manage your finances effectively</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Smart Categorization</h3>
                    <p class="feature-description">Automatically categorize your transactions and get insights into your spending patterns.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Budget Planning</h3>
                    <p class="feature-description">Set monthly budgets and track your progress with visual indicators and alerts.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M9 11L12 14L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Goal Tracking</h3>
                    <p class="feature-description">Set financial goals and monitor your progress with detailed analytics and reports.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M3 9H21" stroke="currentColor" stroke-width="2"/>
                            <path d="M9 3V21" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Detailed Reports</h3>
                    <p class="feature-description">Generate comprehensive reports and visualize your financial data with beautiful charts.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Real-time Sync</h3>
                    <p class="feature-description">Access your budget data from any device with real-time synchronization across platforms.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="12" cy="16" r="1" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">Secure & Private</h3>
                    <p class="feature-description">Your financial data is encrypted and secure. We never share your information with third parties.</p>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta">
            <div class="cta-content">
                <h2 class="cta-title">Ready to Transform Your Finances?</h2>
                <p class="cta-description">Join thousands of users who are already taking control of their budget</p>
                <div class="cta-actions">
                    <button class="btn-primary btn-large" onclick="window.location.href='login.php'">Get Started Free</button>
                    <button class="btn-outline btn-large">Learn More</button>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo-container">
                        <div class="footer-logo-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9"></path>
                            </svg>
                        </div>
                        <span class="footer-logo-text">BudgetTracker</span>
                    </div>
                    <p class="footer-description">Your trusted partner in financial management and budget tracking.</p>
                </div>
                <div class="footer-section">
                    <h4 class="footer-title">Product</h4>
                    <ul class="footer-links">
                        <li><a href="#">Features</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">Updates</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4 class="footer-title">Company</h4>
                    <ul class="footer-links">
                        <li><a href="#">About</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4 class="footer-title">Legal</h4>
                    <ul class="footer-links">
                        <li><a href="#">Privacy</a></li>
                        <li><a href="#">Terms</a></li>
                        <li><a href="#">Security</a></li>
                        <li><a href="#">Cookies</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 BudgetTracker. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Report Modal -->
    <div class="modal-overlay" id="reportModal" onclick="closeModalOnOverlay(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 class="modal-title">Submit a Report</h2>
                <button class="modal-close" onclick="closeReportModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="alert alert-success" id="successAlert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Report submitted successfully! We'll get back to you soon.</span>
            </div>

            <div class="alert alert-error" id="errorAlert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please fill in all required fields.</span>
            </div>

            <form class="report-form" id="reportForm" onsubmit="submitReport(event)">
                <div class="form-group">
                    <label for="username" class="form-label">
                        Username <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        placeholder="Enter your username"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        Email Address <span class="required">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="your.email@example.com"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="subject" class="form-label">
                        Report Type
                    </label>
                    <select
                        id="subject"
                        name="subject"
                        class="form-input"
                    >
                        <option value="">Select a category</option>
                        <option value="bug">Bug Report</option>
                        <option value="feature">Feature Request</option>
                        <option value="security">Security Issue</option>
                        <option value="account">Account Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message" class="form-label">
                        Message <span class="required">*</span>
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        class="form-textarea"
                        placeholder="Please describe your issue or feedback in detail..."
                        rows="5"
                        required
                    ></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="closeReportModal()" class="btn-cancel">
                        Cancel
                    </button>
                    <button type="submit" class="btn-submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <span>Submit Report</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        const html = document.documentElement;

        // Check for saved theme or default to 'dark'
        const currentTheme = localStorage.getItem('theme') || 'dark';
        html.setAttribute('data-theme', currentTheme);

        // Update icon based on current theme
        if (currentTheme === 'dark') {
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }

        themeToggle.addEventListener('click', () => {
            const theme = html.getAttribute('data-theme');
            const newTheme = theme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Toggle icons
            if (newTheme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        });

        // Navigation Functions
        function navigateTo(page) {
            if (page === 'dashboard' || page === 'transactions') {
                window.location.href = 'login.php';
            }
        }

        // Modal Functions
        function openReportModal() {
            document.getElementById('reportModal').classList.add('active');
        }

        function closeReportModal() {
            document.getElementById('reportModal').classList.remove('active');
            document.getElementById('reportForm').reset();
            document.getElementById('successAlert').classList.remove('active');
            document.getElementById('errorAlert').classList.remove('active');
        }

        function closeModalOnOverlay(event) {
            if (event.target === event.currentTarget) {
                closeReportModal();
            }
        }

        // Form Submission
        function submitReport(event) {
            event.preventDefault();
            
            const form = event.target;
            const username = form.username.value;
            const email = form.email.value;
            const subject = form.subject.value;
            const message = form.message.value;
            
            if (username && email && message) {
                fetch('submit_report.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, email, subject, message })
                })
                .then(r => r.json())
                .then(out => {
                    if (!out || !out.success) {
                        document.getElementById('successAlert').classList.remove('active');
                        document.getElementById('errorAlert').classList.add('active');
                        setTimeout(() => {
                            document.getElementById('errorAlert').classList.remove('active');
                        }, 3000);
                        return;
                    }

                    // Show success alert
                    document.getElementById('errorAlert').classList.remove('active');
                    document.getElementById('successAlert').classList.add('active');
                    
                    // Reset form and close modal after 2 seconds
                    setTimeout(() => {
                        closeReportModal();
                    }, 2000);
                })
                .catch(() => {
                    document.getElementById('successAlert').classList.remove('active');
                    document.getElementById('errorAlert').classList.add('active');
                    setTimeout(() => {
                        document.getElementById('errorAlert').classList.remove('active');
                    }, 3000);
                });
            } else {
                // Show error alert
                document.getElementById('successAlert').classList.remove('active');
                document.getElementById('errorAlert').classList.add('active');
                
                setTimeout(() => {
                    document.getElementById('errorAlert').classList.remove('active');
                }, 3000);
            }
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Console welcome message
        console.log('%cBudget Tracker', 'font-size: 24px; font-weight: bold; background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 50%, #5b21b6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;');
        console.log('%cWelcome to Budget Tracker! 🎉', 'font-size: 14px; color: #8b5cf6;');
    </script>
</body>
</html>
