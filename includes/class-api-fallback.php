<?php
if (!defined('ABSPATH')) exit;

class PUBG_API_Fallback {
    
    private $fallback_active = false;
    
    public function __construct() {
        add_action('init', array($this, 'init_fallback'));
    }
    
    public function init_fallback() {
        error_log('PUBG Fallback: Starting initialization');
        
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        $this->schedule_health_checks();
        $this->init_ajax_handlers();
        
        error_log('PUBG Fallback: Initialization completed');
    }
    
    public function add_custom_schedules($schedules) {
        $schedules['pubg_5min'] = array(
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        );
        return $schedules;
    }
    
    public function schedule_health_checks() {
        if (!wp_next_scheduled('pubg_api_health_check')) {
            wp_schedule_event(time(), 'pubg_5min', 'pubg_api_health_check');
        }
        
        add_action('pubg_api_health_check', array($this, 'perform_health_check'));
    }
    
    public function init_ajax_handlers() {
        add_action('wp_ajax_pubg_force_health_check', array($this, 'ajax_force_health_check'));
        add_action('wp_ajax_pubg_toggle_fallback_mode', array($this, 'ajax_toggle_fallback_mode'));
    }
    
    public function perform_health_check() {
        error_log('PUBG Fallback: Running health check');
        
        $api_url = get_option('pubg_api_url', '');
        $api_key = get_option('pubg_api_key', '');
        
        if (empty($api_url) || empty($api_key)) {
            update_option('pubg_api_last_status', 'not_configured');
            return;
        }
        
        // محاولة تجربة الـ API
        $start_time = microtime(true);
        $test_result = $this->test_api_connection();
        $response_time = (microtime(true) - $start_time) * 1000;
        
        $previous_status = get_option('pubg_api_last_status', 'unknown');
        $current_status = $test_result ? 'healthy' : 'unhealthy';
        
        update_option('pubg_api_last_status', $current_status);
        update_option('pubg_api_last_check', time());
        update_option('pubg_api_response_time', round($response_time, 2));
        
        // التحقق من تغيير الحالة
        if (!$test_result && $previous_status === 'healthy') {
            $this->activate_fallback_mode();
            $this->send_api_down_notification();
        } elseif ($test_result && $previous_status !== 'healthy') {
            $this->deactivate_fallback_mode();
            $this->send_api_restored_notification();
        }
        
        error_log('PUBG Fallback: Health check completed - Status: ' . $current_status);
    }
    
    private function test_api_connection() {
        // تجربة بسيطة للـ API
        $api_url = get_option('pubg_api_url', '');
        $api_key = get_option('pubg_api_key', '');
        
        $test_url = rtrim($api_url, '/') . '/pubg/getPlayer';
        
        $response = wp_remote_post($test_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Api-Key' => $api_key
            ),
            'body' => json_encode(array('player_id' => 123456789)),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('PUBG Fallback: API test failed - ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        return ($http_code === 200);
    }
    
    public function is_fallback_active() {
        return get_option('pubg_fallback_mode_active', false);
    }
    
    public function activate_fallback_mode() {
        update_option('pubg_fallback_mode_active', true);
        update_option('pubg_fallback_activated_at', time());
        error_log('PUBG Fallback: Fallback mode activated');
    }
    
    public function deactivate_fallback_mode() {
        update_option('pubg_fallback_mode_active', false);
        delete_option('pubg_fallback_activated_at');
        error_log('PUBG Fallback: Fallback mode deactivated');
    }
    
    private function send_api_down_notification() {
        $email = get_option('pubg_notification_email', '');
        if (!empty($email)) {
            $subject = 'PUBG API Service Down';
            $message = 'The PUBG API service appears to be down. Fallback mode activated.';
            wp_mail($email, $subject, $message);
        }
    }
    
    private function send_api_restored_notification() {
        $email = get_option('pubg_notification_email', '');
        if (!empty($email)) {
            $subject = 'PUBG API Service Restored';
            $message = 'The PUBG API service has been restored. Normal operations resumed.';
            wp_mail($email, $subject, $message);
        }
    }
    
    public function ajax_force_health_check() {
        check_ajax_referer('pubg-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $this->perform_health_check();
        
        wp_send_json_success(array(
            'message' => 'Health check completed',
            'status' => get_option('pubg_api_last_status', 'unknown'),
            'fallback_active' => $this->is_fallback_active()
        ));
    }
    
    public function ajax_toggle_fallback_mode() {
        check_ajax_referer('pubg-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $action = sanitize_text_field($_POST['fallback_action']);
        
        if ($action === 'activate') {
            $this->activate_fallback_mode();
            $message = 'Fallback mode activated manually';
        } else {
            $this->deactivate_fallback_mode();
            $message = 'Fallback mode deactivated manually';
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'fallback_active' => $this->is_fallback_active()
        ));
    }
    
    public function get_fallback_status() {
        return array(
            'active' => $this->is_fallback_active(),
            'activated_at' => get_option('pubg_fallback_activated_at', 0),
            'api_last_status' => get_option('pubg_api_last_status', 'unknown'),
            'api_last_check' => get_option('pubg_api_last_check', 0),
            'api_response_time' => get_option('pubg_api_response_time', 0)
        );
    }
}
