<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #a855f7;
            --primary-dark: #9333ea;
            --secondary: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --bg-gradient: linear-gradient(135deg, #faf9fc 0%, #f5f3ff 50%, #ede9fe 100%);
        }

        body.dark-mode {
            --sidebar-bg: #0f0628;
            --card-bg: rgba(15, 6, 40, 0.95);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --border: rgba(139, 92, 246, 0.25);
            --bg-gradient: linear-gradient(135deg, #0a0118 0%, #1a0b3e 50%, #0f0628 100%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 50%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 256px;
            background: var(--sidebar-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 1.5rem;
            z-index: 50;
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
            transition: all 0.3s ease;
        }

        body.dark-mode .logo-icon {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.6);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        body.dark-mode .logo-text {
            background: linear-gradient(135deg, #c084fc 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(168, 85, 247, 0.1);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .main-content {
            margin-left: 256px;
            flex: 1;
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        body.dark-mode .top-bar {
            background: rgba(15, 6, 40, 0.8);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(168, 85, 247, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: rgba(168, 85, 247, 0.1);
            border-color: var(--primary);
        }

        .btn-small {
            padding: 0.375rem 0.875rem;
            font-size: 0.875rem;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.5);
        }

        .theme-toggle {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .content-area {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .page-description {
            color: var(--text-secondary);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .notification-stats {
            display: flex;
            gap: 2rem;
            font-size: 0.875rem;
        }

        .notification-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-unread {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .badge-read {
            background: rgba(168, 85, 247, 0.1);
            color: var(--primary);
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-card {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-card.unread {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.05);
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.2);
        }

        .notification-content {
            display: flex;
            gap: 1rem;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .notification-icon.transaction {
            background: rgba(16, 185, 129, 0.1);
        }

        .notification-icon.wallet {
            background: rgba(59, 130, 246, 0.1);
        }

        .notification-icon.category {
            background: rgba(245, 158, 11, 0.1);
        }

        .notification-icon.budget {
            background: rgba(168, 85, 247, 0.1);
        }

        .notification-icon.bill {
            background: rgba(236, 72, 153, 0.1);
        }

        .notification-body {
            flex: 1;
        }

        .notification-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .notification-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .type-transaction {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .type-wallet {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .type-category {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .type-budget {
            background: rgba(168, 85, 247, 0.1);
            color: var(--primary);
        }

        .type-bill {
            background: rgba(236, 72, 153, 0.1);
            color: var(--secondary);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--card-bg);
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            color: white;
            border-color: transparent;
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }

            .notification-stats {
                flex-direction: column;
                gap: 0.5rem;
            }

            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 7C3 5.89543 3.89543 5 5 5H19C20.1046 5 21 5.89543 21 7V17C21 18.1046 20.1046 19 19 19H5C3.89543 19 3 18.1046 3 17V7Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 9H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 9H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 13H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 13H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="logo-text">BudgetTracker</div>
            </div>
            
            <ul class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    🏠 Home
                </a>
                <a href="category.php" class="nav-item">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item">
                    📝 Transactions
                </a>
                <a href="analytics.php" class="nav-item">
                    📊 Analytics
                </a>
                <a href="bill.php" class="nav-item">
                    📋 Bills
                </a>
                <a href="notification.php" class="nav-item active">
                    🔔 Notifications
                </a>
                <a href="login.php" class="nav-item" style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                    🚪 Logout
                </a>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-header">
                    <h1 class="page-title">Notifications</h1>
                </div>
                <div class="top-bar-actions">
                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <a href="dashboard.php" class="btn btn-primary btn-small" style="text-decoration: none;">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="notification-header">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Your Notifications</h2>
                        <div class="notification-stats">
                            <div class="notification-stat">
                                <span>Total:</span>
                                <span class="notification-badge badge-read" id="totalCount">0</span>
                            </div>
                            <div class="notification-stat">
                                <span>Unread:</span>
                                <span class="notification-badge badge-unread" id="unreadCount">0</span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn btn-primary btn-small" onclick="markAllAsRead()">
                            ✓ Mark All as Read
                        </button>
                        <button class="btn btn-danger btn-small" onclick="clearAllNotifications()">
                            🗑️ Clear All
                        </button>
                    </div>
                </div>

                <div class="filter-tabs">
                    <button class="filter-tab active" onclick="filterNotifications('all', this)">All</button>
                    <button class="filter-tab" onclick="filterNotifications('transaction', this)">Transactions</button>
                    <button class="filter-tab" onclick="filterNotifications('wallet', this)">Wallets</button>
                    <button class="filter-tab" onclick="filterNotifications('category', this)">Categories</button>
                    <button class="filter-tab" onclick="filterNotifications('budget', this)">Budgets</button>
                    <button class="filter-tab" onclick="filterNotifications('bill', this)">Bills</button>
                </div>

                <div class="notifications-list" id="notificationsList"></div>
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';

        function init() {
            loadTheme();
            renderNotifications();
            updateStats();
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('themeIcon');
            icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        let notificationsCache = [];

        function normalizeNotificationGroup(rawType) {
            const t = String(rawType || 'general').toLowerCase();
            if (t === 'transaction' || t === 'wallet' || t === 'category' || t === 'budget' || t === 'bill' || t === 'general') {
                return t;
            }
            if (t.startsWith('transaction') || t.includes('txn')) return 'transaction';
            if (t.startsWith('wallet') || t.includes('wallet')) return 'wallet';
            if (t.startsWith('category') || t.includes('category')) return 'category';
            if (t.startsWith('budget') || t.includes('budget')) return 'budget';
            if (t.startsWith('bill') || t.includes('bill')) return 'bill';
            return 'general';
        }

        async function loadNotifications() {
            try {
                const res = await fetch('get_notifications.php', { credentials: 'same-origin' });
                if (!res.ok) return [];
                const data = await res.json();
                const list = data.notifications || [];
                // Normalize types so filters (Transactions/Wallets/etc) work even if DB stores detailed types
                notificationsCache = list.map(n => {
                    const group = normalizeNotificationGroup(n.type);
                    return {
                        ...n,
                        group,
                        raw_type: n.type
                    };
                });
                return notificationsCache;
            } catch (e) {
                console.error('Failed to load notifications:', e);
                return [];
            }
        }

        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffInSeconds = Math.floor((now - time) / 1000);

            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;
            
            return time.toLocaleDateString();
        }

        function renderNotifications() {
            const notifications = notificationsCache;
            const container = document.getElementById('notificationsList');

            let filteredNotifications = notifications;
            if (currentFilter !== 'all') {
                filteredNotifications = notifications.filter(n => (n.group || normalizeNotificationGroup(n.type)) === currentFilter);
            }

            if (filteredNotifications.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔔</div>
                        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">No Notifications</h3>
                        <p>${currentFilter === 'all' ? 'You have no notifications yet' : `No ${currentFilter} notifications`}</p>
                    </div>
                `;
                return;
            }

            const iconMap = {
                transaction: '💸',
                wallet: '👛',
                category: '📂',
                bill: '📅',
                budget: '🎯',
                general: '🔔'
            };

            container.innerHTML = filteredNotifications.map(notification => `
                <div class="notification-card ${notification.is_read ? '' : 'unread'}" onclick="markAsRead(${notification.id})">
                    <div class="notification-content">
                        <div class="notification-icon ${(notification.group || normalizeNotificationGroup(notification.type))}">
                            ${iconMap[(notification.group || normalizeNotificationGroup(notification.type))] || '🔔'}
                        </div>
                        <div class="notification-body">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-meta">
                                <div class="notification-time">
                                    🕐 ${formatTimeAgo(notification.created_at)}
                                </div>
                                <span>•</span>
                                <div class="notification-type-badge type-${(notification.group || normalizeNotificationGroup(notification.type))}">
                                    ${(notification.group || normalizeNotificationGroup(notification.type))}
                                </div>
                                ${!notification.is_read ? '<span style="color: var(--primary); font-weight: 700;">• New</span>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateStats() {
            const notifications = notificationsCache;
            const unreadCount = notifications.filter(n => !n.is_read).length;
            
            document.getElementById('totalCount').textContent = notifications.length;
            document.getElementById('unreadCount').textContent = unreadCount;
        }

        async function persistReadStatus(payload) {
            const body = new URLSearchParams(payload);
            const res = await fetch('mark_notification_read.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body
            });
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();
            if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Server error');
            return data;
        }

        async function refreshFromServer() {
            await loadNotifications();
            renderNotifications();
            updateStats();
        }

        async function markAsRead(notificationId) {
            const target = notificationsCache.find(n => n.id === notificationId);
            if (!target || target.is_read) return;

            // Optimistic UI update
            notificationsCache = notificationsCache.map(n =>
                n.id === notificationId ? { ...n, is_read: true } : n
            );
            renderNotifications();
            updateStats();

            try {
                await persistReadStatus({ id: String(notificationId) });
            } catch (e) {
                console.error('Failed to persist read status:', e);
                await refreshFromServer();
            }
        }

        async function markAllAsRead() {
            // Optimistic UI update
            notificationsCache = notificationsCache.map(n => ({ ...n, is_read: true }));
            renderNotifications();
            updateStats();

            try {
                await persistReadStatus({ all: '1' });
            } catch (e) {
                console.error('Failed to persist mark-all:', e);
                await refreshFromServer();
            }
        }

        async function clearAllNotifications() {
            // Current UX: "Clear" means mark all as read
            await markAllAsRead();
        }

        function filterNotifications(type, el) {
            currentFilter = type;
            
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            if (el) {
                el.classList.add('active');
            }
            
            renderNotifications();
        }

        (async function initPage() {
            await loadNotifications();
            renderNotifications();
            updateStats();
        })();
    </script>
</body>
</html>
