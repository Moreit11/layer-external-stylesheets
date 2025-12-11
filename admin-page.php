<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$config = get_option('les_stylesheets_config', array());
$layer_name = get_option('les_layer_name', 'plugin-styles');
?>

<div class="wrap">
    <h1>Layer External Stylesheets Settings</h1>

    <p>This plugin wraps plugin stylesheets in CSS cascade layers, giving you better control over the CSS cascade and specificity.</p>

    <h2 class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active">Settings</a>
        <a href="#cache" class="nav-tab">Cache</a>
        <a href="#help" class="nav-tab">Help</a>
    </h2>

    <div id="settings" class="tab-content">
        <form method="post" action="">
            <?php wp_nonce_field('les_settings_action', 'les_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="layer_name">CSS Layer Name</label>
                    </th>
                    <td>
                        <input type="text"
                               id="layer_name"
                               name="layer_name"
                               value="<?php echo esc_attr($layer_name); ?>"
                               class="regular-text">
                        <p class="description">The name of the CSS @layer that will wrap all plugin styles.</p>
                    </td>
                </tr>
            </table>

            <h2>Configured Stylesheets</h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Enabled</th>
                        <th>Style Handle</th>
                        <th>Source Path</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="stylesheets-list">
                    <?php
                    $index = 0;
                    if (!empty($config)) {
                        foreach ($config as $key => $stylesheet) :
                    ?>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       name="stylesheets[<?php echo $index; ?>][enabled]"
                                       value="1"
                                       <?php checked(!empty($stylesheet['enabled'])); ?>>
                            </td>
                            <td>
                                <input type="text"
                                       name="stylesheets[<?php echo $index; ?>][handle]"
                                       value="<?php echo esc_attr($stylesheet['handle']); ?>"
                                       class="regular-text"
                                       placeholder="e.g., contact-form-7">
                            </td>
                            <td>
                                <input type="text"
                                       name="stylesheets[<?php echo $index; ?>][source]"
                                       value="<?php echo esc_attr($stylesheet['source']); ?>"
                                       class="large-text"
                                       placeholder="e.g., <?php echo WP_CONTENT_DIR; ?>/plugins/plugin-name/css/style.css">
                            </td>
                            <td>
                                <button type="button" class="button remove-stylesheet">Remove</button>
                            </td>
                        </tr>
                    <?php
                        $index++;
                        endforeach;
                    }
                    ?>
                </tbody>
            </table>

            <p>
                <button type="button" id="add-stylesheet" class="button">Add Stylesheet</button>
            </p>

            <p class="submit">
                <input type="submit" name="les_save_settings" class="button button-primary" value="Save Changes">
            </p>
        </form>

        <h2>Registered Style Handles (Frontend)</h2>
        <p>These are the style handles currently registered in WordPress. Click a handle to add it to your configuration.</p>

        <?php
        // Get registered styles from the frontend
        // We need to capture them from a frontend request since admin styles differ
        $registered_styles_option = get_option('les_registered_styles_cache', array());

        if (empty($registered_styles_option)) {
            echo '<p><em>No cached style handles found. Visit your site\'s frontend, then refresh this page to see registered handles.</em></p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Handle</th><th>Source</th><th>Action</th></tr></thead>';
            echo '<tbody>';

            foreach ($registered_styles_option as $handle => $data) {
                $src = !empty($data['src']) ? esc_html($data['src']) : '<em>No source</em>';
                $handle_esc = esc_attr($handle);
                $src_path = !empty($data['src_path']) ? esc_attr($data['src_path']) : '';

                echo '<tr>';
                echo '<td><code>' . esc_html($handle) . '</code></td>';
                echo '<td style="font-size: 11px; word-break: break-all;">' . $src . '</td>';
                echo '<td><button type="button" class="button button-small add-handle-to-config" data-handle="' . $handle_esc . '" data-source="' . $src_path . '">Add</button></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
        ?>
    </div>

    <div id="cache" class="tab-content" style="display: none;">
        <h2>Cache Management</h2>

        <p>Layered CSS files are cached for performance. Clear the cache to regenerate all layered stylesheets.</p>

        <form method="post" action="">
            <?php wp_nonce_field('les_clear_cache_action', 'les_clear_cache_nonce'); ?>
            <p>
                <input type="submit" name="les_clear_cache" class="button button-secondary" value="Clear Cache">
            </p>
        </form>

        <h3>Cache Information</h3>
        <?php
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/layered-styles';

        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . '/*.css');
            if ($files) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>File</th><th>Size</th><th>Modified</th></tr></thead>';
                echo '<tbody>';
                foreach ($files as $file) {
                    $filename = basename($file);
                    $size = size_format(filesize($file));
                    $modified = date('Y-m-d H:i:s', filemtime($file));
                    echo "<tr><td>{$filename}</td><td>{$size}</td><td>{$modified}</td></tr>";
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No cached files found.</p>';
            }
        } else {
            echo '<p>Cache directory does not exist yet.</p>';
        }
        ?>
    </div>

    <div id="help" class="tab-content" style="display: none;">
        <h2>How to Use</h2>

        <h3>1. Find Plugin Style Handles</h3>
        <p>To find the style handle of a plugin, you can:</p>
        <ul>
            <li>View the page source and look for <code>&lt;link&gt;</code> tags with <code>id='handle-css'</code></li>
            <li>Use browser developer tools to inspect stylesheets</li>
            <li>Check the plugin's source code for <code>wp_enqueue_style()</code> calls</li>
        </ul>

        <h3>2. Locate Source Files</h3>
        <p>Plugin CSS files are typically located in:</p>
        <code><?php echo WP_CONTENT_DIR; ?>/plugins/plugin-name/assets/css/</code>

        <h3>3. Add Configuration</h3>
        <p>Click "Add Stylesheet" and enter:</p>
        <ul>
            <li><strong>Style Handle:</strong> The handle used by <code>wp_enqueue_style()</code></li>
            <li><strong>Source Path:</strong> Full server path to the CSS file</li>
            <li>Check "Enabled" to activate the layering</li>
        </ul>

        <h3>4. CSS Cascade Layers</h3>
        <p>This plugin uses CSS <code>@layer</code> to wrap plugin styles. This allows you to control specificity:</p>
        <pre>/* In your theme CSS */
@layer plugin-styles, theme-styles;

@layer theme-styles {
    /* Your theme styles will override plugin styles
       without needing !important or high specificity */
}</pre>

        <h3>Common Plugin Handles</h3>
        <ul>
            <li><strong>Contact Form 7:</strong> contact-form-7</li>
            <li><strong>WooCommerce:</strong> woocommerce-general, woocommerce-layout, woocommerce-smallscreen</li>
            <li><strong>Fluent Forms:</strong> fluent-form-styles</li>
            <li><strong>Elementor:</strong> elementor-frontend</li>
        </ul>
    </div>
</div>

<style>
    .tab-content {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-top: none;
        margin-bottom: 20px;
    }
    .nav-tab-wrapper {
        margin-bottom: 0 !important;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        $(target).show();
    });

    // Add new stylesheet row
    var stylesheetIndex = <?php echo $index; ?>;

    $('#add-stylesheet').on('click', function() {
        var newRow = '<tr>' +
            '<td><input type="checkbox" name="stylesheets[' + stylesheetIndex + '][enabled]" value="1" checked></td>' +
            '<td><input type="text" name="stylesheets[' + stylesheetIndex + '][handle]" class="regular-text" placeholder="e.g., contact-form-7"></td>' +
            '<td><input type="text" name="stylesheets[' + stylesheetIndex + '][source]" class="large-text" placeholder="<?php echo WP_CONTENT_DIR; ?>/plugins/plugin-name/css/style.css"></td>' +
            '<td><button type="button" class="button remove-stylesheet">Remove</button></td>' +
            '</tr>';

        $('#stylesheets-list').append(newRow);
        stylesheetIndex++;
    });

    // Remove stylesheet row
    $(document).on('click', '.remove-stylesheet', function() {
        $(this).closest('tr').remove();
    });

    // Add handle from registered styles list
    $(document).on('click', '.add-handle-to-config', function() {
        var handle = $(this).data('handle');
        var source = $(this).data('source');

        var newRow = '<tr>' +
            '<td><input type="checkbox" name="stylesheets[' + stylesheetIndex + '][enabled]" value="1" checked></td>' +
            '<td><input type="text" name="stylesheets[' + stylesheetIndex + '][handle]" class="regular-text" value="' + handle + '"></td>' +
            '<td><input type="text" name="stylesheets[' + stylesheetIndex + '][source]" class="large-text" value="' + source + '"></td>' +
            '<td><button type="button" class="button remove-stylesheet">Remove</button></td>' +
            '</tr>';

        $('#stylesheets-list').append(newRow);
        stylesheetIndex++;

        // Scroll to the new row
        $('html, body').animate({
            scrollTop: $('#stylesheets-list tr:last').offset().top - 100
        }, 300);
    });
});
</script>
<?php
