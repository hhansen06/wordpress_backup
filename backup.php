<?php
/**
 * Plugin Name: H2 Backup API
 * Description: Provides a REST API for SQL dump, wp-content index, and wp-content file download protected by a bearer token.
 * Version: 1.0.2
 * Author: Henrik Hansen
 * Text Domain: h2_backup_api_plugin
 * Update URI: h2_backup_api_plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Backup_API_Plugin
{
    const OPTION_TOKEN = 'backup_api_token';
    const REST_NAMESPACE = 'backup/v1';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_settings_page()
    {
        add_options_page(
            __('Backup API', 'h2_backup_api_plugin'),
            __('Backup API', 'h2_backup_api_plugin'),
            'manage_options',
            'h2_backup_api_plugin',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('h2_backup_api_plugin_settings', self::OPTION_TOKEN, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        add_settings_section(
            'backup_api_section',
            __('API Settings', 'h2_backup_api_plugin'),
            function () {
                echo '<p>' . esc_html__('Set the bearer token used to access the Backup API.', 'h2_backup_api_plugin') . '</p>';
            },
            'h2_backup_api_plugin'
        );

        add_settings_field(
            'backup_api_token',
            __('Bearer Token', 'h2_backup_api_plugin'),
            [$this, 'render_token_field'],
            'h2_backup_api_plugin',
            'backup_api_section'
        );
    }

    public function render_token_field()
    {
        $value = get_option(self::OPTION_TOKEN, '');
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_TOKEN) . '" value="' . esc_attr($value) . '" />';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $base = trailingslashit(rest_url(self::REST_NAMESPACE));
        $sql_url = $base . 'sql-dump';
        $index_url = $base . 'wp-content-index';
        $file_url = $base . 'wp-content-file&path=relative/path/under/wp-content';
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Backup API Settings', 'h2_backup_api_plugin') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('h2_backup_api_plugin_settings');
        do_settings_sections('h2_backup_api_plugin');
        submit_button();
        echo '</form>';
        echo '<hr />';
        echo '<h2>' . esc_html__('API Endpoints', 'h2_backup_api_plugin') . '</h2>';
        echo '<p>' . esc_html__('Use these URLs with the Bearer token:', 'h2_backup_api_plugin') . '</p>';
        echo '<ul>';
        echo '<li><strong>SQL Dump</strong>: <code>' . esc_html($sql_url) . '</code></li>';
        echo '<li><strong>wp-content Index</strong>: <code>' . esc_html($index_url) . '</code></li>';
        echo '<li><strong>wp-content File</strong>: <code>' . esc_html($file_url) . '</code></li>';
        echo '</ul>';
        echo '</div>';
    }

    public function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/sql-dump', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_sql_dump'],
            'permission_callback' => [$this, 'check_bearer_token'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/wp-content-index', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wp_content_index'],
            'permission_callback' => [$this, 'check_bearer_token'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/wp-content-file', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_wp_content_file'],
            'permission_callback' => [$this, 'check_bearer_token'],
            'args' => [
                'path' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    private function get_bearer_token_from_request(WP_REST_Request $request)
    {
        $auth = $request->get_header('authorization');
        if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (!$auth && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth = $headers['Authorization'];
            }
        }
        if (!$auth) {
            return '';
        }
        if (preg_match('/^Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    public function check_bearer_token(WP_REST_Request $request)
    {
        $stored = get_option(self::OPTION_TOKEN, '');
        if ($stored === '') {
            return new WP_Error('backup_api_no_token', __('Bearer token not configured.', 'h2_backup_api_plugin'), ['status' => 401]);
        }

        $provided = $this->get_bearer_token_from_request($request);
        if ($provided === '' || !hash_equals($stored, $provided)) {
            return new WP_Error('backup_api_invalid_token', __('Invalid bearer token.', 'h2_backup_api_plugin'), ['status' => 401]);
        }

        return true;
    }

    public function handle_sql_dump(WP_REST_Request $request)
    {
        global $wpdb;

        $tables = $wpdb->get_col('SHOW TABLES');
        if (empty($tables)) {
            return new WP_Error('backup_api_no_tables', __('No database tables found.', 'h2_backup_api_plugin'), ['status' => 500]);
        }

        $charset = $wpdb->get_charset_collate();
        $dump = "SET NAMES utf8mb4;\n";
        $dump .= "SET foreign_key_checks = 0;\n\n";

        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            if (!empty($create[1])) {
                $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $dump .= $create[1] . ";\n\n";
            }

            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    $values = array_map([$this, 'escape_sql_value'], array_values($row));
                    $dump .= "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $values) . ");\n";
                }
                $dump .= "\n";
            }
        }

        $dump .= "SET foreign_key_checks = 1;\n";

        $gz_dump = gzencode($dump, 9);
        if ($gz_dump === false) {
            return new WP_Error('backup_api_gzip_failed', __('Failed to compress SQL dump.', 'h2_backup_api_plugin'), ['status' => 500]);
        }

        $filename = 'backup-' . gmdate('Y-m-d-His') . '.sql.gz';
        return $this->stream_text_response($gz_dump, $filename, 'application/gzip');
    }

    private function escape_sql_value($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        return "'" . esc_sql($value) . "'";
    }

    public function handle_wp_content_index(WP_REST_Request $request)
    {
        $base = WP_CONTENT_DIR;
        $base_len = strlen($base) + 1;

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            $relative = substr($path, $base_len);
            $files[] = [
                'path' => str_replace('\\', '/', $relative),
                'checksum' => hash_file('sha256', $path),
                'size' => $fileInfo->getSize(),
                'modified' => gmdate('c', $fileInfo->getMTime()),
            ];
        }

        return rest_ensure_response([
            'generated_at' => gmdate('c'),
            'count' => count($files),
            'files' => $files,
        ]);
    }

    public function handle_wp_content_file(WP_REST_Request $request)
    {
        $relative = $request->get_param('path');
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');
        if ($relative === '') {
            return new WP_Error('backup_api_invalid_path', __('Invalid file path.', 'h2_backup_api_plugin'), ['status' => 400]);
        }

        $full = wp_normalize_path(WP_CONTENT_DIR . '/' . $relative);
        $content_dir = wp_normalize_path(WP_CONTENT_DIR);

        if (strpos($full, $content_dir) !== 0 || !file_exists($full) || !is_file($full)) {
            return new WP_Error('backup_api_file_not_found', __('File not found.', 'h2_backup_api_plugin'), ['status' => 404]);
        }

        $mime = wp_check_filetype($full);
        $filename = basename($full);

        return $this->stream_file_response($full, $filename, $mime['type'] ?: 'application/octet-stream');
    }

    private function stream_text_response($content, $filename, $content_type)
    {
        $response = new WP_REST_Response($content, 200);
        $response->header('Content-Type', $content_type);
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }

    private function stream_file_response($path, $filename, $content_type)
    {
        $response = new WP_REST_Response(null, 200);
        $response->header('Content-Type', $content_type);
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->header('Content-Length', (string) filesize($path));
        $response->set_data(file_get_contents($path));
        return $response;
    }
}

new Backup_API_Plugin();
