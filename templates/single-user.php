<?php
get_header();

if (isset($_POST['submit_review'])) {
    global $wpdb;

    $user_id = intval($_POST['user_id']);
    $reviewer_name = sanitize_text_field($_POST['reviewer_name']);
    $review_content = sanitize_textarea_field($_POST['review_content']);
    $rating = intval($_POST['rating']);

    // Check if there are any approved reviews for the user
    $approved_reviews = $wpdb->get_var($wpdb->prepare(
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

echo do_shortcode('[urp_single_user]');

?>

<h4>Submit a Review:</h4>
<form method="post" action="">
    <input type="hidden" name="user_id" value="<?php echo esc_attr(get_query_var('urp_user_id')); ?>" />
    <p>
        <label for="reviewer_name">Your Name:</label><br />
        <input type="text" id="reviewer_name" name="reviewer_name" required />
    </p>
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
