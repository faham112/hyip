<?php
/**
 * Admin Functions for HYIP Manager
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add HYIP menu to WordPress admin
 */
function hyip_add_admin_menu() {
    add_menu_page(
        'HYIP Manager',
        'HYIP Manager',
        'manage_options',
        'hyip-manager',
        'hyip_admin_dashboard_page',
        'dashicons-chart-line',
        30
    );
    
    add_submenu_page(
        'hyip-manager',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'hyip-manager',
        'hyip_admin_dashboard_page'
    );
    
    add_submenu_page(
        'hyip-manager',
        'Investment Plans',
        'Investment Plans',
        'manage_options',
        'hyip-plans',
        'hyip_admin_plans_page'
    );
    
    add_submenu_page(
        'hyip-manager',
        'Users',
        'Users',
        'manage_options',
        'hyip-users',
        'hyip_admin_users_page'
    );
    
    add_submenu_page(
        'hyip-manager',
        'Withdrawals',
        'Withdrawals',
        'manage_options',
        'hyip-withdrawals',
        'hyip_admin_withdrawals_page'
    );
    
    add_submenu_page(
        'hyip-manager',
        'Investments',
        'Investments',
        'manage_options',
        'hyip-investments',
        'hyip_admin_investments_page'
    );
    
    add_submenu_page(
        'hyip-manager',
        'Reports',
        'Reports',
        'manage_options',
        'hyip-reports',
        'hyip_admin_reports_page'
    );
}
add_action('admin_menu', 'hyip_add_admin_menu');

/**
 * Admin dashboard page
 */
function hyip_admin_dashboard_page() {
    global $wpdb;
    
    // Get statistics
    $stats = hyip_get_platform_stats();
    $recent_users = $wpdb->get_results("
        SELECT u.ID, u.display_name, u.user_email, u.user_registered, hu.balance, hu.total_invested
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}hyip_users hu ON u.ID = hu.user_id
        ORDER BY u.user_registered DESC
        LIMIT 10
    ");
    
    $pending_withdrawals = hyip_get_pending_withdrawals(10);
    
    ?>
    <div class="wrap">
        <h1>HYIP Manager Dashboard</h1>
        
        <div class="hyip-admin-stats">
            <div class="stat-box">
                <h3>Total Users</h3>
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Invested</h3>
                <div class="stat-number">$<?php echo number_format($stats['total_invested'], 2); ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Earned</h3>
                <div class="stat-number">$<?php echo number_format($stats['total_earned'], 2); ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending Withdrawals</h3>
                <div class="stat-number"><?php echo number_format($stats['pending_withdrawals']); ?></div>
            </div>
        </div>
        
        <div class="admin-grid">
            <!-- Recent Users -->
            <div class="admin-section">
                <h2>Recent Users</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Total Invested</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>$<?php echo number_format($user->balance ?: 0, 2); ?></td>
                            <td>$<?php echo number_format($user->total_invested ?: 0, 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pending Withdrawals -->
            <div class="admin-section">
                <h2>Pending Withdrawals</h2>
                <?php if (empty($pending_withdrawals)) : ?>
                    <p>No pending withdrawals.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_withdrawals as $withdrawal) : ?>
                            <tr>
                                <td><?php echo esc_html($withdrawal->display_name ?: 'N/A'); ?></td>
                                <td>$<?php echo number_format($withdrawal->amount, 2); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $withdrawal->payment_method))); ?></td>
                                <td><?php echo date('M j, Y', strtotime($withdrawal->request_date)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-actions">
            <a href="<?php echo admin_url('admin.php?page=hyip-withdrawals'); ?>" class="button button-primary">
                Review Withdrawals
            </a>
            <a href="<?php echo admin_url('admin.php?page=hyip-plans'); ?>" class="button">
                Manage Plans
            </a>
            <button class="button" onclick="processAllProfits()">Process Daily Profits</button>
        </div>
    </div>
    
    <style>
    .hyip-admin-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
        border-left: 4px solid #2271b1;
    }
    
    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #2271b1;
        margin-top: 10px;
    }
    
    .admin-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .admin-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .admin-actions {
        text-align: center;
    }
    
    .admin-actions .button {
        margin-right: 10px;
    }
    
    @media (max-width: 768px) {
        .admin-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    function processAllProfits() {
        if (confirm('Process daily profits for all active investments?')) {
            // AJAX call would go here
            alert('Daily profits processing initiated...');
        }
    }
    </script>
    <?php
}

