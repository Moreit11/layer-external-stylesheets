<?php
/**
 * Plugin Name: Layer External Stylesheets
 * Plugin URI: https://github.com/yourusername/layer-external-stylesheets
 * Description: Wraps plugin stylesheets in CSS cascade layers for better style control
 * Version: 1.0.0
 * Author: Moritz Reitz
 * Author URI: https://moritzreitz.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: layer-external-stylesheets
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Layer_External_Stylesheets {

    private $layer_name = 'plugin-styles';
    private $cache_dir;
    private $cache_dir_url;
    private $stylesheets_to_layer = array();

    public function __construct() {
        // Set cache directory in uploads
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/layered-styles';
        $this->cache_dir_url = $upload_dir['baseurl'] . '/layered-styles';

        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'dequeue_original_styles'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_layered_styles'), 101);
        add_action('wp_enqueue_scripts', array($this, 'cache_registered_styles'), 999);

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Create cache directory if it doesn't exist
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            // Add .htaccess for security
            $htaccess = $this->cache_dir . '/.htaccess';
            file_put_contents($htaccess, "# Protect directory listing\nOptions -Indexes");
        }

        // Get stylesheets to layer from settings
        $this->stylesheets_to_layer = $this->get_stylesheets_config();
    }

    /**
     * Get stylesheets configuration
     */
    private function get_stylesheets_config() {
        // Get from options
        $saved_config = get_option('les_stylesheets_config', array());

        // Return saved config without defaults
        return $saved_config;
    }

    /**
     * Dequeue original plugin stylesheets
     */
    public function dequeue_original_styles() {
        foreach ($this->stylesheets_to_layer as $config) {
            if (!empty($config['enabled']) && !empty($config['handle'])) {
                wp_dequeue_style($config['handle']);
                wp_deregister_style($config['handle']);
            }
        }
    }

    /**
     * Generate layered CSS file
     */
    private function generate_layered_css($handle, $source_path) {
        // Check if source file exists
        if (!file_exists($source_path)) {
            return false;
        }

        // Create cache filename
        $cache_filename = sanitize_file_name($handle) . '-layered.css';
        $cache_file_path = $this->cache_dir . '/' . $cache_filename;

        // Regenerate if source is newer or cache doesn't exist
        if (!file_exists($cache_file_path) || filemtime($source_path) > filemtime($cache_file_path)) {
            $css_content = file_get_contents($source_path);

            if ($css_content === false) {
                return false;
            }

            // Wrap in CSS layer
            $layered_css = sprintf(
                "/**\n * Layered stylesheet for: %s\n * Generated: %s\n * Source: %s\n */\n\n@layer %s {\n%s\n}\n",
                $handle,
                date('Y-m-d H:i:s'),
                basename($source_path),
                $this->layer_name,
                $css_content
            );

            // Write to cache file
            $result = file_put_contents($cache_file_path, $layered_css);

            if ($result === false) {
                return false;
            }
        }

        return $cache_file_path;
    }

    /**
     * Enqueue layered stylesheets
     */
    public function enqueue_layered_styles() {
        foreach ($this->stylesheets_to_layer as $config) {
            if (empty($config['enabled']) || empty($config['handle']) || empty($config['source'])) {
                continue;
            }

            $cache_file = $this->generate_layered_css($config['handle'], $config['source']);

            if ($cache_file && file_exists($cache_file)) {
                $cache_filename = basename($cache_file);
                $cache_url = $this->cache_dir_url . '/' . $cache_filename;
                $version = filemtime($cache_file);

                wp_enqueue_style(
                    $config['handle'] . '-layered',
                    $cache_url,
                    array(),
                    $version
                );
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Layer External Stylesheets',
            'Layered Styles',
            'manage_options',
            'layer-external-stylesheets',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('les_settings', 'les_stylesheets_config');
        register_setting('les_settings', 'les_layer_name');
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings
        if (isset($_POST['les_save_settings']) && check_admin_referer('les_settings_action', 'les_settings_nonce')) {
            $this->save_admin_settings();
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        // Clear cache
        if (isset($_POST['les_clear_cache']) && check_admin_referer('les_clear_cache_action', 'les_clear_cache_nonce')) {
            $this->clear_cache();
            echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
        }

        include plugin_dir_path(__FILE__) . 'admin-page.php';
    }

    /**
     * Save admin settings
     */
    private function save_admin_settings() {
        $config = array();

        if (isset($_POST['stylesheets']) && is_array($_POST['stylesheets'])) {
            foreach ($_POST['stylesheets'] as $key => $stylesheet) {
                if (!empty($stylesheet['handle'])) {
                    $config[$key] = array(
                        'handle' => sanitize_text_field($stylesheet['handle']),
                        'source' => sanitize_text_field($stylesheet['source']),
                        'enabled' => !empty($stylesheet['enabled'])
                    );
                }
            }
        }

        update_option('les_stylesheets_config', $config);

        if (isset($_POST['layer_name'])) {
            $this->layer_name = sanitize_text_field($_POST['layer_name']);
            update_option('les_layer_name', $this->layer_name);
        }
    }

    /**
     * Clear cache directory
     */
    private function clear_cache() {
        $files = glob($this->cache_dir . '/*.css');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Cache registered styles from frontend for display in admin
     */
    public function cache_registered_styles() {
        global $wp_styles;

        if (!is_object($wp_styles)) {
            return;
        }

        $registered_styles = array();

        foreach ($wp_styles->registered as $handle => $style) {
            // Get the source URL
            $src = $style->src;

            // Try to convert URL to file path
            $src_path = '';
            if (!empty($src)) {
                // Handle relative URLs
                if (strpos($src, '//') === false) {
                    $src = site_url($src);
                }

                // Try to convert URL to file path
                $site_url = site_url();
                $wp_content_url = content_url();

                if (strpos($src, $wp_content_url) === 0) {
                    $src_path = WP_CONTENT_DIR . str_replace($wp_content_url, '', strtok($src, '?'));
                } else if (strpos($src, $site_url) === 0) {
                    $src_path = ABSPATH . str_replace($site_url, '', strtok($src, '?'));
                }
            }

            $registered_styles[$handle] = array(
                'src' => $src,
                'src_path' => $src_path,
                'deps' => $style->deps,
                'ver' => $style->ver
            );
        }

        // Only update if we have styles and it's different from cached version
        if (!empty($registered_styles)) {
            $cached = get_option('les_registered_styles_cache', array());
            if ($cached !== $registered_styles) {
                update_option('les_registered_styles_cache', $registered_styles, false);
            }
        }
    }
}

// Initialize plugin
new Layer_External_Stylesheets();
