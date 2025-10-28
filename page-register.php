<?php
/**
 * Template Name: Register Page
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

// Handle the registration form
$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = hyip_handle_registration();
}


get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="auth-form">
                    <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
                    <p style="text-align: center; margin-bottom: 2rem; color: #666;">
                        Join our investment platform and start earning today!
                    </p>
                    
                    <?php
                    // Display registration messages
                    if (!empty($errors)) {
                        echo '<div class="alert alert-danger">';
                        foreach ($errors as $error) {
                            echo '<p>' . esc_html($error) . '</p>';
                        }
                        echo '</div>';
                    }
                    if (isset($_GET['registration']) && $_GET['registration'] == 'failed') {
                        echo '<div class="alert alert-danger">Registration failed. Please check your information and try again.</div>';
                    }
                    if (isset($_GET['registration']) && $_GET['registration'] == 'disabled') {
                        echo '<div class="alert alert-danger">User registration is currently disabled.</div>';
                    }
                    ?>
                    
                    <form name="registerform" id="registerform" action="<?php echo esc_url(get_permalink()); ?>" method="post">
                        <?php wp_nonce_field('hyip_register', 'register_nonce'); ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_login">Username <span style="color: red;">*</span></label>
                                    <input type="text" name="user_login" id="user_login" class="form-control" value="<?php echo esc_attr(wp_unslash($_POST['user_login'] ?? '')); ?>" size="20" autocapitalize="off" required />
                                    <small class="form-text text-muted">Username must be unique and cannot be changed later.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_email">Email Address <span style="color: red;">*</span></label>
                                    <input type="email" name="user_email" id="user_email" class="form-control" value="<?php echo esc_attr(wp_unslash($_POST['user_email'] ?? '')); ?>" size="25" required />
                                    <small class="form-text text-muted">We'll send your login details to this email.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo esc_attr(wp_unslash($_POST['first_name'] ?? '')); ?>" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo esc_attr(wp_unslash($_POST['last_name'] ?? '')); ?>" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_pass">Password <span style="color: red;">*</span></label>
                            <input type="password" name="user_pass" id="user_pass" class="form-control" size="20" required />
                            <small class="form-text text-muted">Password should be at least 8 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span style="color: red;">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" size="20" required />
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms_agree" id="terms_agree" value="1" required />
                                <span class="checkmark"></span>
                                I agree to the <a href="#terms" target="_blank">Terms of Service</a> and <a href="#privacy" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="marketing_agree" id="marketing_agree" value="1" />
                                <span class="checkmark"></span>
                                I would like to receive marketing communications and investment updates
                            </label>
                        </div>
                        
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr(home_url('/login?registration=complete')); ?>" />
                        
                        <button type="submit" name="register_submit" id="register_submit" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </form>
                    
                    <div class="text-center" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e9ecef;">
                        <p>Already have an account? <a href="<?php echo home_url('/login'); ?>">Sign in here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div style="background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 2rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
                    <h3 style="color: #333; margin-bottom: 2rem; text-align: center;">Account Benefits</h3>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="background: #28a745; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h5 style="color: #333; margin-bottom: 0.25rem;">Free Account Setup</h5>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">No setup fees or hidden charges</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="background: #667eea; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h5 style="color: #333; margin-bottom: 0.25rem;">Multiple Investment Plans</h5>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Choose from 4 different investment plans</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="background: #fd7e14; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h5 style="color: #333; margin-bottom: 0.25rem;">Daily Profit Payouts</h5>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Automatic daily earnings to your balance</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="background: #dc3545; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h5 style="color: #333; margin-bottom: 0.25rem;">Instant Withdrawals</h5>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Quick access to your earnings</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="background: #6f42c1; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h5 style="color: #333; margin-bottom: 0.25rem;">Secure Platform</h5>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">SSL encryption and advanced security</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1.5rem; border-radius: 10px; text-align: center; margin-top: 2rem;">
                        <h4 style="margin-bottom: 0.5rem;">Start Earning Today!</h4>
                        <p style="margin: 0; font-size: 0.9rem;">Join <?php echo number_format(1247 + rand(1, 500)); ?>+ successful investors</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerform');
    const password = document.getElementById('user_pass');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            showAlert('Passwords do not match!', 'danger');
            return false;
        }
        
        if (password.value.length < 8) {
            e.preventDefault();
            showAlert('Password must be at least 8 characters long!', 'danger');
            return false;
        }
        
        if (!document.getElementById('terms_agree').checked) {
            e.preventDefault();
            showAlert('You must agree to the Terms of Service!', 'danger');
            return false;
        }
    });
    
    confirmPassword.addEventListener('blur', function() {
        if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
            showAlert('Passwords do not match!', 'warning');
        }
    });
});
</script>

<style>
.checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    gap: 0.5rem;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
}

.auth-form {
    max-width: none;
}

@media (max-width: 768px) {
    .row .col-md-6, .row .col-md-4 {
        margin-bottom: 1rem;
    }
}
</style>

<?php get_footer(); ?>
