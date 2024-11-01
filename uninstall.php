<?php
// Exit if accessed directly
if( ! defined('WP_UNINSTALL_PLUGIN')){
    exit;
}
// Delete plugin options
delete_option('text_to_audio_with_ads_api_key');
delete_option('text_to_audio_with_ads_api_password');
delete_option('text_to_audio_with_ads_player_secret');
delete_option('text_to_audio_with_ads_error_logging');

// Clean up any additional data
// For example, if your plugin stores any custom post meta data or custom database tables, delete them here

// Example: Delete custom post meta
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_previous_status'");
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_previous_content'");

?>
