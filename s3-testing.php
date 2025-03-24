<?php
/**
 * Plugin Name: S3 Testing
 * Description: Testing S3
 * Version: 1.0
 * Author: Me
 */
if (!class_exists(\S3Testing::class, false)) {
    final class S3Testing
    {
        private static $instance;

        private static $plugin_data = [];

        private static $destinations = [];

        private static $registered_destinations = [];

        private static $job_types;

        private function __construct()
        {
            if (!is_main_network() && !is_main_site()) {
                return;
            }

            require_once __DIR__ . '/inc/functions.php';
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            }

            if (!wp_next_scheduled('s3testing_check_cleanup')) {
                S3Testing_Install::activate();
            }

            if (defined('DOING_CRON') && DOING_CRON) {
                if (!empty($_GET['s3testing_run']) && class_exists(\S3Testing_Job::class)) {

                    S3Testing_Job::disable_caches();

                    add_action('wp_loaded', [\S3Testing_Cron::class, 'cron_active'], PHP_INT_MAX);
                } else {
                    //add cron actions
                    add_action('s3testing_cron', [\S3Testing_Cron::class, 'run']);
                    add_action('s3testing_check_cleanup', [\S3Testing_Cron::class, 'check_cleanup']);
                }

                return;
            }

            //Deactivation hook
            register_deactivation_hook(__FILE__, [\S3Testing_Install::class, 'deactivate']);

            $admin = new S3Testing_Admin();
            $admin->init();

            $is_admin_bar = (bool)apply_filters('s3testing_admin_bar', (bool)get_site_option('s3testing_cfg_showadminbar'));

            if (true === $is_admin_bar) {
                $admin_bar = new S3Testing_Adminbar($admin);
                add_action('init', [$admin_bar, 'init']);
            }

        }

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function get_plugin_data($name)
        {
            if ($name) {
                $name = strtolower(trim($name));
            }

            if (empty(self::$plugin_data)) {
                self::$plugin_data = get_file_data(
                    __FILE__,
                    [
                        'name' => 'Plugin Name',
                        'version' => 'Version',
                    ],
                    'plugin'
                );

                self::$plugin_data['name'] = trim(self::$plugin_data['name']);
                self::$plugin_data['hash'] = get_site_option('s3testing_cfg_hash');
                self::$plugin_data['plugindir'] = untrailingslashit(__DIR__);
                if (empty(self::$plugin_data['hash']) || strlen(self::$plugin_data['hash']) < 6
                    || strlen(
                        self::$plugin_data['hash']
                    ) > 12) {
                    self::$plugin_data['hash'] = self::get_generated_hash(6);
                    update_site_option('s3testing_cfg_hash', self::$plugin_data['hash']);
                }
                if (defined('WP_TEMP_DIR') && is_dir(WP_TEMP_DIR)) {
                    self::$plugin_data['temp'] = str_replace(
                            '\\',
                            '/',
                            get_temp_dir()
                        ) . 's3testing/' . self::$plugin_data['hash'] . '/';
                } else {
                    $upload_dir = wp_upload_dir();
                    self::$plugin_data['temp'] = str_replace(
                            '\\',
                            '/',
                            $upload_dir['basedir']
                        ) . '/s3testing/' . self::$plugin_data['hash'] . '/temp/';
                }
                self::$plugin_data['running_file'] = self::$plugin_data['temp'] . 's3testing-working.php';
                self::$plugin_data['url'] = plugins_url('', __FILE__);

                include ABSPATH . WPINC . '/version.php';
                self::$plugin_data['wp_version'] = $wp_version;
            }

            if (!empty($name)) {
                return self::$plugin_data[$name];
            }

            return self::$plugin_data;
        }

        public static function get_generated_hash($length = 6)
        {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

            $hash = '';

            for ($i = 0; $i < 254; ++$i) {
                $hash .= $chars[random_int(0, 61)];
            }

            return substr(md5($hash), random_int(0, 31 - $length), $length);
        }

        public static function get_destination($key)
        {
            $key = strtoupper($key);

            if (isset(self::$destinations[$key]) && is_object(self::$destinations[$key])) {
                return self::$destinations[$key];
            }

            $reg_dests = self::get_registered_destinations();
            if (!empty($reg_dests[$key]['class'])) {
                self::$destinations[$key] = new $reg_dests[$key]['class']();
            } else {
                return null;
            }

            return self::$destinations[$key];
        }

        public static function get_registered_destinations()
        {
            if (!empty(self::$registered_destinations)) {
                return self::$registered_destinations;
            }

            self::$registered_destinations['S3'] = [
                'class' => \S3Testing_Destination_S3::class,
                'info' => [
                    'ID' => 'S3',
                    'name' => 'S3 Service',
                    'description' => 'Backup to an S3 Service'
                ],
                'can_sync' => false,
            ];
            return self::$registered_destinations;
        }

        public static function get_job_types()
        {
            if (!empty(self::$job_types)) {
                return self::$job_types;
            }

            self::$job_types['FILE'] = new S3Testing_JobType_File();
            self::$job_types['DBDUMP'] = new S3Testing_JobType_DBDump();

            self::$job_types = apply_filters('s3testing_job_types', self::$job_types);

            foreach (self::$job_types as $key => $job_type) {
                if (empty($job_type) || !is_object($job_type)) {
                    unset(self::$job_types[$key]);
                }
            }

            return self::$job_types;
        }
    }

    add_action('plugins_loaded', [\S3Testing::class, 'get_instance']);
}