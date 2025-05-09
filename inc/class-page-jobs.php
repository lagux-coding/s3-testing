<?php
if (!class_exists('WP_List_Table')) {
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

        if (strtolower((string)$_GET['order']) === 'asc') {
            $order = SORT_ASC;
        } else {
            $order = SORT_DESC;
        }

        if (empty($_GET['orderby']) || !in_array(strtolower((string)$_GET['orderby']), ['jobname', 'type', 'dest', 'next', 'last'], true)) {
            $orderby = 'jobname';
        } else {
            $orderby = strtolower((string)$_GET['orderby']);
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

    public function get_bulk_actions()
    {
        if (!$this->has_items()) {
            return [];
        }

        $actions = [];
        $actions['delete'] = __('Delete');

        return apply_filters('s3testing_page_jobs_get_bulk_actions', $actions);
    }

    public function get_columns()
    {
        $jobs_columns = [];
        $jobs_columns['cb'] = '<input type="checkbox" />';
        $jobs_columns['jobname'] = __('Job Name');
        $jobs_columns['type'] = __('Type');
        $jobs_columns['dest'] = __('Destinations');
        $jobs_columns['next'] = __('Next Run');
        $jobs_columns['last'] = __('Last Run');
        return $jobs_columns;
    }

    public function get_sortable_columns()
    {
        return [
            'jobname' => 'jobname',
            'type' => 'type',
            'dest' => 'dest',
            'next' => 'next',
            'last' => 'last',
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

        $url = S3Testing_Job::get_jobrun_url('runnowlink', $item);
        $actions['runnow'] = '<a href="' . esc_attr($url['url']) . '">' . esc_html__('Run now') . '</a>';


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

    public function column_next($item)
    {
        $r = '';
        $job_normal_hide = '';
        if (is_object($this->job_object)) {
            $job_normal_hide = ' style="display:none;"';
        }
        if (is_object($this->job_object) && $this->job_object->job['jobid'] == $item) {
            $runtime = current_time('timestamp') - $this->job_object->start_time;
            $r .= '<div class="job-run">' . sprintf(esc_html__('Running for: %s seconds'), '<span id="runtime">' . $runtime . '</span>') . '</div>';
        }
        if (is_object($this->job_object) && $this->job_object->job['jobid'] == $item) {
            $r .= '<div class="job-normal"' . $job_normal_hide . '>';
        }
        if (S3Testing_Option::get($item, 'activetype') == 'wpcron') {
            if ($nextrun = wp_next_scheduled('s3testing_cron', ['arg' => $item]) + (get_option('gmt_offset') * 3600)) {
                $r .= '<span title="' . sprintf(esc_html__('Cron: %s'), S3Testing_Option::get($item, 'cron')) . '">' . sprintf(__('%1$s at %2$s by WP-Cron'), date_i18n(get_option('date_format'), $nextrun, true), date_i18n(get_option('time_format'), $nextrun, true)) . '</span><br />';
            } else {
                $r .= __('Not scheduled!') . '<br />';
            }
        } else {
            $r .= __('Inactive');
        }
        if (is_object($this->job_object) && $this->job_object->job['jobid'] == $item) {
            $r .= '</div>';
        }

        return $r;
    }

    public function column_last($item)
    {
        $r = '';

        if (S3Testing_Option::get($item, 'lastrun')) {
            $lastrun = S3Testing_Option::get($item, 'lastrun');
            $r .= sprintf(__('%1$s at %2$s'), date_i18n(get_option('date_format'), $lastrun, true), date_i18n(get_option('time_format'), $lastrun, true));
            if (S3Testing_Option::get($item, 'lastruntime')) {
                $r .= '<br />' . sprintf(__('Runtime: %d seconds'), S3Testing_Option::get($item, 'lastruntime'));
            }
        } else {
            $r .= __('not yet');
        }
        $r .= '<br />';
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
                        wp_clear_scheduled_hook('s3testing_cron', ['arg' => absint($jobid)]);
                        S3Testing_Option::delete_job(absint($jobid));
                    }
                }
                break;
            case 'runnow':
                $jobid = absint($_GET['jobid']);
                if ($jobid) {

                    //check temp folder
                    $temp_folder_message = S3Testing_File::check_folder(S3Testing::get_plugin_data('TEMP'), true);
                    S3Testing_Admin::message($temp_folder_message, true);

                    //check backup destinations
                    $job_types = S3Testing::get_job_types();
                    $job_conf_types = S3Testing_Option::get($jobid, 'type');
                    $creates_file = false;

                    foreach ($job_types as $id => $job_type_class) {
                        if (in_array($id, $job_conf_types, true) && $job_type_class->creates_file()) {
                            $creates_file = true;
                            break;
                        }
                    }

                    if ($creates_file) {
                        $job_conf_dests = S3Testing_Option::get($jobid, 'destinations');
                        $destinations = 0;

                        foreach (S3Testing::get_registered_destinations() as $id => $dest) {
                            if (!in_array($id, $job_conf_dests, true) || empty($dest['class'])) {
                                continue;
                            }

                            $dest_class = S3Testing::get_destination($id);
                            $job_settings = S3Testing_Option::get_job($jobid);
                            if (!$dest_class->can_run($job_settings)) {
                                S3Testing_Admin::message(sprintf(__('The job "%1$s" destination "%2$s" is not configured properly'), esc_attr(S3Testing_Option::get($jobid, 'name')), $id), true);
                            }
                            ++$destinations;

                            if ($destinations < 1) {
                                S3Testing_Admin::message(sprintf(__('The job "%s" needs properly configured destinations to run!'), esc_attr(S3Testing_Option::get($jobid, 'name'))), true);
                            }
                        }

                        if ($destinations < 1) {
                            S3Testing_Admin::message(sprintf(__('The job "%s" needs properly configured destinations to run!'), esc_attr(S3Testing_Option::get($jobid, 'name'))), true);
                        }

                        $log_messages = S3Testing_Admin::get_messages();
                        if (empty($log_messages)) {
                            S3Testing_Job::get_jobrun_url('runnow', $jobid);
                            S3Testing_Admin::message(sprintf(__('Job "%s" started.'), esc_attr(S3Testing_Option::get($jobid, 'name'))));
                        }
                    }
                }
                break;
            case 'abort':
                check_admin_referer('abort-job');
                if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
                    break;
                }
                S3Testing_Job::user_abort();
                S3Testing_Admin::message(__('Job will be terminated.'));
                break;
            default:
                do_action('s3testing_page_jobs_load', self::$listtable->current_action());
                break;
        }

        self::$listtable->prepare_items();
    }

    public static function admin_print_styles()
    {
        ?>
        <style type="text/css" media="screen">

            .column-last, .column-next, .column-type, .column-dest {
                width: 15%;
            }

            #TB_ajaxContent {
                background-color: black;
                color: #c0c0c0;
            }

            #runningjob {
                padding: 10px;
                position: relative;
                margin: 15px 0 25px 0;
                padding-bottom: 25px;
            }

            .progressbar {
                margin-top: 20px;
                background: #f6f6f6 url('<?php echo S3Testing::get_plugin_data('URL'); ?>/assets/images/progressbarhg.jpg');
            }

            .s3tt-progress {
                background-color: #1d94cf;
                color: #fff;
                padding: 5px 0;
                text-align: center;
            }

            #onstep {
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
        <?php
    }

    public static function page()
    {
        echo '<div class="wrap" id="s3testing-page">';
        echo '<h1>' . esc_html(sprintf(__('%s &rsaquo; Jobs'), S3Testing::get_plugin_data('name'))) . '&nbsp;<a href="' . wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingeditjob', 'edit-job') . '" class="add-new-h2">' . esc_html__('Add new') . '</a></h1>';
        S3Testing_Admin::display_message();
        $job_object = S3Testing_Job::get_working_data();

        if (is_object($job_object)) {
            //read existing logfile
            $logfiledata = file_get_contents($job_object->logfile);
            preg_match('/<body[^>]*>/si', $logfiledata, $match);
            if (!empty($match[0])) {
                $startpos = strpos($logfiledata, $match[0]) + strlen($match[0]);
            } else {
                $startpos = 0;
            }
            $endpos = stripos($logfiledata, '</body>');
            if (empty($endpos)) {
                $endpos = strlen($logfiledata);
            }
            $length = strlen($logfiledata) - (strlen($logfiledata) - $endpos) - $startpos;
            ?>
            <div id="runningjob">
                <div id="runniginfos">
                    <h2 id="runningtitle"><?php esc_html(sprintf(__('Job currently running: %s'), $job_object->job['name'])); ?></h2>
                </div>
                <div class="infobuttons">
                    <a href="#TB_inline?height=440&width=630&inlineId=tb-showworking" id="showworkingbutton"
                       class="thickbox button button-primary button-primary-bwp"
                       title="<?php esc_attr_e('Log of running job'); ?>"><?php esc_html_e('Display working log'); ?></a>
                    <a href="<?php echo wp_nonce_url(network_admin_url('admin.php') . '?page=s3testingjobs&action=abort', 'abort-job'); ?>"
                       id="abortbutton" class="s3testing-fancybox button button-s3"><?php esc_html_e('Abort'); ?></a>
                    <a href="#" id="showworkingclose" title="<?php esc_html_e('Close working screen'); ?>"
                       class="button button-bwp" style="display:none"><?php esc_html_e('Close'); ?></a>

                    <input type="hidden" name="logpos" id="logpos" value="<?php echo strlen($logfiledata); ?>">
                    <div id="lasterrormsg"></div>
                    <div class="progressbar">
                        <div id="progressstep" class="s3tt-progress"
                             style="width:<?php echo($job_object->step_percent); ?>%;"><?php echo esc_html($job_object->step_percent); ?>
                            %
                        </div>
                    </div>
                    <div id="onstep"><?php echo esc_html($job_object->steps_data[$job_object->step_working]['NAME']); ?></div>
                    <div id="tb-showworking" style="display:none;">
                        <div id="showworking"><?php echo substr($logfiledata, $startpos, $length); ?></div>
                    </div>
                </div>
            </div>
            <?php
        }

        //display jobs table
        ?>
        <form id="posts-filter" action="" method="get">
            <input type="hidden" name="page" value="s3testingjobs"/>
            <?php
            echo wp_nonce_field('s3testing_ajax_nonce', 's3testingajaxnonce', false);
            self::$listtable->display();
            ?>
            <div id="ajax-response"></div>
        </form>

        <?php
        if (!empty($job_object->logfile)) { ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    s3testing_show_working = function () {
                        $.ajax({
                            type: 'GET',
                            url: ajaxurl,
                            cache: false,
                            data: {
                                action: 's3testing_working',
                                logpos: $('#logpos').val(),
                                logfile: '<?php echo basename((string)$job_object->logfile); ?>',
                                _ajax_nonce: '<?php echo wp_create_nonce('s3testingworking_ajax_nonce'); ?>'
                            },
                            dataType: 'json',
                            success: function (rundata) {
                                if (rundata == 0) {
                                    $("#abortbutton").remove();
                                    $("#s3testing-adminbar-running").remove();
                                    $(".job-run").hide();
                                    $("#message").hide();
                                    $(".job-normal").show();
                                    $('#showworkingclose').show();
                                }
                                if (0 < rundata.log_pos) {
                                    $('#logpos').val(rundata.log_pos);
                                }
                                if ('' != rundata.log_text) {
                                    $('#showworking').append(rundata.log_text);
                                    $('#TB_ajaxContent').scrollTop(rundata.log_pos * 15);
                                }
                                if (0 < rundata.step_percent) {
                                    $('#progressstep').replaceWith('<div id="progressstep" class="s3tt-progress">' + rundata.step_percent + '%</div>');
                                    $('#progressstep').css('width', parseFloat(rundata.step_percent) + '%');
                                }
                                if (0 < rundata.sub_step_percent) {
                                    $('#progresssteps').replaceWith('<div id="progresssteps" class="s3tt-progress">' + rundata.sub_step_percent + '%</div>');
                                    $('#progresssteps').css('width', parseFloat(rundata.sub_step_percent) + '%');
                                }
                                if ('' != rundata.onstep) {
                                    $('#onstep').replaceWith('<div id="onstep">' + rundata.on_step + '</div>');
                                }
                                if (rundata.job_done == 1) {
                                    $("#abortbutton").remove();
                                    $("#s3testing-adminbar-running").remove();
                                    $(".job-run").hide();
                                    $("#message").hide();
                                    $(".job-normal").show();
                                    $('#showworkingclose').show();
                                } else {
                                    setTimeout('s3testing_show_working()', 400);
                                }
                            },
                            error: function () {
                                setTimeout('s3testing_show_working()', 400);
                            }
                        });
                    };
                    s3testing_show_working();
                    $('#showworkingclose').click(function () {
                        $("#runningjob").hide('slow');
                        return false;
                    });
                });
            </script>
        <?php }
    }

    public static function ajax_working()
    {
        check_ajax_referer('s3testingworking_ajax_nonce');

        $log_folder = get_site_option('s3testing_cfg_logfolder');
        $log_folder = S3Testing_File::get_absolute_path($log_folder);

        try {
            $logfile = self::get_logfile_path($log_folder, $_GET['logfile'] ?? null);
        } catch (\InvalidArgumentException $e) {
            exit(0);
        }

        $logpos = isset($_GET['logpos']) ? absint($_GET['logpos']) : 0;

        $job_object = S3Testing_Job::get_working_data();

        $done = 0;
        if (is_object($job_object)) {
            $step_percent = $job_object->step_percent;
            $substep_percent = $job_object->substep_percent;
            $onstep = $job_object->steps_data[$job_object->step_working]['NAME'];
        } else {
            $step_percent = 100;
            $substep_percent = 100;
            $onstep = '<div class="s3testing-message s3testing-info"><p>' . esc_html__('Job completed') . '</p></div>';
            $done = 1;
        }

        $logfiledata = file_get_contents($logfile, false, null, $logpos);

        preg_match('/<body[^>]*>/si', $logfiledata, $match);
        if (!empty($match[0])) {
            $startpos = strpos($logfiledata, $match[0]) + strlen($match[0]);
        } else {
            $startpos = 0;
        }

        $endpos = stripos($logfiledata, '</body>');
        if (false === $endpos) {
            $endpos = strlen($logfiledata);
        }

        $length = strlen($logfiledata) - (strlen($logfiledata) - $endpos) - $startpos;

        wp_send_json([
            'log_pos' => strlen($logfiledata) + $logpos,
            'log_text' => substr($logfiledata, $startpos, $length),
            'step_percent' => $step_percent,
            'on_step' => $onstep,
            'sub_step_percent' => $substep_percent,
            'job_done' => $done,
        ]);
    }

    private static function get_logfile_path(string $folder, ?string $filename): string
    {
        if (!$filename) {
            throw new \InvalidArgumentException('Log file cannot be null.');
        }

        $filename = basename(trim($filename));

        if (
            preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]{1,5}$/', $filename) === 0
            || strpos($filename, 's3testing_log_') === false
        ) {
            throw new \InvalidArgumentException('Invalidly formatted log filename passed.');
        }

        return $folder . $filename;
    }
}