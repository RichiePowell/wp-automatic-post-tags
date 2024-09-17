<?php
/*
Plugin Name: Automatic Post Tags
Plugin URI: https://github.com/RichiePowell/wp-automatic-post-tags
Description: Scans post content and suggests or automatically adds relevant tags based on keywords found in the text, making it easier for users to organize their posts.
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

    /**
     * Singleton instance.
     *
     * @var Automatic_Post_Tags
     */
    private static $instance = null;

    /**
     * Option name for storing settings.
     *
     * @var string
     */
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
        // Load plugin text domain.
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Add settings menu.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

        // Register settings.
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Hook into save_post.
        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );

        // Enqueue scripts for post editor.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Add suggested tags to the Tags meta box.
        add_filter( 'wp_terms_checklist_args', array( $this, 'modify_tags_meta_box' ), 10, 2 );

        // AJAX handler for suggesting tags.
        add_action( 'wp_ajax_get_suggested_tags', array( $this, 'ajax_get_suggested_tags' ) );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'automatic-post-tags', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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

        $options['method'] = isset( $input['method'] ) ? sanitize_text_field( $input['method'] ) : 'builtin';

        $options['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';

        $options['auto_add_tags'] = isset( $input['auto_add_tags'] ) ? 1 : 0;

        return $options;
    }

    /**
     * Callback for method setting field.
     */
    public function method_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[method]">
            <option value="builtin" <?php selected( $options['method'], 'builtin' ); ?>><?php esc_html_e( 'Built-in Keyword Extraction', 'automatic-post-tags' ); ?></option>
            <option value="chatgpt" <?php selected( $options['method'], 'chatgpt' ); ?>><?php esc_html_e( 'ChatGPT', 'automatic-post-tags' ); ?></option>
            <option value="other_ai" <?php selected( $options['method'], 'other_ai' ); ?>><?php esc_html_e( 'Other AI Service', 'automatic-post-tags' ); ?></option>
        </select>
        <?php
    }

    /**
     * Callback for API key setting field.
     */
    public function api_key_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" value="<?php echo esc_attr( $options['api_key'] ); ?>" size="50" />
        <p class="description"><?php esc_html_e( 'Enter your API key for the AI service (e.g., OpenAI).', 'automatic-post-tags' ); ?></p>
        <?php
    }

    /**
     * Callback for auto add tags setting field.
     */
    public function auto_add_tags_field_callback() {
        $options = get_option( $this->option_name );
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_add_tags]" value="1" <?php checked( $options['auto_add_tags'], 1 ); ?> />
            <?php esc_html_e( 'Automatically add tags to the post upon saving.', 'automatic-post-tags' ); ?>
        </label>
        <?php
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
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'automatic-post-tags-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), '1.0', true );

        wp_localize_script( 'automatic-post-tags-admin', 'AutomaticPostTags', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'automatic_post_tags_nonce' ),
        ) );
    }

    /**
     * Modify the Tags meta box to include suggested tags.
     *
     * @param array   $args     Arguments for terms checklist.
     * @param WP_Post $post     Current post object.
     * @return array Modified arguments.
     */
    public function modify_tags_meta_box( $args, $post ) {
        if ( 'post_tag' !== $args['taxonomy'] ) {
            return $args;
        }

        add_action( 'admin_footer', function() use ( $post ) {
            $tags = $this->get_tags_from_content( $post->post_content );

            if ( ! empty( $tags ) ) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        var suggestedTags = <?php echo wp_json_encode( $tags ); ?>;
                        var container = $('#tagsdiv-post_tag .inside');

                        var suggestedHtml = '<div id="automatic-post-tags-suggested"><p><strong><?php echo esc_js( __( 'Suggested Tags:', 'automatic-post-tags' ) ); ?></strong></p><p>';
                        $.each(suggestedTags, function(index, tag) {
                            suggestedHtml += '<a href="#" class="suggested-tag">' + tag + '</a>, ';
                        });
                        suggestedHtml = suggestedHtml.slice(0, -2) + '</p></div>';

                        container.prepend(suggestedHtml);

                        // Click event to add tag.
                        $('.suggested-tag').on('click', function(e) {
                            e.preventDefault();
                            var tag = $(this).text();
                            $('#new-tag-post_tag').val(tag);
                            $('.tagadd').click();
                        });
                    });
                </script>
                <?php
            }
        });

        return $args;
    }

    /**
     * Add tags to post upon saving.
     *
     * @param int     $post_ID Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function add_tags_to_post( $post_ID, $post, $update ) {
        // Check for valid post type and permissions.
        if ( 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post_ID ) ) {
            return;
        }

        // Prevent infinite loop.
        remove_action( 'save_post', array( $this, 'add_tags_to_post' ), 10 );

        // Check if the nonce is set and valid.
        if ( isset( $_POST['automatic_post_tags_meta_box_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['automatic_post_tags_meta_box_nonce'] ) ), 'automatic_post_tags_meta_box' ) ) {
            // Get selected tags from the meta box.
            $selected_tags = isset( $_POST['automatic_post_tags'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['automatic_post_tags'] ) ) : array();

            if ( ! empty( $selected_tags ) ) {
                wp_set_post_tags( $post_ID, $selected_tags, true );
            }
        } else {
            // Get options.
            $options = get_option( $this->option_name );

            // Check if auto_add_tags is enabled.
            if ( empty( $options['auto_add_tags'] ) ) {
                // Re-add the action.
                add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
                return;
            }

            // Get the tags.
            $tags = $this->get_tags_from_content( $post->post_content );

            // Set the tags.
            if ( ! empty( $tags ) ) {
                wp_set_post_tags( $post_ID, $tags, true );
            }
        }

        // Re-add the action.
        add_action( 'save_post', array( $this, 'add_tags_to_post' ), 10, 3 );
    }

    /**
     * Get tags from content based on the selected method.
     *
     * @param string $content Post content.
     * @return array List of suggested tags.
     */
    private function get_tags_from_content( $content ) {
        $options = get_option( $this->option_name );

        $method = isset( $options['method'] ) ? $options['method'] : 'builtin';

        if ( 'chatgpt' === $method || 'other_ai' === $method ) {
            $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

            if ( empty( $api_key ) ) {
                return array();
            }

            // Use AI to get tags.
            $tags = $this->get_tags_from_ai( $content, $api_key, $method );
        } else {
            // Use built-in method to get tags.
            $tags = $this->get_tags_builtin( $content );
        }

        return $tags;
    }

    /**
     * Get tags using built-in keyword extraction.
     *
     * @param string $content Post content.
     * @return array List of suggested tags.
     */
    private function get_tags_builtin( $content ) {
        $content = strip_tags( $content );
        $content = strtolower( $content );

        // Remove stop words.
        $stop_words = $this->get_stop_words();

        // Split content into words.
        $words = str_word_count( $content, 1 );

        // Count word frequency.
        $frequency = array_count_values( $words );

        // Remove stop words and short words.
        foreach ( $frequency as $word => $count ) {
            if ( in_array( $word, $stop_words, true ) || mb_strlen( $word ) < 4 ) {
                unset( $frequency[ $word ] );
            }
        }

        // Sort by frequency.
        arsort( $frequency );

        // Get top 10 words.
        $tags = array_keys( array_slice( $frequency, 0, 10 ) );

        return $tags;
    }

    /**
     * Get tags using AI services.
     *
     * @param string $content Post content.
     * @param string $api_key API key for the AI service.
     * @param string $method  AI method ('chatgpt' or 'other_ai').
     * @return array List of suggested tags.
     */
    private function get_tags_from_ai( $content, $api_key, $method ) {
        $tags = array();

        // Prepare prompt or payload based on AI service.
        if ( 'chatgpt' === $method ) {
            $prompt = "Extract relevant keywords from the following text:\n\n" . $content;

            // Prepare API request.
            $response = wp_remote_post( 'https://api.openai.com/v1/completions', array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body'    => wp_json_encode( array(
                    'model'       => 'text-davinci-003',
                    'prompt'      => $prompt,
                    'max_tokens'  => 60,
                    'n'           => 1,
                    'stop'        => null,
                    'temperature' => 0.5,
                ) ),
                'timeout' => 60,
            ) );

        } else {
            // Handle other AI services here.
            return $tags;
        }

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            error_log( 'Automatic Post Tags - AI Error: ' . $response->get_error_message() );
            return $tags;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['text'] ) ) {
            $text = $data['choices'][0]['text'];

            // Extract tags from the text.
            $tags = array_map( 'trim', explode( ',', $text ) );
            $tags = array_filter( $tags );
        }

        return $tags;
    }

    /**
     * Get stop words for the built-in keyword extraction.
     *
     * @return array List of stop words.
     */
    private function get_stop_words() {
        // A basic list of stop words.
        $stop_words = array(
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

        return $stop_words;
    }

    /**
     * AJAX handler to get suggested tags.
     */
    public function ajax_get_suggested_tags() {
        // Check nonce.
        check_ajax_referer( 'automatic_post_tags_nonce', 'nonce' );

        // Check user capabilities.
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
}

endif;

// Initialize the plugin.
Automatic_Post_Tags::get_instance();