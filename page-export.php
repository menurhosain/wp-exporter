<?php

/**
 * Plugin Name: Page Exporter 
 * Description: Export Single Page. 
 * Version: 0.0.1
 * Author URI: http://rstheme.com
 * Plugin URI: https://wordpress.org/plugins/ultimate-coming-soon/
 * Author: RSTheme
 * License: GPL v2 or later
 * License URI:http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: page-exporter
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.3
 */



// Add export link to page actions
add_filter('page_row_actions', 'spe_add_export_link', 10, 2);

function spe_add_export_link($actions, $post)
{
  if ($post->post_type === 'page') {
    $url = wp_nonce_url(
      admin_url('admin-post.php?action=spe_export_page&post_id=' . $post->ID),
      'spe_export_page_' . $post->ID
    );
    $actions['export_page'] = '<a href="' . esc_url($url) . '">Export XML</a>';
  }
  return $actions;
}

// Handle export request
add_action('admin_post_spe_export_page', 'spe_handle_export');

function spe_handle_export()
{
  if (empty($_GET['post_id'])) {
    wp_die('No page specified.');
  }

  $post_id = intval($_GET['post_id']);

  if (!current_user_can('export') || !wp_verify_nonce($_GET['_wpnonce'], 'spe_export_page_' . $post_id)) {
    wp_die('You do not have permission to export this page.');
  }

  $post = get_post($post_id);
  if (!$post) {
    wp_die('Page not found.');
  }

  require_once __DIR__ . '/includes/WP_Export_Fork.php';

  // Create temporary XML export for just this page
  $args = ['content' => 'page', 'post_id' => $post_id,];

  header('Content-Description: File Transfer');
  header('Content-Disposition: attachment; filename=' . sanitize_file_name($post->post_name) . '-export.xml');
  header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

  __export_wp_fork($args);
  exit;
}
