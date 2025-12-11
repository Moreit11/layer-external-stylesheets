<?php
//1. DISABLE PUBLIC STYLESHEET
function disable_default_stylesheets() {
  // deregister puplic styles
	wp_dequeue_style('fluent-form-styles'); wp_deregister_style('fluent-form-styles');
}
//2. COPY STYLES FROM FLUENT FORMS DIRECTORY
add_action('wp_enqueue_scripts', 'disable_default_stylesheets', 100);
// write file with styles from fluent forms
function enqueue_fluent_forms_layered() {
    $css_file_path = get_stylesheet_directory() . '/css/ff-styles-layered.css';
    $source_css_path = WP_CONTENT_DIR . '/plugins/fluentform/assets/css/fluent-forms-public.css';
    // Check if source file exists
    if (!file_exists($source_css_path)) {
        return;
    }
    // Regenerate CSS file if it doesn't exist or source has changed
    if (!file_exists($css_file_path) || filemtime($source_css_path) > filemtime($css_file_path)) {
        $css_content = file_get_contents($source_css_path);
        if ($css_content === false) {
            return;
        }
        $layered_css = "@layer ff-styling {\n" . $css_content . "\n}";
        file_put_contents($css_file_path, $layered_css);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_fluent_forms_layered', 1);
// 3. ENQUE NEWLY CREATED STYLESHEET
function add_stylesheets() {
  //custom styles sheets
  wp_enqueue_style('fluent-form-styles-layered', get_stylesheet_directory_uri() . '/css/ff-styles-layered.css',array(), filemtime($stylesheet_dir . '/css/style.css'));
}
add_action('wp_enqueue_scripts', 'add_stylesheets');
?>