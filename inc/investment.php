<?php
/**
 * Investment Management Functions
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create new investment
 */
function hyip_create_investment($user_id, $plan_id, $amount) {
    global $wpdb;
    
    // Validate user and plan
    $hyip_user = hyip_get_user_profile($user_id);
    $plan = hyip_get_investment_plan($plan_id);
    
    if (!$hyip_user || !$plan) {
        return new WP_Error('invalid_data', 'Invalid user or plan data.');
    }
    
    // Check if user can make this investment
    if (!hyip_can_user_invest($user_id, $plan_id, $amount)) {
        return new WP_Error('investment_failed', 'Investment validation failed.');
    }
    
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Calculate daily profit
    $daily_profit = ($amount * $plan->daily_percent) / 100;
    
    // Start database transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Create investment record
        $investment_result = $wpdb->insert(
            $table_investments,
            array(
                'user_id' => $user_id,
                'plan_id' => $plan_id,
                'amount' => $amount,
                'daily_profit' => $daily_profit,
                'total_profit' => 0.00,
                'days_completed' => 0,
                'status' => 'active',
                'invest_date' => current_time('mysql'),
                'next_profit_date' => hyip_get_next_profit_date(current_time('mysql'), 0)
            )
        );
        
        if (!$investment_result) {
            throw new Exception('Failed to create investment record.');
        }
        
        $investment_id = $wpdb->insert_id;
        
        // Update user balance and total invested
        $update_result = $wpdb->update(
            $table_users,
            array(
                'balance' => $hyip_user->balance - $amount,
                'total_invested' => $hyip_user->total_invested + $amount
            ),
            array('user_id' => $user_id),
            array('%f', '%f'),
            array('%d')
        );
        
        if (!$update_result) {
            throw new Exception('Failed to update user balance.');
        }
        
        // Log transaction
        $transaction_result = $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $user_id,
                'type' => 'investment',
                'amount' => $amount,
                'description' => 'Investment in ' . $plan->name,
                'reference_id' => $investment_id,
                'status' => 'completed',
                'transaction_date' => current_time('mysql')
            )
        );
        
        if (!$transaction_result) {
            throw new Exception('Failed to log transaction.');
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Send notification
        hyip_send_investment_notification($user_id, $investment_id, $plan, $amount);
        
        // Log action
        hyip_log_user_action($user_id, 'investment_created', "Investment of $amount in {$plan->name}");
        
        return $investment_id;
        
    } catch (Exception $e) {
        // Rollback transaction
        $wpdb->query('ROLLBACK');
        return new WP_Error('investment_failed', 'Investment creation failed: ' . $e->getMessage());
    }
}


/**
 * Get investment statistics
 */
