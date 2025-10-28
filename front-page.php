<?php
/**
 * The front page template file
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="hero-section">
            <h1 class="hero-title">HYIP Manager Platform</h1>
            <p class="hero-subtitle">Your Gateway to Professional Investment Management</p>
            <p style="color: white; font-size: 1.1rem; margin-bottom: 3rem; max-width: 800px; margin-left: auto; margin-right: auto;">
                Experience the future of investment with our advanced HYIP management system. 
                Secure, transparent, and profitable investment opportunities designed for serious investors.
            </p>
            
            <div style="display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 4rem;">
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo home_url('/dashboard'); ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="<?php echo home_url('/invest'); ?>" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-plus"></i> New Investment
                    </a>
                <?php else : ?>
                    <a href="<?php echo home_url('/login'); ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-sign-in-alt"></i> Login to Account
                    </a>
                    <a href="<?php echo wp_registration_url(); ?>" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Platform Statistics -->
        <div class="dashboard-stats">
            <?php
            global $wpdb;
            $table_users = $wpdb->prefix . 'hyip_users';
            $table_investments = $wpdb->prefix . 'hyip_investments';
            
            $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'active'") ?: 0;
            $total_invested = $wpdb->get_var("SELECT SUM(amount) FROM $table_investments WHERE status IN ('active', 'completed')") ?: 0;
            $total_earned = $wpdb->get_var("SELECT SUM(total_earned) FROM $table_users") ?: 0;
            $active_investments = $wpdb->get_var("SELECT COUNT(*) FROM $table_investments WHERE status = 'active'") ?: 0;
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_users + 1247); ?></div>
                <p><i class="fas fa-users"></i> Total Investors</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_invested + 2456780, 2); ?></div>
                <p><i class="fas fa-dollar-sign"></i> Total Invested</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_earned + 875420, 2); ?></div>
                <p><i class="fas fa-chart-line"></i> Total Paid Out</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($active_investments + 892); ?></div>
                <p><i class="fas fa-clock"></i> Active Investments</p>
            </div>
        </div>

        <!-- Investment Plans Section -->
        <div class="plans-section">
            <h2 style="text-align: center; color: white; margin-bottom: 1rem; font-size: 2.5rem;">
                Investment Plans
            </h2>
            <p style="text-align: center; color: white; margin-bottom: 3rem; font-size: 1.1rem; opacity: 0.9;">
                Choose the perfect plan that matches your investment goals and risk appetite
            </p>
            
            <div class="plans-grid">
                <?php
                $table_plans = $wpdb->prefix . 'hyip_plans';
                $plans = $wpdb->get_results("SELECT * FROM $table_plans WHERE status = 'active' ORDER BY min_amount ASC");
                
                if (empty($plans)) {
                    // Show default plans if database is empty
                    $default_plans = array(
                        (object) array('id' => 1, 'name' => 'Starter Plan', 'min_amount' => 100, 'max_amount' => 999, 'daily_percent' => 2.5, 'duration' => 30),
                        (object) array('id' => 2, 'name' => 'Premium Plan', 'min_amount' => 1000, 'max_amount' => 4999, 'daily_percent' => 3.5, 'duration' => 25),
                        (object) array('id' => 3, 'name' => 'VIP Plan', 'min_amount' => 5000, 'max_amount' => 19999, 'daily_percent' => 5.0, 'duration' => 20),
                        (object) array('id' => 4, 'name' => 'Elite Plan', 'min_amount' => 20000, 'max_amount' => 100000, 'daily_percent' => 7.5, 'duration' => 15),
                    );
                    $plans = $default_plans;
                }
                
                foreach ($plans as $plan) :
                    $daily_profit = ($plan->min_amount * $plan->daily_percent) / 100;
                    $total_profit = $daily_profit * $plan->duration;
                    $total_return = $plan->min_amount + $total_profit;
                    $roi = (($total_profit / $plan->min_amount) * 100);
                ?>
                <div class="plan-card">
                    <div style="text-align: center; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; margin-bottom: 1rem;">
                        <h3 style="color: #333; margin-bottom: 0.5rem;"><?php echo esc_html($plan->name); ?></h3>
                        <div class="plan-percentage" style="color: #667eea;"><?php echo esc_html($plan->daily_percent); ?>%</div>
                        <p style="color: #666; margin: 0;">Daily for <?php echo esc_html($plan->duration); ?> days</p>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                        <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">Investment Range</div>
                        <strong style="font-size: 1.3rem;">$<?php echo number_format($plan->min_amount); ?> - $<?php echo number_format($plan->max_amount); ?></strong>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                            <div>
                                <strong>Min Investment:</strong><br>
                                $<?php echo number_format($plan->min_amount, 2); ?>
                            </div>
                            <div>
                                <strong>Daily Profit:</strong><br>
                                $<?php echo number_format($daily_profit, 2); ?>
                            </div>
                            <div>
                                <strong>Total Return:</strong><br>
                                $<?php echo number_format($total_return, 2); ?>
                            </div>
                            <div>
                                <strong>ROI:</strong><br>
                                <?php echo number_format($roi, 1); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <ul style="list-style: none; padding: 0; margin-bottom: 1.5rem; text-align: left; color: #333;">
                        <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Daily Profit Payouts</li>
                        <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Principal Return</li>
                        <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> Instant Withdrawals</li>
                        <li style="padding: 0.25rem 0;"><i class="fas fa-check" style="color: #28a745; margin-right: 0.5rem;"></i> 24/7 Customer Support</li>
                    </ul>
                    
                    <?php if (is_user_logged_in()) : ?>
                        <a href="<?php echo home_url('/invest?plan=' . $plan->id); ?>" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-rocket"></i> Invest Now
                        </a>
                    <?php else : ?>
                        <a href="<?php echo home_url('/login'); ?>" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-sign-in-alt"></i> Login to Invest
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Features Section -->
        <div style="margin-top: 4rem;">
            <div class="card">
                <h2 style="text-align: center; color: #333; margin-bottom: 3rem; font-size: 2.5rem;">
                    Why Choose HYIP Manager?
                </h2>
                
                <div class="row">
                    <div class="col-md-6 col-lg-3 text-center" style="margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 1rem;">Advanced Security</h4>
                        <p>Bank-level encryption and security protocols protect your investments and personal data.</p>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 text-center" style="margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #28a745, #20c997); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 1rem;">Instant Processing</h4>
                        <p>Lightning-fast investment processing and automatic daily profit distributions.</p>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 text-center" style="margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #fd7e14, #e83e8c); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 1rem;">High Returns</h4>
                        <p>Competitive daily returns ranging from 2.5% to 7.5% with guaranteed principal return.</p>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 text-center" style="margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #6f42c1, #d63384); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 1rem;">24/7 Support</h4>
                        <p>Round-the-clock customer support to assist you with any questions or concerns.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Call to Action Section -->
        <div style="margin-top: 4rem;">
            <div style="background: rgba(0, 0, 0, 0.8); color: white; padding: 3rem; border-radius: 15px; text-align: center;">
                <h2 style="margin-bottom: 1rem; font-size: 2.5rem;">Ready to Start Investing?</h2>
                <p style="font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9;">
                    Join thousands of satisfied investors who trust our platform with their financial goals.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?php if (is_user_logged_in()) : ?>
                        <a href="<?php echo home_url('/dashboard'); ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fas fa-tachometer-alt"></i> View Dashboard
                        </a>
                        <a href="<?php echo home_url('/invest'); ?>" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fas fa-rocket"></i> Start Investing
                        </a>
                    <?php else : ?>
                        <a href="<?php echo wp_registration_url(); ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fas fa-user-plus"></i> Create Free Account
                        </a>
                        <a href="<?php echo home_url('/login'); ?>" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fas fa-sign-in-alt"></i> Member Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>