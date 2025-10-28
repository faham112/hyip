<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <header id="masthead" class="site-header">
        <div class="container">
            <div class="site-branding">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-title" rel="home">
                            <?php bloginfo('name'); ?>
                        </a>
                    </h1>
                    <?php $description = get_bloginfo('description', 'display'); ?>
                    <?php if ($description || is_customize_preview()) : ?>
                        <p class="site-description"><?php echo $description; ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <nav id="site-navigation" class="main-navigation">
                <?php
                // Different menu for logged in users
                if (is_user_logged_in()) {
                    $current_user = wp_get_current_user();
                    
                    // Admin menu
                    if (user_can($current_user, 'manage_options')) {
                        echo '<ul id="primary-menu">';
                        echo '<li><a href="' . home_url('/') . '">Home</a></li>';
                        echo '<li><a href="' . home_url('/admin') . '">Admin Panel</a></li>';
                        echo '<li><a href="' . admin_url() . '">WP Admin</a></li>';
                        echo '<li><a href="' . wp_logout_url(home_url()) . '">Logout</a></li>';
                        echo '</ul>';
                    } else {
                        // Regular user menu
                        echo '<ul id="primary-menu">';
                        echo '<li><a href="' . home_url('/') . '">Home</a></li>';
                        echo '<li><a href="' . home_url('/dashboard') . '">Dashboard</a></li>';
                        echo '<li><a href="' . home_url('/invest') . '">Invest</a></li>';
                        echo '<li><a href="' . home_url('/withdraw') . '">Withdraw</a></li>';
                        echo '<li><a href="' . home_url('/profile') . '">Profile</a></li>';
                        echo '<li><a href="' . wp_logout_url(home_url('/login')) . '">Logout</a></li>';
                        echo '</ul>';
                    }
                } else {
                    // Guest menu
                    echo '<ul id="primary-menu">';
                    echo '<li><a href="' . home_url('/') . '">Home</a></li>';
                    echo '<li><a href="' . home_url('/login') . '">Login</a></li>';
                    echo '<li><a href="' . wp_registration_url() . '">Register</a></li>';
                    echo '</ul>';
                }
                ?>
                
                <div class="mobile-menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </nav>
        </div>
    </header>