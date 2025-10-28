<?php
/**
 * Withdrawal Management Functions
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create withdrawal request
 */
function hyip_create_withdrawal($user_id, $amount, $payment_method, $payment_details) {
    global $wpdb;
    
    // Validate user and amount
    $hyip_user = hyip_get_user_profile($user_id);
    if (!$hyip_user) {
        return new WP_Error('invalid_user', 'Invalid user data.');
    }
    
    $min_withdrawal = 10.00;
    
    if (!hyip_can_user_withdraw($user_id, $amount)) {
        return new WP_Error('withdrawal_failed', 'Withdrawal validation failed.');
    }
    
    // Validate payment details
    if (!hyip_validate_payment_details($payment_method, $payment_details)) {
        return new WP_Error('invalid_payment_details', 'Invalid payment details provided.');
    }
    
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Start database transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Create withdrawal request
        $withdrawal_result = $wpdb->insert(
            $table_withdrawals,
            array(
                'user_id' => $user_id,
                'amount' => $amount,
                'payment_method' => sanitize_text_field($payment_method),
                'payment_details' => sanitize_textarea_field($payment_details),
                'status' => 'pending',
                'request_date' => current_time('mysql')
            )
        );
        
        if (!$withdrawal_result) {
            throw new Exception('Failed to create withdrawal request.');
        }
        
        $withdrawal_id = $wpdb->insert_id;
        
        // Update user balance (deduct withdrawal amount)
        $update_result = $wpdb->update(
            $table_users,
            array('balance' => $hyip_user->balance - $amount),
            array('user_id' => $user_id),
            array('%f'),
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
                'type' => 'withdrawal',
                'amount' => $amount,
                'description' => 'Withdrawal request via ' . $payment_method,
                'reference_id' => $withdrawal_id,
                'status' => 'pending',
                'transaction_date' => current_time('mysql')
            )
        );
        
        if (!$transaction_result) {
            throw new Exception('Failed to log transaction.');
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Send notification
        hyip_send_withdrawal_notification($user_id, $withdrawal_id, $amount, $payment_method);
        
        // Notify admin
        hyip_notify_admin_new_withdrawal($withdrawal_id);
        
        // Log action
        hyip_log_user_action($user_id, 'withdrawal_requested', "Withdrawal of $amount via {$payment_method}");
        
        return $withdrawal_id;
        
    } catch (Exception $e) {
        // Rollback transaction
        $wpdb->query('ROLLBACK');
        return new WP_Error('withdrawal_failed', 'Withdrawal creation failed: ' . $e->getMessage());
    }
}

/**
 * Process withdrawal (approve/reject)
 */
