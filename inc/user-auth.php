<?php
/**
 * User Authentication Functions for HYIP Manager
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom login redirect for HYIP users
 */
function hyip_login_redirect_handler($redirect_to, $request, $user) {
    if (!is_wp_error($user)) {
        // Create/update HYIP user profile on login
        hyip_ensure_user_profile($user->ID);
        
        // Admin users go to admin dashboard
        if (user_can($user, 'manage_options')) {
            return home_url('/admin');
        }
        
        // Regular users go to user dashboard
        return home_url('/dashboard');
    }
    
    return $redirect_to;
}
add_filter('login_redirect', 'hyip_login_redirect_handler', 10, 3);

/**
 * Custom registration handling
 */
function hyip_user_registration_handler($user_id) {
    // Create HYIP user profile
    hyip_ensure_user_profile($user_id);
    
    // Send welcome email
    $user = get_user_by('ID', $user_id);
    if ($user) {
        hyip_send_welcome_email($user);
    }
    
    // Log registration
    hyip_log_user_action($user_id, 'user_registered', 'User account created');
}
add_action('user_register', 'hyip_user_registration_handler');

/**
 * Ensure user has HYIP profile
 */
function hyip_ensure_user_profile($user_id) {
    global $wpdb;
    $table_users = $wpdb->prefix . 'hyip_users';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE user_id = %d", $user_id));
    
    if (!$existing) {
        $wpdb->insert($table_users, array(
            'user_id' => $user_id,
            'balance' => 0.00,
            'total_invested' => 0.00,
            'total_earned' => 0.00,
            'total_withdrawn' => 0.00,
            'status' => 'active',
            'joined_date' => current_time('mysql')
        ));
    }
}

/**
 * Send welcome email to new users
 */
function hyip_send_welcome_email($user) {
    $subject = 'Welcome to ' . get_bloginfo('name') . '!';
    $message = '
        <h2>Welcome to our HYIP Investment Platform!</h2>
        <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
        <p>Thank you for joining our investment platform. Your account has been successfully created and is ready for use.</p>
        
        <h3>Getting Started:</h3>
        <ol>
            <li><strong>Login to your account:</strong> <a href="' . home_url('/login') . '">Login Here</a></li>
            <li><strong>Explore investment plans:</strong> Choose from our 4 different investment plans</li>
            <li><strong>Make your first investment:</strong> Start earning daily profits immediately</li>
            <li><strong>Track your progress:</strong> Monitor your investments from your dashboard</li>
        </ol>
        
        <h3>Your Account Details:</h3>
        <ul>
            <li><strong>Username:</strong> ' . esc_html($user->user_login) . '</li>
            <li><strong>Email:</strong> ' . esc_html($user->user_email) . '</li>
            <li><strong>Registration Date:</strong> ' . date('F j, Y') . '</li>
        </ul>
        
        <div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            <h4 style="color: #0066cc; margin-bottom: 0.5rem;">Investment Plans Available:</h4>
            <ul>
                <li><strong>Starter Plan:</strong> 2.5% daily for 30 days ($100 - $999)</li>
                <li><strong>Premium Plan:</strong> 3.5% daily for 25 days ($1,000 - $4,999)</li>
                <li><strong>VIP Plan:</strong> 5.0% daily for 20 days ($5,000 - $19,999)</li>
                <li><strong>Elite Plan:</strong> 7.5% daily for 15 days ($20,000+)</li>
            </ul>
        </div>
        
        <h3>Security Tips:</h3>
        <ul>
            <li>Never share your login credentials with anyone</li>
            <li>Use a strong, unique password for your account</li>
            <li>Enable two-factor authentication when available</li>
            <li>Always logout when using public computers</li>
        </ul>
        
        <p>If you have any questions or need assistance, our support team is available 24/7 to help you.</p>
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="' . home_url('/dashboard') . '" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                Access Your Dashboard
            </a>
        </div>
        
        <p>Best regards,<br>
        The ' . get_bloginfo('name') . ' Team</p>
    ';
    
    hyip_send_notification($user->ID, $subject, $message, 'welcome');
}

/**
 * Custom login form for HYIP
 */
function hyip_custom_login_form() {
    if (is_user_logged_in()) {
        wp_redirect(home_url('/dashboard'));
        exit;
    }
    
    $login_url = wp_login_url();
    $register_url = wp_registration_url();
    $lost_password_url = wp_lostpassword_url();
    
    // Handle login form submission
    if (isset($_POST['login_submit'])) {
        $credentials = array(
            'user_login' => sanitize_user($_POST['user_login']),
            'user_password' => $_POST['user_password'],
            'remember' => isset($_POST['remember_me'])
        );
        
        $user = wp_signon($credentials, false);
        
        if (is_wp_error($user)) {
            $login_error = $user->get_error_message();
        } else {
            wp_redirect(home_url('/dashboard'));
            exit;
        }
    }
    
    return compact('login_url', 'register_url', 'lost_password_url', 'login_error');
}

/**
 * Custom registration form handling
 */
function hyip_handle_registration() {
    if (isset($_POST['register_submit']) && wp_verify_nonce($_POST['register_nonce'], 'hyip_register')) {
        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $password = $_POST['user_password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $terms_agreed = isset($_POST['terms_agree']);
        
        $errors = array();
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            $errors[] = 'All required fields must be filled.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!$terms_agreed) {
            $errors[] = 'You must agree to the Terms of Service.';
        }
        
        if (username_exists($username)) {
            $errors[] = 'Username already exists.';
        }
        
        if (email_exists($email)) {
            $errors[] = 'Email address already registered.';
        }
        
        if (!is_email($email)) {
            $errors[] = 'Invalid email address.';
        }
        
        if (empty($errors)) {
            // Create user
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                // Update user meta
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => trim($first_name . ' ' . $last_name)
                ));
                
                // Redirect to login with success message
                wp_redirect(home_url('/login?registration=complete'));
                exit;
            } else {
                $errors[] = 'Registration failed: ' . $user_id->get_error_message();
            }
        }
        
        return $errors;
    }
    
    return array();
}

