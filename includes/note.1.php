<?php
// Add the admin menu and submenu pages
function urp_admin_menu()
{
    add_menu_page('User Reviews', 'User Reviews', 'manage_options', 'user-reviews-plugin', 'urp_user_list_page', '', 5);
    add_submenu_page('user-reviews-plugin', 'User List', 'User List', 'manage_options', 'user-reviews-plugin', 'urp_user_list_page');
    add_submenu_page('user-reviews-plugin', 'Add User', 'Add User', 'manage_options', 'user-reviews-plugin-add-user', 'urp_add_user_page');
    add_submenu_page('user-reviews-plugin', 'Review List', 'Review List', 'manage_options', 'user-reviews-plugin-review-list', 'urp_review_list_page');
    add_submenu_page('user-reviews-plugin', 'Pending Reviews', 'Pending Reviews', 'manage_options', 'user-reviews-plugin-approve-reviews', 'urp_approve_reviews_page');
    add_submenu_page('user-reviews-plugin', 'Edit User', '', 'manage_options', 'edit_user', 'urp_edit_user_page');
    add_submenu_page('user-reviews-plugin', 'User Reviews', '', 'manage_options', 'user_reviews', 'urp_user_reviews_page');
}
add_action('admin_menu', 'urp_admin_menu');

// Function to display user list page
function urp_user_list_page()
{
    global $wpdb;

    // Handle deletion of a user
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);

        // Fetch the product ID related to the user
        $product_id = $wpdb->get_var($wpdb->prepare("SELECT product_id FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $user_id));

        // Delete user, reviews, and product
        $wpdb->delete("{$wpdb->prefix}urp_custom_users", array('id' => $user_id), array('%d'));
        $wpdb->delete("{$wpdb->prefix}urp_custom_reviews", array('user_id' => $user_id), array('%d'));

        // Check if product ID is valid and delete the product
        if ($product_id) {
            wp_delete_post($product_id, true); // Force delete the product
        }

        echo '<div class="updated"><p>User, related reviews, and associated product deleted successfully!</p></div>';
    }

    // Query to get users along with their total, approved, and pending review counts
    $users = $wpdb->get_results("
        SELECT u.id, u.name, u.email,
               COUNT(r.id) as total_reviews,
               SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_reviews,
               SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_reviews
        FROM {$wpdb->prefix}urp_custom_users u
        LEFT JOIN {$wpdb->prefix}urp_custom_reviews r ON u.id = r.user_id
        GROUP BY u.id
    ");

    // Display the user list in a table
    echo '<div class="wrap">';
    echo '<h2>User List</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Total Reviews</th><th>Approved Reviews</th><th>Pending Reviews</th><th>Actions</th><th>View Reviews</th></tr></thead><tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . esc_html($user->id) . '</td>';
        echo '<td>' . esc_html($user->name) . '</td>';
        echo '<td>' . esc_html($user->email) . '</td>';
        echo '<td>' . esc_html($user->total_reviews) . '</td>';
        echo '<td>' . esc_html($user->approved_reviews) . '</td>';
        echo '<td>' . esc_html($user->pending_reviews) . '</td>';
        echo '<td>
                <a href="' . esc_url(admin_url('admin.php?page=edit_user&user_id=' . esc_attr($user->id))) . '" class="button">Edit</a>
                <a href="' . esc_url(admin_url('admin.php?page=user-reviews-plugin&action=delete&user_id=' . esc_attr($user->id))) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this user?\')">Delete</a>
              </td>';
        echo '<td>
                <a href="' . esc_url(admin_url('admin.php?page=user_reviews&user_id=' . esc_attr($user->id))) . '" class="button">View Reviews</a>
              </td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Function to display the edit user page
function urp_edit_user_page()
{
    global $wpdb;

    // Check if the user ID is provided
    if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);

        // Get the user details
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}urp_custom_users WHERE id = %d",
                $user_id
            )
        );

        // Check if the form is submitted
        if (isset($_POST['submit_user'])) {
            $name = sanitize_text_field($_POST['name']);
            $email = sanitize_email($_POST['email']);

            // Update the user details in the database
            $wpdb->update(
                "{$wpdb->prefix}urp_custom_users",
                array(
                    'name' => $name,
                    'email' => $email,
                ),
                array('id' => $user_id),
                array(
                    '%s',
                    '%s',
                ),
                array('%d')
            );

            echo '<div class="updated"><p>User updated successfully!</p></div>';

            // Refresh user data
            $user = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}urp_custom_users WHERE id = %d",
                    $user_id
                )
            );
        }
        ?>
        <div class="wrap">
            <h2>Edit User</h2>
            <form method="post" action="">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="name">Name</label></th>
                            <td><input type="text" name="name" id="name" class="regular-text"
                                    value="<?php echo esc_attr($user->name); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email">Email</label></th>
                            <td><input type="email" name="email" id="email" class="regular-text"
                                    value="<?php echo esc_attr($user->email); ?>" required></td>
                        </tr>
                    </tbody>
                </table>
                <input type="submit" name="submit_user" id="submit_user" class="button button-primary" value="Update User">
            </form>
        </div>
        <?php
    } else {
        echo '<div class="wrap"><h2>No user selected</h2></div>';
    }
}

