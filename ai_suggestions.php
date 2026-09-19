<?php
// Library for generating savings suggestions. When included, it MUST NOT echo or exit.
// If executed directly (as an endpoint), it will return JSON.

if (!defined('AI_SUGGESTIONS_LIB')) {
    define('AI_SUGGESTIONS_LIB', true);
}

// Only initialize session/headers when this file is the main script.
$__is_direct = isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']);
if ($__is_direct) {
    session_start();
    header('Content-Type: application/json');
    require "db.php";
    require_once "currency_util.php";
    require_once "ai_config.php";
} else {
    // When included, assume the host script already required db & currency_util.
    if (!function_exists('normalize_currency')) {
        require_once "currency_util.php";
    }
    if (!class_exists('mysqli')) {
        require "db.php";
    }
    if (file_exists(__DIR__ . '/ai_config.php')) {
        require_once "ai_config.php";
    }
}

// Helper to fetch current user id when session exists
$__user_id = null;
if (isset($_SESSION['user_id'])) {
    $__user_id = (int)$_SESSION['user_id'];
}

/**
 * Analyze a single category expense against monthly income with concise advice.
 * Assumes healthy range: 25–30% of income for the category.
 */
function analyze_category_expense(float $income, string $category, float $old_total, float $new_expense, string $month = ''): array {
    $income = max(0.0, $income);
    $old_total = max(0.0, $old_total);
    $new_expense = max(0.0, $new_expense);
    $new_total = $old_total + $new_expense;

    if ($income <= 0) {
        return [
            'success' => true,
            'status' => 'Unknown',
            'new_total' => $new_total,
            'advice' => 'Set your monthly income to get accurate guidance.',
            'should_reduce' => false,
        ];
    }

    $near_limit = $income * 0.25; // 25%
    $max_limit = $income * 0.30;  // 30%
    $ratio = $new_total / $income;
    $percent = round($ratio * 100, 1);

    if ($new_total >= $max_limit) {
        return [
            'success' => true,
            'status' => 'Over budget',
            'new_total' => $new_total,
            'advice' => "Spending in {$category} is high ({$percent}% of income). Cut back for the rest of the month.",
            'should_reduce' => true,
        ];
    }

    if ($new_total >= $near_limit) {
        return [
            'success' => true,
            'status' => 'Near limit',
            'new_total' => $new_total,
            'advice' => "{$category} spending is getting close ({$percent}% of income). Ease up to stay on track.",
            'should_reduce' => true,
        ];
    }

    return [
        'success' => true,
        'status' => 'Safe',
        'new_total' => $new_total,
        'advice' => "You're okay on {$category}. This level is within a healthy range.",
        'should_reduce' => false,
    ];
}

/**
 * Generate AI-powered savings suggestions based on user's income, expenses, and budget goals
 */
