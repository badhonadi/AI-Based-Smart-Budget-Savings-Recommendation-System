<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$roles = $_SESSION['roles'] ?? [];
$isSupport = in_array('support', $roles, true) || !empty($_SESSION['is_support']);
if (!$isSupport) {
    header('Location: dashboard.php');
    exit;
}

$support_user_id = (int)$_SESSION['user_id'];

function redirect_with(string $query): void {
    header("Location: support_dashboard.php?$query");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $report_id = (int)($_POST['report_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'Open'));
    $solution = trim((string)($_POST['solution'] ?? ''));

    $allowedStatus = ['Open','In Progress','Resolved'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'Open';
    }

    if ($report_id <= 0) {
        redirect_with('error=1');
    }

    $handled_at = ($status === 'Resolved' || $solution !== '') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE support_reports SET status = ?, solution = ?, handled_by = ?, handled_at = ? WHERE report_id = ?");
    $stmt->bind_param('ssisi', $status, $solution, $support_user_id, $handled_at, $report_id);

    if ($stmt->execute()) {
        redirect_with('saved=1');
    }

    redirect_with('error=1');
}

// Load reports
$filter = trim((string)($_GET['status'] ?? ''));
$allowedFilter = ['Open','In Progress','Resolved'];
$where = '';
$params = [];
$types = '';

if ($filter !== '' && in_array($filter, $allowedFilter, true)) {
    $where = 'WHERE sr.status = ?';
    $params[] = $filter;
    $types .= 's';
}

$sql = "
    SELECT
        sr.report_id,
        sr.user_id,
        sr.username,
        sr.email,
        sr.report_type,
        sr.message,
        sr.status,
        sr.solution,
        sr.created_at,
        sr.updated_at,
        u.first_name,
        u.last_name,
        u.username AS app_username
    FROM support_reports sr
    LEFT JOIN users u ON u.user_id = sr.user_id
    $where
    ORDER BY sr.created_at DESC
    LIMIT 300
";

if ($where) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

$reports = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reports[] = $row;
    }
}

function badge_class(string $status): string {
    if ($status === 'Resolved') return 'resolved';
    if ($status === 'In Progress') return 'progress';
    return 'open';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Support Dashboard - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        }
        .app-container { display: flex; min-height: 100vh; }
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
        .logo-section { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
            transition: all 0.3s ease;
        }
        body.dark-mode .logo-icon {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.6);
        }
        .logo-icon svg { width: 24px; height: 24px; }
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
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item {
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }
        .nav-item.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }
        .nav-item:hover { background: rgba(168, 85, 247, 0.1); color: var(--primary); }

        .main-content { margin-left: 256px; flex: 1; min-height: 100vh; }
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
            gap: 1rem;
        }
        body.dark-mode .top-bar { background: rgba(15, 6, 40, 0.8); }
        .search-bar { flex: 1; max-width: 640px; }
        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }
        .content-area { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 2rem; font-weight: 900; }
        .page-subtitle { color: var(--text-secondary); margin-top: 0.5rem; }

        .filters { display: flex; gap: 0.75rem; margin-top: 1.25rem; }
        .select {
            padding: 0.6rem 0.9rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 700;
        }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; }
        .card {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 1.25rem;
        }
        .card:hover { border-color: var(--primary); box-shadow: 0 8px 24px rgba(168, 85, 247, 0.15); }
        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .meta { color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem; }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 900;
            border: 1px solid var(--border);
            white-space: nowrap;
        }
        .badge.open { background: rgba(245, 158, 11, 0.12); color: var(--warning); }
        .badge.progress { background: rgba(59, 130, 246, 0.12); color: var(--info); }
        .badge.resolved { background: rgba(16, 185, 129, 0.12); color: var(--success); }

        .label { display: block; font-weight: 800; margin: 0.75rem 0 0.4rem; }
        textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            min-height: 110px;
            resize: vertical;
        }
        .btn-primary {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }
        .btn-primary:hover { transform: translateY(-1px); }
        .actions { display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; margin-top: 0.9rem; }
        .small { font-size: 0.85rem; color: var(--text-secondary); }
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            border: 2px dashed var(--border);
            border-radius: 24px;
            color: var(--text-secondary);
            background: rgba(168, 85, 247, 0.03);
        }
    </style>
