<?php

class S3Testing_Adminbar
{
    private $admin;

    public function __construct(S3Testing_Admin $admin)
    {
        $this->admin = $admin;
    }

    public function init()
    {
        add_action('admin_bar_menu', [$this, 'admin_bar'], 100);
        add_action('wp_head', [$this->admin, 'admin_css']);
    }

    public function admin_bar()
    {
        if (!is_admin_bar_showing()) {
            return;
        }

        global $wp_admin_bar;

        $menu_title = '<span class="ab-icon"></span>';
        $menu_href = network_admin_url('admin.php?page=s3testingjobs');
        if (file_exists(S3Testing::get_plugin_data('running_file'))) {
            $menu_title = '<span class="ab-icon"></span><span class="ab-label">' . esc_html(S3Testing::get_plugin_data('name')) . ' <span id="s3testing-adminbar-running">' . esc_html__('running') . '</span></span>';
            $menu_href = network_admin_url('admin.php?page=s3testingjobs');
        }

        $wp_admin_bar->add_menu([
            'id' => 's3testing',
            'title' => $menu_title,
            'href' => $menu_href,
            'meta' => ['title' => S3Testing::get_plugin_data('name')],
        ]);

        if (file_exists(S3Testing::get_plugin_data('running_file'))) {
            $wp_admin_bar->add_menu([
                'id' => 's3testing_working',
                'parent' => 's3testing_jobs',
                'title' => esc_html__('Now Running'),
                'href' => network_admin_url('admin.php?page=s3testingjobs'),
            ]);
            $wp_admin_bar->add_menu([
                'id' => 's3testing_working_abort',
                'parent' => 's3testing_working',
                'title' => __('Abort!'),
                'href' => wp_nonce_url(network_admin_url('admin.php?page=s3testing&action=abort'), 'abort-job'),
            ]);
        }

        $wp_admin_bar->add_menu([
            'id' => 's3testing_jobs',
            'parent' => 's3testing',
            'title' => __('Jobs'),
            'href' => network_admin_url('admin.php?page=s3testingjobs'),
        ]);

        $wp_admin_bar->add_menu([
            'id' => 's3testing_jobs_new',
            'parent' => 's3testing_jobs',
            'title' => __('Add new'),
            'href' => network_admin_url('admin.php?page=s3testingeditjob&tab=job'),
        ]);

        $wp_admin_bar->add_menu([
            'id' => 's3testing_backups',
            'parent' => 's3testing',
            'title' => __('Backups'),
            'href' => network_admin_url('admin.php?page=s3testingbackups'),
        ]);

        //add jobs
        $jobs = (array)S3Testing_Option::get_job_ids();

        foreach ($jobs as $jobid) {
            $name = S3Testing_Option::get($jobid, 'name');
            $wp_admin_bar->add_menu([
                'id' => 's3testing_jobs_' . $jobid,
                'parent' => 's3testing_jobs',
                'title' => $name,
                'href' => wp_nonce_url(network_admin_url('admin.php?page=s3testingeditjob&tab=job&jobid=' . $jobid), 'edit-job'),
            ]);

            $url = S3Testing_Job::get_jobrun_url('runnowlink', $jobid);
            $wp_admin_bar->add_menu([
                'id' => 's3testing_jobs_runnow_' . $jobid,
                'parent' => 's3testing_jobs_' . $jobid,
                'title' => __('Run Now'),
                'href' => esc_url($url['url']),
            ]);
        }
    }
}