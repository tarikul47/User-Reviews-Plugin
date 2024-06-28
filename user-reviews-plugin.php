<?php
/**
 * Plugin Name: User Reviews Plugin
 * Description: A plugin to manage users and their reviews.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin paths
define('URP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('URP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once URP_PLUGIN_DIR . 'includes/database.php';
require_once URP_PLUGIN_DIR . 'includes/import.php';
require_once URP_PLUGIN_DIR . 'includes/admin.php';
require_once URP_PLUGIN_DIR . 'includes/frontend.php';

// Activation hook to create database tables
register_activation_hook(__FILE__, 'urp_create_custom_tables');

// Add rewrite rules on plugin activation
function urp_add_rewrite_rules()
{
    add_rewrite_rule('^user/([0-9]+)/?', 'index.php?urp_user_id=$matches[1]', 'top');
}
register_activation_hook(__FILE__, 'urp_add_rewrite_rules');

// Flush rewrite rules on plugin activation and deactivation
function urp_flush_rewrite_rules()
{
    urp_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'urp_flush_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// Add custom query vars
function urp_query_vars($vars)
{
    $vars[] = 'urp_user_id';
    return $vars;
}
add_filter('query_vars', 'urp_query_vars');

// Template redirect
function urp_template_redirect()
{
    if (get_query_var('urp_user_id')) {
        include plugin_dir_path(__FILE__) . 'templates/single-user.php';
        exit;
    }
}
add_action('template_redirect', 'urp_template_redirect');