/**
 * Investment Plans admin page
 */
function hyip_admin_plans_page() {
    global $wpdb;
    
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_plan' && wp_verify_nonce($_POST['plan_nonce'], 'add_plan')) {
            $result = $wpdb->insert($table_plans, array(
                'name' => sanitize_text_field($_POST['name']),
                'min_amount' => floatval($_POST['min_amount']),
                'max_amount' => floatval($_POST['max_amount']),
                'daily_percent' => floatval($_POST['daily_percent']),
                'duration' => intval($_POST['duration']),
                'status' => sanitize_text_field($_POST['status'])
            ));
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Plan added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to add plan.</p></div>';
            }
        }
        
        if ($_POST['action'] === 'update_plan' && wp_verify_nonce($_POST['plan_nonce'], 'update_plan')) {
            $result = $wpdb->update($table_plans, array(
                'name' => sanitize_text_field($_POST['name']),
                'min_amount' => floatval($_POST['min_amount']),
                'max_amount' => floatval($_POST['max_amount']),
                'daily_percent' => floatval($_POST['daily_percent']),
                'duration' => intval($_POST['duration']),
                'status' => sanitize_text_field($_POST['status'])
            ), array('id' => intval($_POST['plan_id'])));
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Plan updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to update plan.</p></div>';
            }
        }
    }
    
    $plans = $wpdb->get_results("SELECT * FROM $table_plans ORDER BY min_amount ASC");
    
    ?>
    <div class="wrap">
        <h1>Investment Plans</h1>
        
        <!-- Add New Plan Form -->
        <div class="plan-form">
            <h2>Add New Plan</h2>
            <form method="post">
                <?php wp_nonce_field('add_plan', 'plan_nonce'); ?>
                <input type="hidden" name="action" value="add_plan">
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Plan Name</label></th>
                        <td><input type="text" name="name" id="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="min_amount">Minimum Amount</label></th>
                        <td><input type="number" name="min_amount" id="min_amount" step="0.01" min="0" required></td>
                    </tr>
                    <tr>
                        <th><label for="max_amount">Maximum Amount</label></th>
                        <td><input type="number" name="max_amount" id="max_amount" step="0.01" min="0" required></td>
                    </tr>
                    <tr>
                        <th><label for="daily_percent">Daily Percentage</label></th>
                        <td><input type="number" name="daily_percent" id="daily_percent" step="0.01" min="0" max="100" required></td>
                    </tr>
                    <tr>
                        <th><label for="duration">Duration (Days)</label></th>
                        <td><input type="number" name="duration" id="duration" min="1" required></td>
                    </tr>
                    <tr>
                        <th><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="Add Plan">
                </p>
            </form>
        </div>
        
        <!-- Existing Plans Table -->
        <h2>Existing Plans</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Amount Range</th>
                    <th>Daily %</th>
                    <th>Duration</th>
                    <th>ROI</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan) : 
                    $roi = ($plan->daily_percent * $plan->duration);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($plan->name); ?></strong></td>
                    <td>$<?php echo number_format($plan->min_amount); ?> - $<?php echo number_format($plan->max_amount); ?></td>
                    <td><?php echo $plan->daily_percent; ?>%</td>
                    <td><?php echo $plan->duration; ?> days</td>
                    <td><?php echo number_format($roi, 1); ?>%</td>
                    <td>
                        <span class="status-<?php echo $plan->status; ?>">
                            <?php echo ucfirst($plan->status); ?>
                        </span>
                    </td>
                    <td>
                        <button class="button button-small edit-plan" data-plan='<?php echo json_encode($plan); ?>'>
                            Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .plan-form {
        background: #fff;
        padding: 20px;
        margin-bottom: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .status-active {
        color: #46b450;
        font-weight: bold;
    }
    
    .status-inactive {
        color: #dc3232;
        font-weight: bold;
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edit-plan').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const plan = JSON.parse(this.dataset.plan);
                // Populate form for editing
                // This would typically open a modal or populate an edit form
                alert('Edit functionality would be implemented here');
            });
        });
    });
    </script>
    <?php
}

