<?php

class S3Testing_Page_Backups extends WP_List_Table
{
    private static $listtable;

    private $destinations = [];

    private $jobid = 1;

    private $dest = 'FOLDER';

    public function __construct()
    {
        parent::__construct([
            'plural' => 'backups',
            'singular' => 'backup',
            'ajax' => true,
        ]);

        $this->destinations = S3Testing::get_registered_destinations();
    }

    public function prepare_items()
    {
        $per_page = $this->get_items_per_page('s3testingbackups_per_page');
        if (empty($per_page) || $per_page < 1) {
            $per_page = 10;
        }

        $jobdest = '';
        if (isset($_GET['jobdets-button-top'])) {
            $jobdest = sanitize_text_field($_GET['jobdest-top']);
        }
        if (isset($_GET['jobdets-button-bottom'])) {
            $jobdest = sanitize_text_field($_GET['jobdest-bottom']);
        }

        if (empty($jobdest)) {
            $jobdests = $this->get_destinations_list();
            if (empty($jobdests)) {
                $jobdests = ['_'];
            }
            $jobdest = $jobdests[0];
            $_GET['jobdest-top'] = $jobdests[0];
            $_GET['jobdets-button-top'] = 'empty';
        }

        [$this->jobid, $this->dest] = explode('_', (string) $jobdest);

        if (!empty($this->destinations[$this->dest]['class'])) {
            $dest_object = S3Testing::get_destination($this->dest);
            $this->items = $dest_object->file_get_list($jobdest);
        }

        if (!$this->items) {
            $this->items = '';

            return;
        }
        // Sorting.
        $order = filter_input(INPUT_GET, 'order') ?: 'desc';
        $orderby = filter_input(INPUT_GET, 'orderby') ?: 'time';
        $tmp = [];

        if($orderby === 'time') {
            if($order === 'asc') {
                foreach($this->items as &$item) {
                    $tmp[] = $item['time'];
                }
                array_multisort($tmp, SORT_ASC, $this->items);
            } else {
                foreach($this->items as &$item) {
                    $tmp[] = $item['time'];
                }
                array_multisort($tmp, SORT_DESC, $this->items);
            }
        } elseif ($orderby === 'file') {
            if ($order === 'asc') {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['filename'];
                }
                array_multisort($tmp, SORT_ASC, $this->items);
            } else {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['filename'];
                }
                array_multisort($tmp, SORT_DESC, $this->items);
            }
        } elseif ($orderby === 'folder') {
            if ($order === 'asc') {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['folder'];
                }
                array_multisort($tmp, SORT_ASC, $this->items);
            } else {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['folder'];
                }
                array_multisort($tmp, SORT_DESC, $this->items);
            }
        } elseif ($orderby === 'size') {
            if ($order === 'asc') {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['filesize'];
                }
                array_multisort($tmp, SORT_ASC, $this->items);
            } else {
                foreach ($this->items as &$item) {
                    $tmp[] = &$item['filesize'];
                }
                array_multisort($tmp, SORT_DESC, $this->items);
            }
        }

        $this->set_pagination_args([
            'total_items' => count($this->items),
            'per_page' => $per_page,
            'jobdest' => $jobdest,
            'orderby' => $orderby,
            'order' => $order,
        ]);

        // Only display items on page.
    $start = intval(($this->get_pagenum() - 1) * $per_page);
        $end = $start + $per_page;
        if ($end > count($this->items)) {
            $end = count($this->items);
        }

        $i = -1;
        $paged_items = [];

        foreach ($this->items as $item) {
            ++$i;
            if ($i < $start) {
                continue;
            }
            if ($i >= $end) {
                break;
            }
            $paged_items[] = $item;
        }

        $this->items = $paged_items;
    }

    public function no_items()
    {
        _e('No files could be found. (List will be generated during next backup.)');
    }

    public function get_bulk_actions()
    {
        if (!$this->has_items()) {
            return [];
        }
        $actions = [];
        $actions['delete'] = __('Delete');

        return $actions;
    }

    public function extra_tablenav($which)
    {
        $destinations_list = $this->get_destinations_list();

        if (count($destinations_list) < 1) {
            return;
        }

        if (count($destinations_list) === 1) {
            echo '<input type="hidden" name="jobdest-' . $which . '" value="' . $destinations_list[0] . '">';

            return;
        } ?>
        <div class="alignleft actions">
            <label for="jobdest-<?php echo esc_attr($which); ?>">
                <select name="jobdest-<?php echo esc_html($which); ?>" class="postform"
                        id="jobdest-<?php echo esc_attr($which); ?>">
                    <?php
                    foreach ($destinations_list as $jobdest) {
                        [$jobid, $dest] = explode('_', (string) $jobdest);
                        echo "\t<option value=\"" . $jobdest . '" ' . selected(
                                $this->jobid . '_' . $this->dest,
                                $jobdest,
                                false
                            ) . '>' . $dest . ': ' . esc_html(S3Testing_Option::get(
                                $jobid,
                                'name'
                            )) . '</option>' . PHP_EOL;
                    } ?>
                </select>
            </label>
        </div>
        <?php
    }

    public function get_columns()
    {
        $posts_columns = [];
        $posts_columns['cb'] = '<input type="checkbox" />';
        $posts_columns['time'] = __('Time');
        $posts_columns['file'] = __('File');
        $posts_columns['folder'] = __('Folder');
        $posts_columns['size'] = __('Size');

        return $posts_columns;
    }

    public function get_sortable_columns()
    {
        return [
            'file' => ['file', false],
            'folder' => 'folder',
            'size' => 'size',
            'time' => ['time', false],
        ];
    }

    public function column_cb($item)
    {
        return '<input type="checkbox" name="backupfiles[]" value="' . esc_attr($item['file']) . '" />';
    }

    public function get_destinations_list()
    {
        $jobdests = [];
        $jobids = S3Testing_Option::get_job_ids();

        foreach ($jobids as $jobid) {
            if (S3Testing_Option::get($jobid, 'backuptype') === 'sync') {
                continue;
            }
            $dests = S3Testing_Option::get($jobid, 'destinations');

            foreach ($dests as $dest) {
                if (!$this->destinations[$dest]['class']) {
                    continue;
                }
                $dest_class = S3Testing::get_destination($dest);
                $can_do_dest = $dest_class->file_get_list($jobid . '_' . $dest);
                if (!empty($can_do_dest)) {
                    $jobdests[] = $jobid . '_' . $dest;
                }
            }
        }

        return $jobdests;
    }

    public function column_file($item)
    {
        $actions = [];
        $r = '<strong>' . esc_attr($item['filename']) . '</strong><br />';
        if (!empty($item['info'])) {
            $r .= esc_attr($item['info']) . '<br />';
        }

        $actions['delete'] = $this->delete_item_action($item);

        $r .= $this->row_actions($actions);
        return $r;
    }

    public function column_folder($item)
    {
        return esc_attr($item['folder']);
    }

    public function column_size($item)
    {
        if (!empty($item['filesize']) && $item['filesize'] != -1) {
            return size_format($item['filesize'], 2);
        }

        return __('?');
    }

    public function column_time($item)
    {
        return sprintf(
            __('%1$s at %2$s', 'backwpup'),
            date_i18n(get_option('date_format'), $item['time'], true),
            date_i18n(get_option('time_format'), $item['time'], true)
        );
    }

    public static function load()
    {
        global $current_user;

        //Create Table
        self::$listtable = new S3Testing_Page_Backups();

        switch (self::$listtable->current_action()) {
            case 'delete':
                check_admin_referer('bulk-backups');
                $jobdest = '';
                if (isset($_GET['jobdest'])) {
                    $jobdest = sanitize_text_field($_GET['jobdest']);
                }
                if (isset($_GET['jobdest-top'])) {
                    $jobdest = sanitize_text_field($_GET['jobdest-top']);
                }

                $_GET['jobdest-top'] = $jobdest;
                $_GET['jobdets-button-top'] = 'submit';

                if ($jobdest === '') {
                    return;
                }

                [$jobid, $dest] = explode('_', (string) $jobdest);
                $dest_class = S3Testing::get_destination($dest);
                $files = $dest_class->file_get_list($jobdest);

                foreach($_GET['backupfiles'] as $backupfile) {
                    foreach($files as $file) {
                        if (is_array($file) && $file['file'] == $backupfile) {
                            $dest_class->file_delete($jobdest, $backupfile);
                        }
                    }
                }
                $files = $dest_class->file_get_list($jobdest);
                if (empty($files)) {
                    $_GET['jobdest-top'] = '';
                }
                break;
        }

        //Save page
        if (isset($_POST['screen-options-apply'], $_POST['wp_screen_options']['option'], $_POST['wp_screen_options']['value']) && $_POST['wp_screen_options']['option'] == 's3testingbackups_per_page') {

            check_admin_referer('screen-options-nonce', 'screenoptionnonce');

            if ($_POST['wp_screen_options']['value'] > 0 && $_POST['wp_screen_options']['value'] < 1000) {
                update_user_option(
                    $current_user->ID,
                    's3testingbackups_per_page',
                    (int) $_POST['wp_screen_options']['value']
                );

                wp_redirect(remove_query_arg(['pagenum', 'apage', 'paged'], wp_get_referer()));

                exit;
            }
        }

        add_screen_option(
                'per_page',
                [
                    'label' => __('Backups'),
                    'default' => 10,
                    'option' => 's3testingbackups_per_page',
                ],
        );

        self::$listtable->prepare_items();
    }

    public static function page()
    {
        ?>
        <div class="wrap" id="s3testing-page">
            <h1>
                <?php
                    echo esc_html(sprintf(__('%s &rsaquo; Manage Backup Archives'), S3Testing::get_plugin_data('name')));
                ?></h1>
                <?php S3Testing_Admin::display_message() ?>
                <form id="posts-filter" action="" method="get">
                    <input type="hidden" name="page" value="s3testingbackups"/>
                    <?php self::$listtable->display(); ?>
                    <div id="ajax-response"></div>
                </form>
        </div><?php
    }

    public static function admin_print_styles()
    {
        ?>
        <style type="text/css" media="screen">

            .column-size, .column-time {
                width: 10%;
            }

            @media screen and (max-width: 782px) {
                .column-size, .column-runtime, .column-size, .column-folder {
                    display: none;
                }

                .column-time {
                    width: 18%;
                }
            }
        </style>
        <?php
    }

    private function delete_item_action($item) {
        $query = sprintf(
            '?page=s3testingbackups&action=delete&jobdest-top=%1$s&paged=%2$s&backupfiles[]=%3$s',
            $this->jobid . '_' . $this->dest,
            $this->get_pagenum(),
            esc_attr($item['file'])
        );
        $url = wp_nonce_url(network_admin_url('admin.php') . $query, 'bulk-backups');

        return sprintf(
            '<a class="submitdelete" href="%1$s">%2$s</a>',
            $url,
            __('Delete')
        );
    }
}