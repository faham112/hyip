<?php
/**
 * Template Name: Admin Dashboard
 */

// Security check - only administrators
if (!current_user_can('manage_options')) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

global $wpdb;
$table_users = $wpdb->prefix . 'hyip_users';
$table_investments = $wpdb->prefix . 'hyip_investments';
$table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
$table_plans = $wpdb->prefix . 'hyip_plans';

// Get admin statistics
$stats = array(
    'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users"),
    'active_users' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'active'"),
    'total_invested' => $wpdb->get_var("SELECT SUM(amount) FROM $table_investments"),
    'active_investments' => $wpdb->get_var("SELECT COUNT(*) FROM $table_investments WHERE status = 'active'"),
    'pending_withdrawals' => $wpdb->get_var("SELECT COUNT(*) FROM $table_withdrawals WHERE status = 'pending'"),
    'total_withdrawn' => $wpdb->get_var("SELECT SUM(amount) FROM $table_withdrawals WHERE status = 'approved'"),
    'pending_withdrawal_amount' => $wpdb->get_var("SELECT SUM(amount) FROM $table_withdrawals WHERE status = 'pending'"),
);

// Handle withdrawal approval/rejection
if (isset($_POST['action']) && $_POST['action'] == 'process_withdrawal' && wp_verify_nonce($_POST['admin_nonce'], 'admin_action')) {
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $status = sanitize_text_field($_POST['status']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
    
    if (in_array($status, ['approved', 'rejected'])) {
        $update_data = array(
            'status' => $status,
            'processed_date' => current_time('mysql'),
            'admin_notes' => $admin_notes
        );
        
        $result = $wpdb->update($table_withdrawals, $update_data, array('id' => $withdrawal_id));
        
        if ($result) {
            // If rejected, return money to user balance
            if ($status == 'rejected') {
                $withdrawal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_withdrawals WHERE id = %d", $withdrawal_id));
                if ($withdrawal) {
                    $wpdb->query($wpdb->prepare("
                        UPDATE $table_users 
                        SET balance = balance + %f 
                        WHERE user_id = %d
                    ", $withdrawal->amount, $withdrawal->user_id));
                }
            }
            
            $admin_message = 'Withdrawal processed successfully!';
        } else {
            $admin_error = 'Failed to process withdrawal.';
        }
    }
}

// Get recent data for admin review
$pending_withdrawals = $wpdb->get_results("
    SELECT w.*, u.display_name, u.user_email, hu.balance
    FROM $table_withdrawals w
    LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
    LEFT JOIN $table_users hu ON w.user_id = hu.user_id
    WHERE w.status = 'pending'
    ORDER BY w.request_date ASC
    LIMIT 20
");

$recent_investments = $wpdb->get_results("
    SELECT i.*, p.name as plan_name, u.display_name, u.user_email
    FROM $table_investments i
    LEFT JOIN $table_plans p ON i.plan_id = p.id
    LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
    ORDER BY i.invest_date DESC
    LIMIT 10
");

$top_investors = $wpdb->get_results("
    SELECT hu.*, u.display_name, u.user_email, u.user_registered
    FROM $table_users hu
    LEFT JOIN {$wpdb->users} u ON hu.user_id = u.ID
    WHERE hu.total_invested > 0
    ORDER BY hu.total_invested DESC
    LIMIT 10
");

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container" style="background: white; min-height: 100vh; padding: 2rem; margin-top: -2rem;">
        <div class="admin-header" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 2px solid #e9ecef;">
            <h1 style="color: #333; margin-bottom: 0.5rem;">
                <i class="fas fa-tachometer-alt"></i> HYIP Admin Dashboard
            </h1>
            <p style="color: #666; margin: 0;">Complete system overview and management</p>
        </div>

        <?php if (isset($admin_message)) : ?>
            <div class="alert alert-success"><?php echo esc_html($admin_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($admin_error)) : ?>
            <div class="alert alert-danger"><?php echo esc_html($admin_error); ?></div>
        <?php endif; ?>

        <!-- Admin Stats -->
        <div class="admin-stats">
            <div class="admin-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: #667eea; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                        <small><?php echo number_format($stats['active_users']); ?> active</small>
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: #28a745; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h3>$<?php echo number_format($stats['total_invested'], 2); ?></h3>
                        <p>Total Invested</p>
                        <small><?php echo number_format($stats['active_investments']); ?> active investments</small>
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: #dc3545; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <h3>$<?php echo number_format($stats['total_withdrawn'], 2); ?></h3>
                        <p>Total Withdrawn</p>
                        <small>$<?php echo number_format($stats['pending_withdrawal_amount'], 2); ?> pending</small>
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: #ffc107; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h3><?php echo number_format($stats['pending_withdrawals']); ?></h3>
                        <p>Pending Withdrawals</p>
                        <small>Requires review</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Withdrawals -->
            <div class="col-lg-8">
                <div class="card">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-exclamation-circle"></i> Pending Withdrawals
                        <?php if (count($pending_withdrawals) > 0) : ?>
                            <span class="badge badge-warning"><?php echo count($pending_withdrawals); ?></span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (empty($pending_withdrawals)) : ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-check-circle" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <h3>No Pending Withdrawals</h3>
                            <p>All withdrawal requests have been processed</p>
                        </div>
                    <?php else : ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                        <th>Balance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_withdrawals as $withdrawal) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($withdrawal->display_name ?: 'N/A'); ?></strong><br>
                                            <small><?php echo esc_html($withdrawal->user_email); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($withdrawal->amount, 2); ?></strong></td>
                                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $withdrawal->payment_method))); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($withdrawal->request_date)); ?></td>
                                        <td>$<?php echo number_format($withdrawal->balance, 2); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-success btn-sm approve-btn" 
                                                        data-id="<?php echo $withdrawal->id; ?>"
                                                        data-user="<?php echo esc_attr($withdrawal->display_name); ?>"
                                                        data-amount="<?php echo $withdrawal->amount; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm reject-btn"
                                                        data-id="<?php echo $withdrawal->id; ?>"
                                                        data-user="<?php echo esc_attr($withdrawal->display_name); ?>"
                                                        data-amount="<?php echo $withdrawal->amount; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm view-btn"
                                                        data-details="<?php echo esc_attr($withdrawal->payment_details); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Investments -->
                <div class="card" style="margin-top: 2rem;">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-chart-bar"></i> Recent Investments
                    </h2>
                    
                    <?php if (empty($recent_investments)) : ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <h4>No Recent Investments</h4>
                            <p>Investment activity will appear here</p>
                        </div>
                    <?php else : ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Daily Profit</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_investments as $investment) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($investment->display_name ?: 'N/A'); ?></strong><br>
                                            <small><?php echo esc_html($investment->user_email); ?></small>
                                        </td>
                                        <td><?php echo esc_html($investment->plan_name); ?></td>
                                        <td><strong>$<?php echo number_format($investment->amount, 2); ?></strong></td>
                                        <td><strong>$<?php echo number_format($investment->daily_profit, 2); ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($investment->invest_date)); ?></td>
                                        <td>
                                            <span class="badge <?php echo $investment->status == 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($investment->status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Investors & Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-crown"></i> Top Investors
                    </h3>
                    
                    <?php if (empty($top_investors)) : ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-user-friends" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <h5>No Investors Yet</h5>
                            <p>Top investors will appear here</p>
                        </div>
                    <?php else : ?>
                        <?php foreach (array_slice($top_investors, 0, 5) as $index => $investor) : ?>
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="background: <?php echo $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : ($index == 2 ? '#CD7F32' : '#667eea')); ?>; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo $index + 1; ?>
                            </div>
                            <div style="flex: 1;">
                                <strong><?php echo esc_html($investor->display_name ?: 'Anonymous'); ?></strong><br>
                                <small>$<?php echo number_format($investor->total_invested, 2); ?> invested</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-tools"></i> Quick Actions
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <a href="<?php echo admin_url(); ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-cog"></i> WordPress Admin
                        </a>
                        <a href="<?php echo admin_url('users.php'); ?>" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                        <button class="btn btn-success" onclick="processAllProfits()" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-coins"></i> Process Daily Profits
                        </button>
                        <button class="btn btn-info" onclick="generateReports()" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-chart-pie"></i> Generate Reports
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div style="background: #e7f3ff; border: 1px solid #bee5eb; border-radius: 8px; padding: 1.5rem; margin-top: 2rem;">
                    <h5 style="color: #0066cc; margin-bottom: 1rem;">
                        <i class="fas fa-server"></i> System Status
                    </h5>
                    <div style="font-size: 0.9rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Theme Version:</span>
                            <strong>v1.0.2</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>WordPress Version:</span>
                            <strong><?php echo get_bloginfo('version'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>System Status:</span>
                            <span style="color: #28a745; font-weight: bold;">
                                <i class="fas fa-check-circle"></i> Online
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Withdrawal Processing Modal -->
<div id="withdrawalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; padding: 2rem; width: 90%; max-width: 500px;">
        <h3 style="margin-bottom: 1.5rem;" id="modalTitle">Process Withdrawal</h3>
        
        <form id="withdrawalForm" method="post">
            <?php wp_nonce_field('admin_action', 'admin_nonce'); ?>
            <input type="hidden" name="action" value="process_withdrawal">
            <input type="hidden" id="withdrawal_id" name="withdrawal_id">
            <input type="hidden" id="withdrawal_status" name="status">
            
            <div id="withdrawalDetails" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <!-- Details will be populated by JavaScript -->
            </div>
            
            <div class="form-group">
                <label for="admin_notes">Admin Notes (Optional)</label>
                <textarea id="admin_notes" name="admin_notes" class="form-control" rows="3" placeholder="Add any notes about this withdrawal..."></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" id="cancelModal" class="btn btn-secondary">Cancel</button>
                <button type="submit" id="confirmAction" class="btn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('withdrawalModal');
    const modalTitle = document.getElementById('modalTitle');
    const withdrawalDetails = document.getElementById('withdrawalDetails');
    const withdrawalIdInput = document.getElementById('withdrawal_id');
    const withdrawalStatusInput = document.getElementById('withdrawal_status');
    const confirmBtn = document.getElementById('confirmAction');
    const cancelBtn = document.getElementById('cancelModal');
    
    // Approve buttons
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const user = this.dataset.user;
            const amount = this.dataset.amount;
            
            modalTitle.textContent = 'Approve Withdrawal';
            withdrawalDetails.innerHTML = `
                <strong>User:</strong> ${user}<br>
                <strong>Amount:</strong> $${parseFloat(amount).toFixed(2)}<br>
                <strong>Action:</strong> <span style="color: #28a745;">APPROVE</span>
            `;
            withdrawalIdInput.value = id;
            withdrawalStatusInput.value = 'approved';
            confirmBtn.className = 'btn btn-success';
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve Withdrawal';
            
            modal.style.display = 'flex';
        });
    });
    
    // Reject buttons
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const user = this.dataset.user;
            const amount = this.dataset.amount;
            
            modalTitle.textContent = 'Reject Withdrawal';
            withdrawalDetails.innerHTML = `
                <strong>User:</strong> ${user}<br>
                <strong>Amount:</strong> $${parseFloat(amount).toFixed(2)}<br>
                <strong>Action:</strong> <span style="color: #dc3545;">REJECT</span><br>
                <small>Amount will be returned to user's balance</small>
            `;
            withdrawalIdInput.value = id;
            withdrawalStatusInput.value = 'rejected';
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.innerHTML = '<i class="fas fa-times"></i> Reject Withdrawal';
            
            modal.style.display = 'flex';
        });
    });
    
    // View payment details buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const details = this.dataset.details;
            alert('Payment Details:\n\n' + details);
        });
    });
    
    // Cancel modal
    cancelBtn.addEventListener('click', () => modal.style.display = 'none');
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
    });
});

function processAllProfits() {
    if (confirm('Process daily profits for all active investments?')) {
        // This would trigger the AJAX call to process profits
        showAlert('Daily profits processing initiated...', 'info');
    }
}

function generateReports() {
    showAlert('Report generation feature will be implemented in future updates.', 'info');
}
</script>

<?php get_footer(); ?>