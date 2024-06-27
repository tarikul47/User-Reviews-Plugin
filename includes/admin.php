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

        // Check if product ID is valid
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                // Get all downloadable files for the product
                $downloads = $product->get_downloads();
                foreach ($downloads as $download) {
                    // Delete the actual file from the server
                    $file_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $download['file']);
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }

            // Force delete the product
            wp_delete_post($product_id, true);
        }

        echo '<div class="updated"><p>User, related reviews, and associated product and PDF file(s) deleted successfully!</p></div>';
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

        $content = '<h1>Reviews for ' . $name . '</h1>';
        $content .= '<h2>Review by ' . esc_html(wp_get_current_user()->display_name) . '</h2>';
        $content .= '<p>Review Content: ' . esc_html($review_content) . '</p>';
        $content .= '<p>Rating: ' . esc_html($rating) . '</p>';
        $content .= '<hr>';


        // Generate PDF from review content
        $pdf_url = generate_product_pdf_from_person_review($name, $content);

        // Create downloadable product in WooCommerce
        $product_id = create_or_update_downloadable_product($name, $pdf_url);

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


// Function to display approve reviews page
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

        // Fetch user name and product ID
        $user = $wpdb->get_row($wpdb->prepare("SELECT name, product_id FROM {$wpdb->prefix}urp_custom_users WHERE id = %d", $review->user_id));
        $user_name = $user->name;
        $product_id = $user->product_id;

        // Retrieve all approved reviews for the user
        $existing_reviews = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}urp_custom_reviews WHERE user_id = %d AND status = 'approved'", $review->user_id));

        $content = '<h1>Reviews for ' . $user_name . '</h1>';
        foreach ($existing_reviews as $existing_review) {
            $content .= '<h2>Review by ' . esc_html($existing_review->reviewer_name) . '</h2>';
            $content .= '<p>Review Content: ' . esc_html($existing_review->review_content) . '</p>';
            $content .= '<p>Rating: ' . esc_html($existing_review->rating) . '</p>';
            $content .= '<hr>';
        }

        // Generate PDF
        $pdf_url = generate_product_pdf_from_person_review($user_name, $content);

        if ($pdf_url) {
            // Use the create_or_update_downloadable_product function to update the existing product
            $updated_product_id = create_or_update_downloadable_product($user_name, $pdf_url, $product_id);

            if ($updated_product_id) {
                echo '<div class="notice notice-success is-dismissible"><p>Review approved and PDF updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Review approved but there was an error updating the product with the new PDF.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Review approved but there was an error generating the PDF.</p></div>';
        }
    }
}


// Function to generate PDF from review content
function generate_product_pdf_from_person_review($user_name, $content)
{
    try {
        // Generate PDF
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($content);

        // Save PDF to server
        $upload_dir = wp_upload_dir();
        $pdf_file = $upload_dir['path'] . '/user_' . sanitize_title($user_name) . '_review.pdf';
        $mpdf->Output($pdf_file, 'F');

        // Ensure the file has correct permissions
        chmod($pdf_file, 0644);

        // Check if the PDF file is correctly created
        if (!file_exists($pdf_file) || filesize($pdf_file) == 0) {
            throw new Exception('PDF file creation failed or the file is empty.');
        }

        return $upload_dir['url'] . '/user_' . sanitize_title($user_name) . '_review.pdf';
    } catch (Exception $e) {
        error_log('Error generating PDF: ' . $e->getMessage());
        return false;
    }
}


// Function to create or update a downloadable product in WooCommerce
function create_or_update_downloadable_product($user_name, $pdf_url, $product_id = null)
{
    if ($product_id) {
        // If product ID is provided, update the existing product
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log('Product with ID ' . $product_id . ' not found.');
            return false;
        }
    } else {
        // If no product ID is provided, create a new product
        $product = new WC_Product();
    }

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