// Function to display add user page
function urp_add_user_page()
{
    global $wpdb;

    if (isset($_POST['submit_user'])) {
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $review_content = sanitize_textarea_field($_POST['review_content']);
        $rating = intval($_POST['rating']);

        // Generate PDF from review content
        $pdf_url = generate_or_append_pdf_from_review($name, $review_content, $rating);

        // Create downloadable product in WooCommerce
        $product_id = create_downloadable_product($name, $pdf_url);

        // Insert user into the database with product ID
        $wpdb->insert(
            "{$wpdb->prefix}urp_custom_users",
            array(
                'name' => $name,
                'email' => $email,
                'product_id' => $product_id,
            ),
            array(
                '%s',
                '%s',
                '%d',
            )
        );

        // Get the ID of the newly inserted user
        $user_id = $wpdb->insert_id;

        // Insert review into the database
        $wpdb->insert(
            "{$wpdb->prefix}urp_custom_reviews",
            array(
                'user_id' => $user_id,
                'reviewer_name' => wp_get_current_user()->display_name,
                'review_content' => $review_content,
                'rating' => $rating,
                'status' => 'approved',
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%d',
                '%s'
            )
        );

        echo '<div class="updated"><p>User added successfully! Review added for the user, and product created.</p></div>';
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

    // Query to get all reviews with user names
    $reviews = $wpdb->get_results("
        SELECT r.id, r.user_id, r.reviewer_name, r.review_content, r.rating, r.status, u.name as user_name
        FROM {$wpdb->prefix}urp_custom_reviews r
        LEFT JOIN {$wpdb->prefix}urp_custom_users u ON r.user_id = u.id
    ");

    // Display the review list in a table
    echo '<div class="wrap">';
    echo '<h2>Review List</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>User Profile</th><th>Reviewer Name</th><th>Review Content</th><th>Rating</th><th>Status</th></tr></thead><tbody>';

    foreach ($reviews as $review) {
        echo '<tr>';
        echo '<td>' . esc_html($review->id) . '</td>';
        echo '<td>' . esc_html($review->user_name) . '</td>';
        echo '<td>' . esc_html($review->reviewer_name) . '</td>';
        echo '<td>' . esc_html($review->review_content) . '</td>';
        echo '<td>' . esc_html($review->rating) . '</td>';
        echo '<td>' . esc_html($review->status) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}


// Example usage in urp_approve_reviews_page function:
function urp_approve_reviews_page()
{
    global $wpdb;

    // Check if a review is being approved
    if (isset($_POST['approve_review']) && isset($_POST['review_id'])) {
        $review_id = intval($_POST['review_id']);
        approve_review_and_update_pdf($review_id);
    }

    // Get all pending reviews
    $pending_reviews = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE status = 'pending'");

    // Display the pending reviews in a table
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


// Function to approve reviews and update PDF
function approve_review_and_update_pdf($review_id)
{
    global $wpdb;

    // Fetch review details
    $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE id = %d", $review_id));

    if ($review) {
        // Update review status to 'approved'
        $wpdb->update(
            "{$wpdb->prefix}urp_custom_reviews",
            array('status' => 'approved'),
            array('id' => $review_id),
            array('%s'),
            array('%d')
        );

        // Fetch user name for PDF
        $user_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $review->user_id));

        // Generate or append the PDF
        $pdf_url = generate_or_append_pdf_from_review($user_name, $review->review_content, $review->rating);

        if ($pdf_url) {
            // Ensure the product exists before updating
            $product = wc_get_product($review->user_id);
            if ($product) {
                $downloadable_files = $product->get_downloads();
                $downloadable_files[] = new WC_Product_Download(
                    array(
                        'name' => 'Review for ' . $user_name,
                        'file' => $pdf_url
                    )
                );
                $product->set_downloads($downloadable_files);
                $product->save();

                echo '<div class="notice notice-success is-dismissible"><p>Review approved and PDF updated successfully!</p></div>';
            } else {
                error_log('Product with ID ' . $review->user_id . ' not found.');
                echo '<div class="notice notice-error is-dismissible"><p>Review approved but the product was not found. PDF was not updated.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Review approved but there was an error generating the PDF.</p></div>';
        }
    }
}



// Function to generate or append PDF from review content
function generate_or_append_pdf_from_review($user_name, $review_content, $rating)
{
    global $wpdb;

    try {
        // Get the upload directory path and URL
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path']; // Use 'path' for the upload directory

        // Ensure the upload directory exists
        if (!file_exists($upload_path)) {
            wp_mkdir_p($upload_path);
        }

        // Define PDF file name and path
        $pdf_file_name = 'user_' . sanitize_title($user_name) . '_review.pdf';
        $pdf_file_path = $upload_path . '/' . $pdf_file_name;

        // Delete the existing PDF if it exists
        if (file_exists($pdf_file_path)) {
            unlink($pdf_file_path);
        }

        // Retrieve all approved reviews for the user
        $reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d AND status = 'approved'", $review->user_id));

        // Prepare HTML content for PDF
        $content = '<h1>Reviews for ' . esc_html($user_name) . '</h1>';
        foreach ($reviews as $review) {
            $content .= '<h2>Review by ' . esc_html($review->reviewer_name) . '</h2>';
            $content .= '<p>Review Content: ' . esc_html($review->review_content) . '</p>';
            $content .= '<p>Rating: ' . esc_html($review->rating) . '</p>';
            $content .= '<hr>';
        }

        // Generate PDF
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($content);
        $mpdf->Output($pdf_file_path, 'F');

        // Ensure the file has correct permissions
        chmod($pdf_file_path, 0644);

        // Check if the PDF file is correctly created
        if (!file_exists($pdf_file_path) || filesize($pdf_file_path) == 0) {
            throw new Exception('PDF file creation failed or the file is empty.');
        }

        // Convert the file path to a URL
        $pdf_file_url = $upload_dir['url'] . '/' . $pdf_file_name;

        // Debug output for verification
        error_log('Generated PDF URL: ' . $pdf_file_url);

        return $pdf_file_url;

    } catch (Exception $e) {
        error_log('Error generating PDF: ' . $e->getMessage());
        return false;
    }
}



// Function to create a downloadable product in WooCommerce
function create_downloadable_product($user_name, $pdf_url)
{
    $product = new WC_Product();
    $product->set_name('Review for ' . $user_name);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_description('Review PDF for ' . $user_name);
    $product->set_regular_price(10);
    $product->set_downloadable(true);
    $product->set_virtual(true);

    // Attach the PDF as a downloadable file
    $download_id = wp_generate_uuid4();
    $downloads = [
        $download_id => [
            'name' => 'Review PDF',
            'file' => $pdf_url
        ]
    ];
    $product->set_downloads($downloads);
    $product->save();

    return $product->get_id();
}

// Function to display reviews for a specific user
function urp_user_reviews_page()
{
    global $wpdb;

    // Check if the user ID is provided
    if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);

        // Get reviews for the specific user
        $reviews = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d",
                $user_id
            )
        );

        // Display the review list in a table
        echo '<div class="wrap">';
        echo '<h2>Reviews for User ID: ' . esc_html($user_id) . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Reviewer Name</th><th>Review Content</th><th>Rating</th><th>Status</th></tr></thead><tbody>';

        foreach ($reviews as $review) {
            echo '<tr>';
            echo '<td>' . esc_html($review->id) . '</td>';
            echo '<td>' . esc_html($review->reviewer_name) . '</td>';
            echo '<td>' . esc_html($review->review_content) . '</td>';
            echo '<td>' . esc_html($review->rating) . '</td>';
            echo '<td>' . esc_html($review->status) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    } else {
        echo '<div class="wrap"><h2>No user selected</h2></div>';
    }
}
?>