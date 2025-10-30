<?php
/**
 * Template Name: User Dashboard
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Custom-Dashboard-Theme
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( home_url( '/login' ) );
    exit;
}

if ( isset( $_POST['invest_submit'] ) && isset( $_POST['_wpnonce'] ) ) {
    $plan_id = intval( $_POST['plan_id'] );
    $amount = floatval( $_POST['amount'] );
    $current_user = wp_get_current_user();

    if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'invest_in_plan_' . $plan_id ) ) {
        wp_die( 'Security check failed.' );
    }

    global $wpdb;
    $table_plans = $wpdb->prefix . 'hyip_plans';
    $table_investments = $wpdb->prefix . 'hyip_investments';
    $table_users = $wpdb->prefix . 'hyip_users';
    $table_transactions = $wpdb->prefix . 'hyip_transactions';

    $plan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_plans WHERE id = %d AND status = 'active'", $plan_id ) );
    $user_balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM $table_users WHERE user_id = %d", $current_user->ID ) );

    if ( $plan && $amount >= $plan->min_amount && $amount <= $plan->max_amount && $user_balance >= $amount ) {
        // Deduct amount from user balance
        $wpdb->query( $wpdb->prepare( "UPDATE $table_users SET balance = balance - %f, total_invested = total_invested + %f WHERE user_id = %d", $amount, $amount, $current_user->ID ) );

        // Calculate daily profit
        $daily_profit = ( $amount * $plan->daily_percent ) / 100;

        // Insert into investments table
        $wpdb->insert(
            $table_investments,
            array(
                'user_id' => $current_user->ID,
                'plan_id' => $plan_id,
                'amount' => $amount,
                'daily_profit' => $daily_profit,
                'next_profit_date' => date( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
            )
        );

        // Log transaction
        $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $current_user->ID,
                'type' => 'deposit', // Using deposit type for investment outflow
                'amount' => -$amount, // Negative amount for deduction
                'description' => 'Investment in ' . $plan->name,
                'status' => 'completed'
            )
        );

        // Redirect to avoid form resubmission
        wp_redirect( home_url( '/user-dashboard?invest_success=1' ) );
        exit;
    } else {
        // Handle error (e.g., insufficient balance, invalid amount)
        wp_redirect( home_url( '/user-dashboard?invest_error=1' ) );
        exit;
    }
}

get_header();
?>

	<main id="primary" class="site-main">

		<div class="container">
			<h1>User Dashboard</h1>

			<?php
			if ( isset( $_GET['invest_success'] ) && $_GET['invest_success'] == '1' ) {
				echo '<div class="alert alert-success">Investment successful!</div>';
			} elseif ( isset( $_GET['invest_error'] ) && $_GET['invest_error'] == '1' ) {
				echo '<div class="alert alert-danger">Investment failed. Please check your balance and the amount.</div>';
			}
			?>

			<!-- Website Overview -->
			<div id="website-overview" class="dashboard-section">
				<h2>Website Overview</h2>
				<p>Welcome to your personalized dashboard! Here you can manage your investments, track your referrals, and monitor your account.</p>
				<p>Explore the different sections to get a complete overview of your activity on the platform.</p>
			</div>

			<!-- Investment Plans -->
			<div id="investment-plans" class="dashboard-section">
				<h2>Investment Plans</h2>
				<?php
				global $wpdb;
				$table_plans = $wpdb->prefix . 'hyip_plans';
				$plans = $wpdb->get_results( "SELECT * FROM $table_plans WHERE status = 'active'" );

				if ( $plans ) {
					echo '<ul>';
					foreach ( $plans as $plan ) {
						echo '<li>';
						echo '<h3>' . esc_html( $plan->name ) . '</h3>';
						echo '<p>Min Amount: ' . esc_html( $plan->min_amount ) . '</p>';
						echo '<p>Max Amount: ' . esc_html( $plan->max_amount ) . '</p>';
						echo '<p>Daily Percent: ' . esc_html( $plan->daily_percent ) . '%</p>';
						echo '<p>Duration: ' . esc_html( $plan->duration ) . ' days</p>';
						echo '<form class="invest-form" action="" method="POST">';
						echo '<input type="hidden" name="plan_id" value="' . esc_attr( $plan->id ) . '">';
						echo '<p>';
						echo '<label for="amount-' . esc_attr( $plan->id ) . '">Amount (Min: ' . esc_html( $plan->min_amount ) . ', Max: ' . esc_html( $plan->max_amount ) . ')</label>';
						echo '<input type="number" name="amount" id="amount-' . esc_attr( $plan->id ) . '" min="' . esc_attr( $plan->min_amount ) . '" max="' . esc_attr( $plan->max_amount ) . '" step="0.01" required>';
						echo '</p>';
						wp_nonce_field( 'invest_in_plan_' . $plan->id );
						echo '<p><input type="submit" name="invest_submit" value="Invest Now"></p>';
						echo '</form>';
						echo '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p>No investment plans available at the moment.</p>';
				}
				?>
			</div>

			<!-- My Investments -->
			<div id="my-investments" class="dashboard-section">
				<h2>My Investments</h2>
				<?php
				$current_user = wp_get_current_user();
				$table_investments = $wpdb->prefix . 'hyip_investments';
				$investments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_investments WHERE user_id = %d AND status = 'active'", $current_user->ID ) );

				if ( $investments ) {
					echo '<ul>';
					foreach ( $investments as $investment ) {
						$plan_name = get_plan_name( $investment->plan_id );
						echo '<li>';
						echo '<h3>' . esc_html( $plan_name ) . '</h3>';
						echo '<p>Amount: ' . esc_html( $investment->amount ) . '</p>';
						echo '<p>Daily Profit: ' . esc_html( $investment->daily_profit ) . '</p>';
						echo '<p>Days Completed: ' . esc_html( $investment->days_completed ) . '</p>';
						echo '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p>You have no active investments.</p>';
				}
				?>
			</div>

			<!-- Referral System -->
			<div id="referral-system" class="dashboard-section">
				<h2>Referral System</h2>
				<?php
				$current_user = wp_get_current_user();
				$ref_id = get_user_meta( $current_user->ID, 'ref_id', true );
				?>
				<p>Your referral ID is: <?php echo esc_html( $ref_id ); ?></p>
				<p>Your referral link is: <a href="<?php echo esc_url( home_url( '/register?ref=' . $ref_id ) ); ?>"><?php echo esc_url( home_url( '/register?ref=' . $ref_id ) ); ?></a></p>
			</div>

			<!-- Account Summary -->
			<div id="account-summary" class="dashboard-section">
				<h2>Account Summary</h2>
				<?php
				$user_data = $wpdb->get_row( $wpdb->prepare( "SELECT balance, total_invested, total_earned FROM {$wpdb->prefix}hyip_users WHERE user_id = %d", $current_user->ID ) );
				$total_invested = $user_data ? $user_data->total_invested : '0.00';
				$total_earned = $user_data ? $user_data->total_earned : '0.00';
				?>
				<p>Current Balance: <?php echo esc_html( $user_data->balance ); ?></p>
				<p>Total Invested: <?php echo esc_html( $total_invested ); ?></p>
				<p>Total Earned: <?php echo esc_html( $total_earned ); ?></p>
				<p>Total ROI: 0%</p>
			</div>

			<!-- Personal Accounting -->
			<div id="personal-accounting" class="dashboard-section">
				<h2>Personal Accounting</h2>
				<p>This section will display your detailed transaction history and financial summaries.</p>
				<p>Coming soon: Advanced Hisaab tracking features!</p>
			</div>

		</div>

	</main><!-- #main -->

<?php
get_footer();
