<?php
if (!defined('ABSPATH')) exit;

class PUBG_API {
    
    private $api_url;
    private $api_key;
    private $timeout;
    private $max_retries;
    
    public function __construct() {
        $this->api_url = get_option('pubg_api_url', '');
        $this->api_key = get_option('pubg_api_key', '');
        $this->timeout = 30;
        $this->max_retries = 3;
    }
    
    public function is_configured() {
        return !empty($this->api_url) && !empty($this->api_key);
    }
    
    public function get_player_info($player_id) {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => 'API not configured properly');
        }
        
        $endpoint = $this->api_url . '/pubg/getPlayer';
        $data = array('player_id' => (int)$player_id);
        
        pubg_debug_log("Player validation request", array(
            'player_id' => $player_id,
            'endpoint' => $endpoint
        ));
        
        $response = $this->make_request($endpoint, $data);
        
        if ($response['success']) {
            pubg_debug_log("Player validation response", array(
                'player_id' => $player_id,
                'response' => $response['data']
            ));
            
            // تحقق من البنية الصحيحة للاستجابة
            if (is_array($response['data'])) {
                // إذا كانت الاستجابة مباشرة من API
                if (isset($response['data']['status']) && $response['data']['status'] === 'success') {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'player_name' => $response['data']['player_name'] ?? 'Unknown Player',
                            'player_id' => $player_id,
                            'message' => 'Player found successfully'
                        )
                    );
                }
                // إذا كانت الاستجابة تحتوي على data منفصل
                elseif (isset($response['data']['data']['status']) && $response['data']['data']['status'] === 'success') {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'player_name' => $response['data']['data']['player_name'] ?? 'Unknown Player',
                            'player_id' => $player_id,
                            'message' => 'Player found successfully'
                        )
                    );
                }
                // إذا كانت الاستجابة ناجحة بدون status
                elseif (isset($response['data']['player_name'])) {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'player_name' => $response['data']['player_name'],
                            'player_id' => $player_id,
                            'message' => 'Player found successfully'
                        )
                    );
                }
                else {
                    return array(
                        'success' => false,
                        'data' => array(
                            'message' => $response['data']['message'] ?? 'Player not found'
                        )
                    );
                }
            } else {
                return array(
                    'success' => false,
                    'data' => array(
                        'message' => 'Invalid response format'
                    )
                );
            }
        } else {
            pubg_debug_log("Player validation failed", array(
                'player_id' => $player_id,
                'error' => $response['message']
            ));
            
            return array(
                'success' => false,
                'data' => array(
                    'message' => $response['message']
                )
            );
        }
    }
    
    public function activate_uc_code($player_id, $uc_code) {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => 'API not configured properly');
        }
        
        $endpoint = $this->api_url . '/pubg/activate';
        $data = array(
            'player_id' => (int)$player_id,
            'uc_code' => $uc_code
        );
        
        pubg_debug_log("UC activation request", array(
            'player_id' => $player_id,
            'code' => substr($uc_code, 0, 8) . '...',
            'endpoint' => $endpoint
        ));
        
        $response = $this->make_request($endpoint, $data);
        
        if ($response['success']) {
            pubg_debug_log("UC activation response", array(
                'player_id' => $player_id,
                'code' => substr($uc_code, 0, 8) . '...',
                'response' => $response['data']
            ));
            
            // تحقق من البنية الصحيحة للاستجابة
            if (is_array($response['data'])) {
                // إذا كانت الاستجابة مباشرة من API
                if (isset($response['data']['status']) && $response['data']['status'] === 'success') {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'message' => 'UC activated successfully',
                            'player_id' => $player_id
                        )
                    );
                }
                // إذا كانت الاستجابة تحتوي على data منفصل
                elseif (isset($response['data']['data']['status']) && $response['data']['data']['status'] === 'success') {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'message' => 'UC activated successfully',
                            'player_id' => $player_id
                        )
                    );
                }
                // إذا كانت الاستجابة ناجحة بدون status محدد
                elseif (isset($response['data']['success']) && $response['data']['success'] === true) {
                    return array(
                        'success' => true,
                        'data' => array(
                            'status' => 'success',
                            'message' => 'UC activated successfully',
                            'player_id' => $player_id
                        )
                    );
                }
                else {
                    return array(
                        'success' => false,
                        'data' => array(
                            'status' => 'failed',
                            'message' => $response['data']['message'] ?? 'Activation failed'
                        )
                    );
                }
            } else {
                return array(
                    'success' => false,
                    'data' => array(
                        'status' => 'failed',
                        'message' => 'Invalid response format'
                    )
                );
            }
        } else {
            pubg_debug_log("UC activation failed", array(
                'player_id' => $player_id,
                'code' => substr($uc_code, 0, 8) . '...',
                'error' => $response['message']
            ));
            
            return array(
                'success' => false,
                'data' => array(
                    'status' => 'failed',
                    'message' => $response['message']
                )
            );
        }
    }
    
    public function test_connection() {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => 'API credentials not configured');
        }
        
        $test_player_id = '5555511111';
        $result = $this->get_player_info($test_player_id);
        
        if ($result['success']) {
            return array('success' => true, 'message' => 'API connection successful');
        } else {
            return array(
                'success' => false, 
                'message' => 'API connection failed: ' . ($result['data']['message'] ?? $result['message'] ?? 'Unknown error')
            );
        }
    }
    
    private function make_request($endpoint, $data, $retry_count = 0) {
        $headers = array(
            'Content-Type' => 'application/json',
            'X-Api-Key' => $this->api_key,
            'User-Agent' => 'PUBG-Recharge-System/' . PUBG_RECHARGE_VERSION
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $this->timeout,
            'method' => 'POST',
            'sslverify' => true,
            'blocking' => true
        );
        
        pubg_debug_log("Making API request", array(
            'endpoint' => $endpoint,
            'data' => $data,
            'retry_count' => $retry_count
        ));
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            if ($retry_count < $this->max_retries && in_array($response->get_error_code(), array('http_request_failed', 'connect_error'))) {
                pubg_debug_log("API request failed, retrying", array(
                    'endpoint' => $endpoint,
                    'error' => $error_message,
                    'retry_count' => $retry_count + 1
                ));
                
                sleep(1);
                return $this->make_request($endpoint, $data, $retry_count + 1);
            }
            
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $error_message,
                'error_code' => $response->get_error_code()
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        pubg_debug_log("API response received", array(
            'http_code' => $http_code,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 200)
        ));
        
        if ($http_code !== 200) {
            if ($retry_count < $this->max_retries && in_array($http_code, array(500, 502, 503, 504))) {
                pubg_debug_log("API server error, retrying", array(
                    'endpoint' => $endpoint,
                    'http_code' => $http_code,
                    'retry_count' => $retry_count + 1
                ));
                
                sleep(2);
                return $this->make_request($endpoint, $data, $retry_count + 1);
            }
            
            return array(
                'success' => false,
                'message' => 'HTTP error: ' . $http_code . ' - ' . $body,
                'http_code' => $http_code,
                'response_body' => $body
            );
        }
        
        if (empty($body)) {
            return array(
                'success' => false,
                'message' => 'Empty response from API',
                'http_code' => $http_code
            );
        }
        
        $decoded_response = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            pubg_debug_log("JSON decode error", array(
                'json_error' => json_last_error_msg(),
                'raw_body' => $body
            ));
            
            return array(
                'success' => false,
                'message' => 'Invalid JSON response from API: ' . json_last_error_msg(),
                'raw_response' => $body
            );
        }
        
        if (!is_array($decoded_response)) {
            return array(
                'success' => false,
                'message' => 'Unexpected response format from API',
                'response_type' => gettype($decoded_response),
                'raw_response' => $body
            );
        }
        
        return array(
            'success' => true,
            'data' => $decoded_response,
            'http_code' => $http_code
        );
    }
    
    public function validate_api_credentials($api_url, $api_key) {
        if (empty($api_url) || empty($api_key)) {
            return array('success' => false, 'message' => 'API URL and Key are required');
        }
        
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            return array('success' => false, 'message' => 'Invalid API URL format');
        }
        
        // احفظ القيم الحالية
        $temp_api_url = $this->api_url;
        $temp_api_key = $this->api_key;
        
        // استخدم القيم الجديدة للاختبار
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
        
        $test_result = $this->test_connection();
        
        // أرجع القيم الأصلية
        $this->api_url = $temp_api_url;
        $this->api_key = $temp_api_key;
        
        return $test_result;
    }
    
    public function get_api_status() {
        $status = array(
            'configured' => $this->is_configured(),
            'api_url_set' => !empty($this->api_url),
            'api_key_set' => !empty($this->api_key),
            'connection_test' => null,
            'last_test_time' => get_option('pubg_last_api_test', 0)
        );
        
        if ($status['configured']) {
            $test_result = $this->test_connection();
            $status['connection_test'] = $test_result['success'];
            $status['last_error'] = $test_result['success'] ? null : $test_result['message'];
            update_option('pubg_last_api_test', time());
        }
        
        return $status;
    }
    
    public function get_rate_limit_info() {
        return array(
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'current_usage' => $this->get_current_usage(),
            'reset_time' => $this->get_reset_time()
        );
    }
    
    private function get_current_usage() {
        $usage_key = 'pubg_api_usage_' . date('Y-m-d-H');
        return get_transient($usage_key) ?: 0;
    }
    
    private function increment_usage() {
        $usage_key = 'pubg_api_usage_' . date('Y-m-d-H');
        $current_usage = $this->get_current_usage();
        set_transient($usage_key, $current_usage + 1, HOUR_IN_SECONDS);
    }
    
    private function get_reset_time() {
        return strtotime('next hour');
    }
    
    public function is_rate_limited() {
        $current_usage = $this->get_current_usage();
        $rate_limit = $this->get_rate_limit_info();
        
        return $current_usage >= $rate_limit['requests_per_hour'];
    }
    
    public function format_api_error($error_data) {
        if (is_string($error_data)) {
            return $error_data;
        }
        
        if (is_array($error_data) && isset($error_data['message'])) {
            return $error_data['message'];
        }
        
        if (is_array($error_data) && isset($error_data['error'])) {
            return $error_data['error'];
        }
        
        return 'Unknown API error occurred';
    }
    
    public function get_supported_endpoints() {
        return array(
            'getPlayer' => array(
                'method' => 'POST',
                'description' => 'Validate player ID and get player information',
                'required_params' => array('player_id')
            ),
            'activate' => array(
                'method' => 'POST',
                'description' => 'Activate UC code for player',
                'required_params' => array('player_id', 'uc_code')
            )
        );
    }
    
    public function get_api_health() {
        $health_data = array(
            'status' => 'unknown',
            'response_time' => null,
            'last_successful_call' => get_option('pubg_last_successful_api_call', 0),
            'last_failed_call' => get_option('pubg_last_failed_api_call', 0),
            'total_calls_today' => $this->get_daily_call_count(),
            'success_rate_today' => $this->get_daily_success_rate()
        );
        
        if ($this->is_configured()) {
            $start_time = microtime(true);
            $test_result = $this->test_connection();
            $response_time = (microtime(true) - $start_time) * 1000;
            
            $health_data['status'] = $test_result['success'] ? 'healthy' : 'unhealthy';
            $health_data['response_time'] = round($response_time, 2);
            $health_data['last_error'] = $test_result['success'] ? null : $test_result['message'];
            
            if ($test_result['success']) {
                update_option('pubg_last_successful_api_call', time());
            } else {
                update_option('pubg_last_failed_api_call', time());
            }
        }
        
        return $health_data;
    }
    
    private function get_daily_call_count() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE DATE(date_created) = CURDATE()") ?: 0;
    }
    
    private function get_daily_success_rate() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE DATE(date_created) = CURDATE()") ?: 0;
        $successful = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE DATE(date_created) = CURDATE() AND status = 'success'") ?: 0;
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 0;
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        
        $deleted_api_logs = delete_option('pubg_api_usage_' . date('Y-m-d-H', strtotime('-2 hours')));
        
        $old_test_results = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pubg_api_test_%' AND option_name < 'pubg_api_test_" . date('Y-m-d', strtotime('-7 days')) . "'"
        );
        
        foreach ($old_test_results as $option) {
            delete_option($option->option_name);
        }
        
        pubg_debug_log("API logs cleanup completed", array(
            'deleted_usage_logs' => $deleted_api_logs,
            'deleted_test_results' => count($old_test_results)
        ));
    }
    
    public function export_api_settings() {
        return array(
            'api_url' => $this->api_url,
            'api_key' => $this->api_key ? 'CONFIGURED' : 'NOT_SET',
            'timeout' => $this->timeout,
            'max_retries' => $this->max_retries,
            'last_test_time' => get_option('pubg_last_api_test', 0),
            'health_status' => $this->get_api_health()
        );
    }
    
    public function import_api_settings($settings) {
        $imported = array();
        
        if (isset($settings['api_url']) && !empty($settings['api_url'])) {
            update_option('pubg_api_url', sanitize_url($settings['api_url']));
            $this->api_url = $settings['api_url'];
            $imported['api_url'] = true;
        }
        
        if (isset($settings['api_key']) && !empty($settings['api_key']) && $settings['api_key'] !== 'CONFIGURED') {
            update_option('pubg_api_key', sanitize_text_field($settings['api_key']));
            $this->api_key = $settings['api_key'];
            $imported['api_key'] = true;
        }
        
        return array(
            'success' => !empty($imported),
            'imported_settings' => $imported,
            'message' => !empty($imported) ? 'API settings imported successfully' : 'No valid API settings found to import'
        );
    }
    
    public function batch_validate_players($player_ids) {
        $results = array();
        $valid_players = array();
        $invalid_players = array();
        
        foreach ($player_ids as $player_id) {
            $result = $this->get_player_info($player_id);
            $results[$player_id] = $result;
            
            if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
                $valid_players[] = array(
                    'player_id' => $player_id,
                    'player_name' => $result['data']['player_name'] ?? 'Unknown'
                );
            } else {
                $invalid_players[] = array(
                    'player_id' => $player_id,
                    'error' => $result['data']['message'] ?? $result['message'] ?? 'Unknown error'
                );
            }
        }
        
        return array(
            'total_checked' => count($player_ids),
            'valid_count' => count($valid_players),
            'invalid_count' => count($invalid_players),
            'valid_players' => $valid_players,
            'invalid_players' => $invalid_players,
            'detailed_results' => $results
        );
    }
}
