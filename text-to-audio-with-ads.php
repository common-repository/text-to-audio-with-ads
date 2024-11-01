<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/*
Plugin Name: Text-to-Audio With Ads
Description: A plugin to hook into the content body and convert your articles' Text to Audio
asynchronously when an article is created or updated.
Version: 1.1.9
Author: Kuzalab Creatives
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: text-to-audio-with-ads
Domain Path: /languages
*/

//enqueue the script
function text_to_audio_with_ads_enqueue_scripts($hook){
    if($hook === 'post.php' || $hook === 'post-new.php'){
        wp_enqueue_script('text-to-audio-with-ads', plugin_dir_url(__FILE__).'css/text-to-audio-with-ads.css', array(), '1.0.0');
    }
}
add_action('admin_enqueue_scripts', 'text_to_audio_with_ads_enqueue_scripts');

// Add settings menu item
function text_to_audio_with_ads_add_settings_page(){
    add_options_page(
        __('Text to Audio Settings', 'text-to-audio-with-ads'),
        __('Text to Audio Settings', 'text-to-audio-with-ads'),
        'manage_options',
        'text_to_audio_with_ads-settings',
        'text_to_audio_with_ads_render_settings_page'
    );
}
add_action('admin_menu', 'text_to_audio_with_ads_add_settings_page');