function hyip_process_withdrawal($withdrawal_id, $status, $admin_notes = '', $transaction_id = '') {
    if (!current_user_can('manage_options')) {
        return new WP_Error('permission_denied', 'You do not have permission to process withdrawals.');
    }
    
    if (!in_array($status, array('approved', 'rejected'))) {
        return new WP_Error('invalid_status', 'Invalid withdrawal status.');
    }
    
    global $wpdb;
    
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Get withdrawal details
    $withdrawal = $wpdb->get_row($wpdb->prepare("
        SELECT w.*, u.display_name, u.user_email 
        FROM $table_withdrawals w
        LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
        WHERE w.id = %d AND w.status = 'pending'
    ", $withdrawal_id));
    
    if (!$withdrawal) {
        return new WP_Error('withdrawal_not_found', 'Pending withdrawal not found.');
    }
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // Update withdrawal status
        $update_data = array(
            'status' => $status,
            'processed_date' => current_time('mysql'),
            'admin_notes' => sanitize_textarea_field($admin_notes)
        );
        
        if (!empty($transaction_id)) {
            $update_data['transaction_id'] = sanitize_text_field($transaction_id);
        }
        
        $update_result = $wpdb->update(
            $table_withdrawals,
            $update_data,
            array('id' => $withdrawal_id)
        );
        
        if (!$update_result) {
            throw new Exception('Failed to update withdrawal status.');
        }
        
        // Handle rejection - return money to user balance
        if ($status === 'rejected') {
            $balance_update = $wpdb->query($wpdb->prepare("
                UPDATE $table_users 
                SET balance = balance + %f 
                WHERE user_id = %d
            ", $withdrawal->amount, $withdrawal->user_id));
            
            if (!$balance_update) {
                throw new Exception('Failed to refund user balance.');
            }
            
            // Update transaction status
            $wpdb->update(
                $table_transactions,
                array('status' => 'failed'),
                array('reference_id' => $withdrawal_id, 'type' => 'withdrawal')
            );
        } else {
            // Handle approval
            // Update user total withdrawn
            $wpdb->query($wpdb->prepare("
                UPDATE $table_users 
                SET total_withdrawn = total_withdrawn + %f 
                WHERE user_id = %d
            ", $withdrawal->amount, $withdrawal->user_id));
            
            // Update transaction status
            $wpdb->update(
                $table_transactions,
                array(
                    'status' => 'completed',
                    'description' => 'Withdrawal approved via ' . $withdrawal->payment_method . '. TxID: ' . ($transaction_id ?: 'N/A')
                ),
                array('reference_id' => $withdrawal_id, 'type' => 'withdrawal')
            );
        }
        
        $wpdb->query('COMMIT');
        
        // Send notification to user
        hyip_send_withdrawal_status_notification($withdrawal->user_id, $withdrawal, $status, $admin_notes, $transaction_id);
        
        // Log admin action
        hyip_log_user_action($withdrawal->user_id, 'withdrawal_' . $status, "Withdrawal #{$withdrawal_id} {$status} by admin. Notes: {$admin_notes}");
        
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('processing_failed', 'Withdrawal processing failed: ' . $e->getMessage());
    }
}

/**
 * Get withdrawal statistics
 */
function hyip_get_withdrawal_statistics($user_id = null) {
    global $wpdb;
    
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    
    $where_clause = '';
    $params = array();
    
    if ($user_id) {
        $where_clause = 'WHERE user_id = %d';
        $params[] = $user_id;
    }
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            payment_method,
            status,
            COUNT(*) as total_requests,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount
        FROM $table_withdrawals
        $where_clause
        GROUP BY payment_method, status
        ORDER BY payment_method, status
    ", ...$params));
}

/**
 * Get pending withdrawals for admin
 */
function hyip_get_pending_withdrawals($limit = 50) {
    if (!current_user_can('manage_options')) {
        return array();
    }
    
    global $wpdb;
    
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    $table_users = $wpdb->prefix . 'hyip_users';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT w.*, u.display_name, u.user_email, hu.balance as current_balance
        FROM $table_withdrawals w
        LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
        LEFT JOIN $table_users hu ON w.user_id = hu.user_id
        WHERE w.status = 'pending'
        ORDER BY w.request_date ASC
        LIMIT %d
    ", $limit));
}

/**
 * Cancel withdrawal request (user)
 */
function hyip_cancel_withdrawal($withdrawal_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'User not logged in.');
    }
    
    global $wpdb;
    
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Get withdrawal details (only if belongs to user and is pending)
    $withdrawal = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $table_withdrawals 
        WHERE id = %d AND user_id = %d AND status = 'pending'
    ", $withdrawal_id, $user_id));
    
    if (!$withdrawal) {
        return new WP_Error('withdrawal_not_found', 'Pending withdrawal not found or access denied.');
    }
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // Update withdrawal status
        $update_result = $wpdb->update(
            $table_withdrawals,
            array(
                'status' => 'cancelled',
                'processed_date' => current_time('mysql'),
                'admin_notes' => 'Cancelled by user'
            ),
            array('id' => $withdrawal_id)
        );
        
        if (!$update_result) {
            throw new Exception('Failed to update withdrawal status.');
        }
        
        // Return money to user balance
        $balance_update = $wpdb->query($wpdb->prepare("
            UPDATE $table_users 
            SET balance = balance + %f 
            WHERE user_id = %d
        ", $withdrawal->amount, $user_id));
        
        if (!$balance_update) {
            throw new Exception('Failed to refund user balance.');
        }
        
        // Update transaction status
        $wpdb->update(
            $table_transactions,
            array('status' => 'cancelled'),
            array('reference_id' => $withdrawal_id, 'type' => 'withdrawal')
        );
        
        $wpdb->query('COMMIT');
        
        // Log action
        hyip_log_user_action($user_id, 'withdrawal_cancelled', "User cancelled withdrawal #{$withdrawal_id}");
        
        return true;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('cancellation_failed', 'Withdrawal cancellation failed: ' . $e->getMessage());
    }
}

/**
 * Send withdrawal request notification to user
 */
function hyip_send_withdrawal_notification($user_id, $withdrawal_id, $amount, $payment_method) {
    $user = get_user_by('ID', $user_id);
    if (!$user) return;
    
    $subject = 'Withdrawal Request Submitted - ' . get_bloginfo('name');
    $message = '
        <h2>Withdrawal Request Received</h2>
        <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
        <p>We have received your withdrawal request and it is now being processed.</p>
        
        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;">
            <h3>Withdrawal Details:</h3>
            <ul>
                <li><strong>Withdrawal ID:</strong> #' . $withdrawal_id . '</li>
                <li><strong>Amount:</strong> $' . number_format($amount, 2) . '</li>
                <li><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $payment_method)) . '</li>
                <li><strong>Status:</strong> Pending Review</li>
                <li><strong>Request Date:</strong> ' . date('F j, Y g:i A') . '</li>
            </ul>
        </div>
        
        <h3>Processing Information:</h3>
        <ul>
            <li><strong>Review Time:</strong> All withdrawal requests are manually reviewed within 24 hours</li>
            <li><strong>Processing Time:</strong> Once approved, payments are processed according to the selected method</li>
            <li><strong>Status Updates:</strong> You will receive email notifications for any status changes</li>
        </ul>
        
        <h3>Expected Processing Times:</h3>
        <ul>
            <li><strong>Cryptocurrency:</strong> Instant after approval</li>
            <li><strong>PayPal:</strong> 1-3 hours after approval</li>
            <li><strong>Bank Transfer:</strong> 1-5 business days after approval</li>
        </ul>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/dashboard') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                View Dashboard
            </a>
        </div>
        
        <p>If you have any questions about your withdrawal, please contact our support team.</p>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user_id, $subject, $message, 'withdrawal_request');
}

/**
 * Send withdrawal status notification
 */
function hyip_send_withdrawal_status_notification($user_id, $withdrawal, $status, $admin_notes = '', $transaction_id = '') {
    $user = get_user_by('ID', $user_id);
    if (!$user) return;
    
    if ($status === 'approved') {
        $subject = 'Withdrawal Approved - ' . get_bloginfo('name');
        $message = '
            <h2>✅ Withdrawal Approved!</h2>
            <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
            <p>Great news! Your withdrawal request has been approved and processed.</p>
            
            <div style="background: #d4edda; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 1px solid #c3e6cb;">
                <h3>Withdrawal Details:</h3>
                <ul>
                    <li><strong>Withdrawal ID:</strong> #' . $withdrawal->id . '</li>
                    <li><strong>Amount:</strong> $' . number_format($withdrawal->amount, 2) . '</li>
                    <li><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $withdrawal->payment_method)) . '</li>
                    <li><strong>Approval Date:</strong> ' . date('F j, Y g:i A') . '</li>
                    ' . (!empty($transaction_id) ? '<li><strong>Transaction ID:</strong> ' . esc_html($transaction_id) . '</li>' : '') . '
                </ul>
            </div>
            
            ' . (!empty($admin_notes) ? '<div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <h4>Processing Notes:</h4>
                <p>' . esc_html($admin_notes) . '</p>
            </div>' : '') . '
            
            <p>Your funds have been sent to your specified payment method. Please allow the standard processing time for your payment method to receive the funds.</p>
        ';
    } else {
        $subject = 'Withdrawal Rejected - ' . get_bloginfo('name');
        $message = '
            <h2>Withdrawal Request Rejected</h2>
            <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
            <p>We regret to inform you that your withdrawal request has been rejected.</p>
            
            <div style="background: #f8d7da; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 1px solid #f5c6cb;">
                <h3>Withdrawal Details:</h3>
                <ul>
                    <li><strong>Withdrawal ID:</strong> #' . $withdrawal->id . '</li>
                    <li><strong>Amount:</strong> $' . number_format($withdrawal->amount, 2) . '</li>
                    <li><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $withdrawal->payment_method)) . '</li>
                    <li><strong>Rejection Date:</strong> ' . date('F j, Y g:i A') . '</li>
                </ul>
            </div>
            
            ' . (!empty($admin_notes) ? '<div style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <h4>Reason for Rejection:</h4>
                <p>' . esc_html($admin_notes) . '</p>
            </div>' : '') . '
            
            <p>The withdrawal amount has been returned to your account balance and is available for:</p>
            <ul>
                <li>Creating a new withdrawal request (please address any issues mentioned above)</li>
                <li>Making new investments</li>
                <li>Keeping in your account for future use</li>
            </ul>
            
            <p>If you need clarification about the rejection reason, please contact our support team.</p>
        ';
    }
    
    $message .= '
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/dashboard') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                View Dashboard
            </a>
        </div>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user_id, $subject, $message, 'withdrawal_' . $status);
}