function generate_savings_suggestions(mysqli $conn, int $user_id, string $category_name = '', float $recent_expense = 0): array {
    try {
        // Get current month income
        $current_month = date('Y-m-01');
        $current_month_end = date('Y-m-d', strtotime('last day of this month'));
        
        $income_result = $conn->query(
            "SELECT SUM(amount) as total FROM transactions 
            WHERE user_id = $user_id AND transaction_type = 'Income' 
            AND DATE(transaction_time) >= '$current_month' 
            AND DATE(transaction_time) <= '$current_month_end'"
        );
        $income_data = $income_result->fetch_assoc();
        $current_income = (float)($income_data['total'] ?? 0);

        // Get current month expenses
        $expense_result = $conn->query(
            "SELECT SUM(amount) as total FROM transactions 
            WHERE user_id = $user_id AND transaction_type = 'Expense' 
            AND DATE(transaction_time) >= '$current_month' 
            AND DATE(transaction_time) <= '$current_month_end'"
        );
        $expense_data = $expense_result->fetch_assoc();
        $current_expenses = (float)($expense_data['total'] ?? 0);

        // Get expenses by category for this month
        $category_result = $conn->query(
            "SELECT c.category_name, SUM(t.amount) as total, COUNT(t.transaction_id) as count
            FROM transactions t
            JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = $user_id AND t.transaction_type = 'Expense'
            AND DATE(t.transaction_time) >= '$current_month'
            AND DATE(t.transaction_time) <= '$current_month_end'
            GROUP BY c.category_id
            ORDER BY total DESC"
        );
        
        $category_expenses = [];
        while ($row = $category_result->fetch_assoc()) {
            $category_expenses[] = [
                'name' => $row['category_name'],
                'total' => (float)$row['total'],
                'count' => (int)$row['count']
            ];
        }

        // Get active budgets
        $budget_result = $conn->query(
            "SELECT bi.budget_item_id, c.category_name, bi.amount_limit, bi.manual_progress
            FROM budgets b
            JOIN budget_items bi ON b.budget_id = bi.budget_id
            JOIN categories c ON c.category_id = bi.category_id
            WHERE b.user_id = $user_id AND b.end_date >= CURDATE()
            ORDER BY b.end_date ASC"
        );
        
        $active_budgets = [];
        while ($row = $budget_result->fetch_assoc()) {
            $active_budgets[] = [
                'id' => (int)$row['budget_item_id'],
                'category' => $row['category_name'],
                'limit' => (float)$row['amount_limit'],
                'progress' => (float)($row['manual_progress'] ?? 0)
            ];
        }

        // Generate suggestions
        $suggestions = [];
        $remaining_income = $current_income - $current_expenses;

        // Suggestion 1: Budget compliance warnings
        foreach ($active_budgets as $budget) {
            // Find category spending
            $category_spent = 0;
            foreach ($category_expenses as $cat) {
                if (stripos($cat['name'], $budget['category']) !== false) {
                    $category_spent = $cat['total'];
                    break;
                }
            }

            $budget_used = $budget['progress'] + $category_spent;
            $budget_remaining = $budget['limit'] - $budget_used;

            if ($budget_used > $budget['limit']) {
                $overspent = $budget_used - $budget['limit'];
                $suggestions[] = [
                    'type' => 'budget_exceeded',
                    'priority' => 'high',
                    'category' => $budget['category'],
                    'message' => "You've exceeded the {$budget['category']} budget by " . number_format($overspent, 2) . " BDT",
                    'recommendation' => "Reduce {$budget['category']} spending immediately to stay within budget",
                    'amount_to_save' => $overspent
                ];
            } elseif ($budget_remaining > 0 && $budget_remaining < ($budget['limit'] * 0.2)) {
                $suggestions[] = [
                    'type' => 'budget_warning',
                    'priority' => 'medium',
                    'category' => $budget['category'],
                    'message' => "Only " . number_format($budget_remaining, 2) . " BDT remaining in {$budget['category']} budget",
                    'recommendation' => "Be cautious with {$budget['category']} spending for the rest of the month",
                    'amount_to_save' => 0
                ];
            }
        }

        // Suggestion 2: High spending categories
        $avg_spending = $current_expenses / count($category_expenses) > 0 ? count($category_expenses) : 1;
        foreach ($category_expenses as $cat) {
            if ($cat['total'] > ($current_income * 0.3)) { // More than 30% of income
                $savings_potential = ($current_income * 0.25) - $cat['total']; // Target 25% max per category
                if ($savings_potential > 0) {
                    $suggestions[] = [
                        'type' => 'high_spending',
                        'priority' => 'medium',
                        'category' => $cat['name'],
                        'message' => "{$cat['name']} is consuming " . number_format(($cat['total'] / $current_income) * 100, 1) . "% of your income",
                        'recommendation' => "Reduce {$cat['name']} spending by " . number_format($savings_potential, 2) . " BDT to optimize your budget",
                        'amount_to_save' => max(0, $savings_potential)
                    ];
                }
            }
        }

        // Suggestion 3: Savings opportunity
        $savings_rate = ($remaining_income / $current_income) * 100;
        if ($savings_rate < 10 && $current_income > 0) {
            $total_savings_needed = ($current_income * 0.15) - $remaining_income; // Target 15% savings
            if ($total_savings_needed > 0) {
                $suggestions[] = [
                    'type' => 'low_savings',
                    'priority' => 'high',
                    'category' => 'Overall',
                    'message' => "Your savings rate is only " . number_format($savings_rate, 1) . "% of income",
                    'recommendation' => "You can save an additional " . number_format($total_savings_needed, 2) . " BDT this month. Look for areas to cut back.",
                    'amount_to_save' => $total_savings_needed
                ];
            }
        }

        // Suggestion 4: Recent expense analysis
        if ($recent_expense > 0 && !empty($category_name)) {
            // Get average spending in this category
            $avg_query = $conn->query(
                "SELECT AVG(amount) as avg_amount FROM transactions
                WHERE user_id = $user_id AND transaction_type = 'Expense'
                AND category_id IN (SELECT category_id FROM categories WHERE category_name LIKE '%$category_name%')
                AND DATE(transaction_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            );
            $avg_row = $avg_query->fetch_assoc();
            $avg_amount = (float)($avg_row['avg_amount'] ?? 0);

            if ($recent_expense > ($avg_amount * 1.5)) {
                $savings_amount = $recent_expense - ($avg_amount * 1.1);
                $suggestions[] = [
                    'type' => 'unusual_expense',
                    'priority' => 'low',
                    'category' => $category_name,
                    'message' => "Your recent {$category_name} expense of " . number_format($recent_expense, 2) . " BDT is higher than usual",
                    'recommendation' => "Your typical {$category_name} spending is " . number_format($avg_amount, 2) . " BDT. You can save " . number_format(max(0, $savings_amount), 2) . " BDT by matching typical spending.",
                    'amount_to_save' => max(0, $savings_amount)
                ];
            }
        }

        // Calculate total potential savings
        $total_savings_potential = 0;
        foreach ($suggestions as $sug) {
            $total_savings_potential += $sug['amount_to_save'];
        }

        // Sort by priority
        usort($suggestions, function($a, $b) {
            $priority_map = ['high' => 1, 'medium' => 2, 'low' => 3];
            return $priority_map[$a['priority']] - $priority_map[$b['priority']];
        });

        $result = [
            'success' => true,
            'current_income' => $current_income,
            'current_expenses' => $current_expenses,
            'remaining' => $remaining_income,
            'savings_rate' => $current_income > 0 ? ($remaining_income / $current_income) * 100 : 0,
            'total_savings_potential' => $total_savings_potential,
            'suggestions' => array_slice($suggestions, 0, 5), // Top 5 suggestions
            'category_breakdown' => $category_expenses,
            'active_budgets' => $active_budgets
        ];

        // Optional: augment with Gemini AI text if enabled
        if (defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI) {
            try {
                $assistant = include __DIR__ . '/gemini_ai.php';
                if ($assistant && $assistant->isConfigured()) {
                    $aiText = $assistant->generateSmartRecommendations($result);
                    if (!empty($aiText)) {
                        $result['ai_text'] = $aiText;
                    }
                }
            } catch (Throwable $e) {
                error_log('Gemini augmentation failed: ' . $e->getMessage());
            }
        }

        return $result;
    } catch (Exception $e) {
        error_log("AI Suggestions error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to generate suggestions'
        ];
    }
}

// If executed directly, act as an endpoint
if ($__is_direct) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "error" => "User not logged in"]);
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $category_name = isset($data['category']) ? (string)$data['category'] : '';
        $recent_expense = isset($data['amount']) ? (float)$data['amount'] : 0;

        // Lightweight assistant mode if all fields are provided
        $income = isset($data['income']) ? (float)$data['income'] : null;
        $old_total = isset($data['old_category_total']) ? (float)$data['old_category_total'] : null;
        $month = isset($data['month']) ? (string)$data['month'] : '';

        if ($income !== null && $old_total !== null && $recent_expense !== null) {
            $resp = analyze_category_expense($income, $category_name ?: 'This category', $old_total, $recent_expense, $month);
            echo json_encode($resp);
            exit;
        }

        $suggestions = generate_savings_suggestions($conn, $user_id, $category_name, $recent_expense);
        echo json_encode($suggestions);
        exit;
    } else {
        $suggestions = generate_savings_suggestions($conn, $user_id);
        echo json_encode($suggestions);
        exit;
    }
}