</head>
<body>
    <div class="app-container">
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
                <a href="support_dashboard.php" class="nav-item active">🧾 Reports</a>
                <a href="dashboard.php" class="nav-item">↩️ User Dashboard</a>
                <a href="login.php" class="nav-item" style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">🚪 Logout</a>
            </ul>
        </aside>

        <div class="main-content">
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" placeholder="Search by email, username, message..." id="search" oninput="searchReports()" />
                </div>
                <button class="btn btn-outline" onclick="toggleDarkMode()"><span id="themeIcon">🌙</span></button>
            </div>

            <div class="content-area">
                <div>
                    <div class="page-title">Support Dashboard</div>
                    <div class="page-subtitle">View user reports and send solutions</div>
                </div>

                <div class="filters">
                    <select class="select" onchange="location.href='support_dashboard.php' + (this.value ? ('?status=' + encodeURIComponent(this.value)) : '')">
                        <option value="" <?php echo $filter===''?'selected':''; ?>>All</option>
                        <option value="Open" <?php echo $filter==='Open'?'selected':''; ?>>Open</option>
                        <option value="In Progress" <?php echo $filter==='In Progress'?'selected':''; ?>>In Progress</option>
                        <option value="Resolved" <?php echo $filter==='Resolved'?'selected':''; ?>>Resolved</option>
                    </select>
                </div>

                <div id="grid" class="grid">
                    <?php if (count($reports) === 0): ?>
                        <div class="empty" style="grid-column: 1 / -1;">
                            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🧾</div>
                            <div style="font-weight: 900; font-size: 1.25rem; margin-bottom: 0.5rem;">No reports found</div>
                            <div>Reports submitted from Home will show here.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reports as $r):
                            $displayName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                            if ($displayName === '') $displayName = $r['username'] ?? '';
                            if ($displayName === '') $displayName = $r['app_username'] ?? '';
                            $email = $r['email'] ?? '';
                            $type = $r['report_type'] ?? 'other';
                            $status = $r['status'] ?? 'Open';
                        ?>
                            <div class="card" data-search="<?php echo htmlspecialchars(strtolower(($displayName ?? '') . ' ' . ($email ?? '') . ' ' . ($r['message'] ?? '') . ' ' . ($type ?? ''))); ?>">
                                <div class="row">
                                    <div>
                                        <div style="font-weight: 900; font-size: 1.05rem;"><?php echo htmlspecialchars($displayName ?: 'Unknown User'); ?></div>
                                        <div class="meta"><?php echo htmlspecialchars($email ?: ''); ?></div>
                                        <div class="meta">Type: <strong><?php echo htmlspecialchars($type); ?></strong> • Created: <strong><?php echo date('d M, Y', strtotime($r['created_at'])); ?></strong></div>
                                    </div>
                                    <div class="badge <?php echo badge_class($status); ?>"><?php echo htmlspecialchars($status); ?></div>
                                </div>

                                <div class="label">Message</div>
                                <div class="meta" style="white-space: pre-wrap; line-height: 1.5; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($r['message']); ?>
                                </div>

                                <form method="POST" action="support_dashboard.php">
                                    <input type="hidden" name="action" value="update" />
                                    <input type="hidden" name="report_id" value="<?php echo (int)$r['report_id']; ?>" />

                                    <div class="label">Status</div>
                                    <select class="select" name="status">
                                        <option value="Open" <?php echo $status==='Open'?'selected':''; ?>>Open</option>
                                        <option value="In Progress" <?php echo $status==='In Progress'?'selected':''; ?>>In Progress</option>
                                        <option value="Resolved" <?php echo $status==='Resolved'?'selected':''; ?>>Resolved</option>
                                    </select>

                                    <div class="label">Solution</div>
                                    <textarea name="solution" placeholder="Write your solution..."><?php echo htmlspecialchars($r['solution'] ?? ''); ?></textarea>

                                    <div class="actions">
                                        <div class="small">Updated: <?php echo date('d M, Y H:i', strtotime($r['updated_at'])); ?></div>
                                        <button class="btn btn-primary" type="submit">💾 Save</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
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

        function searchReports() {
            const term = (document.getElementById('search')?.value || '').toLowerCase();
            document.querySelectorAll('#grid .card').forEach(card => {
                const s = card.getAttribute('data-search') || '';
                card.style.display = (!term || s.includes(term)) ? '' : 'none';
            });
        }

        loadTheme();
    </script>
</body>
</html>
