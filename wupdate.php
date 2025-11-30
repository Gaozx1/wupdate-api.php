<?php
/**
 * Plugin Name: WordPress API Extended
 * Plugin URI: https://wpapi.uuk.pp.ua/
 * Description: 扩展WordPress REST API功能，提供文章列表和文章详情接口，支持API密钥认证和密码验证，通过API管理内容
 * Version: 1.1.0
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
define('WP_API_EXTENDED_VERSION', '1.1.0');
define('WP_API_EXTENDED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_API_EXTENDED_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WP_API_EXTENDED_UPDATE_URL', 'https://wpapi.uuk.pp.ua/versions/latest.json');
define('WP_API_EXTENDED_GITHUB_URL', 'https://github.com/Gaozx1/wupdate-api/releases');

// 自动更新类
class WP_API_Extended_Updater {
    
    private $plugin_slug;
    private $plugin_file;
    private $update_url;
    
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->update_url = WP_API_EXTENDED_UPDATE_URL;
        
        $this->init();
    }
    
    private function init() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }
    
    /**
     * 检查更新
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare(WP_API_EXTENDED_VERSION, $remote_version, '<')) {
            $plugin_data = get_plugin_data($this->plugin_file);
            
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = $plugin_data['PluginURI'];
            $obj->package = $this->get_download_url();
            $obj->tested = $this->get_tested_version();
            
            $transient->response[$this->plugin_slug] = $obj;
        }
        
        return $transient;
    }
    
    /**
     * 获取远程版本信息
     */
    private function get_remote_version() {
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['version'])) {
            return $data['version'];
        }
        
        return false;
    }
    
    /**
     * 获取下载URL
     */
    private function get_download_url() {
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // 如果主更新服务器失败，尝试GitHub
            return $this->get_github_download_url();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['download_url'])) {
            return 'https://wpapi.uuk.pp.ua/' . $data['download_url'];
        }
        
        return $this->get_github_download_url();
    }
    
    /**
     * 获取GitHub下载URL
     */
    private function get_github_download_url() {
        $response = wp_remote_get(WP_API_EXTENDED_GITHUB_URL, array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // 简单的正则匹配最新版本的zip文件
        if (preg_match('/href="(https:\/\/github\.com\/Gaozx1\/wupdate-api\/releases\/download\/[^"]+\.zip)"/', $body, $matches)) {
            return $matches[1];
        }
        
        return false;
    }
    
    /**
     * 获取测试的WordPress版本
     */
    private function get_tested_version() {
        global $wp_version;
        return $wp_version;
    }
    
    /**
     * 插件信息
     */
    public function plugin_info($false, $action, $arg) {
        if ($action !== 'plugin_information' || $arg->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        
        $information = new stdClass();
        $information->name = $plugin_data['Name'];
        $information->slug = $this->plugin_slug;
        $information->version = $this->get_remote_version();
        $information->author = $plugin_data['Author'];
        $information->author_profile = $plugin_data['PluginURI'];
        $information->requires = '5.0';
        $information->tested = $this->get_tested_version();
        $information->last_updated = date('Y-m-d');
        $information->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => $this->get_changelog()
        );
        $information->download_link = $this->get_download_url();
        
        return $information;
    }
    
    /**
     * 获取更新日志
     */
    private function get_changelog() {
        return '<h3>版本 1.1.0</h3>
                <ul>
                    <li>修复API撤销后数据库没有删除的bug</li>
                    <li>添加自动更新功能</li>
                    <li>优化汉化文本</li>
                    <li>改进错误处理机制</li>
                </ul>';
    }
    
    /**
     * 安装后处理
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($wp_filesystem->exists($install_directory . $this->plugin_slug)) {
            $wp_filesystem->delete($install_directory . $this->plugin_slug);
        }
        
        return $result;
    }
}

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
        
        // 初始化自动更新
        add_action('init', array($this, 'init_auto_updater'));
    }
    
    public function init_auto_updater() {
        new WP_API_Extended_Updater(__FILE__);
    }
    
    // ... existing code ...
