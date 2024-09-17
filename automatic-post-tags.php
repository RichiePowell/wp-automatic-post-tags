<?php
/*
Plugin Name: Automatic Post Tags
Plugin URI: https://github.com/RichiePowell/wp-automatic-post-tags
Description: Scans post content and suggests or automatically adds relevant tags based on keywords found in the text, making it easier for users to organize their posts.
Version: 1.0
Author: Rich Powell
Author URI: https://richpowell.co.uk/
License: GPL2
Text Domain: automatic-post-tags
Domain Path: /languages
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Automatic_Post_Tags' ) ) :

class Automatic_Post_Tags {

    private static $instance = null;
    private $option_name = 'automatic_post_tags_options';

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load plugin text domain
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Add settings menu
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Hook into save_post
        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );

        // Enqueue scripts for post editor
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Add metabox to post editor
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

        // AJAX handler
        add_action( 'wp_ajax_get_suggested_tags', array( $this, 'ajax_get_suggested_tags' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'automatic-post-tags', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Automatic Post Tags Settings', 'automatic-post-tags' ),
            __( 'Automatic Post Tags', 'automatic-post-tags' ),
            'manage_options',
            'automatic-post-tags',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'automatic_post_tags_group',
            $this->option_name,
            array( $this, 'sanitize_options' )
        );

        add_settings_section(
            'automatic_post_tags_section',
            __( 'Settings', 'automatic-post-tags' ),
            null,
            'automatic-post-tags'
        );

        add_settings_field(
            'method',
            __( 'Tag Generation Method', 'automatic-post-tags' ),
            array( $this, 'method_field_callback' ),
            'automatic-post-tags',
            'automatic_post_tags_section'
        );

        add_settings_field(
            'api_key',
            __( 'API Key', 'automatic-post-tags' ),
            array( $this, 'api_key_field_callback' ),
            'automatic-post-tags',
            'automatic_post_tags_section'
        );

        add_settings_field(
            'auto_add_tags',
            __( 'Automatically Add Tags', 'automatic-post-tags' ),
            array( $this, 'auto_add_tags_field_callback' ),
            'automatic-post-tags',
            'automatic_post_tags_section'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options( $input ) {
        $options = get_option( $this->option_name );

        $options['method'] = sanitize_text_field( $input['method'] );

        $options['api_key'] = sanitize_text_field( $input['api_key'] );

        $options['auto_add_tags'] = isset( $input['auto_add_tags'] ) ? 1 : 0;

        return $options;
    }

    /**
     * Method field callback
     */
    public function method_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <select name="<?php echo $this->option_name; ?>[method]">
            <option value="builtin" <?php selected( $options['method'], 'builtin' ); ?>><?php _e( 'Built-in Keyword Extraction', 'automatic-post-tags' ); ?></option>
            <option value="ai" <?php selected( $options['method'], 'ai' ); ?>><?php _e( 'AI (ChatGPT or other)', 'automatic-post-tags' ); ?></option>
        </select>
        <?php
    }

    /**
     * API Key field callback
     */
    public function api_key_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" name="<?php echo $this->option_name; ?>[api_key]" value="<?php echo esc_attr( $options['api_key'] ); ?>" size="50" />
        <p class="description"><?php _e( 'Enter your API key for the AI service (e.g., OpenAI).', 'automatic-post-tags' ); ?></p>
        <?php
    }

    /**
     * Auto Add Tags field callback
     */
    public function auto_add_tags_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <label>
            <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_add_tags]" value="1" <?php checked( $options['auto_add_tags'], 1 ); ?> />
            <?php _e( 'Automatically add tags to the post upon saving.', 'automatic-post-tags' ); ?>
        </label>
        <?php
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Automatic Post Tags Settings', 'automatic-post-tags' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'automatic_post_tags_group' );
                do_settings_sections( 'automatic-post-tags' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
            return;
        }

        wp_enqueue_script( 'automatic-post-tags-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), '1.0', true );

        wp_localize_script( 'automatic-post-tags-admin', 'AutomaticPostTags', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'automatic_post_tags_nonce' ),
        ) );
    }

    /**
     * Add metabox to post editor
     */
    public function add_meta_box() {
        add_meta_box(
            'automatic_post_tags_metabox',
            __( 'Suggested Tags', 'automatic-post-tags' ),
            array( $this, 'render_meta_box' ),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the metabox content
     */
    public function render_meta_box( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'automatic_post_tags_meta_box', 'automatic_post_tags_meta_box_nonce' );

        // Get the suggested tags
        $tags = $this->get_tags_from_content( $post->post_content );

        // Get existing tags
        $existing_tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

        echo '<p>' . __( 'Select the tags you want to add to this post:', 'automatic-post-tags' ) . '</p>';

        echo '<div id="automatic-post-tags-checkboxes">';

        if ( !empty( $tags ) ) {
            foreach ( $tags as $tag ) {
                $checked = in_array( $tag, $existing_tags ) ? 'checked' : '';
                echo '<label><input type="checkbox" name="automatic_post_tags[]" value="' . esc_attr( $tag ) . '" ' . $checked . '> ' . esc_html( $tag ) . '</label><br>';
            }
        } else {
            echo '<p>' . __( 'No suggested tags available.', 'automatic-post-tags' ) . '</p>';
        }

        echo '</div>';

        echo '<button type="button" id="automatic-post-tags-refresh" class="button">' . __( 'Refresh Tags', 'automatic-post-tags' ) . '</button>';
    }

    /**
     * Add tags to post
     */
    public function add_tags_to_post( $post_ID, $post, $update ) {
        // Check if it's a valid post type (e.g., 'post')
        if ( 'post' !== $post->post_type ) {
            return;
        }

        // Check user capabilities
        if ( !current_user_can( 'edit_post', $post_ID ) ) {
            return;
        }

        // Prevent infinite loop
        remove_action( 'save_post', array( $this, 'add_tags_to_post' ), 10 );

        // Check if the nonce is set (metabox data)
        if ( isset( $_POST['automatic_post_tags_meta_box_nonce'] ) && wp_verify_nonce( $_POST['automatic_post_tags_meta_box_nonce'], 'automatic_post_tags_meta_box' ) ) {
            // Get selected tags from metabox
            $selected_tags = isset( $_POST['automatic_post_tags'] ) ? array_map( 'sanitize_text_field', $_POST['automatic_post_tags'] ) : array();

            if ( !empty( $selected_tags ) ) {
                wp_set_post_tags( $post_ID, $selected_tags, true );
            }
        } else {
            // Get options
            $options = get_option( $this->option_name );

            // Check if auto_add_tags is enabled
            if ( empty( $options['auto_add_tags'] ) ) {
                // Re-add the action
                add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
                return;
            }

            // Get the tags
            $tags = $this->get_tags_from_content( $post->post_content );

            // Set the tags
            if ( !empty( $tags ) ) {
                wp_set_post_tags( $post_ID, $tags, true );
            }
        }

        // Re-add the action
        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
    }

    /**
     * Get tags from content
     */
    private function get_tags_from_content( $content ) {
        $options = get_option( $this->option_name );

        $method = isset( $options['method'] ) ? $options['method'] : 'builtin';

        if ( 'ai' == $method ) {
            $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

            if ( empty( $api_key ) ) {
                return array();
            }

            // Use AI to get tags
            $tags = $this->get_tags_from_ai( $content, $api_key );
        } else {
            // Use built-in method to get tags
            $tags = $this->get_tags_builtin( $content );
        }

        return $tags;
    }

    /**
     * Get tags using built-in method
     */
    private function get_tags_builtin( $content ) {
        $content = strip_tags( $content );
        $content = strtolower( $content );

        // Remove stop words
        $stop_words = $this->get_stop_words();

        // Split content into words
        $words = str_word_count( $content, 1 );

        // Count word frequency
        $frequency = array_count_values( $words );

        // Remove stop words and short words
        foreach ( $frequency as $word => $count ) {
            if ( in_array( $word, $stop_words ) || strlen( $word ) < 4 ) {
                unset( $frequency[$word] );
            }
        }

        // Sort by frequency
        arsort( $frequency );

        // Get top 10 words
        $tags = array_keys( array_slice( $frequency, 0, 10 ) );

        return $tags;
    }

    /**
     * Get tags using AI
     */
    private function get_tags_from_ai( $content, $api_key ) {
        // Use OpenAI API as an example
        $prompt = "Extract keywords from the following text:\n\n" . $content;

        $tags = array();

        // Prepare API request
        $response = wp_remote_post( 'https://api.openai.com/v1/engines/text-davinci-003/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode( array(
                'prompt' => $prompt,
                'max_tokens' => 60,
                'n' => 1,
                'stop' => null,
                'temperature' => 0.5,
            ) ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return $tags;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['text'] ) ) {
            $text = $data['choices'][0]['text'];

            // Extract tags from the text
            $tags = array_map( 'trim', explode( ',', $text ) );
        }

        return $tags;
    }

    /**
     * Get stop words
     */
    private function get_stop_words() {
        // A basic list of stop words
        $stop_words = array(
            'the', 'and', 'that', 'have', 'for', 'not', 'with', 'you',
            'this', 'but', 'his', 'from', 'they', 'she', 'which', 'there',
            'were', 'been', 'their', 'what', 'when', 'your', 'can', 'said',
            'who', 'will', 'would', 'all', 'each', 'about', 'other', 'into',
            'more', 'some', 'could', 'them', 'these', 'than', 'then', 'now',
            'look', 'only', 'come', 'its', 'over', 'think', 'also', 'back',
            'after', 'use', 'two', 'how', 'our', 'work', 'first', 'well',
            'way', 'even', 'new', 'want', 'because', 'any', 'these', 'give',
            'most', 'us',
        );

        return $stop_words;
    }

    /**
     * AJAX handler to get suggested tags
     */
    public function ajax_get_suggested_tags() {
        // Check nonce
        check_ajax_referer( 'automatic_post_tags_nonce', 'nonce' );

        // Check user capabilities
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'automatic-post-tags' ) );
            wp_die();
        }

        $post_content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

        if ( empty( $post_content ) ) {
            wp_send_json_error( __( 'Post content is empty.', 'automatic-post-tags' ) );
            wp_die();
        }

        $tags = $this->get_tags_from_content( $post_content );

        if ( empty( $tags ) ) {
            wp_send_json_error( __( 'No suggested tags found.', 'automatic-post-tags' ) );
            wp_die();
        }

        wp_send_json_success( $tags );
        wp_die();
    }

}

endif;

// Initialize the plugin
Automatic_Post_Tags::get_instance();