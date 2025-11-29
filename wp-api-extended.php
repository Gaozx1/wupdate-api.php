<?php
/**
 * Plugin Name: WordPress API Extended
 * Plugin URI: https://gaozx.uuk.pp.ua
 * Description: Extend WordPress REST API functionality, provide article lists and article details interfaces, support API key authentication and password verification, and manage content via API
 * Version: 1.1.0a-4
 * Author: gaozx
 * License: GPL v2 or later
 * Text Domain: wp-api-extended
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_API_EXTENDED_VERSION', '1.1.0a-4');
define('WP_API_EXTENDED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_API_EXTENDED_PLUGIN_PATH', plugin_dir_path(__FILE__));

class WP_API_Extended {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    public function init() {
        // Load text domain for internationalization
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load dependencies immediately
        $this->load_dependencies();
        
        // Initialize features only if dependencies loaded successfully
        if ($this->check_dependencies()) {
            $this->init_hooks();
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-api-extended',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    private function load_dependencies() {
        try {
            // Load in correct dependency order
            $files = [
                'class-api-keys.php',      // Base classes first
                'class-api-functions.php',
                'class-api-routes.php', 
                'class-api-auth.php',      // Depends on other classes
                'class-admin-panel.php'
            ];
            
            foreach ($files as $file) {
                $file_path = WP_API_EXTENDED_PLUGIN_PATH . 'includes/' . $file;
                if (!file_exists($file_path)) {
                    throw new Exception("Missing required file: $file");
                }
                require_once $file_path;
            }
            
        } catch (Exception $e) {
            // Log error and show admin notice
            error_log('WP API Extended Load Error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                if (current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p><strong>API Extended Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
                }
            });
        }
    }
    
    private function check_dependencies() {
        $required_classes = [
            'WP_API_Extended_Keys',
            'WP_API_Extended_Functions',
            'WP_API_Extended_Routes',
            'WP_API_Extended_Auth',
            'WP_API_Extended_Admin_Panel'
        ];
        
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                error_log("WP API Extended: Required class $class not loaded");
                return false;
            }
        }
        
        return true;
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'check_database'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_revoke_api_key', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_verify_password', array($this, 'ajax_verify_password'));
        add_action('wp_ajax_get_other_data', array($this, 'ajax_get_other_data'));
        add_action('wp_ajax_upload_media_test', array($this, 'ajax_upload_media_test'));
        add_action('wp_ajax_create_post_test', array($this, 'ajax_create_post_test'));
        
        // 添加重置功能
        add_action('wp_ajax_reset_api_keys', array($this, 'ajax_reset_api_keys'));
        add_action('wp_ajax_force_generate_api_key', array($this, 'ajax_force_generate_api_key'));
        
        // Alpha version notice
        add_action('admin_notices', array($this, 'alpha_version_notice'));
    }
    
    public function alpha_version_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'wp-api-extended') !== false) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php printf(
                    __('You are using <strong>WordPress API Extended %s</strong> alpha version. This version may contain incomplete features and is not recommended for production use.', 'wp-api-extended'),
                    WP_API_EXTENDED_VERSION
                ); ?></p>
            </div>
            <?php
        }
    }
    
    public function check_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_api_extended_keys';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log("WP API Extended: Table $table_name not found, creating...");
            $this->init_database();
            
            // 再次检查确认表创建成功
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log("WP API Extended: Critical - Failed to create table $table_name");
                add_action('admin_notices', function() use ($table_name) {
                    echo '<div class="notice notice-error"><p>API Extended: 数据库表创建失败 - ' . $table_name . '。请检查错误日志。</p></div>';
                });
                return false;
            }
        } else {
            // 表存在，检查结构
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            $column_names = wp_list_pluck($columns, 'Field');
            
            // 确保所有必要字段都存在
            $required_columns = ['id', 'user_id', 'api_key', 'status', 'created_at', 'updated_at'];
            $missing_columns = array_diff($required_columns, $column_names);
            
            if (!empty($missing_columns)) {
                error_log("WP API Extended: Missing columns in $table_name: " . implode(', ', $missing_columns));
                // 重新创建表
                $this->init_database();
            }
        }
        
        return true;
    }
    
    public function activate() {
        $this->init_database();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function init_database() {
        if (class_exists('WP_API_Extended_Keys')) {
            WP_API_Extended_Keys::create_table();
        }
    }
    
    public function register_routes() {
        if (class_exists('WP_API_Extended_Routes')) {
            WP_API_Extended_Routes::register_routes();
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('WP API Extended', 'wp-api-extended'),
            __('API Extended', 'wp-api-extended'),
            'manage_options',
            'wp-api-extended',
            array($this, 'admin_dashboard_page'),
            'dashicons-rest-api',
            30
        );
        
        // Submenus
        add_submenu_page(
            'wp-api-extended',
            __('API Settings', 'wp-api-extended'),
            __('API Settings', 'wp-api-extended'),
            'manage_options',
            'wp-api-extended-settings',
            array($this, 'admin_settings_page')
        );
        
        add_submenu_page(
            'wp-api-extended',
            __('Content Management', 'wp-api-extended'),
            __('Content Management', 'wp-api-extended'),
            'manage_options',
            'wp-api-extended-content',
            array($this, 'admin_content_page')
        );
        
        add_submenu_page(
            'wp-api-extended',
            __('Media Upload', 'wp-api-extended'),
            __('Media Upload', 'wp-api-extended'),
            'manage_options',
            'wp-api-extended-media',
            array($this, 'admin_media_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-api-extended') === false) {
            return;
        }
        
        // Enqueue common CSS
        wp_enqueue_style(
            'wp-api-extended-admin',
            WP_API_EXTENDED_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_API_EXTENDED_VERSION
        );
        
        // Enqueue common JS
        wp_enqueue_script(
            'wp-api-extended-admin',
            WP_API_EXTENDED_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_API_EXTENDED_VERSION,
            true
        );
        
        // Media upload specific JS
        if ($hook === 'toplevel_page_wp-api-extended-media' || $hook === 'api-extended_page_wp-api-extended-content') {
            wp_enqueue_script(
                'wp-api-extended-media',
                WP_API_EXTENDED_PLUGIN_URL . 'assets/js/media-upload.js',
                array('jquery'),
                WP_API_EXTENDED_VERSION,
                true
            );
            
            // Enqueue media uploader
            wp_enqueue_media();
        }
        
        // Pass data to JavaScript
        wp_localize_script('wp-api-extended-admin', 'wpApiExtendedSettings', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'confirm_generate' => __('Are you sure you want to generate a new API key? The old key will be invalidated immediately.', 'wp-api-extended'),
                'confirm_revoke' => __('Are you sure you want to revoke the current API key? It cannot be recovered after revocation.', 'wp-api-extended'),
                'enter_password' => __('Please enter your password', 'wp-api-extended'),
                'enter_api_key' => __('Please enter your API key or temporary token for testing:', 'wp-api-extended'),
                'generating' => __('Generating...', 'wp-api-extended'),
                'revoking' => __('Revoking...', 'wp-api-extended'),
                'verifying' => __('Verifying...', 'wp-api-extended'),
                'requesting' => __('Requesting...', 'wp-api-extended'),
                'loading' => __('Loading...', 'wp-api-extended'),
                'uploading' => __('Uploading...', 'wp-api-extended'),
                'creating' => __('Creating...', 'wp-api-extended')
            )
        ));
    }
    
    public function admin_dashboard_page() {
        if (class_exists('WP_API_Extended_Admin_Panel')) {
            WP_API_Extended_Admin_Panel::dashboard_page();
        } else {
            echo '<div class="wrap"><h1>Error: Admin Panel class not loaded</h1></div>';
        }
    }
    
    public function admin_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page', 'wp-api-extended'));
        }
        
        $user_id = get_current_user_id();
        $api_key = '';
        
        if (class_exists('WP_API_Extended_Keys')) {
            $api_key = WP_API_Extended_Keys::get_api_key($user_id);
        }
        ?>
        <div class="wrap">
            <h1><?php _e('API Extended - Settings', 'wp-api-extended'); ?> <span class="version-badge">Alpha 3</span></h1>
            
            <?php if (!class_exists('WP_API_Extended_Keys')): ?>
            <div class="notice notice-error">
                <p>Error: API Keys class not loaded. Please check plugin installation.</p>
            </div>
            <?php endif; ?>
            
            <div class="api-extended-grid">
                <div class="api-extended-card">
                    <h2>🔐 <?php _e('API Key Management', 'wp-api-extended'); ?></h2>
                    
                    <?php if ($api_key && $api_key !== '********（密钥已安全存储）'): ?>
                    <div class="notice notice-success">
                        <p><?php _e('Your API Key:', 'wp-api-extended'); ?></p>
                        <code class="api-key-display"><?php echo esc_html($api_key); ?></code>
                        <p><strong>⚠️ <?php _e('Important: This key will only be displayed once and cannot be viewed again after closing the page!', 'wp-api-extended'); ?></strong></p>
                    </div>
                    
                    <button id="revoke-api-key" class="button button-danger"><?php _e('Revoke Current Key', 'wp-api-extended'); ?></button>
                    <?php elseif ($api_key === '********（密钥已安全存储）'): ?>
                    <div class="notice notice-info">
                        <p><?php _e('You already have an active API key.', 'wp-api-extended'); ?></p>
                        <button id="revoke-api-key" class="button button-danger"><?php _e('Revoke Current Key', 'wp-api-extended'); ?></button>
                    </div>
                    <?php else: ?>
                    <p><?php _e('You have not generated an API key yet.', 'wp-api-extended'); ?></p>
                    <button id="generate-api-key" class="button button-primary"><?php _e('Generate New API Key', 'wp-api-extended'); ?></button>
                    <?php endif; ?>
                    
                    <!-- 添加重置按钮 -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h3>🛠️ <?php _e('Troubleshooting', 'wp-api-extended'); ?></h3>
                        <p><small><?php _e('If you are having issues generating API keys, try these options:', 'wp-api-extended'); ?></small></p>
                        <button id="force-generate-api-key" class="button button-secondary"><?php _e('Force Generate Key', 'wp-api-extended'); ?></button>
                        <button id="reset-all-keys" class="button button-danger"><?php _e('Reset All Keys', 'wp-api-extended'); ?></button>
                    </div>
                </div>
                
                <div class="api-extended-card">
                    <h2>🔑 <?php _e('Password Verification Access', 'wp-api-extended'); ?></h2>
                    <p><?php _e('Enter your WordPress password to temporarily access API features:', 'wp-api-extended'); ?></p>
                    <div class="password-section">
                        <input type="password" id="user-password" placeholder="<?php _e('Enter your WordPress password', 'wp-api-extended'); ?>" class="password-input">
                        <button id="verify-password" class="button"><?php _e('Verify Password', 'wp-api-extended'); ?></button>
                    </div>
                    <div id="password-result" class="result-container"></div>
                </div>
            </div>
            
            <div class="api-extended-card">
                <h2>🌐 <?php _e('API Endpoint Information', 'wp-api-extended'); ?></h2>
                <div class="endpoints-grid">
                    <div class="endpoint-group">
                        <h3><?php _e('Content Endpoints', 'wp-api-extended'); ?></h3>
                        <ul>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/posts'); ?></code> - <?php _e('Get posts list', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/posts/{id}'); ?></code> - <?php _e('Get post details', 'wp-api-extended'); ?></li>
                            <li><strong>POST</strong> <code><?php echo rest_url('wp-api-extended/v1/posts'); ?></code> - <?php _e('Create new post', 'wp-api-extended'); ?></li>
                            <li><strong>PUT</strong> <code><?php echo rest_url('wp-api-extended/v1/posts/{id}'); ?></code> - <?php _e('Update post', 'wp-api-extended'); ?></li>
                            <li><strong>DELETE</strong> <code><?php echo rest_url('wp-api-extended/v1/posts/{id}'); ?></code> - <?php _e('Delete post', 'wp-api-extended'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="endpoint-group">
                        <h3><?php _e('Media Endpoints', 'wp-api-extended'); ?></h3>
                        <ul>
                            <li><strong>POST</strong> <code><?php echo rest_url('wp-api-extended/v1/media'); ?></code> - <?php _e('Upload media', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/media'); ?></code> - <?php _e('Get media list', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/media/{id}'); ?></code> - <?php _e('Get media details', 'wp-api-extended'); ?></li>
                            <li><strong>DELETE</strong> <code><?php echo rest_url('wp-api-extended/v1/media/{id}'); ?></code> - <?php _e('Delete media', 'wp-api-extended'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="endpoint-group">
                        <h3><?php _e('Utility Endpoints', 'wp-api-extended'); ?></h3>
                        <ul>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/search'); ?></code> - <?php _e('Search posts', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/system-info'); ?></code> - <?php _e('Get system information', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/categories'); ?></code> - <?php _e('Get categories', 'wp-api-extended'); ?></li>
                            <li><strong>GET</strong> <code><?php echo rest_url('wp-api-extended/v1/tags'); ?></code> - <?php _e('Get tags', 'wp-api-extended'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <h3><?php _e('Usage Instructions', 'wp-api-extended'); ?></h3>
                <p><?php _e('Add to request headers:', 'wp-api-extended'); ?></p>
                <code>X-API-Key: <?php _e('Your API Key', 'wp-api-extended'); ?></code>
                <p><?php _e('Or use temporary token:', 'wp-api-extended'); ?></p>
                <code>Authorization: Bearer <?php _e('Temporary Token', 'wp-api-extended'); ?></code>
                
                <h3><?php _e('Test API', 'wp-api-extended'); ?></h3>
                <button id="test-api" class="button button-secondary"><?php _e('Test Get Posts List', 'wp-api-extended'); ?></button>
                <div id="api-result" class="result-container"></div>
            </div>
        </div>
        <?php
    }
    
    public function admin_content_page() {
        if (class_exists('WP_API_Extended_Admin_Panel')) {
            WP_API_Extended_Admin_Panel::content_management_page();
        } else {
            echo '<div class="wrap"><h1>Error: Admin Panel class not loaded</h1></div>';
        }
    }
    
    public function admin_media_page() {
        if (class_exists('WP_API_Extended_Admin_Panel')) {
            WP_API_Extended_Admin_Panel::media_upload_page();
        } else {
            echo '<div class="wrap"><h1>Error: Admin Panel class not loaded</h1></div>';
        }
    }
    
    // AJAX handlers with error handling
    public function ajax_generate_api_key() {
        try {
            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            // Check if required class exists
            if (!class_exists('WP_API_Extended_Keys')) {
                throw new Exception('API Keys class not loaded');
            }
            
            $user_id = get_current_user_id();
            
            // 添加调试信息
            error_log("WP API Extended: Starting API key generation for user: " . $user_id);
            
            // 检查数据库表是否存在
            if (!WP_API_Extended_Keys::check_table_exists()) {
                error_log("WP API Extended: Table does not exist, attempting to create");
                WP_API_Extended_Keys::create_table();
            }
            
            $api_key = WP_API_Extended_Keys::generate_api_key($user_id);
            
            if ($api_key && !is_wp_error($api_key)) {
                error_log("WP API Extended: API key generated successfully for user: " . $user_id);
                wp_send_json_success(array(
                    'api_key' => $api_key,
                    'message' => __('API key generated successfully', 'wp-api-extended')
                ));
            } else {
                $error_msg = __('Failed to generate API key', 'wp-api-extended');
                if (is_wp_error($api_key)) {
                    $error_msg .= ': ' . $api_key->get_error_message();
                }
                error_log("WP API Extended: API key generation failed for user: " . $user_id);
                throw new Exception($error_msg);
            }
            
        } catch (Exception $e) {
            error_log("WP API Extended: Exception in ajax_generate_api_key: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_revoke_api_key() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            if (!class_exists('WP_API_Extended_Keys')) {
                throw new Exception('API Keys class not loaded');
            }
            
            $user_id = get_current_user_id();
            $result = WP_API_Extended_Keys::revoke_api_key($user_id);
            
            if ($result) {
                wp_send_json_success(__('API key revoked successfully', 'wp-api-extended'));
            } else {
                throw new Exception(__('Failed to revoke API key', 'wp-api-extended'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_verify_password() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            $password = sanitize_text_field($_POST['password'] ?? '');
            
            if (empty($password)) {
                throw new Exception(__('Please enter password', 'wp-api-extended'));
            }
            
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            
            if ($user && wp_check_password($password, $user->user_pass, $user_id)) {
                if (!class_exists('WP_API_Extended_Keys')) {
                    throw new Exception('API Keys class not loaded');
                }
                
                $temp_token = WP_API_Extended_Keys::generate_temp_token($user_id);
                wp_send_json_success(array(
                    'message' => __('Password verification successful', 'wp-api-extended'),
                    'temp_token' => $temp_token,
                    'expires_in' => 3600
                ));
            } else {
                throw new Exception(__('Incorrect password', 'wp-api-extended'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_other_data() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            $type = sanitize_text_field($_POST['type'] ?? 'system_info');
            
            switch ($type) {
                case 'system_info':
                    $data = $this->get_system_info();
                    break;
                case 'user_stats':
                    $data = $this->get_user_stats();
                    break;
                case 'plugin_info':
                    $data = $this->get_plugin_info();
                    break;
                default:
                    throw new Exception(__('Unknown data type', 'wp-api-extended'));
            }
            
            wp_send_json_success(array(
                'type' => $type,
                'data' => $data
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_upload_media_test() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            // This is just a test endpoint for the admin panel
            wp_send_json_success(array(
                'message' => __('Media upload test successful', 'wp-api-extended'),
                'test_data' => array(
                    'max_upload_size' => size_format(wp_max_upload_size()),
                    'allowed_types' => get_allowed_mime_types()
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_create_post_test() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            // Create a test post
            $post_data = array(
                'post_title' => __('Test Post from API', 'wp-api-extended'),
                'post_content' => __('This is a test post created via the API Extended plugin.', 'wp-api-extended'),
                'post_status' => 'draft',
                'post_author' => get_current_user_id()
            );
            
            $post_id = wp_insert_post($post_data);
            
            if ($post_id && !is_wp_error($post_id)) {
                wp_send_json_success(array(
                    'message' => __('Test post created successfully', 'wp-api-extended'),
                    'post_id' => $post_id,
                    'edit_url' => get_edit_post_link($post_id)
                ));
            } else {
                throw new Exception(__('Failed to create test post', 'wp-api-extended'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // 新增的重置功能
    public function ajax_reset_api_keys() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            if (!class_exists('WP_API_Extended_Keys')) {
                throw new Exception('API Keys class not loaded');
            }
            
            $result = WP_API_Extended_Keys::reset_all_keys();
            
            if ($result) {
                wp_send_json_success(__('All API keys have been reset successfully', 'wp-api-extended'));
            } else {
                throw new Exception(__('Failed to reset API keys', 'wp-api-extended'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_force_generate_api_key() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'wp-api-extended'));
            }
            
            check_ajax_referer('wp_rest', 'nonce');
            
            if (!class_exists('WP_API_Extended_Keys')) {
                throw new Exception('API Keys class not loaded');
            }
            
            $user_id = get_current_user_id();
            $api_key = WP_API_Extended_Keys::force_generate_api_key($user_id);
            
            if ($api_key) {
                wp_send_json_success(array(
                    'api_key' => $api_key,
                    'message' => __('API key generated successfully (forced)', 'wp-api-extended')
                ));
            } else {
                throw new Exception(__('Failed to generate API key even with force method', 'wp-api-extended'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function get_system_info() {
        global $wpdb;
        
        return array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'language' => get_bloginfo('language'),
                'charset' => get_bloginfo('charset')
            ),
            'php' => array(
                'version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ),
            'database' => array(
                'version' => $wpdb->db_version(),
                'charset' => $wpdb->charset,
                'table_prefix' => $wpdb->prefix
            ),
            'server' => array(
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown'
            )
        );
    }
    
    private function get_user_stats() {
        $user_counts = count_users();
        
        return array(
            'total_users' => $user_counts['total_users'],
            'user_roles' => $user_counts['avail_roles'],
            'current_user' => array(
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'role' => wp_get_current_user()->roles[0] ?? 'unknown'
            )
        );
    }
    
    private function get_plugin_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');
        
        $plugins_info = array();
        foreach ($active_plugins as $plugin) {
            if (isset($all_plugins[$plugin])) {
                $plugins_info[] = array(
                    'name' => $all_plugins[$plugin]['Name'],
                    'version' => $all_plugins[$plugin]['Version'],
                    'author' => $all_plugins[$plugin]['Author']
                );
            }
        }
        
        return array(
            'total_plugins' => count($all_plugins),
            'active_plugins' => count($active_plugins),
            'plugins' => $plugins_info
        );
    }
}

// Initialize plugin
function wp_api_extended_init() {
    return WP_API_Extended::get_instance();
}

// Start plugin
add_action('plugins_loaded', 'wp_api_extended_init');