<?php
function urp_create_custom_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $users_table = $wpdb->prefix . 'urp_custom_users';
    $reviews_table = $wpdb->prefix . 'urp_custom_reviews';

    $sql = "
    CREATE TABLE {$wpdb->prefix}urp_custom_users (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;

    CREATE TABLE {$wpdb->prefix}urp_custom_reviews (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        reviewer_name tinytext NOT NULL,
        review_content text NOT NULL,
        rating tinyint(1) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        PRIMARY KEY  (id),
        FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}urp_custom_users(id) ON DELETE CASCADE
    ) $charset_collate;
";

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Logging to confirm table creation
    error_log("Custom tables created: $users_table, $reviews_table");
}
