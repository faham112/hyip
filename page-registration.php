<?php
/**
 * Template Name: Registration Page
 *
 * @package Custom-Dashboard-Theme
 */

if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/user-dashboard' ) );
    exit;
}

if ( isset( $_POST['submit'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'user_registration' ) ) {
    $user_login = sanitize_user( $_POST['user_login'] );
    $user_email = sanitize_email( $_POST['user_email'] );
    $user_pass  = $_POST['user_pass'];

    $user_id = wp_create_user( $user_login, $user_pass, $user_email );

    if ( ! is_wp_error( $user_id ) ) {
        // Generate custom user ID
        $ref_id = 'REF' . ( 12345 + $user_id );
        update_user_meta( $user_id, 'ref_id', $ref_id );

        // Handle referral
        if ( isset( $_GET['ref'] ) ) {
            $referrer_ref_id = sanitize_text_field( $_GET['ref'] );
            $referrer = get_users( array( 'meta_key' => 'ref_id', 'meta_value' => $referrer_ref_id ) );
            if ( ! empty( $referrer ) ) {
                update_user_meta( $user_id, 'referrer_id', $referrer[0]->ID );
            }
        }

        // Log the user in
        wp_set_current_user( $user_id, $user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user_login, get_user_by('id', $user_id) );


        // Redirect to dashboard
        wp_redirect( home_url( '/user-dashboard' ) );
        exit;
    }
}

get_header();
?>

	<main id="primary" class="site-main">

		<div class="container">
			<h1>Register</h1>

			<form id="registration-form" action="" method="POST">
				<p>
					<label for="user_login">Username</label>
					<input type="text" name="user_login" id="user_login" required>
				</p>
				<p>
					<label for="user_email">Email</label>
					<input type="email" name="user_email" id="user_email" required>
				</p>
				<p>
					<label for="user_pass">Password</label>
					<input type="password" name="user_pass" id="user_pass" required>
				</p>
				<?php wp_nonce_field( 'user_registration' ); ?>
				<p>
					<input type="submit" name="submit" value="Register">
				</p>
			</form>

		</div>

	</main><!-- #main -->

<?php
get_footer();
