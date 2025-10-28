<?php
/**
 * HYIP Manager Theme Functions
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Theme setup
function hyip_theme_setup() {
    // Add theme support for various features
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'hyip-manager'),
        'user-dashboard' => __('User Dashboard Menu', 'hyip-manager'),
    ));
}
add_action('after_setup_theme', 'hyip_theme_setup');

// Enqueue scripts and styles
function hyip_enqueue_scripts() {
    // Styles
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), '1.0');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
    wp_enqueue_style('hyip-style', get_stylesheet_uri(), array(), '1.0.2');
    
    // Scripts
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    wp_enqueue_script('hyip-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), '1.0.2', true);
    
    // Localize script for AJAX
    wp_localize_script('hyip-main', 'hyip_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hyip_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'hyip_enqueue_scripts');

// Include required files
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/user-auth.php';
require_once get_template_directory() . '/inc/investment.php';
require_once get_template_directory() . '/inc/withdraw.php';
require_once get_template_directory() . '/inc/admin.php';

// Database tables creation on theme activation
function hyip_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // HYIP Users Table
    $table_users = $wpdb->prefix . 'hyip_users';
    $sql_users = "CREATE TABLE $table_users (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        balance decimal(15,2) DEFAULT '0.00',
        total_invested decimal(15,2) DEFAULT '0.00',
        total_earned decimal(15,2) DEFAULT '0.00',
        total_withdrawn decimal(15,2) DEFAULT '0.00',
        referral_earnings decimal(15,2) DEFAULT '0.00',
        status enum('active','suspended','blocked') DEFAULT 'active',
        joined_date datetime DEFAULT CURRENT_TIMESTAMP,
        last_login datetime,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // HYIP Investment Plans Table
    $table_plans = $wpdb->prefix . 'hyip_plans';
    $sql_plans = "CREATE TABLE $table_plans (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        min_amount decimal(15,2) NOT NULL,
        max_amount decimal(15,2) NOT NULL,
        daily_percent decimal(5,2) NOT NULL,
        duration int(11) NOT NULL,
        status enum('active','inactive') DEFAULT 'active',
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // HYIP Investments Table
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $sql_investments = "CREATE TABLE $table_investments (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        plan_id mediumint(9) NOT NULL,
        amount decimal(15,2) NOT NULL,
        daily_profit decimal(15,2) NOT NULL,
        total_profit decimal(15,2) DEFAULT '0.00',
        days_completed int(11) DEFAULT 0,
        status enum('active','completed','cancelled') DEFAULT 'active',
        invest_date datetime DEFAULT CURRENT_TIMESTAMP,
        next_profit_date datetime,
        completion_date datetime,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY plan_id (plan_id)
    ) $charset_collate;";
    
    // HYIP Withdrawals Table
    $table_withdrawals = $wpdb->prefix . 'hyip_withdrawals';
    $sql_withdrawals = "CREATE TABLE $table_withdrawals (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        amount decimal(15,2) NOT NULL,
        payment_method varchar(50) NOT NULL,
        payment_details text NOT NULL,
        status enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
        request_date datetime DEFAULT CURRENT_TIMESTAMP,
        processed_date datetime,
        admin_notes text,
        transaction_id varchar(100),
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // HYIP Transactions Table
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    $sql_transactions = "CREATE TABLE $table_transactions (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        type enum('deposit','withdrawal','earning','bonus','referral') NOT NULL,
        amount decimal(15,2) NOT NULL,
        description text,
        reference_id mediumint(9),
        status enum('completed','pending','failed') DEFAULT 'completed',
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_users);
    dbDelta($sql_plans);
    dbDelta($sql_investments);
    dbDelta($sql_withdrawals);
    dbDelta($sql_transactions);
    
    // Insert default investment plans
    hyip_insert_default_plans();
}

// Insert default investment plans
function hyip_insert_default_plans() {
    global $wpdb;
    
    $table_plans = $wpdb->prefix . 'hyip_plans';
    
    // Check if plans already exist
    $existing_plans = $wpdb->get_var("SELECT COUNT(*) FROM $table_plans");
    
    if ($existing_plans == 0) {
        $default_plans = array(
            array(
                'name' => 'Starter Plan',
                'min_amount' => 100.00,
                'max_amount' => 999.00,
                'daily_percent' => 2.50,
                'duration' => 30
            ),
            array(
                'name' => 'Premium Plan',
                'min_amount' => 1000.00,
                'max_amount' => 4999.00,
                'daily_percent' => 3.50,
                'duration' => 25
            ),
            array(
                'name' => 'VIP Plan',
                'min_amount' => 5000.00,
                'max_amount' => 19999.00,
                'daily_percent' => 5.00,
                'duration' => 20
            ),
            array(
                'name' => 'Elite Plan',
                'min_amount' => 20000.00,
                'max_amount' => 100000.00,
                'daily_percent' => 7.50,
                'duration' => 15
            ),
        );
        
        foreach ($default_plans as $plan) {
            $wpdb->insert($table_plans, $plan);
        }
    }
}

// Run table creation on theme switch
// add_action('after_switch_theme', 'hyip_create_tables'); // Temporarily disabled for debugging

// Hide admin bar for non-admin users
function hyip_hide_admin_bar() {
    if (!current_user_can('manage_options')) {
        show_admin_bar(false);
    }
}
add_action('wp_loaded', 'hyip_hide_admin_bar');

// Redirect non-admin users from wp-admin to dashboard
function hyip_redirect_non_admin_users() {
    if (is_admin() && !current_user_can('manage_options') && !(defined('DOING_AJAX') && DOING_AJAX)) {
        wp_redirect(home_url('/dashboard'));
        exit;
    }
}
add_action('admin_init', 'hyip_redirect_non_admin_users');

// Login redirect for regular users
function hyip_login_redirect($redirect_to, $request, $user) {
    if (!is_wp_error($user) && !user_can($user, 'manage_options')) {
        return home_url('/dashboard');
    }
    return $redirect_to;
}
add_filter('login_redirect', 'hyip_login_redirect', 10, 3);

// Custom body classes
function hyip_body_classes($classes) {
    if (is_page_template('page-dashboard.php') || 
        is_page_template('page-invest.php') || 
        is_page_template('page-withdraw.php') ||
        is_page_template('page-profile.php')) {
        $classes[] = 'hyip-user-area';
    }
    
    if (is_page_template('page-admin.php')) {
        $classes[] = 'hyip-admin-area';
    }
    
    return $classes;
}
add_filter('body_class', 'hyip_body_classes');

// Remove WordPress version from head
remove_action('wp_head', 'wp_generator');

// Security enhancements
function hyip_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}
add_action('wp_head', 'hyip_security_headers', 1);

// Custom excerpt length
function hyip_excerpt_length($length) {
    return 20;
}
add_filter('excerpt_length', 'hyip_excerpt_length');

// Schedule daily profit cron job
if (!wp_next_scheduled('hyip_daily_profit_cron')) {
    wp_schedule_event(strtotime('00:00:00'), 'daily', 'hyip_daily_profit_cron');
}

// Add cron hook
add_action('hyip_daily_profit_cron', 'hyip_process_daily_profits');

// Process daily profits function
function hyip_process_daily_profits() {
    global $wpdb;
    
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';
    
    // Get all active investments that are due for profit
    $investments = $wpdb->get_results("
        SELECT i.*, p.duration, p.daily_percent 
        FROM $table_investments i 
        LEFT JOIN {$wpdb->prefix}hyip_plans p ON i.plan_id = p.id 
        WHERE i.status = 'active' 
        AND (i.next_profit_date IS NULL OR i.next_profit_date <= NOW())
        AND i.days_completed < p.duration
    ");
    
    foreach ($investments as $investment) {
        // Calculate daily profit
        $daily_profit = ($investment->amount * $investment->daily_percent) / 100;
        
        // Update user balance
        $wpdb->query($wpdb->prepare("
            UPDATE $table_users 
            SET balance = balance + %f, total_earned = total_earned + %f 
            WHERE user_id = %d
        ", $daily_profit, $daily_profit, $investment->user_id));
        
        // Update investment
        $new_days_completed = $investment->days_completed + 1;
        $status = ($new_days_completed >= $investment->duration) ? 'completed' : 'active';
        $completion_date = ($status == 'completed') ? current_time('mysql') : null;
        
        $wpdb->update(
            $table_investments,
            array(
                'total_profit' => $investment->total_profit + $daily_profit,
                'days_completed' => $new_days_completed,
                'next_profit_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'status' => $status,
                'completion_date' => $completion_date
            ),
            array('id' => $investment->id)
        );
        
        // Log transaction
        $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $investment->user_id,
                'type' => 'earning',
                'amount' => $daily_profit,
                'description' => 'Daily profit from ' . get_plan_name($investment->plan_id),
                'reference_id' => $investment->id,
                'status' => 'completed'
            )
        );
        
        // Return principal if investment completed
        if ($status == 'completed') {
            $wpdb->query($wpdb->prepare("
                UPDATE $table_users 
                SET balance = balance + %f 
                WHERE user_id = %d
            ", $investment->amount, $investment->user_id));
            
            // Log principal return transaction
            $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $investment->user_id,
                    'type' => 'deposit',
                    'amount' => $investment->amount,
                    'description' => 'Principal return from ' . get_plan_name($investment->plan_id),
                    'reference_id' => $investment->id,
                    'status' => 'completed'
                )
            );
        }
    }
}

// Helper function to get plan name
function get_plan_name($plan_id) {
    global $wpdb;
    $table_plans = $wpdb->prefix . 'hyip_plans';
    return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_plans WHERE id = %d", $plan_id));
}

// Clean up scheduled events on theme deactivation
function hyip_cleanup_cron() {
    wp_clear_scheduled_hook('hyip_daily_profit_cron');
}
add_action('switch_theme', 'hyip_cleanup_cron');
