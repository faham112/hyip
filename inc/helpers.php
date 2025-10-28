<?php
/**
 * Helper Functions for HYIP Manager Theme
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get user's HYIP profile data
 */
function hyip_get_user_profile($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    global $wpdb;
    $table_users = $wpdb->prefix . 'hyip_users';
    
    $hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
    
    // Create profile if doesn't exist
    if (!$hyip_user) {
        $wpdb->insert($table_users, array('user_id' => $user_id));
        $hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
    }
    
    return $hyip_user;
}

/**
 * Get user's active investments
 */
function hyip_get_user_investments($user_id = null, $status = 'all') {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return array();
    }
    
    global $wpdb;
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    $where_clause = "WHERE i.user_id = %d";
    $params = array($user_id);
    
    if ($status !== 'all') {
        $where_clause .= " AND i.status = %s";
        $params[] = $status;
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT i.*, p.name as plan_name, p.daily_percent, p.duration
        FROM $table_investments i
        LEFT JOIN $table_plans p ON i.plan_id = p.id
        $where_clause
        ORDER BY i.invest_date DESC
    ", ...$params));
}

/**
 * Get user's transaction history
 */
function hyip_get_user_transactions($user_id = null, $limit = 20) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return array();
    }
    
    global $wpdb;
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_transactions
        WHERE user_id = %d
        ORDER BY transaction_date DESC
        LIMIT %d
    ", $user_id, $limit));
}

/**
 * Get all investment plans
 */
function hyip_get_investment_plans($status = 'active') {
    global $wpdb;
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    if ($status === 'all') {
        return $wpdb->get_results("SELECT * FROM $table_plans ORDER BY min_amount ASC");
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_plans 
        WHERE status = %s 
        ORDER BY min_amount ASC
    ", $status));
}

/**
 * Get single investment plan
 */
function hyip_get_investment_plan($plan_id) {
    global $wpdb;
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_plans WHERE id = %d", $plan_id));
}

/**
 * Calculate investment returns
 */
function hyip_calculate_returns($amount, $daily_percent, $duration) {
    $daily_profit = ($amount * $daily_percent) / 100;
    $total_profit = $daily_profit * $duration;
    $total_return = $amount + $total_profit;
    
    return array(
        'amount' => $amount,
        'daily_profit' => $daily_profit,
        'total_profit' => $total_profit,
        'total_return' => $total_return,
        'roi' => ($total_profit / $amount) * 100
    );
}

/**
 * Format currency
 */
function hyip_format_currency($amount, $show_currency = true) {
    $formatted = number_format($amount, 2);
    return $show_currency ? '$' . $formatted : $formatted;
}

/**
 * Get user's pending withdrawals
 */
function hyip_get_user_withdrawals($user_id = null, $status = 'all') {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return array();
    }
    
    global $wpdb;
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    
    $where_clause = "WHERE user_id = %d";
    $params = array($user_id);
    
    if ($status !== 'all') {
        $where_clause .= " AND status = %s";
        $params[] = $status;
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_withdrawals
        $where_clause
        ORDER BY request_date DESC
    ", ...$params));
}

/**
 * Check if user can make withdrawal
 */
function hyip_can_user_withdraw($user_id = null, $amount = 0) {
    $hyip_user = hyip_get_user_profile($user_id);
    
    if (!$hyip_user) {
        return false;
    }
    
    $min_withdrawal = 10.00;
    
    return ($amount >= $min_withdrawal && $amount <= $hyip_user->balance);
}

/**
 * Check if user can make investment
 */
function hyip_can_user_invest($user_id = null, $plan_id = 0, $amount = 0) {
    $hyip_user = hyip_get_user_profile($user_id);
    $plan = hyip_get_investment_plan($plan_id);
    
    if (!$hyip_user || !$plan) {
        return false;
    }
    
    return ($amount >= $plan->min_amount && 
            $amount <= $plan->max_amount && 
            $amount <= $hyip_user->balance &&
            $plan->status === 'active');
}

/**
 * Log user action
 */
function hyip_log_user_action($user_id, $action, $details = '') {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'hyip_logs';
    
    // Create logs table if it doesn't exist
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            ip_address varchar(45),
            user_agent text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action)
        )
    ");
    
    $wpdb->insert($table_logs, array(
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ));
}

/**
 * Get platform statistics
 */
function hyip_get_platform_stats() {
    global $wpdb;
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    
    return array(
        'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'active'"),
        'total_invested' => $wpdb->get_var("SELECT SUM(amount) FROM $table_investments WHERE status IN ('active', 'completed')"),
        'total_earned' => $wpdb->get_var("SELECT SUM(total_earned) FROM $table_users"),
        'total_withdrawn' => $wpdb->get_var("SELECT SUM(amount) FROM $table_withdrawals WHERE status = 'approved'"),
        'active_investments' => $wpdb->get_var("SELECT COUNT(*) FROM $table_investments WHERE status = 'active'"),
        'pending_withdrawals' => $wpdb->get_var("SELECT COUNT(*) FROM $table_withdrawals WHERE status = 'pending'")
    );
}

/**
 * Generate transaction reference
 */
function hyip_generate_transaction_ref($type = 'TXN') {
    return $type . date('YmdHis') . rand(100, 999);
}

/**
 * Send notification email
 */
function hyip_send_notification($user_id, $subject, $message, $type = 'general') {
    $user = get_user_by('ID', $user_id);
    
    if (!$user) {
        return false;
    }
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $email_template = '
        <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; text-align: center;">
                <h1>HYIP Manager</h1>
            </div>
            <div style="padding: 2rem; background: #f8f9fa;">
                <h2>' . esc_html($subject) . '</h2>
                ' . wp_kses_post($message) . '
            </div>
            <div style="background: #333; color: white; padding: 1rem; text-align: center; font-size: 0.9rem;">
                <p>&copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.</p>
            </div>
        </div>
    ';
    
    return wp_mail($user->user_email, $subject, $email_template, $headers);
}

/**
 * Validate payment method details
 */
function hyip_validate_payment_details($method, $details) {
    $details = trim($details);
    
    if (empty($details)) {
        return false;
    }
    
    switch ($method) {
        case 'bitcoin':
            // Basic Bitcoin address validation
            return (strlen($details) >= 26 && strlen($details) <= 35);
            
        case 'ethereum':
            // Basic Ethereum address validation
            return (strlen($details) == 42 && substr($details, 0, 2) === '0x');
            
        case 'paypal':
            // Basic email validation
            return filter_var($details, FILTER_VALIDATE_EMAIL);
            
        default:
            return strlen($details) >= 10; // Minimum length for other methods
    }
}

/**
 * Get time ago string
 */
function hyip_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Check if user is admin
 */
function hyip_is_admin($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    return user_can($user_id, 'manage_options');
}

/**
 * Get next profit date for investment
 */
function hyip_get_next_profit_date($investment_date, $days_completed = 0) {
    $next_date = strtotime($investment_date . ' +' . ($days_completed + 1) . ' days');
    return date('Y-m-d H:i:s', $next_date);
}

/**
 * Calculate compound interest (if needed for future plans)
 */
function hyip_calculate_compound_returns($principal, $rate, $periods, $compound_frequency = 1) {
    $amount = $principal * pow((1 + $rate / $compound_frequency), $compound_frequency * $periods);
    return $amount - $principal; // Return only the interest
}

/**
 * Safe redirect function
 */
function hyip_safe_redirect($location) {
    if (!wp_safe_redirect($location)) {
        wp_redirect($location);
    }
    exit;
}