/**
 * Notify admin of new withdrawal request
 */
function hyip_notify_admin_new_withdrawal($withdrawal_id) {
    global $wpdb;
    
    $withdrawal = $wpdb->get_row($wpdb->prepare("
        SELECT w.*, u.display_name, u.user_email 
        FROM {$wpdb->prefix}hyip_withdrawals w
        LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
        WHERE w.id = %d
    ", $withdrawal_id));
    
    if (!$withdrawal) return;
    
    $admin_email = get_option('admin_email');
    $subject = 'New Withdrawal Request - ' . get_bloginfo('name');
    
    $message = '
        <h2>New Withdrawal Request</h2>
        <p>A new withdrawal request has been submitted and requires admin approval.</p>
        
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;">
            <h3>Request Details:</h3>
            <ul>
                <li><strong>Withdrawal ID:</strong> #' . $withdrawal->id . '</li>
                <li><strong>User:</strong> ' . esc_html($withdrawal->display_name ?: 'N/A') . ' (' . esc_html($withdrawal->user_email) . ')</li>
                <li><strong>Amount:</strong> $' . number_format($withdrawal->amount, 2) . '</li>
                <li><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $withdrawal->payment_method)) . '</li>
                <li><strong>Request Date:</strong> ' . date('F j, Y g:i A', strtotime($withdrawal->request_date)) . '</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/admin') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                Review in Admin Panel
            </a>
        </div>
    ';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * AJAX handler for withdrawal creation
 */
function hyip_ajax_create_withdrawal() {
    check_ajax_referer('hyip_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }
    
    $user_id = get_current_user_id();
    $amount = floatval($_POST['amount']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $payment_details = sanitize_textarea_field($_POST['payment_details']);
    
    $result = hyip_create_withdrawal($user_id, $amount, $payment_method, $payment_details);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'withdrawal_id' => $result,
            'message' => 'Withdrawal request submitted successfully!'
        ));
    }
}
add_action('wp_ajax_hyip_create_withdrawal', 'hyip_ajax_create_withdrawal');

/**
 * AJAX handler for withdrawal processing (admin only)
 */
function hyip_ajax_process_withdrawal() {
    check_ajax_referer('hyip_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $status = sanitize_text_field($_POST['status']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
    $transaction_id = sanitize_text_field($_POST['transaction_id']);
    
    $result = hyip_process_withdrawal($withdrawal_id, $status, $admin_notes, $transaction_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'message' => "Withdrawal {$status} successfully!"
        ));
    }
}
add_action('wp_ajax_hyip_process_withdrawal', 'hyip_ajax_process_withdrawal');