/**
 * Password reset handling
 */
function hyip_handle_password_reset() {
    if (isset($_POST['reset_submit']) && wp_verify_nonce($_POST['reset_nonce'], 'hyip_reset')) {
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            return array('Please enter your username or email address.');
        }
        
        $user = get_user_by('login', $user_login);
        if (!$user) {
            $user = get_user_by('email', $user_login);
        }
        
        if (!$user) {
            return array('No user found with that username or email address.');
        }
        
        // Generate reset key
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            return array('Failed to generate reset key.');
        }
        
        // Send reset email
        $subject = 'Password Reset Request - ' . get_bloginfo('name');
        $message = '
            <h2>Password Reset Request</h2>
            <p>Dear ' . esc_html($user->display_name ?: $user->user_login) . ',</p>
            <p>You have requested a password reset for your account on ' . get_bloginfo('name') . '.</p>
            
            <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <p><strong>Username:</strong> ' . esc_html($user->user_login) . '</p>
                <p><strong>Email:</strong> ' . esc_html($user->user_email) . '</p>
            </div>
            
            <p>To reset your password, please click the link below:</p>
            
            <div style="text-align: center; margin: 2rem 0;">
                <a href="' . wp_lostpassword_url() . '?action=rp&key=' . $reset_key . '&login=' . rawurlencode($user->user_login) . '" 
                   style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; display: inline-block;">
                    Reset Your Password
                </a>
            </div>
            
            <p>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
            
            <p>For security reasons, this link will expire in 24 hours.</p>
            
            <p>Best regards,<br>
            The ' . get_bloginfo('name') . ' Team</p>
        ';
        
        if (hyip_send_notification($user->ID, $subject, $message, 'password_reset')) {
            return array('success' => 'Password reset link has been sent to your email address.');
        } else {
            return array('Failed to send password reset email.');
        }
    }
    
    return array();
}

/**
 * Update last login time
 */
function hyip_update_last_login($user_login, $user) {
    global $wpdb;
    $table_users = $wpdb->prefix . 'hyip_users';
    
    $wpdb->update(
        $table_users,
        array('last_login' => current_time('mysql')),
        array('user_id' => $user->ID)
    );
    
    // Log login
    hyip_log_user_action($user->ID, 'user_login', 'User logged in');
}
add_action('wp_login', 'hyip_update_last_login', 10, 2);

/**
 * Logout redirect
 */
function hyip_logout_redirect() {
    wp_redirect(home_url('/login'));
    exit;
}
add_action('wp_logout', 'hyip_logout_redirect');

/**
 * Check if user account is active
 */
function hyip_is_user_active($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $hyip_user = hyip_get_user_profile($user_id);
    
    return $hyip_user && $hyip_user->status === 'active';
}

/**
 * Block login for inactive users
 */
function hyip_check_user_status($user, $username, $password) {
    if (!is_wp_error($user) && !hyip_is_user_active($user->ID)) {
        return new WP_Error('account_suspended', 'Your account has been suspended. Please contact support.');
    }
    
    return $user;
}
add_filter('authenticate', 'hyip_check_user_status', 30, 3);

/**
 * Add custom user profile fields
 */
function hyip_add_custom_user_profile_fields($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $hyip_user = hyip_get_user_profile($user->ID);
    ?>
    <h3>HYIP Account Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="hyip_status">Account Status</label></th>
            <td>
                <select name="hyip_status" id="hyip_status">
                    <option value="active" <?php selected($hyip_user->status ?? 'active', 'active'); ?>>Active</option>
                    <option value="suspended" <?php selected($hyip_user->status ?? 'active', 'suspended'); ?>>Suspended</option>
                    <option value="blocked" <?php selected($hyip_user->status ?? 'active', 'blocked'); ?>>Blocked</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>Account Balance</th>
            <td><strong>$<?php echo hyip_format_currency($hyip_user->balance ?? 0, false); ?></strong></td>
        </tr>
        <tr>
            <th>Total Invested</th>
            <td><strong>$<?php echo hyip_format_currency($hyip_user->total_invested ?? 0, false); ?></strong></td>
        </tr>
        <tr>
            <th>Total Earned</th>
            <td><strong>$<?php echo hyip_format_currency($hyip_user->total_earned ?? 0, false); ?></strong></td>
        </tr>
        <tr>
            <th>Total Withdrawn</th>
            <td><strong>$<?php echo hyip_format_currency($hyip_user->total_withdrawn ?? 0, false); ?></strong></td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'hyip_add_custom_user_profile_fields');
add_action('edit_user_profile', 'hyip_add_custom_user_profile_fields');

/**
 * Save custom user profile fields
 */
function hyip_save_custom_user_profile_fields($user_id) {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    if (isset($_POST['hyip_status'])) {
        global $wpdb;
        $table_users = $wpdb->prefix . 'hyip_users';
        
        $status = sanitize_text_field($_POST['hyip_status']);
        
        $wpdb->update(
            $table_users,
            array('status' => $status),
            array('user_id' => $user_id)
        );
        
        // Log status change
        hyip_log_user_action($user_id, 'status_changed', "Status changed to: $status");
    }
}
add_action('personal_options_update', 'hyip_save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'hyip_save_custom_user_profile_fields');