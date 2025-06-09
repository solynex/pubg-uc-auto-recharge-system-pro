<?php
/**
 * Plugin Name: PUBG UC Recharge System Pro
 * Description: Complete automation system for PUBG UC recharge with advanced features
 * Version: 7.1.0
 * Author: Eyad Amer
 */

if (!defined('ABSPATH')) exit;

define('PUBG_RECHARGE_VERSION', '7.1.0');
define('PUBG_RECHARGE_DIR', plugin_dir_path(__FILE__));
define('PUBG_RECHARGE_URL', plugin_dir_url(__FILE__));

class PUBG_Recharge_System {
    
    public function __construct() {
        $this->init_hooks();
        $this->load_modules();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activation'));
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));
        register_uninstall_hook(__FILE__, array($this, 'uninstall'));
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }
    
    private function load_modules() {
        require_once PUBG_RECHARGE_DIR . 'includes/class-database.php';
        require_once PUBG_RECHARGE_DIR . 'includes/class-api.php';
        require_once PUBG_RECHARGE_DIR . 'includes/class-bundle.php';
        require_once PUBG_RECHARGE_DIR . 'includes/class-woocommerce.php';
        require_once PUBG_RECHARGE_DIR . 'includes/class-admin.php';
    }
    
    public function init() {
        load_plugin_textdomain('pubg-recharge', false, dirname(plugin_basename(__FILE__)) . '/languages');
        $this->check_dependencies();
    }
    
    private function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>PUBG Recharge System:</strong> WooCommerce is required for this plugin to work.</p></div>';
            });
        }
    }
    
    public function activation() {
        $db = new PUBG_Database();
        $db->create_tables();
        $this->add_default_options();
        wp_schedule_event(time(), 'daily', 'pubg_daily_cleanup');
    }
    
    public function deactivation() {
        wp_clear_scheduled_hook('pubg_daily_cleanup');
    }
    
    public function uninstall() {
        $db = new PUBG_Database();
        $db->drop_tables();
        $this->remove_options();
    }
    
    private function add_default_options() {
        $defaults = array(
            'pubg_api_url' => '',
            'pubg_api_key' => '',
            'pubg_enable_auto_recharge' => 1,
            'pubg_notification_email' => get_option('admin_email'),
            'pubg_low_stock_threshold' => 10,
            'pubg_debug_mode' => 0,
            'pubg_player_id_label' => 'Player ID',
            'pubg_success_message' => 'Your PUBG UC has been successfully recharged!',
            'pubg_processing_message' => 'Your order is being processed. UC will be delivered shortly due to high server load.',
            'pubg_enable_multi_variation' => 0,
            'pubg_plugin_version' => PUBG_RECHARGE_VERSION
        );
        
        foreach ($defaults as $key => $value) {
            add_option($key, $value);
        }
    }
    
    private function remove_options() {
        $options = array(
            'pubg_api_url', 'pubg_api_key', 'pubg_enable_auto_recharge',
            'pubg_notification_email', 'pubg_low_stock_threshold', 'pubg_debug_mode',
            'pubg_player_id_label', 'pubg_success_message', 'pubg_processing_message',
            'pubg_enable_multi_variation', 'pubg_plugin_version'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    public function admin_menu() {
        add_menu_page(
            'PUBG Recharge System',
            'PUBG Recharge',
            'manage_options',
            'pubg-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-cart',
            30
        );
        
        $submenu_pages = array(
            array('pubg-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'pubg-dashboard', array($this, 'dashboard_page')),
            array('pubg-dashboard', 'Categories', 'Categories', 'manage_options', 'pubg-categories', array($this, 'categories_page')),
            array('pubg-dashboard', 'Codes', 'Codes', 'manage_options', 'pubg-codes', array($this, 'codes_page')),
            array('pubg-dashboard', 'Pending Operations', 'Pending Ops', 'manage_options', 'pubg-pending', array($this, 'pending_page')),
            array('pubg-dashboard', 'System Logs', 'Logs', 'manage_options', 'pubg-logs', array($this, 'logs_page')),
            array('pubg-dashboard', 'Import/Export', 'Import/Export', 'manage_options', 'pubg-import-export', array($this, 'import_export_page')),
            array('pubg-dashboard', 'Settings', 'Settings', 'manage_options', 'pubg-settings', array($this, 'settings_page'))
        );
        
        foreach ($submenu_pages as $page) {
            add_submenu_page($page[0], $page[1], $page[2], $page[3], $page[4], $page[5]);
        }
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'pubg-') !== false) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'pubg_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pubg-admin-nonce')
            ));
            $this->enqueue_admin_styles();
        }
    }
    
    public function frontend_scripts() {
        if (is_product() || is_checkout() || is_cart()) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'pubg_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pubg-frontend-nonce')
            ));
        }
    }
    
    private function enqueue_admin_styles() {
        ?>
        <style>
        .wrap { margin: 10px 20px 0 2px; }
        .wrap h1 { font-size: 23px; font-weight: 400; margin: 0 0 20px 0; padding: 9px 15px 4px 0; line-height: 29px; }
        .wrap h2 { font-size: 18px; font-weight: 600; margin: 20px 0 10px 0; padding: 0; }
        .widefat th, .widefat td { padding: 8px 10px; }
        .wp-list-table th, .wp-list-table td { padding: 8px 10px; }
        .button { margin: 2px; }
        .form-table th { width: 200px; padding: 20px 10px 20px 0; }
        .form-table td { padding: 15px 10px; }
        .notice { margin: 15px 0; }
        .postbox { margin-bottom: 20px; border: 1px solid #c3c4c7; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .postbox .hndle { border-bottom: 1px solid #c3c4c7; background: #f6f7f7; }
        .postbox .inside { margin: 11px 0; }
        table.widefat { border-collapse: collapse; margin: 0; width: 100%; background: #fff; border: 1px solid #c3c4c7; }
        table.widefat thead th { background: #f6f7f7; border-bottom: 1px solid #c3c4c7; font-weight: 600; }
        table.widefat tbody tr:nth-child(odd) { background: #f9f9f9; }
        table.widefat tbody tr:hover { background: #f0f0f1; }
        table.widefat th, table.widefat td { border-right: 1px solid #c3c4c7; }
        table.widefat th:last-child, table.widefat td:last-child { border-right: none; }
        .delete-code-btn { color: #d63638 !important; }
        .delete-code-btn:hover { color: #fff !important; background-color: #d63638 !important; border-color: #d63638 !important; }
        .pubg-stats-card { text-align: center; padding: 20px; }
        .pubg-stats-number { font-size: 32px; font-weight: bold; margin: 10px 0; }
        .pubg-success { color: #28a745; }
        .pubg-failed { color: #dc3545; }
        .pubg-pending { color: #ffc107; }
        .pubg-info { color: #17a2b8; }
        .pubg-default { color: #333; }
        @media (max-width: 768px) {
            .wrap { margin: 10px 10px 0 10px; }
            table.widefat { font-size: 12px; }
            .form-table th, .form-table td { display: block; width: 100%; padding: 10px 0; }
            .form-table th { border-bottom: none; }
        }
        </style>
        <?php
    }
    
    public function dashboard_page() {
        $admin = new PUBG_Admin();
        $admin->dashboard_page();
    }
    
    public function categories_page() {
        $admin = new PUBG_Admin();
        $admin->categories_page();
    }
    
    public function codes_page() {
        $admin = new PUBG_Admin();
        $admin->codes_page();
    }
    
    public function pending_page() {
        $admin = new PUBG_Admin();
        $admin->pending_page();
    }
    
    public function logs_page() {
        $admin = new PUBG_Admin();
        $admin->logs_page();
    }
    
    public function import_export_page() {
        $admin = new PUBG_Admin();
        $admin->import_export_page();
    }
    
    public function settings_page() {
        $admin = new PUBG_Admin();
        $admin->settings_page();
    }
}

// Helper Functions
function pubg_debug_log($message, $data = null) {
    if (get_option('pubg_debug_mode', 0)) {
        $log_message = '[PUBG DEBUG ' . date('H:i:s') . '] ' . $message;
        if ($data !== null) {
            $log_message .= ' - Data: ' . print_r($data, true);
        }
        error_log($log_message);
    }
}

function pubg_get_categories() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pubg_recharge_categories ORDER BY uc_amount ASC");
}

function pubg_get_available_codes_count($category_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}pubg_recharge_codes WHERE category_id = %d AND status = 'available'",
        $category_id
    ));
}

function pubg_is_low_stock($category_id) {
    $available = pubg_get_available_codes_count($category_id);
    $threshold = get_option('pubg_low_stock_threshold', 10);
    return $available <= $threshold;
}

function pubg_send_notification($subject, $message) {
    $email = get_option('pubg_notification_email');
    if (!empty($email)) {
        wp_mail($email, $subject, $message);
    }
}

function pubg_format_status($status) {
    $statuses = array(
        'available' => array('text' => 'Available', 'class' => 'pubg-success'),
        'used' => array('text' => 'Used', 'class' => 'pubg-info'),
        'failed' => array('text' => 'Failed', 'class' => 'pubg-failed'),
        'success' => array('text' => 'Success', 'class' => 'pubg-success'),
        'pending' => array('text' => 'Pending', 'class' => 'pubg-pending')
    );
    
    $format = $statuses[$status] ?? array('text' => ucfirst($status), 'class' => 'pubg-default');
    return '<span class="button button-small ' . $format['class'] . '">' . $format['text'] . '</span>';
}

function pubg_truncate_code($code, $length = 8) {
    return strlen($code) > $length ? substr($code, 0, $length) . '...' : $code;
}

function pubg_sanitize_player_id($player_id) {
    $player_id = sanitize_text_field($player_id);
    return preg_match('/^[0-9]{6,12}$/', $player_id) ? $player_id : false;
}

function pubg_get_system_stats() {
    global $wpdb;
    
    $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    
    return array(
        'total_codes' => $wpdb->get_var("SELECT COUNT(*) FROM $codes_table"),
        'available_codes' => $wpdb->get_var("SELECT COUNT(*) FROM $codes_table WHERE status = 'available'"),
        'used_codes' => $wpdb->get_var("SELECT COUNT(*) FROM $codes_table WHERE status = 'used'"),
        'failed_codes' => $wpdb->get_var("SELECT COUNT(*) FROM $codes_table WHERE status = 'failed'"),
        'success_operations' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'success'"),
        'failed_operations' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'failed'"),
        'pending_operations' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'pending'")
    );
}

function pubg_get_player_info($player_id) {
    $api_key = get_option('pubg_api_key');
    $api_url = get_option('pubg_api_url');
    
    if (empty($api_key) || empty($api_url)) {
        return array('success' => false, 'message' => 'API not configured');
    }
    
    $response = wp_remote_post($api_url . '/pubg/getPlayer', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Api-Key' => $api_key
        ),
        'body' => json_encode(array(
            'player_id' => (int)$player_id
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body;
}

function pubg_activate_uc_code($player_id, $uc_code) {
    $api_key = get_option('pubg_api_key');
    $api_url = get_option('pubg_api_url');
    
    if (empty($api_key) || empty($api_url)) {
        return array('success' => false, 'message' => 'API not configured');
    }
    
    $response = wp_remote_post($api_url . '/pubg/activate', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Api-Key' => $api_key
        ),
        'body' => json_encode(array(
            'player_id' => (int)$player_id,
            'uc_code' => $uc_code
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body;
}

function pubg_count_low_stock_categories() {
    $categories = pubg_get_categories();
    $low_stock_count = 0;
    
    foreach ($categories as $category) {
        if (pubg_is_low_stock($category->id)) {
            $low_stock_count++;
        }
    }
    
    return $low_stock_count;
}

// AJAX Handlers - مُحدثة بدون استخدام الـ Classes
add_action('wp_ajax_pubg_validate_player', 'pubg_ajax_validate_player');
add_action('wp_ajax_nopriv_pubg_validate_player', 'pubg_ajax_validate_player');

function pubg_ajax_validate_player() {
    check_ajax_referer('pubg-frontend-nonce', 'nonce');
    
    $player_id = pubg_sanitize_player_id($_POST['player_id']);
    
    if (!$player_id) {
        wp_send_json_error(array('message' => 'Invalid Player ID format. Must be 6-12 digits.'));
    }
    
    $result = pubg_get_player_info($player_id);
    
    if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
        wp_send_json_success(array(
            'player_name' => $result['data']['player_name'] ?? 'Valid Player',
            'player_id' => $player_id
        ));
    } else {
        wp_send_json_error(array(
            'message' => $result['data']['message'] ?? $result['message'] ?? 'Player not found or invalid'
        ));
    }
}

add_action('wp_ajax_pubg_delete_code', 'pubg_ajax_delete_code');
function pubg_ajax_delete_code() {
    check_ajax_referer('pubg-admin-nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $code_id = intval($_POST['code_id']);
    
    global $wpdb;
    $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
    
    $code = $wpdb->get_row($wpdb->prepare("SELECT * FROM $codes_table WHERE id = %d", $code_id));
    
    if (!$code) {
        wp_send_json_error('Code not found');
    }
    
    if ($code->status !== 'available') {
        wp_send_json_error('Cannot delete non-available codes');
    }
    
    $deleted = $wpdb->delete($codes_table, array('id' => $code_id));
    
    if ($deleted) {
        pubg_debug_log("Code deleted manually", array('code_id' => $code_id, 'admin_user' => get_current_user_id()));
        wp_send_json_success('Code deleted successfully');
    } else {
        wp_send_json_error('Failed to delete code');
    }
}

add_action('wp_ajax_pubg_manual_process', 'pubg_ajax_manual_process');
function pubg_ajax_manual_process() {
    check_ajax_referer('pubg-admin-nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $order_id = intval($_POST['order_id']);
    
    pubg_debug_log("Manual processing initiated", array('order_id' => $order_id));
    
    delete_post_meta($order_id, '_pubg_processed');
    
    // استخدام نفس منطق المعالجة من الكود الأصلي
    pubg_process_order($order_id);
    
    wp_send_json_success(array(
        'message' => 'Order processing started',
        'order_id' => $order_id
    ));
}

// Order Processing Function
function pubg_process_order($order_id) {
    pubg_debug_log("Order status changed, checking for PUBG products", array('order_id' => $order_id));
    
    $order = wc_get_order($order_id);
    if (!$order) {
        pubg_debug_log("Order not found", array('order_id' => $order_id));
        return;
    }
    
    $processed = get_post_meta($order_id, '_pubg_processed', true);
    if ($processed === 'yes') {
        pubg_debug_log("Order already processed", array('order_id' => $order_id));
        return;
    }
    
    $has_pubg_items = false;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $is_pubg = get_post_meta($product_id, 'is_pubg_recharge', true);
        
        if ($is_pubg == 'yes') {
            $has_pubg_items = true;
            
            $player_id = $item->get_meta('_pubg_player_id');
            if (empty($player_id)) {
                $player_id = $item->get_meta('Player ID');
            }
            
            if ($player_id) {
                pubg_allocate_code($order_id, $product_id, $variation_id, $player_id, $item_id);
            } else {
                pubg_debug_log("No Player ID found for PUBG product", array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'item_id' => $item_id
                ));
            }
        }
    }
   if ($has_pubg_items) {
    global $wpdb;
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    
    $failed_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $log_table WHERE order_id = %d AND status = 'failed'",
        $order_id
    ));
    
    if ($failed_count == 0) {
        update_post_meta($order_id, '_pubg_processed', 'yes');
        pubg_debug_log("PUBG order processing completed successfully", array('order_id' => $order_id));
    } else {
        pubg_debug_log("PUBG order has failed items, keeping for retry", array(
            'order_id' => $order_id,
            'failed_count' => $failed_count
        ));
    }
}
}

function pubg_allocate_code($order_id, $product_id, $variation_id, $player_id, $item_id) {
    global $wpdb;
    
    $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    
    // تحديد الفئة
    $is_multi_variation = get_post_meta($product_id, 'is_pubg_multi_variation', true);
    
    if ($is_multi_variation == 'yes' && $variation_id) {
        $category_id = get_post_meta($variation_id, 'pubg_variation_category_id', true);
    } else {
        $category_id = get_post_meta($product_id, 'pubg_category_id', true);
    }
    
    if (!$category_id) {
        pubg_debug_log("No category found", array('product_id' => $product_id, 'variation_id' => $variation_id));
        return;
    }
    
    $existing_log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $log_table WHERE order_id = %d AND player_id = %s AND status != 'failed'",
        $order_id, $player_id
    ));
    
    if ($existing_log) {
        pubg_debug_log("Already processed", array('order_id' => $order_id, 'player_id' => $player_id));
        return;
    }
    
    $wpdb->query('START TRANSACTION');
    
    try {
        $wpdb->query("LOCK TABLES $codes_table WRITE");
        
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $codes_table WHERE category_id = %d AND status = 'available' ORDER BY id ASC LIMIT 1",
            $category_id
        ));
        
        if (!$code) {
            $wpdb->query("UNLOCK TABLES");
            $wpdb->query('ROLLBACK');
            
            $wpdb->insert($log_table, array(
                'code_id' => 0,
                'order_id' => $order_id,
                'player_id' => $player_id,
                'status' => 'failed',
                'message' => 'No codes available for this category'
            ));
            return;
        }
        
        $updated = $wpdb->update($codes_table, array(
            'status' => 'used',
            'order_id' => $order_id,
            'player_id' => $player_id,
            'date_used' => current_time('mysql')
        ), array('id' => $code->id));
        
        $wpdb->query("UNLOCK TABLES");
        
        if ($updated) {
            $wpdb->query('COMMIT');
            
            $wpdb->insert($log_table, array(
                'code_id' => $code->id,
                'order_id' => $order_id,
                'player_id' => $player_id,
                'status' => 'pending',
                'message' => 'Code allocated, processing...'
            ));
            
            if (get_option('pubg_enable_auto_recharge', 1)) {
                pubg_send_to_api($code->id, $code->code, $player_id, $order_id);
            }
        } else {
            $wpdb->query('ROLLBACK');
            pubg_debug_log("Failed to update code status", array('code_id' => $code->id));
        }
        
    } catch (Exception $e) {
        $wpdb->query("UNLOCK TABLES");
        $wpdb->query('ROLLBACK');
        pubg_debug_log("Transaction failed", array('error' => $e->getMessage()));
    }
}

function pubg_send_to_api($code_id, $code, $player_id, $order_id) {
    $result = pubg_activate_uc_code($player_id, $code);
    
    global $wpdb;
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
    
    if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
        $wpdb->update($log_table, array(
            'status' => 'success',
            'message' => 'UC activated successfully'
        ), array(
            'code_id' => $code_id,
            'order_id' => $order_id
        ));
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status('completed', 'PUBG UC activated successfully for Player ID: ' . $player_id);
        }
        
    } else {
        $error = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
        
        $wpdb->update($codes_table, array(
            'status' => 'failed'
        ), array('id' => $code_id));
        
        $wpdb->update($log_table, array(
            'status' => 'failed',
            'message' => 'Activation failed: ' . $error
        ), array(
            'code_id' => $code_id,
            'order_id' => $order_id
        ));
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('PUBG UC activation failed for Player ID: ' . $player_id . ' - Error: ' . $error);
        }
    }
}

// Scheduled Events
add_action('pubg_daily_cleanup', 'pubg_daily_cleanup_function');
function pubg_daily_cleanup_function() {
    global $wpdb;
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    
    $deleted = $wpdb->query("DELETE FROM $log_table WHERE date_created < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    pubg_debug_log("Daily cleanup completed", array('deleted_logs' => $deleted));
    
    $stats = pubg_get_system_stats();
    $subject = 'Daily Report - PUBG Recharge System';
    $message = "Daily Statistics:\n";
    $message .= "Successful Operations: " . $stats['success_operations'] . "\n";
    $message .= "Failed Operations: " . $stats['failed_operations'] . "\n";
    $message .= "Available Codes: " . $stats['available_codes'] . "\n";
    $message .= "Low Stock Categories: " . pubg_count_low_stock_categories() . "\n";
    
    pubg_send_notification($subject, $message);
}
// Admin Notices
add_action('admin_notices', 'pubg_admin_notices');
function pubg_admin_notices() {
   $screen = get_current_screen();
   if (strpos($screen->id, 'pubg-') === false) return;
   
   if (!class_exists('WooCommerce')) {
       echo '<div class="notice notice-warning"><p><strong>PUBG Recharge System:</strong> WooCommerce is required for this plugin to work properly.</p></div>';
   }
   
   $api_key = get_option('pubg_api_key');
   if (empty($api_key)) {
       echo '<div class="notice notice-warning"><p><strong>PUBG Recharge System:</strong> API configuration required. Please enter your API key in <a href="' . admin_url('admin.php?page=pubg-settings') . '">Settings</a>.</p></div>';
   }
   
   $categories = pubg_get_categories();
   $low_stock_threshold = get_option('pubg_low_stock_threshold', 10);
   $low_stock_categories = array();
   
   foreach ($categories as $category) {
       $available = pubg_get_available_codes_count($category->id);
       if ($available <= $low_stock_threshold) {
           $low_stock_categories[] = $category->name . ' (' . $category->uc_amount . ' UC) - Available: ' . $available . ' codes';
       }
   }
   
   if (!empty($low_stock_categories)) {
       echo '<div class="notice notice-error"><p><strong>Low Stock Alert:</strong> The following categories need restocking:</p><ul style="margin: 10px 0;">';
       foreach ($low_stock_categories as $category_info) {
           echo '<li>' . esc_html($category_info) . '</li>';
       }
       echo '</ul></div>';
   }
}

// Initialize Plugin
new PUBG_Recharge_System();
?>
