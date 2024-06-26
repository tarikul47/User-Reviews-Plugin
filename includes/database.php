<?php
function urp_create_custom_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $users_table = "CREATE TABLE {$wpdb->prefix}urp_custom_users (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email text NOT NULL,
        product_id mediumint(9) DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $reviews_table = "CREATE TABLE {$wpdb->prefix}urp_custom_reviews (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        reviewer_name tinytext NOT NULL,
        review_content text NOT NULL,
        rating int(1) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($users_table);
    dbDelta($reviews_table);

    // Logging to confirm table creation
    error_log("Custom tables created: $users_table, $reviews_table");
}
