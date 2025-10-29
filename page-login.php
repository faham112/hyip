<?php
/**
 * Template Name: Login Page
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    if (user_can($user, 'manage_options')) {
        wp_redirect(home_url('/admin'));
    } else {
        wp_redirect(home_url('/dashboard'));
    }
    exit;
}

$login_error = '';
if (isset($_POST['wp-submit'])) {
    $credentials = array(
        'user_login'    => sanitize_user($_POST['log']),
        'user_password' => $_POST['pwd'],
        'remember'      => isset($_POST['rememberme'])
    );

    $user = wp_signon($credentials, false);

    if (is_wp_error($user)) {
        $login_error = $user->get_error_message();
    } else {
        // Redirection is handled by hyip_login_redirect_handler filter
        // No explicit redirect here, as the filter will take care of it.
        // If for some reason the filter doesn't fire, a fallback to dashboard.
        if (user_can($user, 'manage_options')) {
            wp_redirect(home_url('/admin'));
        } else {
            wp_redirect(home_url('/dashboard'));
        }
        exit;
    }
}

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="auth-form">
                    <h2><i class="fas fa-sign-in-alt"></i> Login to Your Account</h2>
                    
                    <?php
                    // Display custom login errors
                    if (!empty($login_error)) {
                        echo '<div class="alert alert-danger">' . esc_html($login_error) . '</div>';
                    }
                    // Display other messages
                    if (isset($_GET['registration']) && $_GET['registration'] == 'complete') {
                        echo '<div class="alert alert-success">Registration completed! You can now login.</div>';
                    }
                    if (isset($_GET['password']) && $_GET['password'] == 'changed') {
                        echo '<div class="alert alert-success">Password changed successfully! Please login with your new password.</div>';
                    }
                    ?>
                    
                    <form name="loginform" id="loginform" action="<?php echo esc_url(get_permalink()); ?>" method="post">
                        <div class="form-group">
                            <label for="user_login">Username or Email Address</label>
                            <input type="text" name="log" id="user_login" class="form-control" value="<?php echo esc_attr(wp_unslash($_POST['log'] ?? '')); ?>" size="20" autocapitalize="off" required />
                        </div>
                        
                        <div class="form-group">
                            <label for="user_pass">Password</label>
                            <input type="password" name="pwd" id="user_pass" class="form-control" value="" size="20" required />
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input name="rememberme" type="checkbox" id="rememberme" value="forever" />
                                <span class="checkmark"></span>
                                Remember Me
                            </label>
                        </div>
                        
                        <input type="hidden" name="testcookie" value="1" />
                        
                        <button type="submit" name="wp-submit" id="wp-submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>
                    </form>
                    
                    <div class="text-center" style="margin-top: 2rem;">
                        <p><a href="<?php echo wp_lostpassword_url(); ?>">Forgot your password?</a></p>
                        <p>Don't have an account? <a href="<?php echo wp_registration_url(); ?>">Create one here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div style="background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 2rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
                    <h3 style="color: #333; margin-bottom: 2rem; text-align: center;">Why Join Us?</h3>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: linear-gradient(135deg, #667eea, #764ba2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h4 style="color: #333; margin-bottom: 0.25rem;">Secure Platform</h4>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Bank-level security protects your investments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: linear-gradient(135deg, #28a745, #20c997); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h4 style="color: #333; margin-bottom: 0.25rem;">High Returns</h4>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Daily returns up to 7.5% on investments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: linear-gradient(135deg, #fd7e14, #e83e8c); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <h4 style="color: #333; margin-bottom: 0.25rem;">Instant Payouts</h4>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Quick withdrawal processing</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: linear-gradient(135deg, #6f42c1, #d63384); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div>
                                <h4 style="color: #333; margin-bottom: 0.25rem;">24/7 Support</h4>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Round-the-clock customer assistance</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1.5rem; border-radius: 10px;">
                            <h4 style="margin-bottom: 0.5rem;">Ready to Start?</h4>
                            <p style="margin-bottom: 1rem; font-size: 0.9rem;">Join thousands of successful investors</p>
                            <a href="<?php echo wp_registration_url(); ?>" class="btn" style="background: white; color: #667eea; text-decoration: none;">
                                Create Free Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    gap: 0.5rem;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
}
</style>

<?php get_footer(); ?>
