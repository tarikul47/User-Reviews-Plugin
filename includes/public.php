<?php
function urp_custom_users_shortcode() {
    global $wpdb;
    $users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}urp_custom_users");

    $output = '<h2>Users</h2><ul>';
    foreach ($users as $user) {
        $output .= '<li><a href="' . get_permalink() . '?user_id=' . $user->id . '">' . esc_html($user->name) . '</a></li>';
    }
    $output .= '</ul>';

    return $output;
}

add_shortcode('custom_users', 'urp_custom_users_shortcode');

function urp_custom_user_detail_shortcode() {
    if (!isset($_GET['user_id'])) {
        return 'No user specified.';
    }

    global $wpdb;
    $user_id = intval($_GET['user_id']);
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $user_id));
    $reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d", $user_id));

    if (!$user) {
        return 'User not found.';
    }

    $output = '<h2>' . esc_html($user->name) . '</h2>';
    $output .= '<p>Email: ' . esc_html($user->email) . '</p>';
    $output .= '<h3>Reviews</h3><ul>';

    foreach ($reviews as $review) {
        $output .= '<li><strong>' . esc_html($review->reviewer_name) . ':</strong> ' . esc_html($review->review_content) . ' (' . esc_html($review->rating) . '/5)</li>';
    }
    $output .= '</ul>';

    $output .= '
    <h3>Submit a Review</h3>
    <form method="post">
        <input type="hidden" name="user_id" value="' . esc_attr($user_id) . '">
        <label for="reviewer_name">Name</label>
        <input type="text" id="reviewer_name" name="reviewer_name" required><br>
        <label for="review_content">Review</label>
        <textarea id="review_content" name="review_content" required></textarea><br>
        <label for="rating">Rating</label>
        <input type="number" id="rating" name="rating" min="1" max="5" required><br>
        <input type="submit" name="submit_review" value="Submit Review">
    </form>';

    return $output;
}

add_shortcode('custom_user_detail', 'urp_custom_user_detail_shortcode');

function urp_handle_review_submission() {
    if (isset($_POST['submit_review'])) {
        global $wpdb;
        $user_id = intval($_POST['user_id']);
        $reviewer_name = sanitize_text_field($_POST['reviewer_name']);
        $review_content = sanitize_textarea_field($_POST['review_content']);
        $rating = intval($_POST['rating']);

        $wpdb->insert($wpdb->prefix . 'urp_custom_reviews', [
            'user_id' => $user_id,
            'reviewer_name' => $reviewer_name,
            'review_content' => $review_content,
            'rating' => $rating
        ]);

        wp_redirect(add_query_arg('user_id', $user_id, get_permalink()));
        exit;
    }
}

add_action('template_redirect', 'urp_handle_review_submission');
?>
