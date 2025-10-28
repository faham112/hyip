<?php
/**
 * Template Name: Dashboard Page
 */

// Security check - redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

// Redirect admin users to admin panel
if (current_user_can('manage_options')) {
    wp_redirect(home_url('/admin'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's HYIP data
global $wpdb;
$table_users = $wpdb->prefix . 'hyip_users';
$table_investments = $wpdb->prefix . 'hyip_investments';
$table_plans = $wpdb->prefix . 'hyip_plans';
$table_transactions = $wpdb->prefix . 'hyip_transactions';

// Get or create user HYIP profile
$hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
if (!$hyip_user) {
    $wpdb->insert($table_users, array('user_id' => $user_id));
    $hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
}

// Handle deposit form submission
$deposit_message = hyip_handle_deposit_submission();

// Get user's investments
$investments = $wpdb->get_results($wpdb->prepare("
    SELECT i.*, p.name as plan_name, p.daily_percent, p.duration
    FROM $table_investments i
    LEFT JOIN $table_plans p ON i.plan_id = p.id
    WHERE i.user_id = %d
    ORDER BY i.invest_date DESC
", $user_id));

// Get recent transactions
$transactions = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_transactions
    WHERE user_id = %d
    ORDER BY transaction_date DESC
    LIMIT 10
", $user_id));

// Get user's deposits
$table_deposits = $wpdb->prefix . 'hyip_deposits';
$deposits = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_deposits
    WHERE user_id = %d
    ORDER BY deposit_date DESC
    LIMIT 5
", $user_id));

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="dashboard-header" style="color: white; text-align: center; margin-bottom: 3rem;">
            <h1>Welcome back, <?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?>!</h1>
            <p>Your Investment Dashboard</p>
        </div>

        <?php if ($deposit_message) : ?>
            <div class="alert <?php echo is_array($deposit_message) ? 'alert-danger' : 'alert-success'; ?>">
                <?php
                if (is_array($deposit_message)) {
                    foreach ($deposit_message as $error) {
                        echo '<p>' . esc_html($error) . '</p>';
                    }
                } else {
                    echo '<p>' . esc_html($deposit_message) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- User Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: linear-gradient(135deg, #28a745, #20c997); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div>
                        <div class="stat-number">$<?php echo number_format($hyip_user->balance, 2); ?></div>
                        <p>Account Balance</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: linear-gradient(135deg, #667eea, #764ba2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="stat-number">$<?php echo number_format($hyip_user->total_invested, 2); ?></div>
                        <p>Total Invested</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: linear-gradient(135deg, #fd7e14, #e83e8c); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <div class="stat-number">$<?php echo number_format($hyip_user->total_earned, 2); ?></div>
                        <p>Total Earned</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: linear-gradient(135deg, #dc3545, #c92a42); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div>
                        <div class="stat-number">$<?php echo number_format($hyip_user->total_withdrawn, 2); ?></div>
                        <p>Total Withdrawn</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Active Investments -->
            <div class="col-lg-8">
                <div class="card">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-chart-bar"></i> Active Investments
                        <a href="<?php echo home_url('/invest'); ?>" class="btn btn-primary" style="float: right;">
                            <i class="fas fa-plus"></i> New Investment
                        </a>
                    </h2>
                    
                    <?php if (empty($investments)) : ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-chart-line" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <h3>No Investments Yet</h3>
                            <p>Start your investment journey today!</p>
                            <a href="<?php echo home_url('/invest'); ?>" class="btn btn-primary">
                                <i class="fas fa-rocket"></i> Start Investing
                            </a>
                        </div>
                    <?php else : ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Daily Profit</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Next Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($investments as $investment) : 
                                        $progress_percent = ($investment->days_completed / $investment->duration) * 100;
                                        $next_profit = $investment->next_profit_date ? date('M j, Y H:i', strtotime($investment->next_profit_date)) : 'N/A';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($investment->plan_name); ?></strong></td>
                                        <td><strong>$<?php echo number_format($investment->amount, 2); ?></strong></td>
                                        <td><strong>$<?php echo number_format($investment->daily_profit, 2); ?></strong></td>
                                        <td>
                                            <div style="margin-bottom: 0.5rem;">
                                                <?php echo $investment->days_completed; ?> / <?php echo $investment->duration; ?> days
                                            </div>
                                            <div style="background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden;">
                                                <div style="background: linear-gradient(135deg, #667eea, #764ba2); height: 100%; width: <?php echo $progress_percent; ?>%;"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($investment->status == 'active') : ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif ($investment->status == 'completed') : ?>
                                                <span class="badge badge-warning">Completed</span>
                                            <?php else : ?>
                                                <span class="badge badge-danger">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $next_profit; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h2>
                    
                    <div style="display: grid; gap: 1rem;">
                        <a href="<?php echo home_url('/invest'); ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 1rem; padding: 1rem;">
                            <i class="fas fa-plus-circle" style="font-size: 1.5rem;"></i>
                            <div style="text-align: left;">
                                <strong>New Investment</strong><br>
                                <small>Start earning today</small>
                            </div>
                        </a>

                        <button type="button" id="depositBtn" class="btn btn-info" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; text-align: left; width: 100%;">
                            <i class="fas fa-donate" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong>Deposit Funds</strong><br>
                                <small>Add to your balance</small>
                            </div>
                        </button>
                        
                        <a href="<?php echo home_url('/withdraw'); ?>" class="btn btn-success" style="display: flex; align-items: center; gap: 1rem; padding: 1rem;">
                            <i class="fas fa-money-bill-wave" style="font-size: 1.5rem;"></i>
                            <div style="text-align: left;">
                                <strong>Withdraw Funds</strong><br>
                                <small>Access your earnings</small>
                            </div>
                        </a>
                        
                        <a href="<?php echo home_url('/profile'); ?>" class="btn btn-secondary" style="display: flex; align-items: center; gap: 1rem; padding: 1rem;">
                            <i class="fas fa-user-cog" style="font-size: 1.5rem;"></i>
                            <div style="text-align: left;">
                                <strong>Profile Settings</strong><br>
                                <small>Update your info</small>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Account Summary -->
                <div class="card" style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-chart-pie"></i> Account Summary
                    </h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Member Since:</span>
                            <strong><?php echo date('M Y', strtotime($hyip_user->joined_date)); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Active Investments:</span>
                            <strong><?php echo count(array_filter($investments, function($inv) { return $inv->status == 'active'; })); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Account Status:</span>
                            <span class="badge badge-success"><?php echo ucfirst($hyip_user->status); ?></span>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                        <div style="text-align: center;">
                            <small style="color: #666;">Total ROI</small><br>
                            <strong style="font-size: 1.5rem; color: #28a745;">
                                <?php 
                                $roi = $hyip_user->total_invested > 0 ? (($hyip_user->total_earned / $hyip_user->total_invested) * 100) : 0;
                                echo number_format($roi, 1); 
                                ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card" style="margin-top: 2rem;">
            <h2 style="margin-bottom: 2rem; color: #333;">
                <i class="fas fa-history"></i> Recent Transactions
                <a href="<?php echo home_url('/history'); ?>" class="btn btn-secondary" style="float: right;">
                    <i class="fas fa-list"></i> View All
                </a>
            </h2>
            
            <?php if (empty($transactions)) : ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h4>No Transactions Yet</h4>
                    <p>Your transaction history will appear here</p>
                </div>
            <?php else : ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($transactions, 0, 5) as $transaction) : ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i', strtotime($transaction->transaction_date)); ?></td>
                                <td>
                                    <?php
                                    $type_class = '';
                                    $type_icon = '';
                                    switch($transaction->type) {
                                        case 'earning':
                                            $type_class = 'badge-warning';
                                            $type_icon = 'fas fa-coins';
                                            break;
                                        case 'deposit':
                                            $type_class = 'badge-success';
                                            $type_icon = 'fas fa-arrow-down';
                                            break;
                                        case 'withdrawal':
                                            $type_class = 'badge-danger';
                                            $type_icon = 'fas fa-arrow-up';
                                            break;
                                        default:
                                            $type_class = 'badge-secondary';
                                            $type_icon = 'fas fa-exchange-alt';
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_class; ?>">
                                        <i class="<?php echo $type_icon; ?>"></i> <?php echo ucfirst($transaction->type); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction->description ?: 'N/A'); ?></td>
                                <td>
                                    <strong style="color: <?php echo in_array($transaction->type, ['earning', 'deposit', 'bonus', 'referral']) ? '#28a745' : '#dc3545'; ?>">
                                        <?php echo in_array($transaction->type, ['earning', 'deposit', 'bonus', 'referral']) ? '+' : '-'; ?>$<?php echo number_format($transaction->amount, 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo $transaction->status == 'completed' ? 'badge-success' : ($transaction->status == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                        <?php echo ucfirst($transaction->status); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Deposits -->
        <div class="card" style="margin-top: 2rem;">
            <h2 style="margin-bottom: 2rem; color: #333;">
                <i class="fas fa-history"></i> Recent Deposits
            </h2>
            
            <?php if (empty($deposits)) : ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h4>No Deposits Yet</h4>
                    <p>Your deposit history will appear here</p>
                </div>
            <?php else : ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deposits as $deposit) : ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i', strtotime($deposit->deposit_date)); ?></td>
                                <td>$<?php echo number_format($deposit->amount, 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $deposit->status == 'approved' ? 'badge-success' : ($deposit->status == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                        <?php echo ucfirst($deposit->status); ?>
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
</main>

<!-- Deposit Modal -->
<div id="depositModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; padding: 2rem; width: 90%; max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="margin: 0; color: #333;"><i class="fas fa-donate"></i> Deposit Funds</h2>
            <button type="button" id="closeDepositModal" style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer;">&times;</button>
        </div>
        
        <form id="depositForm" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('hyip_deposit', 'deposit_nonce'); ?>
            <div class="form-group">
                <label for="deposit_amount">Amount ($)</label>
                <input type="number" id="deposit_amount" name="deposit_amount" class="form-control" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="payment_slip">Payment Slip</label>
                <input type="file" id="payment_slip" name="payment_slip" class="form-control" required>
                <small class="form-text text-muted">Upload a screenshot or receipt of your payment.</small>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" id="cancelDeposit" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="submit_deposit" class="btn btn-primary">Submit Deposit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const depositModal = document.getElementById('depositModal');
    const depositBtn = document.getElementById('depositBtn');
    const closeDepositModal = document.getElementById('closeDepositModal');
    const cancelDeposit = document.getElementById('cancelDeposit');

    depositBtn.addEventListener('click', () => {
        depositModal.style.display = 'flex';
    });

    closeDepositModal.addEventListener('click', () => {
        depositModal.style.display = 'none';
    });

    cancelDeposit.addEventListener('click', () => {
        depositModal.style.display = 'none';
    });

    depositModal.addEventListener('click', function(e) {
        if (e.target === depositModal) {
            depositModal.style.display = 'none';
        }
    });

    // Auto-refresh page every 5 minutes to update data
    setTimeout(function() {
        location.reload();
    }, 300000);
    
    // Show welcome message for new users
    <?php if (isset($_GET['welcome']) && $_GET['welcome'] == 'true') : ?>
    showAlert('Welcome to HYIP Manager! Your account is ready for investments.', 'success');
    <?php endif; ?>
});
</script>

<?php get_footer(); ?>