/**
 * Users admin page
 */
function hyip_admin_users_page() {
    global $wpdb;
    
    $users = $wpdb->get_results("
        SELECT u.ID, u.display_name, u.user_email, u.user_registered, 
               hu.balance, hu.total_invested, hu.total_earned, hu.total_withdrawn, hu.status
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}hyip_users hu ON u.ID = hu.user_id
        ORDER BY u.user_registered DESC
    ");
    
    ?>
    <div class="wrap">
        <h1>HYIP Users</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Balance</th>
                    <th>Total Invested</th>
                    <th>Total Earned</th>
                    <th>Total Withdrawn</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($user->display_name ?: 'N/A'); ?></strong><br>
                        <small><?php echo esc_html($user->user_email); ?></small>
                    </td>
                    <td>$<?php echo number_format($user->balance ?: 0, 2); ?></td>
                    <td>$<?php echo number_format($user->total_invested ?: 0, 2); ?></td>
                    <td>$<?php echo number_format($user->total_earned ?: 0, 2); ?></td>
                    <td>$<?php echo number_format($user->total_withdrawn ?: 0, 2); ?></td>
                    <td>
                        <span class="status-<?php echo $user->status ?: 'active'; ?>">
                            <?php echo ucfirst($user->status ?: 'active'); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                    <td>
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button button-small">
                            Edit
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Withdrawals admin page
 */
function hyip_admin_withdrawals_page() {
    $pending_withdrawals = hyip_get_pending_withdrawals();
    
    ?>
    <div class="wrap">
        <h1>Withdrawal Management</h1>
        
        <h2>Pending Withdrawals</h2>
        <?php if (empty($pending_withdrawals)) : ?>
            <p>No pending withdrawals.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Request Date</th>
                        <th>Current Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_withdrawals as $withdrawal) : ?>
                    <tr>
                        <td>#<?php echo $withdrawal->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($withdrawal->display_name ?: 'N/A'); ?></strong><br>
                            <small><?php echo esc_html($withdrawal->user_email); ?></small>
                        </td>
                        <td><strong>$<?php echo number_format($withdrawal->amount, 2); ?></strong></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $withdrawal->payment_method))); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($withdrawal->request_date)); ?></td>
                        <td>$<?php echo number_format($withdrawal->current_balance, 2); ?></td>
                        <td>
                            <button class="button button-primary button-small approve-withdrawal" 
                                    data-id="<?php echo $withdrawal->id; ?>">
                                Approve
                            </button>
                            <button class="button button-secondary button-small reject-withdrawal" 
                                    data-id="<?php echo $withdrawal->id; ?>">
                                Reject
                            </button>
                            <button class="button button-small view-details" 
                                    data-details="<?php echo esc_attr($withdrawal->payment_details); ?>">
                                Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.approve-withdrawal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                if (confirm('Approve this withdrawal request?')) {
                    // AJAX call would go here
                    alert('Withdrawal approved!');
                }
            });
        });
        
        document.querySelectorAll('.reject-withdrawal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const reason = prompt('Reason for rejection (optional):');
                if (reason !== null) {
                    // AJAX call would go here
                    alert('Withdrawal rejected!');
                }
            });
        });
        
        document.querySelectorAll('.view-details').forEach(function(btn) {
            btn.addEventListener('click', function() {
                alert('Payment Details:\n\n' + this.dataset.details);
            });
        });
    });
    </script>
    <?php
}

/**
 * Investments admin page
 */
