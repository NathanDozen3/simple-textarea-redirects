<?php
/**
 * Plugin Name: Simple Textarea Redirects
 * Description: Manage simple and auto-detected regex-based redirects via a textarea. Redirects are stored in the options table.
 * Version: 1.0.1
 * Author: Gemini
 * Author URI: https://gemini.google.com
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-textarea-redirects
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace Gemini\SimpleTextareaRedirects;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main plugin class for Simple Textarea Redirects.
 */
final class SimpleTextareaRedirectsPlugin {

    private const OPTION_NAME = 'str_redirect_rules_list_namespaced_class';
    private const SETTINGS_SLUG = 'simple-textarea-redirects-settings';
    private const NONCE_ACTION = 'str_save_redirects_nonce_action';
    private const NONCE_NAME = 'str_save_redirects_nonce_field';

    /**
     * Initializes the plugin by setting up hooks.
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // This constructor is private to ensure the class is only used statically or via a specific init method.
    }

    /**
     * Setup plugin hooks.
     */
    public static function init(): void {
        \add_action( 'admin_menu', [self::class, 'add_admin_menu'] );
        \add_action( 'admin_post_str_save_redirect_rules', [self::class, 'save_redirect_rules_callback'] );
        \add_action( 'template_redirect', [self::class, 'handle_redirects'], 1 );
        \add_action( 'plugins_loaded', [self::class, 'load_textdomain'] );
    }

