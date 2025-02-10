<?php
final class S3Testing_Admin
{
    public $page_hooks = [];

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
        $this->page_hooks['s3testingjob'] = add_submenu_page(
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

        add_action('load-' . $this->page_hooks['s3testingeditjob'], [\S3Testing_Page_EditJob::class, 'auth']);

        return $page_hooks;
    }

    public function init()
    {
        add_filter('s3testing_admin_pages', [$this, 'admin_page_jobs']);
        add_filter('s3testing_admin_pages', [$this, 'admin_page_editjob']);

        if(is_multisite()) {
            add_action('network_admin_menu', [$this, 'admin_menu']);
        } else {
            add_action('admin_menu', [$this, 'admin_menu']);
        }

        add_action('admin_init', [$this, 'admin_init']);
    }

    public function admin_init()
    {
        add_action('wp_ajax_s3testing_dest_s3', [new S3Testing_Destination_S3(), 'edit_ajax'], 10, 0);
    }
}