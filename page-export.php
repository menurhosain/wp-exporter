<?php
/**
 * Plugin Name: RS Exporter
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
add_filter('post_row_actions', 'spe_add_export_link', 10, 2);

function spe_add_export_link($actions, $post) {
		$url = wp_nonce_url(
			admin_url('admin-post.php?action=spe_export_page&post_id=' . $post->ID),
			'spe_export_page_' . $post->ID
		);
		$actions['export_page'] = '<a href="' . esc_url($url) . '">Export XML</a>';
	return $actions;
}

// Handle export request
add_action('admin_post_spe_export_page', 'spe_handle_export');

function spe_handle_export() {
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
	$args = ['content' => $post->post_type, 'post_id' => $post_id,];

	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename=' . sanitize_file_name($post->post_name) . '-export.xml');
	header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

	__export_wp_fork($args);
	exit;
}

/**
 * Add Export Options admin page to WordPress admin menu
 */
function add_export_options_admin_page() {
	add_menu_page(
		'Export Page Options',           // Page title
		'Export Page Options',           // Menu title
		'manage_options',           // Capability required
		'export-page-options',           // Menu slug
		'render_export_options_page', // Callback function
		'dashicons-download',       // Icon
		80                          // Position
	);
}
add_action('admin_menu', 'add_export_options_admin_page');

/**
 * Handle the export data request
 */
function handle_export_options_data() {
	// Check if export button was clicked
	if (!isset($_POST['export_data'])) {
		return;
	}

	// Verify nonce
	if (!isset($_POST['export_options_nonce']) || !wp_verify_nonce($_POST['export_options_nonce'], 'export_options_action')) {
		wp_die(__('Security check failed.'));
	}

	// Check user capabilities
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to perform this action.'));
	}

	// Get Elementor active kit ID
	$kit_id = get_option('elementor_active_kit');

	if (!$kit_id) {
		wp_die(__('No active Elementor kit found.'));
	}

	// Get kit settings
	$kit_settings = get_post_meta($kit_id, '_elementor_page_settings', true);

	// Get kit post data
	$kit_post = get_post($kit_id);

	// Prepare export data
	$export_data = array(
	'kit_id' => $kit_id,
	'kit_name' => $kit_post->post_title,
	'kit_settings' => $kit_settings,
	'export_date' => current_time('Y-m-d H:i:s'),
	'site_url' => get_site_url(),
	);

	// Convert to JSON
	$json_data = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

	// Set headers for file download
	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="elementor-kit-export-' . date('Y-m-d-H-i-s') . '.json"');
	header('Content-Length: ' . strlen($json_data));
	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: 0');

	// Output JSON and exit
	echo $json_data;
	exit;
}
add_action('admin_init', 'handle_export_options_data');

/**
 * Render the Export Options admin page
 */
function render_export_options_page() {
	// Check user capabilities
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	// Get Elementor kit info for display
	$kit_id = get_option('elementor_active_kit');
	$kit_post = $kit_id ? get_post($kit_id) : null;

	?>
  <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="card">
      <h2>Export Elementor Kit Data</h2>

      <?php if ($kit_post): ?>
        <p><strong>Active Kit:</strong> <?php echo esc_html($kit_post->post_title); ?> (ID: <?php echo esc_html($kit_id); ?>)</p>
        <p>This will export all global colors, fonts, and site settings from your active Elementor kit.</p>
      <?php else: ?>
        <p style="color: #d63638;"><strong>Warning:</strong> No active Elementor kit found.</p>
      <?php endif; ?>

      <form method="post" action="">
        <?php wp_nonce_field('export_options_action', 'export_options_nonce'); ?>

        <p>
          <button type="submit" name="export_data" class="button button-primary" <?php echo !$kit_post ? 'disabled' : ''; ?>>
            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
            Export Kit Data as JSON
          </button>
        </p>
      </form>
    </div>

    <div class="card" style="margin-top: 20px;">
      <h3>What's Included in the Export?</h3>
      <ul style="list-style: disc; margin-left: 20px;">
        <li>Global Colors (System & Custom)</li>
        <li>Global Fonts (System & Custom Typography)</li>
        <li>Site Settings (Buttons, Forms, Lightbox, etc.)</li>
        <li>Kit metadata and configuration</li>
      </ul>
    </div>
  </div>
<?php
}


/**
 * Elementor Page Template Export
 */
add_filter('page_row_actions', 'rs_add_elementor_json_export_link', 10, 2);

function rs_add_elementor_json_export_link($actions, $post) {
	// Only show for posts built with Elementor
	if (get_post_meta($post->ID, '_elementor_edit_mode', true) !== 'builder') {
		return $actions;
	}

	$url = wp_nonce_url(
		admin_url('admin-post.php?action=rs_export_page_json&post_id=' . $post->ID),
		'rs_export_page_json' . $post->ID
	);
	$actions['export_page_json'] = '<a href="' . esc_url($url) . '">Export Elementor JSON</a>';

	return $actions;
}

add_action('admin_post_rs_export_page_json', 'rs_handle_export_page_json');

function rs_handle_export_page_json() {
	if (empty($_GET['post_id'])) {
		wp_die('No post specified.');
	}

	$post_id = intval($_GET['post_id']);

	if (!current_user_can('edit_posts') || !wp_verify_nonce($_GET['_wpnonce'], 'rs_export_page_json' . $post_id)) {
		wp_die('You do not have permission to export this post.');
	}

	$post = get_post($post_id);
	if (!$post) {
		wp_die('Post not found.');
	}

	$elementor_data = get_post_meta($post_id, '_elementor_data', true);
	if (empty($elementor_data)) {
		wp_die('No Elementor data found for this post. Make sure it was built with Elementor.');
	}

	$content = json_decode($elementor_data, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		wp_die('Failed to decode Elementor data.');
	}

	$page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
	if (!is_array($page_settings)) {
		$page_settings = [];
	}

	// Replace absolute site URL with Elementor's placeholder so the JSON is portable
	$content_json = str_replace(get_site_url(), '[url]', json_encode($content));
	$content = json_decode($content_json, true);

	$export_data = [
		'content'       => $content,
		'page_settings' => $page_settings,
		'version'       => '0.4',
		'title'         => $post->post_title,
		'type'          => 'page',
	];

	$filename = 'data.json';

	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: 0');

	echo json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}