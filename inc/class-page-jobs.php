<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class S3Testing_Page_Jobs extends WP_List_Table
{
    private static $listtable;
    private $job_object;
    private $job_types;
    private $destinations;

    public function __construct()
    {
        parent::__construct([
            'plural' => 'jobs',
            'singular' => 'job',
            'ajax' => true,
        ]);
    }
    public function prepare_items()
    {
        $this->items = S3Testing_Option::get_job_ids();
        $this->job_types = S3Testing::get_job_types();
        $this->destinations = S3Testing::get_registered_destinations();

        if (!isset($_GET['order']) || !isset($_GET['orderby'])) {
            return;
        }

        if (strtolower((string) $_GET['order']) === 'asc') {
            $order = SORT_ASC;
        } else {
            $order = SORT_DESC;
        }

        if (empty($_GET['orderby']) || !in_array(strtolower((string) $_GET['orderby']), ['jobname', 'type', 'dest', 'next', 'last'], true)) {
            $orderby = 'jobname';
        } else {
            $orderby = strtolower((string) $_GET['orderby']);
        }

        //sorting
        $job_configs = [];
        $i = 0;

        foreach ($this->items as $item) {
            $job_configs[$i]['jobid'] = $item;
            $job_configs[$i]['jobname'] = S3Testing_Option::get($item, 'name');
            $job_configs[$i]['type'] = S3Testing_Option::get($item, 'type');
            $job_configs[$i]['dest'] = S3Testing_Option::get($item, 'destinations');
            if ($order === SORT_ASC) {
                sort($job_configs[$i]['type']);
                sort($job_configs[$i]['dest']);
            } else {
                rsort($job_configs[$i]['type']);
                rsort($job_configs[$i]['dest']);
            }
            $job_configs[$i]['type'] = array_shift($job_configs[$i]['type']);
            $job_configs[$i]['dest'] = array_shift($job_configs[$i]['dest']);
            ++$i;
        }
        $tmp = [];

        foreach ($job_configs as &$ma) {
            $tmp[] = &$ma[$orderby];
        }
        array_multisort($tmp, $order, $job_configs);

        $this->items = [];

        foreach ($job_configs as $item) {
            $this->items[] = $item['jobid'];
        }

    }

    public function no_items()
    {
        _e('No Jobs.');
    }

    public function get_columns()
    {
        $jobs_columns = [];
        $jobs_columns['cb'] = '<input type="checkbox" />';
        $jobs_columns['jobname'] = __('Job Name');
        $jobs_columns['type'] = __('Type');
        $jobs_columns['dest'] = __('Destinations');

        return $jobs_columns;
    }

    public function get_sortable_columns()
    {
        return [
            'jobname' => 'jobname',
            'type' => 'type',
            'dest' => 'dest',
        ];
    }

    public function column_cb($item)
    {
        return '<input type="checkbox" name="jobs[]" value="' . esc_attr($item) . '" />';
    }

    public function column_jobname($item)
    {
        $job_normal_hide = '';

        $r = '<strong title="' . sprintf(__('Job ID: %d'), $item) . '">' . esc_html(S3Testing_Option::get($item, 'name')) . '</strong>';
        $actions = [];
        //edit
        $actions['edit'] = '<a href="' . wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingeditjob&jobid=' . $item, 'edit-job') . '">' . esc_html__('Edit') . '</a>';
        $actions['copy'] = '<a href="' . wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingjobs&action=copy&jobid=' . $item, 'copy-job_' . $item) . '">' . esc_html__('Copy') . '</a>';
        $actions['delete'] = '<a class="submitdelete" href="' . wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingjobs&action=delete&jobs[]=' . $item, 'bulk-jobs') . '" onclick="return showNotice.warn();">' . esc_html__('Delete') . '</a>';

//        if (current_user_can('s3testing_jobs_start')) {
//            $url = s3testing_Job::get_jobrun_url('runnowlink', $item);
//            $actions['runnow'] = '<a href="' . esc_attr($url['url']) . '">' . esc_html__('Run now', 's3testing') . '</a>';
//        }
//        if (current_user_can('s3testing_logs') && S3Testing_Option::get($item, 'logfile')) {
//            $logfile = basename((string) S3Testing_Option::get($item, 'logfile'));
//            if (is_object($this->job_object) && $this->job_object->job['jobid'] == $item) {
//                $logfile = basename((string) $this->job_object->logfile);
//            }
//            $log_name = str_replace(['.html', '.gz'], '', basename($logfile));
//            $actions['lastlog'] = '<a href="' . admin_url('admin-ajax.php') . '?&action=s3testing_view_log&log=' . $log_name . '&_ajax_nonce=' . wp_create_nonce('view-log_' . $log_name) . '&amp;TB_iframe=true&amp;width=640&amp;height=440\" title="' . esc_attr($logfile) . '" class="thickbox">' . __('Last log', 's3testing') . '</a>';
//        }
        $actions = apply_filters('s3testing_page_jobs_actions', $actions, $item, false);
        $r .= '<div class="job-normal"' . $job_normal_hide . '>' . $this->row_actions($actions) . '</div>';
        if (is_object($this->job_object)) {
            $actionsrun = [];
            $actionsrun = apply_filters('s3testing_page_jobs_actions', $actionsrun, $item, true);
            $r .= '<div class="job-run">' . $this->row_actions($actionsrun) . '</div>';
        }

        return $r;
    }

    public function column_type($item)
    {
        $r = '';
        if ($types = S3Testing_Option::get($item, 'type')) {
            foreach ($types as $type) {
                if (isset($this->job_types[$type])) {
                    $r .= $this->job_types[$type]->info['name'] . '<br />';
                } else {
                    $r .= $type . '<br />';
                }
            }
        }

        return $r;
    }

    public function column_dest($item)
    {
        $r = '';
        $backup_to = false;

        foreach (S3Testing_Option::get($item, 'type') as $typeid) {
            if (isset($this->job_types[$typeid]) && $this->job_types[$typeid]->creates_file()) {
                $backup_to = true;
                break;
            }
        }
        if ($backup_to) {
            foreach (S3Testing_Option::get($item, 'destinations') as $destid) {
                if (isset($this->destinations[$destid]['info']['name'])) {
                    $r .= $this->destinations[$destid]['info']['name'] . '<br />';
                } else {
                    $r .= $destid . '<br />';
                }
            }
        } else {
            $r .= '<i>' . __('Not needed or set') . '</i><br />';
        }

        return $r;
    }

    public static function load()
    {
        self::$listtable = new self();

        switch (self::$listtable->current_action()) {
            case 'delete': //Delete Job
                if (is_array($_GET['jobs'])) {
                    check_admin_referer('bulk-jobs');

                    foreach ($_GET['jobs'] as $jobid) {
                        wp_clear_scheduled_hook('backwpup_cron', ['arg' => absint($jobid)]);
                        S3Testing_Option::delete_job(absint($jobid));
                    }
                }
                break;
        }

        do_action('s3testing_page_jobs_load', self::$listtable->current_action());


        self::$listtable->prepare_items();
    }

    public static function page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(sprintf(__('%s &rsaquo; Jobs'), S3Testing::get_plugin_data('name'))). '&nbsp;<a href="' . wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingeditjob', 'edit-job') . '" class="add-new-h2">' . esc_html__('Add new') . '</a></h1>';
        S3Testing_Admin::display_message();

        //display jobs table?>
        <form id="posts-filter" action="" method="get">
            <input type="hidden" name="page" value="s3testingjobs" />
            <?php
        echo wp_nonce_field('s3testing_ajax_nonce', 's3testingajaxnonce', false);
        self::$listtable->display();
?>
            <div id="ajax-response"></div>
        </form><?php

    }
}