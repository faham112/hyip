<?php
/**
 * Template Name: Profile Page
 */

// Security check
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Handle profile update
if (isset($_POST['update_profile']) && wp_verify_nonce($_POST['profile_nonce'], 'update_profile')) {
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $user_email = sanitize_email($_POST['user_email']);
    
    // Update user data
    $user_data = array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'user_email' => $user_email,
        'display_name' => $first_name . ' ' . $last_name
    );
    
    $result = wp_update_user($user_data);
    
    if (!is_wp_error($result)) {
        $success_message = 'Profile updated successfully!';
        $current_user = get_user_by('ID', $user_id); // Refresh user data
    } else {
        $error_message = 'Profile update failed: ' . $result->get_error_message();
    }
}

// Handle password change
if (isset($_POST['change_password']) && wp_verify_nonce($_POST['password_nonce'], 'change_password')) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (wp_check_password($current_password, $current_user->user_pass, $user_id)) {
        if ($new_password === $confirm_password && strlen($new_password) >= 8) {
            wp_set_password($new_password, $user_id);
            $password_success = 'Password changed successfully!';
        } else {
            $password_error = 'New passwords do not match or are too short (minimum 8 characters).';
        }
    } else {
        $password_error = 'Current password is incorrect.';
    }
}

global $wpdb;
$table_users = $wpdb->prefix . 'hyip_users';
$hyip_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="page-header" style="color: white; text-align: center; margin-bottom: 3rem;">
            <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
            <p>Manage your account information and preferences</p>
        </div>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-8">
                <div class="card">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-user-edit"></i> Personal Information
                    </h2>
                    
                    <?php if (isset($success_message)) : ?>
                        <div class="alert alert-success"><?php echo esc_html($success_message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)) : ?>
                        <div class="alert alert-danger"><?php echo esc_html($error_message); ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <?php wp_nonce_field('update_profile', 'profile_nonce'); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?php echo esc_attr($current_user->first_name); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?php echo esc_attr($current_user->last_name); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_email">Email Address</label>
                            <input type="email" id="user_email" name="user_email" class="form-control" 
                                   value="<?php echo esc_attr($current_user->user_email); ?>" required>
                            <small class="form-text text-muted">This email is used for login and notifications.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_login">Username</label>
                            <input type="text" id="user_login" class="form-control" 
                                   value="<?php echo esc_attr($current_user->user_login); ?>" disabled>
                            <small class="form-text text-muted">Username cannot be changed.</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <!-- Password Change -->
                <div class="card" style="margin-top: 2rem;">
                    <h2 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-lock"></i> Change Password
                    </h2>
                    
                    <?php if (isset($password_success)) : ?>
                        <div class="alert alert-success"><?php echo esc_html($password_success); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($password_error)) : ?>
                        <div class="alert alert-danger"><?php echo esc_html($password_error); ?></div>
                    <?php endif; ?>
                    
                    <form id="passwordForm" method="post">
                        <?php wp_nonce_field('change_password', 'password_nonce'); ?>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           minlength="8" required>
                                    <small class="form-text text-muted">Minimum 8 characters required.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           minlength="8" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-danger">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <h3 style="margin-bottom: 2rem; color: #333;">
                        <i class="fas fa-chart-pie"></i> Account Overview
                    </h3>
                    
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2); width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin: 0 auto 1rem;">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4><?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?></h4>
                        <p class="text-muted"><?php echo esc_html($current_user->user_email); ?></p>
                    </div>
                    
                    <div class="account-stats">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <span>Member Since:</span>
                            <strong><?php echo date('M Y', strtotime($current_user->user_registered)); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <span>Account Status:</span>
                            <span class="badge badge-success"><?php echo ucfirst($hyip_user->status ?? 'active'); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <span>Current Balance:</span>
                            <strong style="color: #28a745;">$<?php echo number_format($hyip_user->balance ?? 0, 2); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Total Invested:</span>
                            <strong>$<?php echo number_format($hyip_user->total_invested ?? 0, 2); ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card" style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <a href="<?php echo home_url('/dashboard'); ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                        <a href="<?php echo home_url('/invest'); ?>" class="btn btn-success" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-plus"></i> New Investment
                        </a>
                        <a href="<?php echo home_url('/withdraw'); ?>" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-money-bill-wave"></i> Withdraw Funds
                        </a>
                    </div>
                </div>
                
                <!-- Security Tips -->
                <div style="background: #e7f3ff; border: 1px solid #bee5eb; border-radius: 8px; padding: 1.5rem; margin-top: 2rem;">
                    <h5 style="color: #0066cc; margin-bottom: 1rem;">
                        <i class="fas fa-shield-alt"></i> Security Tips
                    </h5>
                    <ul style="font-size: 0.9rem; color: #333; margin: 0; padding-left: 1.5rem;">
                        <li>Use a strong, unique password</li>
                        <li>Never share your login credentials</li>
                        <li>Log out when using public computers</li>
                        <li>Keep your email address updated</li>
                        <li>Contact support for suspicious activity</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    // Password confirmation validation
    passwordForm.addEventListener('submit', function(e) {
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            showAlert('New passwords do not match!', 'danger');
            return false;
        }
        
        if (newPassword.value.length < 8) {
            e.preventDefault();
            showAlert('New password must be at least 8 characters long!', 'danger');
            return false;
        }
        
        // Confirm password change
        if (!confirm('Are you sure you want to change your password?')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Real-time password match checking
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    });
    
    newPassword.addEventListener('input', function() {
        if (confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    });
});
</script>

<?php get_footer(); ?>