<?php
/**
 * Plugin Name: Layer External Stylesheets
 * Plugin URI: https://github.com/Moreit11/layer-external-stylesheets
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
        add_action('wp_enqueue_scripts', array($this, 'dequeue_original_styles'), 999);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_layered_styles'), 1000);
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

        // Get hardcoded stylesheets configuration
        $this->stylesheets_to_layer = $this->get_stylesheets_config();
    }

    /**
     * Get stylesheets configuration
     */
    private function get_stylesheets_config() {
        // Hardcoded Fluent Forms stylesheets
        return array(
            'fluent-form-styles' => array(
                'handle' => 'fluent-form-styles',
                'source' => 'plugins/fluentform/assets/css/fluent-forms-public.css',
                'enabled' => true
            ),
            'fluent-forms-conversational' => array(
                'handle' => 'fluent_forms_conversational_form',
                'source' => 'plugins/fluentform/app/Services/FluentConversational/public/css/conversationalForm.css',
                'enabled' => true
            )
        );
    }

    /**
     * Dequeue original plugin stylesheets
     */
    public function dequeue_original_styles() {
        global $wp_styles;

        foreach ($this->stylesheets_to_layer as $config) {
            if (!empty($config['enabled']) && !empty($config['handle'])) {
                // Check if style is actually registered before dequeuing
                if (isset($wp_styles->registered[$config['handle']])) {
                    wp_dequeue_style($config['handle']);
                    wp_deregister_style($config['handle']);
                }
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

            // Convert relative path to absolute
            $source_path = $this->get_absolute_path($config['source']);

            $cache_file = $this->generate_layered_css($config['handle'], $source_path);

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
     * Convert relative path to absolute path
     */
    private function get_absolute_path($path) {
        // If already absolute, return as is
        if (file_exists($path)) {
            return $path;
        }

        // Try WP_CONTENT_DIR first (most common)
        $content_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        if (file_exists($content_path)) {
            return $content_path;
        }

        // Try ABSPATH
        $abs_path = ABSPATH . ltrim($path, '/');
        if (file_exists($abs_path)) {
            return $abs_path;
        }

        // Return original if not found
        return $path;
    }

}

// Initialize plugin
new Layer_External_Stylesheets();