function hyip_get_investment_statistics($user_id = null) {
    global $wpdb;
    
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    $where_clause = '';
    $params = array();
    
    if ($user_id) {
        $where_clause = 'WHERE i.user_id = %d';
        $params[] = $user_id;
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.name as plan_name,
            COUNT(i.id) as total_investments,
            SUM(i.amount) as total_amount,
            SUM(i.total_profit) as total_profit,
            AVG(i.amount) as average_investment,
            SUM(CASE WHEN i.status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM $table_investments i
        LEFT JOIN $table_plans p ON i.plan_id = p.id
        $where_clause
        GROUP BY i.plan_id, p.name
        ORDER BY total_amount DESC
    ", ...$params));
}

/**
 * Cancel investment (admin only)
 */
function hyip_cancel_investment($investment_id, $admin_notes = '') {
    if (!current_user_can('manage_options')) {
        return new WP_Error('permission_denied', 'You do not have permission to cancel investments.');
    }
    
    global $wpdb;
    
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Get investment details
    $investment = $wpdb->get_row($wpdb->prepare("
        SELECT i.*, p.name as plan_name 
        FROM $table_investments i
        LEFT JOIN {$wpdb->prefix}hyip_plans p ON i.plan_id = p.id
        WHERE i.id = %d AND i.status = 'active'
    ", $investment_id));
    
    if (!$investment) {
        return new WP_Error('investment_not_found', 'Active investment not found.');
    }
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // Update investment status
        $update_result = $wpdb->update(
            $table_investments,
            array(
                'status' => 'cancelled',
                'completion_date' => current_time('mysql')
            ),
            array('id' => $investment_id)
        );
        
        if (!$update_result) {
            throw new Exception('Failed to update investment status.');
        }
        
        // Return remaining principal to user
        $wpdb->query($wpdb->prepare("
            UPDATE $table_users 
            SET balance = balance + %f 
            WHERE user_id = %d
        ", $investment->amount, $investment->user_id));
        
        // Log refund transaction
        $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $investment->user_id,
                'type' => 'deposit',
                'amount' => $investment->amount,
                'description' => 'Investment cancellation refund for ' . $investment->plan_name . '. ' . $admin_notes,
                'reference_id' => $investment_id,
                'status' => 'completed',
                'transaction_date' => current_time('mysql')
            )
        );
        
        $wpdb->query('COMMIT');
        
        // Send notification to user
        hyip_send_investment_cancellation_notification($investment->user_id, $investment, $admin_notes);
        
        // Log admin action
        hyip_log_user_action($investment->user_id, 'investment_cancelled', "Investment #{$investment_id} cancelled by admin. Notes: {$admin_notes}");
        
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('cancellation_failed', 'Investment cancellation failed: ' . $e->getMessage());
    }
}

/**
 * Send investment notification
 */
function hyip_send_investment_notification($user_id, $investment_id, $plan, $amount) {
    $user = get_user_by('ID', $user_id);
    if (!$user) return;
    
    $daily_profit = ($amount * $plan->daily_percent) / 100;
    $total_return = $amount + ($daily_profit * $plan->duration);
    
    $subject = 'Investment Confirmation - ' . get_bloginfo('name');
    $message = '
        <h2>Investment Successfully Created!</h2>
        <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
        <p>Your investment has been successfully created and is now active.</p>
        
        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;">
            <h3>Investment Details:</h3>
            <ul>
                <li><strong>Investment ID:</strong> #' . $investment_id . '</li>
                <li><strong>Plan:</strong> ' . esc_html($plan->name) . '</li>
                <li><strong>Amount:</strong> $' . number_format($amount, 2) . '</li>
                <li><strong>Daily Profit:</strong> $' . number_format($daily_profit, 2) . ' (' . $plan->daily_percent . '%)</li>
                <li><strong>Duration:</strong> ' . $plan->duration . ' days</li>
                <li><strong>Total Return:</strong> $' . number_format($total_return, 2) . '</li>
                <li><strong>Start Date:</strong> ' . date('F j, Y') . '</li>
                <li><strong>End Date:</strong> ' . date('F j, Y', strtotime('+' . $plan->duration . ' days')) . '</li>
            </ul>
        </div>
        
        <h3>What Happens Next:</h3>
        <ol>
            <li>Your daily profits will be automatically credited to your account balance every 24 hours</li>
            <li>You can track your investment progress from your dashboard</li>
            <li>After ' . $plan->duration . ' days, your original investment will be returned along with all profits</li>
            <li>You can withdraw your earnings at any time</li>
        </ol>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/dashboard') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                View Your Dashboard
            </a>
        </div>
        
        <p>Thank you for choosing ' . get_bloginfo('name') . ' for your investment needs!</p>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user_id, $subject, $message, 'investment');
}

/**
 * Send investment completion notification
 */
function hyip_send_investment_completion_notification($user_id, $investment) {
    $user = get_user_by('ID', $user_id);
    if (!$user) return;
    
    $total_earned = $investment->total_profit;
    $total_returned = $investment->amount + $total_earned;
    
    $subject = 'Investment Completed - ' . get_bloginfo('name');
    $message = '
        <h2>🎉 Investment Successfully Completed!</h2>
        <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
        <p>Congratulations! Your investment has successfully completed its full term.</p>
        
        <div style="background: #d4edda; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 1px solid #c3e6cb;">
            <h3>Completed Investment Summary:</h3>
            <ul>
                <li><strong>Investment ID:</strong> #' . $investment->id . '</li>
                <li><strong>Plan:</strong> ' . esc_html($investment->plan_name) . '</li>
                <li><strong>Original Investment:</strong> $' . number_format($investment->amount, 2) . '</li>
                <li><strong>Total Profits Earned:</strong> $' . number_format($total_earned, 2) . '</li>
                <li><strong>Total Returned:</strong> $' . number_format($total_returned, 2) . '</li>
                <li><strong>Duration:</strong> ' . $investment->days_completed . ' days</li>
                <li><strong>Completion Date:</strong> ' . date('F j, Y') . '</li>
            </ul>
        </div>
        
        <h3>Funds Available:</h3>
        <p>Both your original investment amount and all earned profits have been credited to your account balance and are available for:</p>
        <ul>
            <li>Immediate withdrawal</li>
            <li>Reinvestment in new plans</li>
            <li>Combining with other funds for larger investments</li>
        </ul>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/invest') . '" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block; margin-right: 1rem;">
                Reinvest Now
            </a>
            <a href="' . home_url('/withdraw') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                Withdraw Funds
            </a>
        </div>
        
        <p>Thank you for trusting ' . get_bloginfo('name') . ' with your investment!</p>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user_id, $subject, $message, 'investment_completed');
}

/**
 * Send investment cancellation notification
 */
function hyip_send_investment_cancellation_notification($user_id, $investment, $admin_notes) {
    $user = get_user_by('ID', $user_id);
    if (!$user) return;
    
    $subject = 'Investment Cancelled - ' . get_bloginfo('name');
    $message = '
        <h2>Investment Cancellation Notice</h2>
        <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
        <p>We are writing to inform you that your investment has been cancelled by our administration.</p>
        
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 1px solid #ffeaa7;">
            <h3>Cancelled Investment Details:</h3>
            <ul>
                <li><strong>Investment ID:</strong> #' . $investment->id . '</li>
                <li><strong>Plan:</strong> ' . esc_html($investment->plan_name) . '</li>
                <li><strong>Original Amount:</strong> $' . number_format($investment->amount, 2) . '</li>
                <li><strong>Days Completed:</strong> ' . $investment->days_completed . '</li>
                <li><strong>Profits Earned:</strong> $' . number_format($investment->total_profit, 2) . '</li>
                <li><strong>Cancellation Date:</strong> ' . date('F j, Y') . '</li>
            </ul>
        </div>
        
        ' . (!empty($admin_notes) ? '<div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            <h4>Admin Notes:</h4>
            <p>' . esc_html($admin_notes) . '</p>
        </div>' : '') . '
        
        <h3>Refund Information:</h3>
        <p>Your original investment amount of <strong>$' . number_format($investment->amount, 2) . '</strong> has been refunded to your account balance. Any profits already earned will remain in your account.</p>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/dashboard') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                View Your Dashboard
            </a>
        </div>
        
        <p>If you have any questions about this cancellation, please contact our support team.</p>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user_id, $subject, $message, 'investment_cancelled');
}

/**
 * AJAX handler for investment creation
 */
function hyip_ajax_create_investment() {
    check_ajax_referer('hyip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }
    
    $user_id = get_current_user_id();
    $plan_id = intval($_POST['plan_id']);
    $amount = floatval($_POST['amount']);
    
    $result = hyip_create_investment($user_id, $plan_id, $amount);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'investment_id' => $result,
            'message' => 'Investment created successfully!'
        ));
    }
}
add_action('wp_ajax_hyip_create_investment', 'hyip_ajax_create_investment');

/**
 * AJAX handler for processing daily profits (admin only)
 */
function hyip_ajax_process_profits() {
    check_ajax_referer('hyip_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $processed = hyip_process_daily_profits();
    
    wp_send_json_success(array(
        'processed' => $processed,
        'message' => "Processed profits for $processed investments."
    ));
}
add_action('wp_ajax_hyip_process_profits', 'hyip_ajax_process_profits');
