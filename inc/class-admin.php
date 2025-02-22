<?php
final class S3Testing_Admin
{
    public $page_hooks = [];

    public function admin_css()
    {
        $pluginDir = untrailingslashit(S3Testing::get_plugin_data('plugindir'));
        $filepath = "{$pluginDir}/assets/css/main.min.css";

        wp_enqueue_style(
            's3testing',
            S3Testing::get_plugin_data('URL') . "/assets/css/main.min.css",
            [],
            filemtime($filepath),
            'screen'
        );
    }

    public static function init_general()
    {
        add_thickbox();

        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

        wp_register_script(
            's3testinggeneral',
            S3Testing::get_plugin_data('URL') . "/assets/js/general{$suffix}.js",
            ['jquery'],
            ($suffix ? S3Testing::get_plugin_data('Version') : time()),
            false
        );
    }

    public function admin_menu()
    {
        add_menu_page(
            S3Testing::get_plugin_data('name'),
            S3Testing::get_plugin_data('name'),
            'manage_options',
            's3testing',
            [
                \S3Testing_Page_S3Testing::class,
                'page'
            ],
            'dashicons-cloud'
        );
        $this->page_hooks['s3testing'] = add_submenu_page(
            's3testing',
            'S3 Testing',
            'Dashboard',
            'manage_options',
            's3testing',
            [
                \S3Testing_Page_S3Testing::class,
                'page'
            ]
        );

        $this->page_hooks = apply_filters('s3testing_admin_pages', $this->page_hooks);
    }

    public function admin_page_jobs($page_hooks)
    {
        $this->page_hooks['s3testingjobs'] = add_submenu_page(
            's3testing',
            'Jobs',
            'Jobs',
            'manage_options',
            's3testingjobs',
            [
                \S3Testing_Page_Jobs::class,
                'page'
            ]
        );

        add_action('load-' . $this->page_hooks['s3testingjobs'], [\S3Testing_Page_Jobs::class, 'load']);
        add_action(
            'admin_print_styles-' . $this->page_hooks['s3testingjobs'],
            [
                \S3Testing_Page_Jobs::class,
                'admin_print_styles',
            ]
        );

        return $page_hooks;
    }

    public function admin_page_editjob($page_hooks)
    {
        $this->page_hooks['s3testingeditjob'] = add_submenu_page(
            's3testing',
            'Add new Job',
            'Add new Job',
            'manage_options',
            's3testingeditjob',
            [
                \S3Testing_Page_EditJob::class,
                'page'
            ]
        );

        add_action('load-' . $this->page_hooks['s3testingeditjob'], [\S3Testing_Admin::class, 'init_general']);
        add_action('load-' . $this->page_hooks['s3testingeditjob'], [\S3Testing_Page_EditJob::class, 'auth']);

        add_action(
            'admin_print_scripts-' .$this->page_hooks['s3testingeditjob'],
            [
                \S3Testing_Page_EditJob::class,
                'admin_print_scripts',
            ]
        );

        return $page_hooks;
    }

    public function admin_page_backups($page_hooks)
    {
        $this->page_hooks['s3testingbackups'] = add_submenu_page(
            's3testing',
            'Backups',
            'Backups',
            'manage_options',
            's3testingbackups',
            [
                \S3Testing_Page_Backups::class,
                'page',
            ]
        );
        add_action('load-' . $this->page_hooks['s3testingbackups'], [\S3Testing_Admin::class, 'init_general']);
        add_action('load-' . $this->page_hooks['s3testingbackups'], [\S3Testing_Page_Backups::class, 'load']);
        add_action(
            'admin_print_styles-' . $this->page_hooks['s3testingbackups'],
            [
                \S3Testing_Page_Backups::class,
                'admin_print_styles',
            ]
        );
        return $page_hooks;
    }

    public function init()
    {
        //add menu pages
        add_filter('s3testing_admin_pages', [$this, 'admin_page_jobs'], 2);
        add_filter('s3testing_admin_pages', [$this, 'admin_page_editjob'], 3);
        add_filter('s3testing_admin_pages', [$this, 'admin_page_backups'], 4);

        if(is_multisite()) {
            add_action('network_admin_menu', [$this, 'admin_menu']);
        } else {
            add_action('admin_menu', [$this, 'admin_menu']);
        }

        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_post_s3testing', [$this, 'save_post_form']);

        //add more actions
        add_action('admin_enqueue_scripts', [$this, 'admin_css']);

        //cron
        add_action('s3testing_cron_interval', '10');
    }

    public function admin_init()
    {
        add_action('wp_ajax_s3testing_working', [\S3Testing_Page_Jobs::class, 'ajax_working']);
        add_action('wp_ajax_s3testing_dest_s3', [new S3Testing_Destination_S3(), 'edit_ajax'], 10, 0);
        add_action('wp_ajax_s3testing_dest_s3_dir', [new S3Testing_Destination_S3(), 'edit_ajax_dir'], 10, 0);
    }

    public function save_post_form()
    {
        $allow_pages = [
            's3testingeditjob',
        ];

        check_admin_referer($_POST['page'] . '_page');

        //build query for redirect
        if (!isset($_POST['anchor'])) {
            $_POST['anchor'] = null;
        }
        $query_args = [];
        if (isset($_POST['page'])) {
            $query_args['page'] = $_POST['page'];
        }
        if (isset($_POST['tab'])) {
            $query_args['tab'] = $_POST['tab'];
        }
        if (isset($_POST['tab'], $_POST['nexttab']) && $_POST['tab'] !== $_POST['nexttab']) {
            $query_args['tab'] = $_POST['nexttab'];
        }

        $jobid = null;
        if (isset($_POST['jobid'])) {
            $jobid = (int) $_POST['jobid'];
            $query_args['jobid'] = $jobid;
        }

        if ($_POST['page'] === 's3testingeditjob') {
            S3Testing_Page_EditJob::save_post_form($_POST['tab'], $jobid);
        }

        wp_safe_redirect(add_query_arg($query_args, network_admin_url('admin.php')) . $_POST['anchor']);
        exit;
    }

    public static function display_message($echo = true)
    {
//        do_action('s3testing_admin_message');

        $message_updated = '';
        $message_error = '';
        $saved_message = self::get_messages();
        $message_id = ' id="message"';

        if (empty($saved_message)) {
            return '';
        }

        if (!empty($saved_message['updated'])) {
            foreach ($saved_message['updated'] as $msg) {
                $message_updated .= '<p>' . $msg . '</p>';
            }
        }
        if (!empty($saved_message['error'])) {
            foreach ($saved_message['error'] as $msg) {
                $message_error .= '<p>' . $msg . '</p>';
            }
        }

        update_site_option('s3testing_messages', []);

        if (!empty($message_updated)) {
            $message_updated = '<div' . $message_id . ' class="updated">' . $message_updated . '</div>';
            $message_id = '';
        }
        if (!empty($message_error)) {
            $message_error = '<div' . $message_id . ' class="bwu-message-error">' . $message_error . '</div>';
        }

        if ($echo) {
            echo $message_updated . $message_error;
        }

        return $message_updated . $message_error;
    }

    public static function message($message, $error = false)
    {
        if(empty($message)) {
            return;
        }

        $saved_message = self::get_messages();

        if ($error) {
            $saved_message['error'][] = $message;
        } else {
            $saved_message['updated'][] = $message;
        }

        update_site_option('s3testing_messages', $saved_message);
    }

    public static function get_messages()
    {
        return get_site_option('s3testing_messages', []);
    }
}