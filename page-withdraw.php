<?php
/**
 * Template Name: Withdraw Page
 */

// Security check
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

global $wpdb;
$table_users = $wpdb->prefix . 'hyip_users';
$table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';

// Get user data
$hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
if (!$hyip_user) {
    $wpdb->insert($table_users, array('user_id' => $user_id));
    $hyip_user = (object) array('balance' => 0);
}

// Handle withdrawal request
if (isset($_POST['submit_withdrawal']) && wp_verify_nonce($_POST['withdrawal_nonce'], 'create_withdrawal')) {
    $amount = floatval($_POST['amount']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $payment_details = sanitize_textarea_field($_POST['payment_details']);
    
    $min_withdrawal = 10.00;
    
    if ($amount >= $min_withdrawal && $amount <= $hyip_user->balance && !empty($payment_details)) {
        // Create withdrawal request
        $withdrawal_result = $wpdb->insert(
            $table_withdrawals,
            array(
                'user_id' => $user_id,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'payment_details' => $payment_details,
                'status' => 'pending'
            )
        );
        
        if ($withdrawal_result) {
            // Update user balance
            $wpdb->update(
                $table_users,
                array('balance' => $hyip_user->balance - $amount),
                array('user_id' => $user_id)
            );
            
            // Log transaction
            $table_transactions = $wpdb->prefix . 'hyip_transactions';
            $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $user_id,
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'description' => 'Withdrawal request via ' . $payment_method,
                    'reference_id' => $wpdb->insert_id,
                    'status' => 'pending'
                )
            );
            
            $success_message = 'Withdrawal request submitted successfully! It will be processed within 24 hours.';
            // Refresh user data
            $hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
        } else {
            $error_message = 'Withdrawal request failed. Please try again.';
        }
    } else {
        $error_message = 'Invalid withdrawal details. Please check the amount, your balance, and payment details.';
    }
}

// Get pending withdrawals
$pending_withdrawals = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_withdrawals 
    WHERE user_id = %d AND status = 'pending' 
    ORDER BY request_date DESC
