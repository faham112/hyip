<?php
/**
 * Template Name: Investment Page
 */

// Security check
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

global $wpdb;
$table_plans = $wpdb->prefix . 'hyip_plans';
$table_users = $wpdb->prefix . 'hyip_users';

// Get investment plans
$plans = $wpdb->get_results("SELECT * FROM $table_plans WHERE status = 'active' ORDER BY min_amount ASC");

// Get user balance
$hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
if (!$hyip_user) {
    $wpdb->insert($table_users, array('user_id' => $user_id));
    $hyip_user = (object) array('balance' => 0);
}

// Handle investment form submission
if (isset($_POST['submit_investment']) && wp_verify_nonce($_POST['investment_nonce'], 'create_investment')) {
    $plan_id = intval($_POST['plan_id']);
    $amount = floatval($_POST['amount']);
    
    // Validate plan
    $selected_plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_plans WHERE id = %d AND status = 'active'", $plan_id));
    
    if ($selected_plan && $amount >= $selected_plan->min_amount && $amount <= $selected_plan->max_amount && $amount <= $hyip_user->balance) {
        $daily_profit = ($amount * $selected_plan->daily_percent) / 100;
        
        // Create investment
        $table_investments = $wpdb->prefix . 'hyip_investments';
        $investment_result = $wpdb->insert(
            $table_investments,
            array(
                'user_id' => $user_id,
                'plan_id' => $plan_id,
                'amount' => $amount,
                'daily_profit' => $daily_profit,
                'next_profit_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
            )
        );
        
        if ($investment_result) {
            // Update user balance and total invested
            $wpdb->update(
                $table_users,
                array(
                    'balance' => $hyip_user->balance - $amount,
                    'total_invested' => $hyip_user->total_invested + $amount
                ),
                array('user_id' => $user_id)
            );
            
            // Log transaction
            $table_transactions = $wpdb->prefix . 'hyip_transactions';
            $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $user_id,
                    'type' => 'investment',
                    'amount' => $amount,
                    'description' => 'Investment in ' . $selected_plan->name,
                    'reference_id' => $wpdb->insert_id,
                    'status' => 'completed'
                )
            );
            
            $success_message = 'Investment created successfully! Your daily profits will start tomorrow.';
            // Refresh user data
            $hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
        } else {
            $error_message = 'Investment creation failed. Please try again.';
        }
    } else {
        $error_message = 'Invalid investment details. Please check the amount and your balance.';
    }
}

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="page-header" style="color: white; text-align: center; margin-bottom: 3rem;">
            <h1><i class="fas fa-chart-line"></i> Investment Plans</h1>
            <p>Choose the perfect plan for your investment goals</p>
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

        <div class="plans-grid">
            <?php foreach ($plans as $plan) : 
                $daily_profit_min = ($plan->min_amount * $plan->daily_percent) / 100;
                $total_return_min = $plan->min_amount + ($daily_profit_min * $plan->duration);
                $roi = (($daily_profit_min * $plan->duration) / $plan->min_amount) * 100;
            ?>
            <div class="plan-card" data-plan-id="<?php echo $plan->id; ?>">
                <div style="text-align: center; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; margin-bottom: 1rem;">
                    <h3 style="color: #333; margin-bottom: 0.5rem;"><?php echo esc_html($plan->name); ?></h3>
                    <div class="plan-percentage" style="color: #667eea;"><?php echo esc_html($plan->daily_percent); ?>%</div>
                    <p style="color: #666; margin: 0;">Daily for <?php echo esc_html($plan->duration); ?> days</p>
                </div>
                
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                    <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">Investment Range</div>
                    <strong style="font-size: 1.3rem;">$<?php echo number_format($plan->min_amount); ?> - $<?php echo number_format($plan->max_amount); ?></strong>
                </div>
                
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                        <div>
                            <strong>Min Daily Profit:</strong><br>
                            $<?php echo number_format($daily_profit_min, 2); ?>
                        </div>
                        <div>
                            <strong>Total ROI:</strong><br>
                            <?php echo number_format($roi, 1); ?>%
                        </div>
                        <div>
                            <strong>Total Return:</strong><br>
                            $<?php echo number_format($total_return_min, 2); ?>
                        </div>
                        <div>
                            <strong>Duration:</strong><br>
                            <?php echo $plan->duration; ?> days
                        </div>
                    </div>
                </div>
                
                <ul style="list-style: none; padding: 0; margin-bottom: 1.5rem; color: #333;">
                    <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Daily Profit Payouts</li>
                    <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Principal Return</li>
                    <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Instant Withdrawals</li>
                    <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> 24/7 Customer Support</li>
                </ul>
                
                <button type="button" class="btn btn-primary select-plan-btn" 
                        data-plan-id="<?php echo $plan->id; ?>"
                        data-plan-name="<?php echo esc_attr($plan->name); ?>"
                        data-min-amount="<?php echo $plan->min_amount; ?>"
                        data-max-amount="<?php echo $plan->max_amount; ?>"
                        data-daily-percent="<?php echo $plan->daily_percent; ?>"
                        data-duration="<?php echo $plan->duration; ?>"
                        style="width: 100%;">
                    <i class="fas fa-rocket"></i> Select This Plan
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Investment Form Modal -->
        <div id="investmentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
            <div style="background: white; border-radius: 15px; padding: 2rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="margin: 0; color: #333;">
                        <i class="fas fa-plus-circle"></i> Make Investment
                    </h2>
                    <button type="button" id="closeModal" style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer;">&times;</button>
                </div>
                
                <form id="investmentForm" method="post">
                    <?php wp_nonce_field('create_investment', 'investment_nonce'); ?>
                    
                    <div class="form-group">
                        <label for="selected_plan_display">Selected Plan</label>
                        <input type="text" id="selected_plan_display" class="form-control" readonly>
                        <input type="hidden" id="plan_id" name="plan_id">
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Investment Amount ($)</label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" required>
                        <small id="amount_range" class="form-text text-muted"></small>
                        <small class="form-text text-muted">Your balance: $<?php echo number_format($hyip_user->balance, 2); ?></small>
                    </div>
                    
                    <div id="investment_summary" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;">
                        <h4 style="margin-bottom: 1rem; color: #333;">Investment Summary</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <strong>Investment Amount:</strong><br>
                                <span id="summary_amount">$0.00</span>
                            </div>
                            <div>
                                <strong>Daily Profit:</strong><br>
                                <span id="summary_daily">$0.00</span>
                            </div>
                            <div>
                                <strong>Total Profit:</strong><br>
                                <span id="summary_profit">$0.00</span>
                            </div>
                            <div>
                                <strong>Total Return:</strong><br>
                                <span id="summary_total">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($hyip_user->balance <= 0) : ?>
                        <div class="alert alert-warning">
                            <strong>Insufficient Balance!</strong> You need to add funds to your account before making an investment.
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" id="cancelInvestment" class="btn btn-secondary">Cancel</button>
                        <button type="submit" name="submit_investment" class="btn btn-success" <?php echo $hyip_user->balance <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i> Confirm Investment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('investmentModal');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelInvestment');
    const selectBtns = document.querySelectorAll('.select-plan-btn');
    const amountInput = document.getElementById('amount');
    const userBalance = <?php echo $hyip_user->balance; ?>;
    
    let selectedPlan = null;
    
    // Plan selection
    selectBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            selectedPlan = {
                id: this.dataset.planId,
                name: this.dataset.planName,
                minAmount: parseFloat(this.dataset.minAmount),
                maxAmount: parseFloat(this.dataset.maxAmount),
                dailyPercent: parseFloat(this.dataset.dailyPercent),
                duration: parseInt(this.dataset.duration)
            };
            
            document.getElementById('selected_plan_display').value = selectedPlan.name;
            document.getElementById('plan_id').value = selectedPlan.id;
            document.getElementById('amount_range').textContent = 
                `Range: $${selectedPlan.minAmount.toLocaleString()} - $${selectedPlan.maxAmount.toLocaleString()}`;
            
            amountInput.min = selectedPlan.minAmount;
            amountInput.max = Math.min(selectedPlan.maxAmount, userBalance);
            amountInput.value = selectedPlan.minAmount;
            
            updateSummary();
            modal.style.display = 'flex';
        });
    });
    
    // Close modal
    closeModal.addEventListener('click', () => modal.style.display = 'none');
    cancelBtn.addEventListener('click', () => modal.style.display = 'none');
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
    });
    
    // Amount input validation and summary update
    amountInput.addEventListener('input', updateSummary);
    
    function updateSummary() {
        if (!selectedPlan) return;
        
        const amount = parseFloat(amountInput.value) || 0;
        const dailyProfit = (amount * selectedPlan.dailyPercent) / 100;
        const totalProfit = dailyProfit * selectedPlan.duration;
        const totalReturn = amount + totalProfit;
        
        document.getElementById('summary_amount').textContent = `$${amount.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('summary_daily').textContent = `$${dailyProfit.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('summary_profit').textContent = `$${totalProfit.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('summary_total').textContent = `$${totalReturn.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        
        // Validate amount
        const submitBtn = document.querySelector('button[name="submit_investment"]');
        if (amount < selectedPlan.minAmount || amount > selectedPlan.maxAmount || amount > userBalance) {
            submitBtn.disabled = true;
            if (amount > userBalance) {
                showAlert('Insufficient balance for this investment amount.', 'warning');
            }
        } else {
            submitBtn.disabled = false;
        }
    }
    
    // Form validation
    document.getElementById('investmentForm').addEventListener('submit', function(e) {
        const amount = parseFloat(amountInput.value);
        
        if (!selectedPlan) {
            e.preventDefault();
            showAlert('Please select an investment plan.', 'danger');
            return false;
        }
        
        if (amount < selectedPlan.minAmount || amount > selectedPlan.maxAmount) {
            e.preventDefault();
            showAlert(`Investment amount must be between $${selectedPlan.minAmount} and $${selectedPlan.maxAmount}.`, 'danger');
            return false;
        }
        
        if (amount > userBalance) {
            e.preventDefault();
            showAlert('Insufficient account balance.', 'danger');
            return false;
        }
        
        return true;
    });
});
</script>

<?php get_footer(); ?>