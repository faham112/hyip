<?php
/**
 * Template Name: Admin Deposits Page
 */

// Security check - redirect if not an admin
if (!current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}

$message = '';
if (isset($_POST['approve_deposit'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $message = hyip_approve_deposit($deposit_id);
}

if (isset($_POST['reject_deposit'])) {
    $deposit_id = intval($_POST['deposit_id']);
    $message = hyip_reject_deposit($deposit_id);
}

global $wpdb;
$table_deposits = $wpdb->prefix . 'hyip_deposits';
$pending_deposits = $wpdb->get_results("SELECT * FROM $table_deposits WHERE status = 'pending' ORDER BY deposit_date DESC");

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <h1>Admin Deposits Management</h1>
        <p>Approve or reject user deposit requests.</p>

        <?php if ($message) : ?>
            <div class="alert alert-info"><?php echo esc_html($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Amount</th>
                        <th>Payment Slip</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_deposits)) : ?>
                        <tr>
                            <td colspan="5">No pending deposits.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($pending_deposits as $deposit) : ?>
                            <tr>
                                <td><?php echo esc_html($deposit->user_id); ?></td>
                                <td>$<?php echo number_format($deposit->amount, 2); ?></td>
                                <td><a href="<?php echo esc_url($deposit->slip_url); ?>" target="_blank">View Slip</a></td>
                                <td><?php echo esc_html($deposit->deposit_date); ?></td>
                                <td>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="deposit_id" value="<?php echo esc_attr($deposit->id); ?>">
                                        <button type="submit" name="approve_deposit" class="btn btn-success">Approve</button>
                                    </form>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="deposit_id" value="<?php echo esc_attr($deposit->id); ?>">
                                        <button type="submit" name="reject_deposit" class="btn btn-danger">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php get_footer(); ?>
