<?php
/*
Plugin Name: Bunny.net Stream Uploader
Description: Upload videos to Bunny.net Stream from Page or Post editor.
Version: 1.0
Author: Infinix Media Dev
*/

// Enqueue CSS
function bunny_stream_uploader_enqueue_styles() {
    wp_enqueue_style('bunny-stream-uploader-css', plugin_dir_url(__FILE__) . 'css/bunny-stream-uploader.css');
}
add_action('admin_enqueue_scripts', 'bunny_stream_uploader_enqueue_styles');

// Create settings page
function bunny_stream_uploader_settings_page() {
    add_options_page('Bunny.net Stream Uploader Settings', 'Bunny.net Stream Uploader', 'manage_options', 'bunny-stream-uploader-settings', 'bunny_stream_uploader_settings_page_content');
}
add_action('admin_menu', 'bunny_stream_uploader_settings_page');

function bunny_stream_uploader_settings_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h2>Bunny.net Stream Uploader Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('bunny_stream_uploader_settings_group'); ?>
            <?php do_settings_sections('bunny-stream-uploader-settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function bunny_stream_uploader_settings_init() {
    register_setting('bunny_stream_uploader_settings_group', 'bunny_stream_uploader_api_key');
    register_setting('bunny_stream_uploader_settings_group', 'bunny_stream_uploader_library_id');
    add_settings_section('bunny_stream_uploader_settings_section', 'Bunny.net Stream Settings', 'bunny_stream_uploader_settings_section_callback', 'bunny-stream-uploader-settings');
    add_settings_field('bunny_stream_uploader_api_key', 'API Key', 'bunny_stream_uploader_api_key_callback', 'bunny-stream-uploader-settings', 'bunny_stream_uploader_settings_section');
    add_settings_field('bunny_stream_uploader_library_id', 'Library ID', 'bunny_stream_uploader_library_id_callback', 'bunny-stream-uploader-settings', 'bunny_stream_uploader_settings_section');
}
add_action('admin_init', 'bunny_stream_uploader_settings_init');

function bunny_stream_uploader_settings_section_callback() {
    echo 'Configure your Bunny.net Stream settings.';
}

function bunny_stream_uploader_api_key_callback() {
    $api_key = esc_attr(get_option('bunny_stream_uploader_api_key'));
    echo "<input type='text' name='bunny_stream_uploader_api_key' value='$api_key' />";
}

function bunny_stream_uploader_library_id_callback() {
    $library_id = esc_attr(get_option('bunny_stream_uploader_library_id'));
    echo "<input type='text' name='bunny_stream_uploader_library_id' value='$library_id' />";
}

