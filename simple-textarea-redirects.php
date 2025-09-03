<?php
/**
 * Plugin Name: Simple Textarea Redirects
 * Description: Manage redirects with separate textareas for high-performance simple redirects and powerful regex redirects.
 * Version: 1.2.0
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

    private const SIMPLE_OPTION_NAME = 'str_redirect_rules_simple_map'; // Changed to reflect it's a map
    private const REGEX_OPTION_NAME = 'str_redirect_rules_regex_list';
    private const SIMPLE_TEXTAREA_NAME_LEGACY = 'str_redirect_rules_simple_list'; // For migration
    private const SETTINGS_SLUG = 'simple-textarea-redirects-settings';
    private const NONCE_ACTION = 'str_save_redirects_nonce_action';
    private const NONCE_NAME = 'str_save_redirects_nonce_field';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

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
        // One-time migration check for users updating from a pre-1.2.0 version
        $redirect_map = \get_option( self::SIMPLE_OPTION_NAME, null );
        if ($redirect_map === null && \get_option( self::SIMPLE_TEXTAREA_NAME_LEGACY ) !== false) {
            // Data exists in old format, but not in new. Trigger a resave to migrate.
            self::save_redirect_rules_from_string(\get_option( self::SIMPLE_TEXTAREA_NAME_LEGACY, '' ));
        }
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Optimized Textarea Redirects', 'simple-textarea-redirects' ); ?></h1>
            
            <?php
            if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
                \add_settings_error( 'str_messages', 'str_message_id', \__( 'Settings saved.', 'simple-textarea-redirects' ), 'updated' );
            }
            \settings_errors( 'str_messages' );
            ?>

            <form method="post" action="<?php echo \esc_url( \admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="str_save_redirect_rules">
                <?php \wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                <h2><?php \esc_html_e( 'Simple Redirects (Exact Match)', 'simple-textarea-redirects' ); ?></h2>
                <p><?php \esc_html_e( 'These are checked first using a hash map for instant lookups. Use for one-to-one redirects.', 'simple-textarea-redirects' ); ?></p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="simple_redirect_rules_textarea">
                                <?php \esc_html_e( 'Simple Redirect Rules', 'simple-textarea-redirects' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="simple_redirect_rules_textarea" name="simple_redirect_rules_textarea" rows="15" cols="80" class="large-text code"><?php
                                echo \esc_textarea( self::get_simple_rules_as_string() );
                            ?></textarea>
                            <p class="description">
                                <?php printf( \esc_html__( 'Format: %s', 'simple-textarea-redirects' ), '<code>source_path destination_url [status_code]</code>' ); ?><br>
                                - <?php printf( \esc_html__( 'Example: %s', 'simple-textarea-redirects' ), '<code>/old-about-us /about 301</code>' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <hr>

                <h2><?php \esc_html_e( 'Regex Redirects (Regular Expressions)', 'simple-textarea-redirects' ); ?></h2>
                <p><?php \esc_html_e( 'These are checked only if no simple redirect is found. Use for complex pattern matching.', 'simple-textarea-redirects' ); ?></p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="regex_redirect_rules_textarea">
                                <?php \esc_html_e( 'Regex Redirect Rules', 'simple-textarea-redirects' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="regex_redirect_rules_textarea" name="regex_redirect_rules_textarea" rows="15" cols="80" class="large-text code"><?php
                                echo \esc_textarea( (string) \get_option( self::REGEX_OPTION_NAME, '' ) );
                            ?></textarea>
                            <p class="description">
                                <?php printf( \esc_html__( 'Format: %s', 'simple-textarea-redirects' ), '<code>source_regex destination_template [status_code]</code>' ); ?><br>
                                - <?php printf( \esc_html__( 'Example: %s', 'simple-textarea-redirects' ), '<code>^/product/(\d+)/?$ /shop/item.php?id=$1 301</code>' ); ?><br>
                                - <?php
                                    echo \wp_kses(
                                        \__( 'All rules in this box are treated as regular expressions. Delimiters (e.g. <code>#...#</code>) are optional; the plugin will add them if missing.', 'simple-textarea-redirects' ),
                                        [ 'code' => [] ]
                                    );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php \submit_button( \__( 'Save All Redirects', 'simple-textarea-redirects' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle saving of the redirect rules from both textareas.
     */
    public static function save_redirect_rules_callback(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \__( 'You do not have sufficient permissions to access this page.', 'simple-textarea-redirects' ) );
        }

        $nonce_value = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
        if ( ! \wp_verify_nonce( \sanitize_text_field(\wp_unslash($nonce_value)), self::NONCE_ACTION ) ) {
            \wp_die( \__( 'Nonce verification failed.', 'simple-textarea-redirects' ) );
        }

        // Save simple redirects by parsing them into a map
        if ( isset( $_POST['simple_redirect_rules_textarea'] ) ) {
            $raw_rules = (string) $_POST['simple_redirect_rules_textarea'];
            self::save_redirect_rules_from_string($raw_rules);
        }

        // Save regex redirects as a simple string
        if ( isset( $_POST['regex_redirect_rules_textarea'] ) ) {
            $raw_rules = (string) $_POST['regex_redirect_rules_textarea'];
            $sanitized_rules = \sanitize_textarea_field( \wp_unslash( $raw_rules ) );
            \update_option( self::REGEX_OPTION_NAME, $sanitized_rules );
        }

        $redirect_url = \add_query_arg(
            ['page' => self::SETTINGS_SLUG, 'settings-updated' => 'true'],
            \admin_url( 'options-general.php' )
        );
        \wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle the redirects, checking simple rules first for performance.
     */
    public static function handle_redirects(): void {
        $current_request_uri_full = isset($_SERVER['REQUEST_URI']) ? \wp_unslash((string)$_SERVER['REQUEST_URI']) : '';
        
        $path_component = \strtok($current_request_uri_full, '?');
        if ($path_component === false) { $path_component = $current_request_uri_full; }
        $decoded_path = \urldecode($path_component);

        // --- STEP 1: Process O(1) simple redirects first ---
        $simple_redirect_map = \get_option( self::SIMPLE_OPTION_NAME, [] );
        if ( !empty( $simple_redirect_map ) && is_array($simple_redirect_map) ) {
            $temp_normalized_path = \rtrim($decoded_path, '/');
            $request_path_for_exact_match = ($temp_normalized_path === '' && $decoded_path === '/') ? '/' : $temp_normalized_path;
            if (empty($request_path_for_exact_match)) { $request_path_for_exact_match = '/'; }

            if (isset($simple_redirect_map[$request_path_for_exact_match])) {
                $redirect_data = $simple_redirect_map[$request_path_for_exact_match];
                \wp_safe_redirect( \esc_url_raw( $redirect_data['dest'] ), $redirect_data['status'] );
                exit;
            }
        }

        // --- STEP 2: Only if no simple redirect was found, process O(n) regex redirects ---
        $regex_rules_string = (string) \get_option( self::REGEX_OPTION_NAME, '' );
        if ( !empty( $regex_rules_string ) ) {
            $rules_array = \explode( "\n", $regex_rules_string );
            foreach ( $rules_array as $rule_line ) {
                self::process_regex_line( $rule_line, $decoded_path );
            }
        }
    }

    /**
     * Processes a single regex line.
     */
    private static function process_regex_line( string $rule_line, string $request_path ): void {
        $rule_line_trimmed = \trim( $rule_line );
        if ( empty( $rule_line_trimmed ) || \str_starts_with( $rule_line_trimmed, '#' ) ) { return; }
        
        $parts = \preg_split( '/\s+/', $rule_line_trimmed, 3 );
        if ($parts === false || \count( $parts ) < 2) { return; }

        $source = \trim( $parts[0] );
        $destination = \trim( $parts[1] );
        $status_code = (isset($parts[2]) && \in_array(\absint($parts[2]), [301, 302, 307, 308], true)) ? \absint($parts[2]) : 301;

        $pattern = $source;
        if (!\preg_match('/^([\/#~%@!;&`\'"])(.*)\1[imsxeADSUXJu]*$/s', $pattern)) {
            $pattern = '#' . \str_replace('#', '\#', $pattern) . '#';
        }
        
        if (@\preg_match($pattern, $request_path, $matches)) {
            $final_destination = \preg_replace_callback('/\$(\d+)/', fn($m) => $matches[$m[1]] ?? '', $destination);
            \wp_safe_redirect( \esc_url_raw( $final_destination ), $status_code );
            exit;
        }
    }
    
    /**
     * Reconstructs the simple rules string for display in the textarea from the saved map.
     */
    private static function get_simple_rules_as_string(): string {
        $redirect_map = \get_option( self::SIMPLE_OPTION_NAME, [] );
        if (empty($redirect_map) || !is_array($redirect_map)) {
            return '';
        }
        
        $lines = [];
        foreach ($redirect_map as $source => $data) {
            $lines[] = "{$source} {$data['dest']} {$data['status']}";
        }
        return \implode("\n", $lines);
    }
    
    /**
     * Parses a string of simple redirect rules and saves them as a map.
     */
    private static function save_redirect_rules_from_string(string $raw_rules): void {
        $sanitized_rules_string = \sanitize_textarea_field( \wp_unslash( $raw_rules ) );
        $lines = \explode("\n", $sanitized_rules_string);
        $redirect_map = [];

        foreach ($lines as $line) {
            $trimmed_line = \trim($line);
            if (empty($trimmed_line) || \str_starts_with($trimmed_line, '#')) {
                continue;
            }

            $parts = \preg_split('/\s+/', $trimmed_line, 3);
            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $source = \trim($parts[0]);
            if ( ! \str_starts_with( $source, '/' ) ) { $source = '/' . $source; }
            $normalized_source = \rtrim($source, '/');
            if (empty($normalized_source)) { $normalized_source = '/'; }

            $redirect_map[$normalized_source] = [
                'dest'   => \trim($parts[1]),
                'status' => (isset($parts[2]) && \in_array(\absint($parts[2]), [301, 302, 307, 308], true)) ? \absint($parts[2]) : 301,
            ];
        }

        \update_option(self::SIMPLE_OPTION_NAME, $redirect_map);

        // Clean up old option format if it exists
        \delete_option(self::SIMPLE_TEXTAREA_NAME_LEGACY);
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

