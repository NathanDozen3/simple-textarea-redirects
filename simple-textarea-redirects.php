<?php
/**
 * Plugin Name: Simple Textarea Redirects
 * Description: Manage redirects with separate textareas for high-performance simple redirects and powerful regex redirects. Includes an import & classification tool.
 * Version: 1.3.8
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

    private const SIMPLE_OPTION_NAME = 'str_redirect_rules_simple_map';
    private const REGEX_OPTION_NAME = 'str_redirect_rules_regex_list';
    private const TOP_LEVEL_SLUG = 'simple-textarea-redirects-menu';
    private const SETTINGS_SLUG = 'simple-textarea-redirects-settings';
    private const CLASSIFY_SLUG = 'simple-textarea-redirects-classify';
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
        \add_action( 'admin_post_str_classify_redirect_rules', [self::class, 'classify_redirect_rules_callback'] );
        \add_action( 'parse_request', [self::class, 'handle_redirects'], 1 );
        \add_action( 'plugins_loaded', [self::class, 'load_textdomain'] );
        \add_filter( 'plugin_action_links_' . \plugin_basename( __FILE__ ), [self::class, 'add_plugin_action_links'] );
    }

    /**
     * Add "Settings" and "Import" links to the plugin action row.
     *
     * @param array<string, string> $links An array of plugin action links.
     * @return array<string, string> An array of plugin action links.
     */
    public static function add_plugin_action_links( array $links ): array {
        $settings_url = \esc_url( \admin_url( 'admin.php?page=' . self::TOP_LEVEL_SLUG ) );
        $import_url = \esc_url( \admin_url( 'admin.php?page=' . self::CLASSIFY_SLUG ) );

        $settings_link = '<a href="' . $settings_url . '">' . \__( 'Settings', 'simple-textarea-redirects' ) . '</a>';
        $import_link = '<a href="' . $import_url . '">' . \__( 'Import & Classify', 'simple-textarea-redirects' ) . '</a>';
        
        return \array_merge( ['settings' => $settings_link, 'import' => $import_link], $links );
    }

    /**
     * Add the options pages to the admin menu.
     */
    public static function add_admin_menu(): void {
        \add_menu_page(
            \__( 'Redirects Settings', 'simple-textarea-redirects' ),
            \__( 'Redirects', 'simple-textarea-redirects' ),
            'manage_options',
            self::TOP_LEVEL_SLUG,
            [self::class, 'render_settings_page'],
            'dashicons-admin-links',
            81
        );

        \add_submenu_page(
            self::TOP_LEVEL_SLUG,
            \__( 'Redirect Settings', 'simple-textarea-redirects' ),
            \__( 'Redirect Settings', 'simple-textarea-redirects' ),
            'manage_options',
            self::TOP_LEVEL_SLUG,
            [self::class, 'render_settings_page']
        );

        \add_submenu_page(
            self::TOP_LEVEL_SLUG,
            \__( 'Import & Classify', 'simple-textarea-redirects' ),
            \__( 'Import & Classify', 'simple-textarea-redirects' ),
            'manage_options',
            self::CLASSIFY_SLUG,
            [self::class, 'render_import_classify_page']
        );
    }

    /**
     * Render the main settings page.
     */
    public static function render_settings_page(): void {
        $simple_rules_transient = \get_transient('str_classified_simple_rules');
        $regex_rules_transient = \get_transient('str_classified_regex_rules');

        $simple_rules_to_display = $simple_rules_transient !== false ? $simple_rules_transient : self::get_simple_rules_as_string();
        $regex_rules_to_display = $regex_rules_transient !== false ? $regex_rules_transient : (string) \get_option( self::REGEX_OPTION_NAME, '' );

        if ($simple_rules_transient !== false) {
            \delete_transient('str_classified_simple_rules');
            \delete_transient('str_classified_regex_rules');
        }
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Optimized Textarea Redirects', 'simple-textarea-redirects' ); ?></h1>
            
            <?php
            if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
                \add_settings_error( 'str_messages', 'str_message_id', \__( 'Settings saved.', 'simple-textarea-redirects' ), 'updated' );
            }
            if ( isset( $_GET['classified'] ) && $_GET['classified'] === 'true' ) {
                $simple_count = \absint($_GET['simple'] ?? 0);
                $regex_count = \absint($_GET['regex'] ?? 0);
                $message = \sprintf(
                    \__('Classification complete. Found %s simple and %s regex rules. Please review and click "Save All Redirects" to apply.', 'simple-textarea-redirects'),
                    \number_format_i18n($simple_count),
                    \number_format_i18n($regex_count)
                );
                \add_settings_error( 'str_messages', 'str_message_id', $message, 'info' );
            }
            \settings_errors( 'str_messages' );
            ?>

            <form method="post" action="<?php echo \esc_url( \admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="str_save_redirect_rules">
                <?php \wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                <h2><?php \esc_html_e( 'Simple Redirects (Exact Match)', 'simple-textarea-redirects' ); ?></h2>
                <p><?php \esc_html_e( 'These are checked first using a hash map for instant lookups. Use for one-to-one redirects.', 'simple-textarea-redirects' ); ?></p>
                <table class="form-table">
                    <tr>
                        <td>
                            <textarea id="simple_redirect_rules_textarea" name="simple_redirect_rules_textarea" rows="15" class="large-text code"><?php
                                echo \esc_textarea( $simple_rules_to_display );
                            ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <hr>

                <h2><?php \esc_html_e( 'Regex Redirects (Regular Expressions)', 'simple-textarea-redirects' ); ?></h2>
                <p><?php \esc_html_e( 'These are checked only if no simple redirect is found. Use for complex pattern matching.', 'simple-textarea-redirects' ); ?></p>
                <table class="form-table">
                    <tr>
                        <td>
                            <textarea id="regex_redirect_rules_textarea" name="regex_redirect_rules_textarea" rows="15" class="large-text code"><?php
                                echo \esc_textarea( $regex_rules_to_display );
                            ?></textarea>
                        </td>
                    </tr>
                </table>

                <?php \submit_button( \__( 'Save All Redirects', 'simple-textarea-redirects' ) ); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render the Import & Classify page.
     */
    public static function render_import_classify_page(): void {
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Import & Classify Redirects', 'simple-textarea-redirects' ); ?></h1>
            <p><?php \esc_html_e( 'Paste your full list of redirects below. The tool will analyze each line and automatically sort them into the high-performance Simple list and the Regex list for you.', 'simple-textarea-redirects' ); ?></p>
            
            <form method="post" action="<?php echo \esc_url( \admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="str_classify_redirect_rules">
                <?php \wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="mixed_redirect_rules_textarea">
                                <?php \esc_html_e( 'Mixed Redirect Rules', 'simple-textarea-redirects' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="mixed_redirect_rules_textarea" name="mixed_redirect_rules_textarea" rows="20" class="large-text code"></textarea>
                            <p class="description">
                                <?php \esc_html_e( 'Paste your list from a .htaccess file, CSV, or other source. One rule per line.', 'simple-textarea-redirects' ); ?><br>
                                <em><?php \esc_html_e( 'Note: Paths containing special characters like ( or ) may be incorrectly classified as regex. Please review the results before saving.', 'simple-textarea-redirects' ); ?></em>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php \submit_button( \__( 'Analyze and Classify', 'simple-textarea-redirects' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the classification of a mixed list of redirects.
     */
    public static function classify_redirect_rules_callback(): void {
        if ( ! \current_user_can( 'manage_options' ) ) { \wp_die( \__('Permission denied.') ); }
        $nonce_value = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
        if ( ! \wp_verify_nonce( \sanitize_text_field(\wp_unslash($nonce_value)), self::NONCE_ACTION ) ) { \wp_die( \__('Nonce error.') ); }

        $simple_rules = [];
        $regex_rules = [];

        if ( isset( $_POST['mixed_redirect_rules_textarea'] ) ) {
            $raw_rules = (string) $_POST['mixed_redirect_rules_textarea'];
            $sanitized_rules = \sanitize_textarea_field( \wp_unslash( $raw_rules ) );
            $lines = \explode("\n", $sanitized_rules);

            foreach ($lines as $line) {
                $clean_line = \preg_replace('/^\s*Redirect\s+\d{3}\s+/', '', \trim($line));
                if (empty($clean_line) || \str_starts_with($clean_line, '#')) continue;

                $parts = \preg_split('/\s+/', $clean_line, 3);
                if (count($parts) < 2) continue;

                $source = $parts[0];
                $destination = $parts[1];
                $status = $parts[2] ?? '301';
                
                $is_regex = false;
                if (\str_contains($destination, '$')) {
                    $is_regex = true;
                } elseif (\preg_match('/[\[\]\(\)\|\*\+]/', $source)) {
                    $is_regex = true;
                } elseif (\str_contains($source, '.*')) {
                    $is_regex = true;
                }
                
                if ($is_regex) {
                    $regex_rules[] = "{$source} {$destination} {$status}";
                } else {
                    $normalized_source = '/' . \trim(\preg_replace('{\^\/?(.*?)\/?\??\$$}', '$1', $source), '/');
                    if (empty($normalized_source)) { $normalized_source = '/'; }
                    $simple_rules[] = "{$normalized_source} {$destination} {$status}";
                }
            }
        }
        
        \set_transient('str_classified_simple_rules', \implode("\n", $simple_rules), HOUR_IN_SECONDS);
        \set_transient('str_classified_regex_rules', \implode("\n", $regex_rules), HOUR_IN_SECONDS);

        $redirect_url = \add_query_arg([
            'page' => self::TOP_LEVEL_SLUG,
            'classified' => 'true',
            'simple' => count($simple_rules),
            'regex' => count($regex_rules)
        ], \admin_url( 'admin.php' ));
        
        \wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle saving of the redirect rules from both textareas.
     */
    public static function save_redirect_rules_callback(): void {
        if ( ! \current_user_can( 'manage_options' ) ) { \wp_die( \__('Permission denied.') ); }
        $nonce_value = isset($_POST[self::NONCE_NAME]) ? (string) $_POST[self::NONCE_NAME] : '';
        if ( ! \wp_verify_nonce( \sanitize_text_field(\wp_unslash($nonce_value)), self::NONCE_ACTION ) ) { \wp_die( \__('Nonce error.') ); }

        if ( isset( $_POST['simple_redirect_rules_textarea'] ) ) {
            self::save_redirect_rules_from_string((string) $_POST['simple_redirect_rules_textarea']);
        }

        if ( isset( $_POST['regex_redirect_rules_textarea'] ) ) {
            $raw_rules = (string) $_POST['regex_redirect_rules_textarea'];
            \update_option( self::REGEX_OPTION_NAME, \sanitize_textarea_field( \wp_unslash( $raw_rules ) ) );
        }

        $redirect_url = \add_query_arg(
            ['page' => self::TOP_LEVEL_SLUG, 'settings-updated' => 'true'],
            \admin_url( 'admin.php' )
        );
        \wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle the redirects, checking simple rules first for performance.
     * The WP object is passed by the 'parse_request' hook, but we don't need to use it.
     */
    public static function handle_redirects( \WP $wp ): void {
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
        
        // Temporary error handler to log invalid regex without breaking the site.
        \set_error_handler(function($errno, $errstr) use ($pattern) {
            if ($errno === E_WARNING && \str_starts_with($errstr, 'preg_match():')) {
                \error_log('Simple Textarea Redirects Plugin: Invalid regex pattern provided: "' . $pattern . '". Error: ' . $errstr);
            }
            return true; // Suppress the warning from showing on the front-end.
        });

        $match_result = \preg_match($pattern, $request_path, $matches);

        \restore_error_handler(); // IMPORTANT: Always restore the default error handler.

        if ($match_result) {
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
        if (empty($redirect_map) || !is_array($redirect_map)) return '';
        
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
            if (empty($trimmed_line) || \str_starts_with($trimmed_line, '#')) continue;
            
            $parts = \preg_split('/\s+/', $trimmed_line, 3);
            if ($parts === false || count($parts) < 2) continue;

            $source = \trim($parts[0]);
            $destination = \trim($parts[1]);
            $status = (isset($parts[2]) && \in_array(\absint($parts[2]), [301, 302, 307, 308], true)) ? \absint($parts[2]) : 301;

            $cleaned_source = \preg_replace('{\^\/?(.*?)\/?\??\$$}', '$1', $source);

            $normalized_source = '/' . \trim($cleaned_source, '/');
            if (empty($normalized_source)) { $normalized_source = '/'; }
            
            $redirect_map[$normalized_source] = [
                'dest'   => $destination,
                'status' => $status,
            ];
        }

        \update_option(self::SIMPLE_OPTION_NAME, $redirect_map);
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