// Render the settings page
function text_to_audio_with_ads_render_settings_page(){
    ?>
    <div class="wrap">
        <h2><?php _e('Text to Audio Settings', 'text-to-audio-with-ads'); ?></h2>
        <p><?php _e('Vocalize uses the power of AI to convert your readers into listeners. No dev work required, this solution generates brand new revenue while also being an amazing value add to publishers’ offerings. Visit', 'text-to-audio-with-ads'); ?> <a href="https://vocalize.africa/"><?php _e('Vocalize', 'text-to-audio-with-ads'); ?></a> <?php _e('to get started', 'text-to-audio-with-ads'); ?></p>
        <form action="options.php" method="post">
            <?php
            settings_fields('text_to_audio_with_ads_settings_group');
            do_settings_sections('text_to_audio_with_ads-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function text_to_audio_with_ads_register_settings(){
    register_setting('text_to_audio_with_ads_settings_group', 'text_to_audio_with_ads_api_key', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('text_to_audio_with_ads_settings_group', 'text_to_audio_with_ads_api_password', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    // Register additional settings for Auth values
    register_setting('text_to_audio_with_ads_settings_group','text_to_audio_with_ads_player_secret', array(
        'sanitize_callback' => 'sanitize_text_field'
    ) );
    register_setting('text_to_audio_with_ads_settings_group', 'text_to_audio_with_ads_error_logging', array(
        'sanitize_callback' => 'absint'
    ));

    add_settings_section(
        'text_to_audio_with_ads_settings_section',
        __('API Credentials', 'text-to-audio-with-ads'),
        null,
        'text_to_audio_with_ads-settings'
    );
    add_settings_field(
        'text_to_audio_with_ads_api_password',
        __('Application/Publisher ID', 'text-to-audio-with-ads'),
        'text_to_audio_with_ads_api_password_callback',
        'text_to_audio_with_ads-settings',
        'text_to_audio_with_ads_settings_section'
    );

    add_settings_field(
        'text_to_audio_with_ads_api_key',
        __('Write API Key', 'text-to-audio-with-ads'),
        'text_to_audio_with_ads_api_key_callback',
        'text_to_audio_with_ads-settings',
        'text_to_audio_with_ads_settings_section'
    );
    add_settings_field(
        'text_to_audio_with_ads_player_secret',
        __('Read API Key', 'text-to-audio-with-ads'),
        'text_to_audio_with_ads_api_secret_callback',
        'text_to_audio_with_ads-settings',
        'text_to_audio_with_ads_settings_section'
    );
    add_settings_field(
        'text_to_audio_with_ads_error_logging',
        __('Enable Error Logging', 'text-to-audio-with-ads'),
        'text_to_audio_with_ads_error_logging_callback',
        'text_to_audio_with_ads-settings',
        'text_to_audio_with_ads_settings_section'
    );

}
add_action('admin_init', 'text_to_audio_with_ads_register_settings');

// Callback function for API Key field
function text_to_audio_with_ads_api_key_callback() {
    $api_key = get_option('text_to_audio_with_ads_api_key');
    echo '<input type="text" id="text_to_audio_with_ads_api_key" name="text_to_audio_with_ads_api_key" value="' . esc_attr($api_key) . '" />';
}

// Callback function for API Password field
function text_to_audio_with_ads_api_password_callback() {
    $api_publisher = get_option('text_to_audio_with_ads_api_password');
    echo '<input type="text" id="text_to_audio_with_ads_api_password" name="text_to_audio_with_ads_api_password" value="' . esc_attr($api_publisher) . '" />';
}

function text_to_audio_with_ads_api_secret_callback() {
    $publisher_secret = get_option('text_to_audio_with_ads_player_secret');
    echo '<input type="text" id="text_to_audio_with_ads_player_secret" name="text_to_audio_with_ads_player_secret" value="' . esc_attr($publisher_secret) . '" />';
}

// Schedule the API call asynchronously
function text_to_audio_with_ads_schedule_post_data($post_id, $post, $update){
    // Check if this is an autosave, if so, do nothing.
    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
        return;
    }
    // if (in_array($post->post_status, array('auto-draft'))) {
    //     return;
    // }
    // Check the post type and user permissions
    // if ($post->post_type != 'post') {
    //     return;
    // }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    // Get the previous status of the post
    $previous_status = get_post_meta($post_id, '_previous_status', true);
    $previous_content = get_post_meta($post_id, '_previous_content', true);

    // Detect if post is published or transitioned from published status
    if ($post->post_status == 'publish' || ($previous_status == 'publish' && ($post->post_status != $previous_status || $post->post_content != $previous_content))) {
        // Save the current content for comparison in the future
        update_post_meta($post_id, '_previous_content', $post->post_content);
        wp_schedule_single_event(time(), 'text_to_audio_with_ads_send_post_data', array($post_id));
    }

    // Update the previous status meta field
    update_post_meta($post_id, '_previous_status', $post->post_status);
}
add_action('save_post', 'text_to_audio_with_ads_schedule_post_data', 10, 3);

function text_to_audio_with_ads_error_logging_callback() {
    $error_logging = get_option('text_to_audio_with_ads_error_logging', 1);
    echo '<input type="checkbox" id="text_to_audio_with_ads_error_logging" name="text_to_audio_with_ads_error_logging" value="1" ' . checked(1, $error_logging, false) . ' />';
    echo '<label for="text_to_audio_with_ads_error_logging"> ' . __('Enable Error Logging', 'text-to-audio-with-ads') . '</label>';
}

function text_to_audio_with_ads_display_admin_notices(){
    // Check if there's a notice to display
    $notice = get_transient('text_to_audio_with_ads_admin_notice');

    if($notice){
        ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
        <?php
        // Delete the transient so it doesn't keep showing
        delete_transient('text_to_audio_with_ads_admin_notice');
    }
}
add_action('admin_notices', 'text_to_audio_with_ads_display_admin_notices');

function text_to_audio_with_ads_send_post_data($post_id){
    // Prepare the data to be sent
    $post = get_post($post_id);
    $author_name = get_the_author_meta('display_name', $post->post_author);
    $featured_image_url = get_the_post_thumbnail_url($post_id, 'full');
    $data = array(
        'identifier' => $post_id,
        'title' => $post->post_title,
        'text' => $post->post_content,
        'status' => $post->post_status,
        'author' => $author_name,
        'date' => $post->post_date,
        'image_url' => $featured_image_url,
    );
     // Retrieve API credentials
    $api_key = get_option('text_to_audio_with_ads_api_key');
    $api_publisher = get_option('text_to_audio_with_ads_api_password');
    $body = wp_json_encode($data);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON encoding error: " . json_last_error_msg());
        return;
    }

    // Send the data to the external API
    $response = wp_remote_post('https://vocalize.africa/api/v1/articles',
        array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Api-Publisher-Secret' => $api_key,
                'X-Api-Publisher-Id' => $api_publisher
                //'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password)
            ),
            'body' => $body,
            'timeout' => 45,
            'sslverify' => true,  // Ensure SSL verification is enabled
        ));
        // Check for errors
        if(is_wp_error($response)){
            $error_message = $response->get_error_message();
            error_log("Failed to send post data: $error_message");
            // Log the error
            if (get_option('text_to_audio_with_ads_error_logging', 1)) {
                $errors = get_option('text_to_audio_with_ads_errors', array());
                $errors[] = "Failed to send post data (ID: $post_id): $error_message";
                update_option('text_to_audio_with_ads_errors', $errors);
            }
            // Optionally, notify the user via an admin notice
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            });
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code >= 200 && $response_code < 300) {
                error_log('Successful API response: ' . $response_body);
            } else {
                error_log("API request failed with status code $response_code: $response_body");
            }
        }
    }
    add_action('text_to_audio_with_ads_send_post_data', 'text_to_audio_with_ads_send_post_data');

    // Hook into post deletion
