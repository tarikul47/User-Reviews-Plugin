<?php
get_header();

if (isset($_POST['submit_review'])) {
    global $wpdb;

    $user_id = intval($_POST['user_id']);
    $review_content = sanitize_textarea_field($_POST['review_content']);
    $rating = intval($_POST['rating']);

    // Get the current user's username
    $current_user = wp_get_current_user();
    $reviewer_name = $current_user->user_login;

    // Check if there are any approved reviews for the user
    $approved_reviews = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d AND status = 'approved'",
            $user_id
        )
    );

    $status = ($approved_reviews > 0) ? 'pending' : 'approved';

    $wpdb->insert(
        "{$wpdb->prefix}urp_custom_reviews",
        [
            'user_id' => $user_id,
            'reviewer_name' => $reviewer_name,
            'review_content' => $review_content,
            'rating' => $rating,
            'status' => $status
        ],
        ['%d', '%s', '%s', '%d', '%s']
    );

    if ($status === 'approved') {
        echo '<div class="notice notice-success is-dismissible"><p>Review submitted and approved successfully!</p></div>';
    } else {
        echo '<div class="notice notice-warning is-dismissible"><p>Review submitted and is pending approval.</p></div>';
    }
}

global $wpdb;

// Fetch user details and reviews
$user_id = intval(get_query_var('urp_user_id'));
$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $user_id));
$reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d AND status = 'approved'", $user_id));
$total_reviews = count($reviews);
$average_rating = $total_reviews ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;

// Fetch the product ID for the Add to Cart button
$product_id = $user->product_id;
?>

<h3>User Profile</h3>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Average Rating</th>
            <th>Total Ratings</th>
            <th>Add to Cart</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?php echo esc_html($user->name); ?></td>
            <td><?php echo esc_html($user->email); ?></td>
            <td><?php echo number_format($average_rating, 2); ?></td>
            <td><?php echo $total_reviews; ?></td>
            <td><a href="<?php echo esc_url(wc_get_cart_url() . '?add-to-cart=' . $product_id); ?>" class="button">Add
                    to Cart</a></td>
        </tr>
    </tbody>
</table>

<h4>Submit a Review:</h4>
<form method="post" action="">
    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
    <p>
        <label for="review_content">Your Review:</label><br />
        <textarea id="review_content" name="review_content" required></textarea>
    </p>
    <p>
        <label for="rating">Rating:</label><br />
        <select id="rating" name="rating" required>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
        </select>
    </p>
    <p>
        <input type="submit" name="submit_review" value="Submit Review" />
    </p>
</form>

<?php
get_footer();
?>