function hyip_admin_investments_page() {
    global $wpdb;
    
    $investments = $wpdb->get_results("
        SELECT i.*, p.name as plan_name, u.display_name, u.user_email
        FROM {$wpdb->prefix}hyip_investments i
        LEFT JOIN {$wpdb->prefix}hyip_plans p ON i.plan_id = p.id
        LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
        ORDER BY i.invest_date DESC
        LIMIT 100
    ");
    
    ?>
    <div class="wrap">
        <h1>Investment Management</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Daily Profit</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($investments as $investment) : ?>
                <tr>
                    <td>#<?php echo $investment->id; ?></td>
                    <td>
                        <strong><?php echo esc_html($investment->display_name ?: 'N/A'); ?></strong><br>
                        <small><?php echo esc_html($investment->user_email); ?></small>
                    </td>
                    <td><?php echo esc_html($investment->plan_name); ?></td>
                    <td><strong>$<?php echo number_format($investment->amount, 2); ?></strong></td>
                    <td>$<?php echo number_format($investment->daily_profit, 2); ?></td>
                    <td>
                        <?php 
                        $plan = hyip_get_investment_plan($investment->plan_id);
                        $progress = $plan ? ($investment->days_completed / $plan->duration) * 100 : 0;
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <small><?php echo $investment->days_completed; ?> / <?php echo $plan->duration ?? 'N/A'; ?> days</small>
                    </td>
                    <td>
                        <span class="status-<?php echo $investment->status; ?>">
                            <?php echo ucfirst($investment->status); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($investment->invest_date)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .progress-bar {
        width: 100%;
        height: 10px;
        background: #e9ecef;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 5px;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.3s ease;
    }
    </style>
    <?php
}

/**
 * Reports admin page
 */
function hyip_admin_reports_page() {
    global $wpdb;
    
    // Get summary data
    $stats = hyip_get_platform_stats();
    
    ?>
    <div class="wrap">
        <h1>HYIP Reports</h1>
        
        <div class="reports-grid">
            <div class="report-section">
                <h2>Platform Overview</h2>
                <table class="wp-list-table widefat">
                    <tr>
                        <td>Total Users</td>
                        <td><?php echo number_format($stats['total_users']); ?></td>
                    </tr>
                    <tr>
                        <td>Total Invested</td>
                        <td>$<?php echo number_format($stats['total_invested'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Total Earned by Users</td>
                        <td>$<?php echo number_format($stats['total_earned'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Total Withdrawn</td>
                        <td>$<?php echo number_format($stats['total_withdrawn'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Active Investments</td>
                        <td><?php echo number_format($stats['active_investments']); ?></td>
                    </tr>
                    <tr>
                        <td>Pending Withdrawals</td>
                        <td><?php echo number_format($stats['pending_withdrawals']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="report-section">
                <h2>Investment Statistics by Plan</h2>
                <?php
                $plan_stats = hyip_get_investment_statistics();
                if (!empty($plan_stats)) :
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Investments</th>
                            <th>Total Amount</th>
                            <th>Total Profit</th>
                            <th>Avg Investment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plan_stats as $stat) : ?>
                        <tr>
                            <td><?php echo esc_html($stat->plan_name); ?></td>
                            <td><?php echo number_format($stat->total_investments); ?></td>
                            <td>$<?php echo number_format($stat->total_amount, 2); ?></td>
                            <td>$<?php echo number_format($stat->total_profit, 2); ?></td>
                            <td>$<?php echo number_format($stat->average_investment, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p>No investment data available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="report-actions">
            <button class="button" onclick="exportReports()">Export Reports</button>
            <button class="button" onclick="generateDetailedReport()">Generate Detailed Report</button>
        </div>
    </div>
    
    <style>
    .reports-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .report-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .report-actions {
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .reports-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    function exportReports() {
        alert('Export functionality would be implemented here');
    }
    
    function generateDetailedReport() {
        alert('Detailed report generation would be implemented here');
    }
    </script>
    <?php
}

/**
 * Admin notices
 */
function hyip_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check for pending withdrawals
    global $wpdb;
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hyip_withdrawals WHERE status = 'pending'");
    
    if ($pending_count > 0) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>HYIP Manager:</strong> You have ' . $pending_count . ' pending withdrawal request(s) that need attention. ';
        echo '<a href="' . admin_url('admin.php?page=hyip-withdrawals') . '">Review them now</a></p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'hyip_admin_notices');