// Create a meta box for video upload
function bunny_stream_uploader_meta_box() {
    $post_types = get_post_types(array('public' => true), 'names');

    foreach ($post_types as $post_type) {
        add_meta_box(
            'bunny_stream_uploader',
            'Bunny.net Stream Uploader',
            'bunny_stream_uploader_meta_box_content',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'bunny_stream_uploader_meta_box');

function bunny_stream_uploader_meta_box_content($post) {
    // Add nonce for security
    wp_nonce_field('bunny_stream_uploader_nonce', 'bunny_stream_uploader_nonce');

    // Display the input field for video upload
    echo '<input type="file" id="bunny_stream_video" name="bunny_stream_video" accept="video/*">';

    // Display placeholders for video title, video ID, video thumbnail, and Direct Play URL
    // Title Section is still bugged, no value is being fetched
    // echo '<p>Video Title: <input type="text" id="bunny_stream_video_title" name="bunny_stream_video_title" placeholder="Video Title" readonly></p>';
    echo '<p>Video ID: <input type="text" id="bunny_stream_video_id" name="bunny_stream_video_id" placeholder="Video ID" readonly></p>';
    echo '<p>Direct Play URL: <input type="text" id="bunny_stream_direct_play_url" name="bunny_stream_direct_play_url" placeholder="Direct Play URL" readonly></p>';
    // Display the video URL input field as a placeholder
    $video_url = get_post_meta($post->ID, 'bunny_stream_video_url', true);
    echo '<p>Video URL: <input type="text" id="bunny_stream_video_url" name="bunny_stream_video_url" value="' . esc_attr($video_url) . '" placeholder="Video URL" readonly></p>';
    echo '<p>Video Thumbnail: <img id="bunny_stream_video_thumbnail" src="" alt="Video Thumbnail"></p>';

    // Display an upload button
    echo '<button id="bunny_stream_upload_button">Upload Video</button>';

    // Display a fetch button to retrieve video information
    echo '<button id="bunny_stream_fetch_info_button">Re-fetch Video Information</button>';

    // Display progress bar
    echo '<div id="bunny_stream_progress" style="display: none;"><progress id="bunny_stream_progress_bar" max="100"></progress><span id="bunny_stream_progress_status"></span></div>';
}

// JavaScript for handling the upload button and progress
function bunny_stream_uploader_js() {
    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('#bunny_stream_upload_button').click(function (e) {
                e.preventDefault();

                var fileInput = $('#bunny_stream_video')[0];
                var file = fileInput.files[0];

                if (file) {
                    var formData = new FormData();
                    formData.append('file', file);
                    formData.append('action', 'bunny_stream_upload_video');
                    formData.append('nonce', '<?php echo wp_create_nonce('bunny_stream_uploader_nonce'); ?>');

                    $('#bunny_stream_progress').show();
                    $('#bunny_stream_progress_bar').val(0);
                    $('#bunny_stream_progress_status').text('Uploading...');

                    $.ajax({
                        type: 'POST',
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function () {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function (e) {
                                if (e.lengthComputable) {
                                    var percentComplete = (e.loaded / e.total) * 100;
                                    $('#bunny_stream_progress_bar').val(percentComplete);
                                    $('#bunny_stream_progress_status').text(percentComplete.toFixed(2) + '%');
                                }
                            }, false);
                            return xhr;
                        },
                        success: function (response) {
                            if (response.success) {
                                // Update the video URL input field with the uploaded URL
                                $('#bunny_stream_video_url').val(response.data.video_url);

                                // Enable the "Re-fetch Video Information" button
                                $('#bunny_stream_fetch_info_button').prop('disabled', false);

                                // Fetch and update the rest of the fields
                                fetchVideoDetails(response.data.video_url);
                            } else {
                                alert('Error uploading video: ' + response.data.message);
                                $('#bunny_stream_progress').hide();
                            }
                        },
                        error: function (xhr, textStatus, errorThrown) {
                            alert('Error: ' + errorThrown);
                            $('#bunny_stream_progress').hide();
                        }
                    });
                }
            });

            $('#bunny_stream_fetch_info_button').click(function (e) {
                e.preventDefault();

                var videoUrl = $('#bunny_stream_video_url').val();

                if (videoUrl) {
                    fetchVideoDetails(videoUrl);
                } else {
                    alert('Video URL is empty. Please upload a video first.');
                }
            });

            function fetchVideoDetails(videoUrl) {
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'bunny_stream_get_video_details',
                        nonce: '<?php echo wp_create_nonce('bunny_stream_uploader_nonce'); ?>',
                        video_url: videoUrl
                    },
                    success: function (response) {
                        if (response.success) {
                            // Update the rest of the fields with fetched data
                            $('#bunny_stream_video_title').val(response.data.video_title || '');
                            $('#bunny_stream_video_id').val(response.data.video_id);
                            $('#bunny_stream_direct_play_url').val(response.data.direct_play_url);
                            $('#bunny_stream_video_thumbnail').attr('src', response.data.thumbnail_url || '');

                            // Enable the "Re-fetch Video Information" button
                            $('#bunny_stream_fetch_info_button').prop('disabled', false);

                            $('#bunny_stream_progress').hide();
                        } else {
                            alert('Error fetching video details: ' + response.data.message);
                            $('#bunny_stream_progress').hide();
                        }
                    },
                    error: function (xhr, textStatus, errorThrown) {
                        alert('Error: ' + errorThrown);
                        $('#bunny_stream_progress').hide();
                    }
                });
            }
        });
    </script>
    <?php
}
add_action('admin_footer', 'bunny_stream_uploader_js');

// Save video-related data as custom fields
function bunny_stream_save_video_data($post_id) {
    if (isset($_POST['bunny_stream_video_url'])) {
        $video_url = sanitize_text_field($_POST['bunny_stream_video_url']);
        update_post_meta($post_id, 'bunny_stream_video_url', $video_url);
    }
}
add_action('save_post', 'bunny_stream_save_video_data');

