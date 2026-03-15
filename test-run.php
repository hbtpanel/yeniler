<?php
// Minimal stubs to emulate required WordPress functions/constants for basic runtime include testing.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
function add_action( $hook, $callable ) {
    // no-op
}
function is_admin() {
    return false;
}
function plugin_dir_path( $file ) {
    return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
    return 'file://' . dirname( $file ) . '/';
}
function get_bloginfo( $show = '' ) {
    if ( 'version' === $show ) {
        return '5.9';
    }
    return '';
}
function esc_html__( $text, $domain = null ) {
    return $text;
}
function register_activation_hook( $file, $callback ) {
    // no-op for testing
}
function register_deactivation_hook( $file, $callback ) {
    // no-op for testing
}
function plugin_basename( $file ) {
    return basename( $file );
}
function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) {
    // no-op for testing
}

try {
    require_once __DIR__ . '/hbt-trendyol-profit-tracker.php';
    echo "Plugin included successfully\n";
} catch ( Throwable $e ) {
    echo "Caught throwable: ", $e->getMessage(), "\n";
} catch ( Exception $e ) {
    echo "Caught exception: ", $e->getMessage(), "\n";
}
