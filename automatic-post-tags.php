<?php
/*
Plugin Name: Automatic Post Tags
Plugin URI: https://github.com/RichiePowell/wp-automatic-post-tags
Description: Scans post content and suggests or automatically adds relevant tags based on keywords found in the text, making it easier for users to organize their posts. Supports both built-in keyword extraction and ChatGPT.
Version: 1.0
Author: Rich Powell
Author URI: https://richpowell.co.uk
License: GPL2
Text Domain: automatic-post-tags
Domain Path: /languages
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Automatic_Post_Tags' ) ) :

/**
 * Main Automatic_Post_Tags Class.
 */
class Automatic_Post_Tags {

    private static $instance = null;
    private $option_name = 'automatic_post_tags_options';

    /**
     * Gets the singleton instance.
     *
     * @return Automatic_Post_Tags
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_get_suggested_tags', array( $this, 'ajax_get_suggested_tags' ) );
    }

    /**
     * Add settings page under the Settings menu.
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
     * Register plugin settings and fields.
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
     * Sanitize and validate plugin settings.
     *
     * @param array $input Input settings array.
     * @return array Sanitized settings array.
     */
    public function sanitize_options( $input ) {
        $options = get_option( $this->option_name );
        $options = is_array( $options ) ? $options : array();

        $options['method'] = isset( $input['method'] ) ? sanitize_text_field( $input['method'] ) : 'builtin';
        $options['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $options['auto_add_tags'] = isset( $input['auto_add_tags'] ) ? (bool) $input['auto_add_tags'] : false;

        return $options;
    }

    /**
     * Display the settings page.
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Automatic Post Tags Settings', 'automatic-post-tags' ); ?></h1>
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
     * Enqueue admin scripts for the post editor.
     */
    public function enqueue_admin_scripts( $hook ) {
        wp_enqueue_script( 'automatic-post-tags-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), '1.0', true );

        wp_localize_script( 'automatic-post-tags-admin', 'AutomaticPostTags', array(
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'automatic_post_tags_nonce' ),
            'refreshing_text'       => __( 'Refreshing...', 'automatic-post-tags' ),
            'refresh_button_text'   => __( 'Refresh Tags', 'automatic-post-tags' ),
        ) );
    }

    /**
     * Add tags to post upon saving.
     */
    public function add_tags_to_post( $post_ID, $post, $update ) {
        if ( 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post_ID ) ) {
            return;
        }

        remove_action( 'save_post', array( $this, 'add_tags_to_post' ), 10 );

        if ( isset( $_POST['automatic_post_tags_meta_box_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['automatic_post_tags_meta_box_nonce'] ) ), 'automatic_post_tags_meta_box' ) ) {
            $selected_tags = isset( $_POST['automatic_post_tags'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['automatic_post_tags'] ) ) : array();

            if ( ! empty( $selected_tags ) ) {
                wp_set_post_tags( $post_ID, $selected_tags, true );
            }
        } else {
            $options = get_option( $this->option_name );
            $options = is_array( $options ) ? $options : array();

            if ( empty( $options['auto_add_tags'] ) ) {
                add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
                return;
            }

            $tags = $this->get_tags_from_content( $post->post_content );

            if ( ! empty( $tags ) ) {
                wp_set_post_tags( $post_ID, $tags, true );
            }
        }

        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
    }

    /**
     * Get tags from content based on the selected method.
     */
    private function get_tags_from_content( $content ) {
        $options = get_option( $this->option_name );
        $options = is_array( $options ) ? $options : array();

        $method = isset( $options['method'] ) ? $options['method'] : 'builtin';

        if ( 'chatgpt' === $method ) {
            $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

            if ( empty( $api_key ) ) {
                return array();
            }

            $tags = $this->get_tags_from_chatgpt( $content, $api_key );
        } else {
            $tags = $this->get_tags_builtin( $content );
        }

        return $tags;
    }

    /**
     * Get tags using built-in keyword extraction.
     */
    private function get_tags_builtin( $content ) {
        $content = strip_tags( $content );
        $content = strtolower( $content );

        $stop_words = $this->get_stop_words();
        $words = str_word_count( $content, 1 );
        $frequency = array_count_values( $words );

        foreach ( $frequency as $word => $count ) {
            if ( in_array( $word, $stop_words, true ) || mb_strlen( $word ) < 4 ) {
                unset( $frequency[ $word ] );
            }
        }

        arsort( $frequency );
        $tags = array_keys( array_slice( $frequency, 0, 10 ) );

        return $tags;
    }

    /**
     * AJAX handler to get suggested tags.
     */
    public function ajax_get_suggested_tags() {
        check_ajax_referer( 'automatic_post_tags_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'automatic-post-tags' ) );
            wp_die();
        }

        $post_content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

        if ( empty( $post_content ) ) {
            wp_send_json_error( esc_html__( 'Post content is empty.', 'automatic-post-tags' ) );
            wp_die();
        }

        $tags = $this->get_tags_from_content( $post_content );

        if ( empty( $tags ) ) {
            wp_send_json_error( esc_html__( 'No suggested tags found.', 'automatic-post-tags' ) );
            wp_die();
        }

        wp_send_json_success( $tags );
        wp_die();
    }

		/**
		 * Get stop words for the built-in keyword extraction.
		 *
		 * These are common words that should not be considered as tags.
		 *
		 * @return array List of stop words.
		 */
		private function get_stop_words() {
			return array(
					'the', 'and', 'that', 'have', 'for', 'not', 'with', 'you',
					'this', 'but', 'his', 'from', 'they', 'she', 'which', 'there',
					'were', 'been', 'their', 'what', 'when', 'your', 'can', 'said',
					'who', 'will', 'would', 'all', 'each', 'about', 'other', 'into',
					'more', 'some', 'could', 'them', 'these', 'than', 'then', 'now',
					'look', 'only', 'come', 'its', 'over', 'think', 'also', 'back',
					'after', 'use', 'two', 'how', 'our', 'work', 'first', 'well',
					'way', 'even', 'new', 'want', 'because', 'any', 'these', 'give',
					'most', 'us', 'are', 'was', 'is', 'on', 'in', 'it', 'of',
					'a', 'to', 'as', 'at', 'by', 'an',
			);
		}
}

endif;

// Initialize the plugin.
Automatic_Post_Tags::get_instance();