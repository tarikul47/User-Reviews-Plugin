<?php
// Shortcode to display user list
function urp_user_list_shortcode()
{
    global $wpdb;

    // Query to get users, their average ratings, and the count of approved reviews
    $users = $wpdb->get_results("
        SELECT u.id, u.name, COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as approved_review_count
        FROM {$wpdb->prefix}urp_custom_users u
        LEFT JOIN {$wpdb->prefix}urp_custom_reviews r 
        ON u.id = r.user_id AND r.status = 'approved'
        GROUP BY u.id
    ");

    ob_start();
    echo '<h3>User List</h3>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Name</th><th>Average Rating</th><th>Approved Reviews</th><th>Actions</th></tr></thead><tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . esc_html($user->name) . '</td>';
        echo '<td>' . (is_null($user->avg_rating) ? 'No reviews yet' : number_format(floatval($user->avg_rating), 2)) . '</td>';
        echo '<td>' . esc_html($user->approved_review_count) . '</td>';
        echo '<td><a href="' . esc_url(home_url('/user/' . esc_attr($user->id))) . '">View Details</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode('urp_user_list', 'urp_user_list_shortcode');


// Shortcode to display single user details
function urp_single_user_shortcode()
{
    $user_id = get_query_var('urp_user_id');
    if (null === $user_id) {
        return 'No user specified.';
    }

    global $wpdb;
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $user_id));
    $reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d AND status = 'approved'", $user_id));

    if (!$user) {
        return 'User not found.';
    }

    ob_start();
    echo '<h3>' . esc_html($user->name) . '</h3>';
    echo '<p>Email: ' . esc_html($user->email) . '</p>';
    echo '<h4>Reviews:</h4>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Reviewer Name</th><th>Review Content</th><th>Rating</th></tr></thead><tbody>';

    foreach ($reviews as $review) {
        echo '<tr>';
        echo '<td>' . esc_html($review->reviewer_name) . '</td>';
        echo '<td>' . esc_html($review->review_content) . '</td>';
        echo '<td>' . esc_html($review->rating) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode('urp_single_user', 'urp_single_user_shortcode');