", $user_id));

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="page-header" style="color: white; text-align: center; margin-bottom: 3rem;">
            <h1><i class="fas fa-money-bill-wave"></i> Withdraw Funds</h1>
            <p>Request a withdrawal from your account balance</p>
            <div style="background: rgba(255, 255, 255, 0.1); padding: 1rem; border-radius: 8px; display: inline-block; margin-top: 1rem;">
                <strong>Available Balance: $<?php echo number_format($hyip_user->balance, 2); ?></strong>
            </div>
        </div>

        <?php if (isset($success_message)) : ?>
            <div class="alert alert-success"><?php echo esc_html($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)) : ?>
            <div class="alert alert-danger"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Withdrawal Form -->
            <div class="col-lg-8">
                <div class="card">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-credit-card"></i> Withdrawal Request
                    </h2>
                    
                    <?php if ($hyip_user->balance < 10) : ?>
                        <div class="alert alert-warning">
                            <strong>Insufficient Balance!</strong> Minimum withdrawal amount is $10.00. Your current balance is $<?php echo number_format($hyip_user->balance, 2); ?>.
                        </div>
                    <?php else : ?>
                        
                        <form id="withdrawalForm" method="post">
                            <?php wp_nonce_field('create_withdrawal', 'withdrawal_nonce'); ?>
                            
                            <div class="form-group">
                                <label for="amount">Withdrawal Amount ($)</label>
                                <input type="number" id="amount" name="amount" class="form-control" 
                                       min="10" max="<?php echo $hyip_user->balance; ?>" step="0.01" required>
                                <small class="form-text text-muted">
                                    Minimum: $10.00 | Maximum: $<?php echo number_format($hyip_user->balance, 2); ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Method</label>
                                <div class="payment-methods" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 0.5rem;">
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                                        <input type="radio" name="payment_method" value="bitcoin" style="margin: 0;" required>
                                        <div style="background: #f7931a; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">₿</div>
                                        <div>
                                            <strong>Bitcoin</strong><br>
                                            <small>Processing: Instant</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                                        <input type="radio" name="payment_method" value="ethereum" style="margin: 0;" required>
                                        <div style="background: #627eea; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">Ξ</div>
                                        <div>
                                            <strong>Ethereum</strong><br>
                                            <small>Processing: Instant</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                                        <input type="radio" name="payment_method" value="paypal" style="margin: 0;" required>
                                        <div style="background: #0070ba; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: bold;">PP</div>
                                        <div>
                                            <strong>PayPal</strong><br>
                                            <small>Processing: 1-3 hours</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                                        <input type="radio" name="payment_method" value="bank_transfer" style="margin: 0;" required>
                                        <div style="background: #28a745; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">🏦</div>
                                        <div>
                                            <strong>Bank Transfer</strong><br>
                                            <small>Processing: 1-5 days</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_details">Payment Details</label>
                                <textarea id="payment_details" name="payment_details" class="form-control" rows="4" 
                                          placeholder="Enter your wallet address, PayPal email, or bank account details..." required></textarea>
                                <small class="form-text text-muted">
                                    Please provide accurate payment details. Incorrect details may delay your withdrawal.
                                </small>
                            </div>
                            
                            <div id="withdrawal_summary" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;">
                                <h4 style="margin-bottom: 1rem; color: #333;">Withdrawal Summary</h4>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Withdrawal Amount:</span>
                                    <strong id="summary_amount">$0.00</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Processing Fee:</span>
                                    <strong id="summary_fee">$2.00</strong>
                                </div>
                                <hr>
                                <div style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                                    <span><strong>You Will Receive:</strong></span>
                                    <strong id="summary_net" style="color: #28a745;">$0.00</strong>
                                </div>
                            </div>
                            
                            <div style="text-align: center;">
                                <button type="submit" name="submit_withdrawal" class="btn btn-success" style="padding: 1rem 2rem; font-size: 1.1rem;">
                                    <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                                </button>
                            </div>
                        </form>
                        
                    <?php endif; ?>
                </div>
            </div>

            <!-- Withdrawal Info & Pending Requests -->
            <div class="col-lg-4">
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-info-circle"></i> Withdrawal Information
                    </h3>
                    
                    <div style="margin-bottom: 2rem;">
                        <h5 style="color: #667eea; margin-bottom: 1rem;">Processing Times</h5>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                <strong>Cryptocurrency:</strong> Instant
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                <strong>PayPal:</strong> 1-3 hours
                            </li>
                            <li style="padding: 0.5rem 0;">
                                <strong>Bank Transfer:</strong> 1-5 business days
                            </li>
                        </ul>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <h5 style="color: #667eea; margin-bottom: 1rem;">Fees & Limits</h5>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                <strong>Minimum:</strong> $10.00
                            </li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                <strong>Processing Fee:</strong> $2.00
                            </li>
                            <li style="padding: 0.5rem 0;">
                                <strong>Daily Limit:</strong> $10,000.00
                            </li>
                        </ul>
                    </div>
                    
                    <div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        <h6 style="color: #0066cc; margin-bottom: 0.5rem;"><i class="fas fa-shield-alt"></i> Security Note</h6>
                        <p style="margin: 0; font-size: 0.9rem; color: #333;">
                            All withdrawals are manually reviewed for security. You'll receive an email confirmation once processed.
                        </p>
                    </div>
                </div>

                <!-- Pending Withdrawals -->
                <?php if (!empty($pending_withdrawals)) : ?>
                <div class="card" style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-clock"></i> Pending Withdrawals
                    </h3>
                    
                    <?php foreach ($pending_withdrawals as $withdrawal) : ?>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <strong>$<?php echo number_format($withdrawal->amount, 2); ?></strong>
                            <span class="badge badge-warning">Pending</span>
                        </div>
                        <div style="font-size: 0.9rem; color: #666;">
                            <div>Method: <?php echo ucfirst(str_replace('_', ' ', $withdrawal->payment_method)); ?></div>
                            <div>Requested: <?php echo date('M j, Y H:i', strtotime($withdrawal->request_date)); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Withdrawals are processed within 24 hours during business days.
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const paymentOptions = document.querySelectorAll('.payment-option');
    const form = document.getElementById('withdrawalForm');
    
    // Payment method selection styling
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            paymentOptions.forEach(option => {
                option.style.borderColor = '#e9ecef';
                option.style.background = 'white';
            });
            if (this.checked) {
                this.closest('.payment-option').style.borderColor = '#667eea';
                this.closest('.payment-option').style.background = '#f8f9ff';
            }
        });
    });
    
    // Update summary on amount change
    if (amountInput) {
        amountInput.addEventListener('input', updateSummary);
        
        function updateSummary() {
            const amount = parseFloat(amountInput.value) || 0;
            const fee = 2.00;
            const net = Math.max(0, amount - fee);
            
            document.getElementById('summary_amount').textContent = `$${amount.toFixed(2)}`;
            document.getElementById('summary_net').textContent = `$${net.toFixed(2)}`;
        }
        
        // Initialize summary
        updateSummary();
    }
    
    // Form validation
    if (form) {
        form.addEventListener('submit', function(e) {
            const amount = parseFloat(amountInput.value);
            const maxBalance = <?php echo $hyip_user->balance; ?>;
            const paymentDetails = document.getElementById('payment_details').value.trim();
            
            if (amount < 10) {
                e.preventDefault();
                showAlert('Minimum withdrawal amount is $10.00', 'danger');
                return false;
            }
            
            if (amount > maxBalance) {
                e.preventDefault();
                showAlert('Withdrawal amount exceeds your available balance', 'danger');
                return false;
            }
            
            if (!paymentDetails) {
                e.preventDefault();
                showAlert('Please provide your payment details', 'danger');
                return false;
            }
            
            // Confirm withdrawal
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod && !confirm(`Confirm withdrawal of $${amount.toFixed(2)} via ${selectedMethod.value.replace('_', ' ')}?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php get_footer(); ?>