function text_to_audio_with_ads_handle_post_deletion($post_id) {
    $post = get_post($post_id);

    //if ($post && $post->post_type == 'post') {
    if ($post && $post->post_type == 'attachment') {
        wp_schedule_single_event(time(), 'text_to_audio_with_ads_send_post_data', array($post_id));
    }
}
add_action('before_delete_post', 'text_to_audio_with_ads_handle_post_deletion');

function text_to_audio_with_ads_enqueue_player_script($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('text-to-audio-with-ads-player-embed', plugin_dir_url(__FILE__) . 'js/player-embed.js', array('text-to-audio-with-ads-player'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'text_to_audio_with_ads_enqueue_player_script');

// Exclude the JS script from being cached by WP Rocket
function text_to_audio_with_ads_exclude_js($excluded_js) {
    $excluded_js[] = 'https://d211dnuaikc3d8.cloudfront.net/assets/widget/main.bundle.js';
    return $excluded_js;
}
add_filter('rocket_exclude_js', 'text_to_audio_with_ads_exclude_js');


//inject player code
function text_to_audio_with_ads_add_player_embed($content){
    if(is_single()){
        global $post;
        // Retrieve the post ID or post slug
        $identifier = $post->ID; // or $identifier = $post->post_name; for post slug
        // Retrieve the auth values from the plugin settings
        $publisher_id = get_option('text_to_audio_with_ads_api_password');
        $publisher_secret = get_option('text_to_audio_with_ads_player_secret');
        $player_embed = '<div id="vocalize-container"></div>';

        // Create the player embed code
        $player_embed = '<script src="https://d211dnuaikc3d8.cloudfront.net/assets/widget/main.bundle.js"></script>
        <div id="vocalize-container"></div>
        <script>
            window.Vocalize.init({
                containerId: "vocalize-container",
                identifier: "' . esc_js($identifier) . '",
                auth: {
                    "X-Api-Publisher-Id": "' . esc_js($publisher_id) . '",
                    "X-Api-Publisher-Secret": "' . esc_js($publisher_secret) . '"
                }
            });

            document.addEventListener("pagehide", window.Vocalize.unload());
            document.addEventListener("unload", window.Vocalize.unload());
        </script>';

        // Prepend the player embed code to the post content
        $content = $player_embed . $content;
        
    }
    return $content;
}
add_filter('the_content', 'text_to_audio_with_ads_add_player_embed');
// Ensure the event schedule is handled (Optional)
function text_to_audio_with_ads_cron_schedules($schedules){
    if(!isset($schedules['every_minute'])){
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'text-to-audio-with-ads')
        );
    }
    return $schedules;
}
add_filter('cron_schedules', 'text_to_audio_with_ads_cron_schedules');


?>