// AJAX handler for uploading the video
function bunny_stream_uploader_ajax_handler() {
    // Check the nonce
    check_ajax_referer('bunny_stream_uploader_nonce', 'nonce');

    if (!empty($_FILES['file'])) {
        // Get API Key and Library ID from plugin settings
        $api_key = get_option('bunny_stream_uploader_api_key');
        $library_id = get_option('bunny_stream_uploader_library_id');

        if (!$api_key || !$library_id) {
            wp_send_json_error(array('message' => 'API Key or Library ID is not configured.'));
        }

        // Get the uploaded file
        $video_path = $_FILES['file']['tmp_name'];
        $video_name = $_FILES['file']['name'];

        // Prepare the request headers
        $headers = array(
            'AccessKey: ' . $api_key,
            'Content-Type: application/json'
        );

        // Prepare the request body
        $request_data = array(
            'title' => $video_name
        );

        $request_data = json_encode($request_data);

        // Create a new cURL resource
        $ch = curl_init();

        // Set cURL options for creating the video
        curl_setopt($ch, CURLOPT_URL, 'https://video.bunnycdn.com/library/' . $library_id . '/videos');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            wp_send_json_error(array('message' => 'cURL Error: ' . curl_error($ch)));
        }

        // Close cURL session
        curl_close($ch);

        // Check if video creation was successful
        if ($response === false) {
            wp_send_json_error(array('message' => 'Error creating video.'));
        }

        $response_data = json_decode($response);

        // Create a new cURL resource for uploading the video
        $ch = curl_init();

        // Set cURL options for uploading the video
        curl_setopt($ch, CURLOPT_URL, 'https://video.bunnycdn.com/library/' . $library_id . '/videos/' . $response_data->guid);
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_INFILE, fopen($video_path, "rb"));
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($video_path));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('AccessKey: ' . $api_key));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute cURL request
        $upload_response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            wp_send_json_error(array('message' => 'cURL Error: ' . curl_error($ch)));
        }

        // Close cURL session
        curl_close($ch);

        // Check if video upload was successful
        if ($upload_response === false) {
            wp_send_json_error(array('message' => 'Error uploading video.'));
        }

        $video_url = 'https://video.bunnycdn.com/library/' . $library_id . '/videos/' . $response_data->guid;

        // Fetch and return video details
        wp_send_json_success(array(
            'video_url' => $video_url,
            'library_id' => $library_id,
        ));
    } else {
        wp_send_json_error(array('message' => 'No file uploaded.'));
    }
}
add_action('wp_ajax_bunny_stream_upload_video', 'bunny_stream_uploader_ajax_handler');
add_action('wp_ajax_nopriv_bunny_stream_upload_video', 'bunny_stream_uploader_ajax_handler');

// AJAX handler for fetching video details from Bunny.net Stream
function bunny_stream_get_video_details_ajax_handler() {
    // Check the nonce
    check_ajax_referer('bunny_stream_uploader_nonce', 'nonce');

    if (isset($_POST['video_url'])) {
        $video_url = $_POST['video_url'];

        // Extract Video ID from the URL
        $videoUrlParts = explode('/', $video_url);
        $videoId = end($videoUrlParts);

        // Get API Key and Library ID from plugin settings
        $api_key = get_option('bunny_stream_uploader_api_key');
        $library_id = get_option('bunny_stream_uploader_library_id');

        // Construct Direct Play URL and Thumbnail URL
        $directPlayUrl = 'https://iframe.mediadelivery.net/play/' . $library_id . '/' . $videoId;
        $thumbnailUrl = 'https://vz-cacedb65-d27.b-cdn.net/' . $videoId . '/thumbnail.jpg';

        // Fetch video title from the uploaded file name
        $videoTitle = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);

        // Update other input fields
        wp_send_json_success(array(
            'video_title' => $videoTitle,
            'video_id' => $videoId,
            'direct_play_url' => $directPlayUrl,
            'thumbnail_url' => $thumbnailUrl,
        ));
    } else {
        wp_send_json_error(array('message' => 'No video URL provided.'));
    }
}
add_action('wp_ajax_bunny_stream_get_video_details', 'bunny_stream_get_video_details_ajax_handler');
add_action('wp_ajax_nopriv_bunny_stream_get_video_details', 'bunny_stream_get_video_details_ajax_handler');
