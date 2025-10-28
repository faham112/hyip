<?php
/**
 * The main template file
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="hero-section">
            <h1 class="hero-title">Welcome to HYIP Manager</h1>
            <p class="hero-subtitle">Professional Investment Platform</p>
            <p style="color: white; font-size: 1.1rem; margin-bottom: 2rem;">
                Start your investment journey with our secure and profitable platform. 
                Choose from multiple investment plans designed to maximize your returns.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo home_url('/invest'); ?>" class="btn btn-primary">Start Investing</a>
                <a href="<?php echo home_url('/dashboard'); ?>" class="btn btn-secondary">View Dashboard</a>
            </div>
        </div>

        <div class="dashboard-stats">
            <?php
            global $wpdb;
            $table_users = $wpdb->prefix . 'hyip_users';
            $table_investments = $wpdb->prefix . 'hyip_investments';
            
            $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'active'");
            $total_invested = $wpdb->get_var("SELECT SUM(amount) FROM $table_investments WHERE status IN ('active', 'completed')");
            $total_earned = $wpdb->get_var("SELECT SUM(total_earned) FROM $table_users");
            $active_investments = $wpdb->get_var("SELECT COUNT(*) FROM $table_investments WHERE status = 'active'");
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_users ?: 0); ?>+</div>
                <p>Happy Investors</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_invested ?: 0, 2); ?></div>
                <p>Total Invested</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_earned ?: 0, 2); ?></div>
                <p>Total Earned</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($active_investments ?: 0); ?></div>
                <p>Active Investments</p>
            </div>
        </div>

        <div class="plans-section">
            <h2 style="text-align: center; color: white; margin-bottom: 3rem; font-size: 2.5rem;">Investment Plans</h2>
            
            <div class="plans-grid">
                <?php
                $table_plans = $wpdb->prefix . 'hyip_plans';
                $plans = $wpdb->get_results("SELECT * FROM $table_plans WHERE status = 'active' ORDER BY min_amount ASC");
                
                foreach ($plans as $plan) :
                    $daily_profit = ($plan->min_amount * $plan->daily_percent) / 100;
                    $total_return = $plan->min_amount + ($daily_profit * $plan->duration);
                ?>
                <div class="plan-card">
                    <h3><?php echo esc_html($plan->name); ?></h3>
                    <div class="plan-percentage"><?php echo esc_html($plan->daily_percent); ?>%</div>
                    <p style="color: #666; margin-bottom: 1rem;">Daily for <?php echo esc_html($plan->duration); ?> days</p>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong>$<?php echo number_format($plan->min_amount); ?> - $<?php echo number_format($plan->max_amount); ?></strong>
                    </div>
                    
                    <ul style="list-style: none; padding: 0; margin-bottom: 1.5rem; text-align: left;">
                        <li>✓ Daily Returns</li>
                        <li>✓ Principal Return</li>
                        <li>✓ Instant Withdrawal</li>
                        <li>✓ 24/7 Support</li>
                    </ul>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                        <p>Investment: <strong>$<?php echo number_format($plan->min_amount); ?></strong></p>
                        <p>Daily Profit: <strong>$<?php echo number_format($daily_profit, 2); ?></strong></p>
                        <p>Total Return: <strong>$<?php echo number_format($total_return, 2); ?></strong></p>
                    </div>
                    
                    <a href="<?php echo home_url('/invest?plan=' . $plan->id); ?>" class="btn btn-primary" style="width: 100%;">
                        Invest Now
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="margin-top: 4rem;">
            <div class="row">
                <div class="col-md-8">
                    <h2 style="color: #333; margin-bottom: 1.5rem;">Why Choose Our Platform?</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">🔒 Secure & Safe</h4>
                                <p>Your investments are protected with bank-level security and encryption.</p>
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">⚡ Instant Payouts</h4>
                                <p>Automatic daily payouts directly to your account balance.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">📱 Mobile Friendly</h4>
                                <p>Access your account anywhere, anytime from any device.</p>
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">🎯 High Returns</h4>
                                <p>Competitive returns with transparent fee structure.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem; border-radius: 15px;">
                        <h3>Start Today!</h3>
                        <p>Join thousands of satisfied investors</p>
                        <a href="<?php echo wp_registration_url(); ?>" class="btn" style="background: white; color: #667eea; margin-top: 1rem;">
                            Register Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>