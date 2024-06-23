<?php
// Add admin menu and submenu pages
function urp_admin_menu()
{
    add_menu_page('User Reviews', 'User Reviews', 'manage_options', 'user-reviews-plugin', 'urp_user_list_page');
    add_submenu_page('user-reviews-plugin', 'User List', 'User List', 'manage_options', 'user-reviews-plugin', 'urp_user_list_page');
    add_submenu_page('user-reviews-plugin', 'Add User', 'Add User', 'manage_options', 'user-reviews-plugin-add-user', 'urp_add_user_page');
    add_submenu_page('user-reviews-plugin', 'Review List', 'Review List', 'manage_options', 'user-reviews-plugin-review-list', 'urp_review_list_page');
    add_submenu_page('user-reviews-plugin', 'Approve Reviews', 'Approve Reviews', 'manage_options', 'user-reviews-plugin-approve-reviews', 'urp_approve_reviews_page');
}
add_action('admin_menu', 'urp_admin_menu');

// Function to display user list page
function urp_user_list_page()
{
    global $wpdb;

    // Query all users from the custom table
    $users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}urp_custom_users");

    // Display the user list in a table
    echo '<div class="wrap">';
    echo '<h2>User List</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th></tr></thead><tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . esc_html($user->id) . '</td>';
        echo '<td>' . esc_html($user->name) . '</td>';
        echo '<td>' . esc_html($user->email) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Function to display add user page
function urp_add_user_page()
{
    global $wpdb;

    if (isset($_POST['submit_user'])) {
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);

        // Insert user into the database
        $wpdb->insert(
            "{$wpdb->prefix}urp_custom_users",
            array(
                'name' => $name,
                'email' => $email,
            ),
            array(
                '%s',
                '%s',
            )
        );

        // Get the ID of the newly inserted user
        $user_id = $wpdb->insert_id;

        // Insert review into the database
        $reviewer_name = sanitize_text_field($_POST['reviewer_name']);
        $review_content = sanitize_textarea_field($_POST['review_content']);
        $rating = intval($_POST['rating']);

        $wpdb->insert(
            "{$wpdb->prefix}urp_custom_reviews",
            array(
                'user_id' => $user_id,
                'reviewer_name' => $reviewer_name,
                'review_content' => $review_content,
                'rating' => $rating,
                'status' => 'approved', // Assuming first review is auto-approved
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            )
        );

        echo '<div class="updated"><p>User added successfully! Review added for the user.</p></div>';
    }
    ?>
    <div class="wrap">
        <h2>Add New User and Review</h2>
        <form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="name">Name</label></th>
                        <td><input type="text" name="name" id="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Email</label></th>
                        <td><input type="email" name="email" id="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reviewer_name">Your Name (Reviewer)</label></th>
                        <td><input type="text" name="reviewer_name" id="reviewer_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="review_content">Review Content</label></th>
                        <td><textarea name="review_content" id="review_content" rows="5" class="regular-text"
                                required></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rating">Rating</label></th>
                        <td>
                            <select name="rating" id="rating" required>
                                <option value="5">5 (Excellent)</option>
                                <option value="4">4 (Good)</option>
                                <option value="3">3 (Average)</option>
                                <option value="2">2 (Fair)</option>
                                <option value="1">1 (Poor)</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="submit" name="submit_user" id="submit_user" class="button button-primary"
                value="Add User and Review">
        </form>
    </div>
    <?php
}

// Function to display review list page
function urp_review_list_page()
{
    global $wpdb;

    // Query all reviews from the custom table
    $reviews = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}urp_custom_reviews");

    // Display the review list in a table
    echo '<div class="wrap">';
    echo '<h2>Review List</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>User ID</th><th>Reviewer Name</th><th>Review Content</th><th>Rating</th><th>Status</th></tr></thead><tbody>';

    foreach ($reviews as $review) {
        echo '<tr>';
        echo '<td>' . esc_html($review->id) . '</td>';
        echo '<td>' . esc_html($review->user_id) . '</td>';
        echo '<td>' . esc_html($review->reviewer_name) . '</td>';
        echo '<td>' . esc_html($review->review_content) . '</td>';
        echo '<td>' . esc_html($review->rating) . '</td>';
        echo '<td>' . esc_html($review->status) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Function to display pending reviews for approval
function urp_approve_reviews_page()
{
    global $wpdb;

    if (isset($_POST['approve_review'])) {
        $review_id = intval($_POST['review_id']);
        $wpdb->update(
            "{$wpdb->prefix}urp_custom_reviews",
            ['status' => 'approved'],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        echo '<div class="notice notice-success is-dismissible"><p>Review approved successfully!</p></div>';
    }

    $pending_reviews = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE status = 'pending'");

    echo '<div class="wrap"><h2>Approve Reviews</h2>';
    echo '<form method="post" action="">';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>User ID</th><th>Reviewer Name</th><th>Review Content</th><th>Rating</th><th>Action</th></tr></thead><tbody>';

    foreach ($pending_reviews as $review) {
        echo '<tr>';
        echo '<td>' . esc_html($review->id) . '</td>';
        echo '<td>' . esc_html($review->user_id) . '</td>';
        echo '<td>' . esc_html($review->reviewer_name) . '</td>';
        echo '<td>' . esc_html($review->review_content) . '</td>';
        echo '<td>' . esc_html($review->rating) . '</td>';
        echo '<td>';
        echo '<input type="hidden" name="review_id" value="' . esc_attr($review->id) . '" />';
        echo '<input type="submit" name="approve_review" class="button button-primary" value="Approve" />';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></form></div>';
}

?>