    /**
     * Add the options page to the admin menu.
     */
    public static function add_admin_menu(): void {
        \add_options_page(
            \__( 'Textarea Redirects', 'simple-textarea-redirects' ),
            \__( 'Textarea Redirects', 'simple-textarea-redirects' ),
            'manage_options',
            self::SETTINGS_SLUG,
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Textarea Redirects (Auto-Detected Regex)', 'simple-textarea-redirects' ); ?></h1>
            
            <?php
            if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
                \add_settings_error( 'str_messages', 'str_message_id', \__( 'Settings saved.', 'simple-textarea-redirects' ), 'updated' );
            }
            \settings_errors( 'str_messages' );
            ?>

            <form method="post" action="<?php echo \esc_url( \admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="str_save_redirect_rules">
                <?php \wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="redirect_rules_textarea">
                                <?php \esc_html_e( 'Redirect Rules', 'simple-textarea-redirects' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="redirect_rules_textarea" name="redirect_rules_textarea" rows="20" cols="80" class="large-text code"><?php
                                echo \esc_textarea( (string) \get_option( self::OPTION_NAME, '' ) );
                            ?></textarea>
                            <p class="description">
                                <?php 
                                printf( 
                                    \esc_html__( 'Enter one redirect per line. Format: %s', 'simple-textarea-redirects' ), 
                                    '<code>source_path_or_regex destination_url_or_template [status_code]</code>' 
                                ); 
                                ?><br><br>
                                <strong><?php \esc_html_e( 'Automatic Regex Detection:', 'simple-textarea-redirects' ); ?></strong><br>
                                - <?php 
                                printf( 
                                    \esc_html__( 'The plugin will attempt to automatically detect if a source is a regular expression.', 'simple-textarea-redirects' )
                                ); 
                                ?><br>
                                - <?php 
                                printf( 
                                    \esc_html__( 'A source is treated as regex if it includes PCRE delimiters (e.g., %1$s or %2$s) and compiles correctly.', 'simple-textarea-redirects' ), 
                                    '<code>/pattern/i</code>', 
                                    '<code>#pattern#</code>' 
                                ); 
                                ?><br>
                                - <?php 
                                printf( 
                                    \wp_kses( /* translators: 1: regex chars example, 2: delimiter example, 3: source example, 4: result example */
                                        __( 'It may also be treated as regex if it lacks delimiters but contains common regex characters (%1$s etc.). The plugin will attempt to auto-delimit these with %2$s (e.g., %3$s becomes %4$s) and use it if it compiles.', 'simple-textarea-redirects' ),
                                        [ 'code' => [] ] // Allow <code> tags
                                    ),
                                    '<code>^ $ * + ? ( ) [ ] { } | \\d</code>', 
                                    '<code>#</code>', 
                                    '<code>^/path(.*)$</code>', 
                                    '<code>#^/path(.*)$#</code>' 
                                ); 
                                ?><br>
                                - <?php 
                                printf( 
                                    \esc_html__( 'Simple paths like %s without specific regex operators will usually be treated as exact matches.', 'simple-textarea-redirects' ), 
                                    '<code>/my-old-page/</code>' 
                                ); 
                                ?><br>
                                - <?php \esc_html_e( 'For regex, use <code>$1</code>, <code>$2</code>, etc., in the destination for capture group backreferences.', 'simple-textarea-redirects' ); // This one is fine as <code> is outside
                                ?><br>
                                <br>
                                <strong><?php \esc_html_e( 'Examples:', 'simple-textarea-redirects' ); ?></strong><br>
                                - <?php \esc_html_e( 'Exact Match:', 'simple-textarea-redirects' ); ?> <code>/old-page /new-page 301</code><br>
                                - <?php \esc_html_e( 'Regex (auto-detected, no delimiters needed):', 'simple-textarea-redirects' ); ?> <code>^/products/(\d+)/(.*)$ /shop/item/$1/$2 301</code><br>
                                - <?php \esc_html_e( 'Regex (with your own delimiters):', 'simple-textarea-redirects' ); ?> <code>#/category/(.*)/feed# /feeds/$1 302</code><br>
                                <br>
                                <strong><?php \esc_html_e( 'General Notes:', 'simple-textarea-redirects' ); ?></strong><br>
                                - <?php \esc_html_e( 'Destination URLs can be relative paths (e.g., `/new-page`) or full URLs (e.g., `https://othersite.com`).', 'simple-textarea-redirects' ); ?><br>
                                - <?php \esc_html_e( 'Supported status codes: 301, 302, 307, 308. If omitted, 301 (Permanent) is used.', 'simple-textarea-redirects' ); ?><br>
                                - <?php \esc_html_e( 'Lines starting with # will be ignored as comments.', 'simple-textarea-redirects' ); ?><br>
                                - <?php \esc_html_e( 'Be cautious with regex, as complex patterns can impact site performance. Test thoroughly.', 'simple-textarea-redirects' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php \submit_button( \__( 'Save Redirects', 'simple-textarea-redirects' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle saving of the redirect rules.
     */
    public static function save_redirect_rules_callback(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \__( 'You do not have sufficient permissions to access this page.', 'simple-textarea-redirects' ) );
        }

        $nonce_value = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
        if ( ! \wp_verify_nonce( \sanitize_text_field(\wp_unslash($nonce_value)), self::NONCE_ACTION ) ) {
            \wp_die( \__( 'Nonce verification failed.', 'simple-textarea-redirects' ) );
        }

        if ( isset( $_POST['redirect_rules_textarea'] ) ) {
            $raw_rules = (string) $_POST['redirect_rules_textarea'];
            $sanitized_rules = \sanitize_textarea_field( \wp_unslash( $raw_rules ) );
            \update_option( self::OPTION_NAME, $sanitized_rules );
        }

        $redirect_url = \add_query_arg(
            [
                'page' => self::SETTINGS_SLUG,
                'settings-updated' => 'true'
            ],
            \admin_url( 'options-general.php' )
        );
        \wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle the actual redirects on the front-end.
     */
    public static function handle_redirects(): void {
        $redirect_rules_string = (string) \get_option( self::OPTION_NAME, '' );

        if ( empty( $redirect_rules_string ) ) {
            return;
        }

        $current_request_uri_full = isset($_SERVER['REQUEST_URI']) ? \wp_unslash((string)$_SERVER['REQUEST_URI']) : '';
        
        $path_component = \strtok($current_request_uri_full, '?');
        if ($path_component === false) {
            $path_component = $current_request_uri_full;
        }
        $current_request_uri_path_for_regex = \urldecode($path_component);
        
        $temp_normalized_path = \rtrim($current_request_uri_path_for_regex, '/');
        $current_request_uri_path_for_exact_match = ($temp_normalized_path === '' && $current_request_uri_path_for_regex === '/') ? '/' : $temp_normalized_path;
        if (empty($current_request_uri_path_for_exact_match) && $current_request_uri_path_for_regex !== '/') {
            $current_request_uri_path_for_exact_match = '/';
        } elseif (empty($current_request_uri_path_for_exact_match) && $current_request_uri_path_for_regex === '/') {
             $current_request_uri_path_for_exact_match = '/';
        }

        $rules_array = \explode( "\n", $redirect_rules_string );

        foreach ( $rules_array as $rule_line ) {
            $rule_line_trimmed = \trim( $rule_line );

            if ( empty( $rule_line_trimmed ) || \str_starts_with( $rule_line_trimmed, '#' ) ) {
                continue;
            }
            
            $parts = \preg_split( '/\s+/', $rule_line_trimmed, 3 );
            if ($parts === false) {
                continue;
            }

            if ( \count( $parts ) < 2 ) {
                continue;
            }

            $source_or_pattern = \trim( $parts[0] );
            $destination_or_template = \trim( $parts[1] );
            $status_code = 301;

            if ( isset( $parts[2] ) ) {
                $potential_status = \absint( \trim( $parts[2] ) );
                if ( \in_array( $potential_status, [ 301, 302, 307, 308 ], true ) ) {
                    $status_code = $potential_status;
                }
            }

            $is_regex_rule = false;
            $final_regex_pattern_to_use = null;
            $matches = [];

            if (\preg_match('/^([\/#~%@!;&`\'"])(.*)\1[imsxeADSUXJu]*$/s', $source_or_pattern, $delimited_matches)) {
                if (@\preg_match($source_or_pattern, '') !== false) {
                    $is_regex_rule = true;
                    $final_regex_pattern_to_use = $source_or_pattern;
                }
            }

            if (!$is_regex_rule) {
                $regex_indicators = [
                    '^', '$', '*', '+', '?', '.',
                    '(', ')', '[', ']', '{', '}', '|',
                    '\\d', '\\D', '\\s', '\\S', '\\w', '\\W', '\\b', '\\B',
                    '\\.', '\\*', '\\+', '\\?', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\|', '\\^', '\\$'
                ];
                $found_indicator = false;
                foreach ($regex_indicators as $indicator) {
                    if (\strpos($source_or_pattern, $indicator) !== false) {
                        $found_indicator = true;
                        break;
                    }
                }

                if ($found_indicator) {
                    $escaped_source_for_hash_delimiter = \str_replace('#', '\#', $source_or_pattern);
                    $test_pattern_with_hash_delimiters = '#' . $escaped_source_for_hash_delimiter . '#';
                    
                    if (@\preg_match($test_pattern_with_hash_delimiters, '') !== false) {
                        $is_regex_rule = true;
                        $final_regex_pattern_to_use = $test_pattern_with_hash_delimiters;
                    }
                }
            }

            if ( $is_regex_rule ) {
                if ($final_regex_pattern_to_use && @\preg_match( $final_regex_pattern_to_use, $current_request_uri_path_for_regex, $matches ) ) {
                    $final_destination = $destination_or_template;
                    foreach ( $matches as $key => $value ) {
                        $final_destination = \str_replace( '$' . (string)$key, (string)$value, $final_destination );
                    }
                    \wp_safe_redirect( \esc_url_raw( $final_destination ), $status_code );
                    exit;
                }
            } else {
                $source_path = $source_or_pattern; 

                if ( ! \str_starts_with( $source_path, '/' ) ) {
                     $source_path = '/' . $source_path;
                }
                
                $normalized_source_path = \rtrim( $source_path, '/' );
                if ( empty($normalized_source_path) && $source_path === '/' ){
                    $normalized_source_path = '/';
                } elseif (empty($normalized_source_path) && $source_path !== '/') {
                     $normalized_source_path = '/';
                }

                if ( $normalized_source_path === $current_request_uri_path_for_exact_match ) {
                    \wp_safe_redirect( \esc_url_raw( $destination_or_template ), $status_code );
                    exit;
                }
            }
        }
    }

    /**
     * Load plugin textdomain for internationalization.
     */
    public static function load_textdomain(): void {
        \load_plugin_textdomain( 'simple-textarea-redirects', false, \dirname( \plugin_basename( __FILE__ ) ) . '/languages/' ); 
    }
}

// Initialize the plugin
SimpleTextareaRedirectsPlugin::init();

?>