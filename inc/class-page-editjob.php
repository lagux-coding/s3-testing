<?php

class S3Testing_Page_EditJob
{
    public static function auth()
    {
        if (isset($_GET['tab'])) {
            $_GET['tab'] = sanitize_title_with_dashes($_GET['tab']);
            if (substr($_GET['tab'], 0, 5) != 'dest-' && substr($_GET['tab'], 0, 8) != 'jobtype-' && !in_array($_GET['tab'], ['job', 'cron'], true)) {
                $_GET['tab'] = 'job';
            }
        } else {
            $_GET['tab'] = 'job';
        }
//        if (substr($_GET['tab'], 0, 5) == 'dest-') {
//            $jobid = (int) $_GET['jobid'];
//            $id = strtoupper(str_replace('dest-', '', $_GET['tab']));
//            $dest_class = S3Testing::get_destination($id);
//            $dest_class->edit_auth($jobid);
//        }
    }

    public static function save_post_form($tab, $jobid)
    {
        $job_types = S3Testing::get_job_types();

        switch ($tab) {
            case 'job':
                S3Testing_Option::update($jobid, 'jobid', $jobid);

                $backuptype = 'archive';
                S3Testing_Option::update($jobid, 'backuptype', $backuptype);

                $type_post = isset($_POST['type']) ? (array)$_POST['type'] : [];
                foreach ($type_post as $key => $value) {
                    if (!isset($job_types[$value])) {
                        unset($type_post[$key]);
                    }
                }

                sort($type_post);
                S3Testing_Option::update($jobid, 'type', $type_post);

                $makes_file = false;

                foreach ($job_types as $type_id => $job_type) {
                    if (in_array($type_id, $type_post, true)) {
                        if ($job_type->creates_file()) {
                            $makes_file = true;
                            break;
                        }
                    }
                }
                if ($makes_file) {
                    $destinations_post = isset($_POST['destinations']) ? (array)$_POST['destinations'] : [];
                } else {
                    $destinations_post = [];
                }

                $destinations = S3Testing::get_registered_destinations();

                foreach ($destinations_post as $key => $dest_id) {
                    if (!isset($destinations[$dest_id])) {
                        unset($destinations_post[$key]);
                        continue;
                    }
                }
                sort($destinations_post);

                S3Testing_Option::update($jobid, 'destinations', $destinations_post);

                $name = sanitize_text_field(trim((string)$_POST['name']));
                if (!$name || $name === __('New Job')) {
                    $name = sprintf(__('Job with ID %d'), $jobid);
                }
                S3Testing_Option::update($jobid, 'name', $name);

                $archiveformat = in_array($_POST['archiveformat'], [
                    '.zip',
                    '.tar',
                    '.tar.gz',
                ], true) ? $_POST['archiveformat'] : '.zip';

                S3Testing_Option::update($jobid, 'archiveformat', $archiveformat);
                S3Testing_Option::update($jobid, 'archivename', sanitize_text_field($_POST['archivename']));

                break;
            case 'runnow':
                $jobid = absint($_GET['jobid']);
                if ($jobid) {

                }
            default:
                if (strstr((string)$tab, 'dest-')) {
                    $dest_class = S3Testing::get_destination(str_replace('dest-', '', (string)$tab));
                    $dest_class->edit_form_post_save($jobid);
                }
                if (strstr((string)$tab, 'jobtype-')) {
                    $id = strtoupper(str_replace('jobtype-', '', (string)$tab));
                    $job_types[$id]->edit_form_post_save($jobid);
                }
        }

        //saved message
        $message = S3Testing_Admin::get_messages();
        if (empty($message['error'])) {
            $url = S3Testing_Job::get_jobrun_url('runnowlink', $jobid);
            S3Testing_Admin::message(sprintf(__('Changes for job <i>%s</i> saved.'), S3Testing_Option::get($jobid, 'name')) . ' <a href="' . network_admin_url('admin.php') . '?page=s3testingjobs">' . __('Jobs overview') . '</a> | <a href="' . $url['url'] . '">' . __('Run now') . '</a>');
        }
    }

    public static function admin_print_scripts()
    {
        wp_enqueue_script('s3testinggeneral');

        if ($_GET['tab'] == 'job') {
            if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
                wp_enqueue_script('s3testingtabjob', S3Testing::get_plugin_data('URL') . '/assets/js/page_edit_tab_job.js', ['jquery'], time(), true);
            } else {
                wp_enqueue_script('s3testingtabjob', S3Testing::get_plugin_data('URL') . '/assets/js/page_edit_tab_job.min.js', ['jquery'], S3Testing::get_plugin_data('Version'), true);
            }
        } elseif($_GET['tab'] == 'cron') {
            if(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
                wp_enqueue_script('s3testingtabcron', S3Testing::get_plugin_data('URL') . '/assets/js/page_edit_tab_cron.js', ['jquery'], time(), true);
            } else {
                wp_enqueue_script('s3testingtabcron', S3Testing::get_plugin_data('URL') . '/assets/js/page_edit_tab_cron.min.js', ['jquery'], S3Testing::get_plugin_data('Version'), true);
            }
        }
        //add js for other tabs
        elseif (strstr((string) $_GET['tab'], 'dest-')) {
            $dest_object = S3Testing::get_destination(str_replace('dest-', '', (string) $_GET['tab']));
            $dest_object->admin_print_scripts();
        } elseif (strstr((string) $_GET['tab'], 'jobtype-')) {
            $job_type = S3Testing::get_job_types();
            $id = strtoupper(str_replace('jobtype-', '', (string) $_GET['tab']));
            $job_type[$id]->admin_print_scripts();
        }
    }

    public static function page()
    {
        if (!empty($_GET['jobid'])) {
            $jobid = (int)$_GET['jobid'];
        } else {
            //generate jobid if not exists
            $jobid = S3Testing_Option::next_job_id();
        }

        $destinations = S3Testing::get_registered_destinations();
        $job_types = S3Testing::get_job_types();
        $archive_format_option = S3Testing_Option::get($jobid, 'archiveformat'); ?>
        <div class="wrap" id="s3testing-page">
        <?php
        echo '<h1>' . sprintf(esc_html__('%1$s &rsaquo; Job: %2$s'), S3Testing::get_plugin_data('name'), '<span id="h2jobtitle">' . esc_html(S3Testing_Option::get($jobid, 'name')) . '</span>') . '</h1>';

        $tabs = [
            'job' => [
                'name' => esc_html__('General'),
                'display' => true,
            ],
            'cron' => [
                'name' => esc_html__('Schedule'),
                'display' => true,
            ],
        ];

        $job_job_types = S3Testing_Option::get($jobid, 'type');

        foreach ($job_types as $typeid => $typeclass) {
            $tabid = 'jobtype-' . strtolower($typeid);
            $tabs[$tabid]['name'] = $typeclass->info['name'];
            $tabs[$tabid]['display'] = true;
            if (!in_array($typeid, $job_job_types, true)) {
                $tabs[$tabid]['display'] = false;
            }
        }
        $jobdests = S3Testing_Option::get($jobid, 'destinations');

        foreach ($destinations as $destid => $dest) {
            $tabid = 'dest-' . strtolower($destid);
            $tabs[$tabid]['name'] = sprintf('To: %s', $dest['info']['name']);
            $tabs[$tabid]['display'] = true;
            if (!in_array($destid, $jobdests, true)) {
                $tabs[$tabid]['display'] = false;
            }
        }

        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $id => $tab) {
            $addclass = '';
            if ($id === $_GET['tab']) {
                $addclass = ' nav-tab-active';
            }
            $display = '';
            if (!$tab['display']) {
                $display = ' style="display:none;"';
            }
            echo '<a href="' . wp_nonce_url(network_admin_url('admin.php?page=s3testingeditjob&tab=' . $id . '&jobid=' . $jobid), 'edit-job') . '" class="nav-tab' . $addclass . '" id="tab-' . esc_attr($id) . '" data-nexttab="' . esc_attr($id) . '"' . $display . '>' . esc_html($tab['name']) . '</a>';


        }
        echo '</h2>';
        S3Testing_Admin::display_message();
        echo '<form name="editjob" id="editjob" method="post" action="' . esc_attr(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" id="jobid" name="jobid" value="' . esc_attr($jobid) . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($_GET['tab']) . '" />';
        echo '<input type="hidden" name="nexttab" value="' . esc_attr($_GET['tab']) . '" />';
        echo '<input type="hidden" name="page" value="s3testingeditjob" />';
        echo '<input type="hidden" name="action" value="s3testing" />';
        echo '<input type="hidden" name="anchor" value="" />';
        wp_nonce_field('s3testingeditjob_page');
        wp_nonce_field('s3testing_ajax_nonce', 's3testingajaxnonce', false);

    switch ($_GET['tab']) {
        case 'job':
            ?>
            <div class="table" id="info-tab-job">
            <h3>Job Name</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name"><?php esc_html_e('Please name this job.'); ?></label></th>
                    <td>
                        <input name="name" type="text" id="name" placeholder="<?php esc_attr_e('Job Name'); ?>"
                               data-empty="<?php esc_attr_e('New Job'); ?>"
                               value="<?php echo esc_attr(S3Testing_Option::get($jobid, 'name')); ?>" class="regular-text"/>
                    </td>
                </tr>
            </table>

            <h3>Job Tasks</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">This job is a&#160;&hellip;</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Job tasks'); ?></span>
                            </legend><?php
                            foreach ($job_types as $id => $typeclass) {
                                $addclass = '';
                                if ($typeclass->creates_file()) {
                                    $addclass .= ' filetype';
                                }
                                echo '<p><label for="jobtype-select-' . strtolower($id) . '"><input class="jobtype-select checkbox' . $addclass . '" id="jobtype-select-' . strtolower($id) . '" type="checkbox" ' . checked(true, in_array($id, S3Testing_Option::get($jobid, 'type'), true), false) . ' name="type[]" value="' . esc_attr($id) . '" /> ' . esc_attr($typeclass->info['description']) . '</label>';
                                if (!empty($typeclass->info['help'])) {
                                    echo '<br><span class="description">' . esc_attr($typeclass->info['help']) . '</span>';
                                }
                                echo '</p>';
                            }
                            ?></fieldset>
                    </td>
                </tr>
            </table>

            <h3 class="title hasdests"><?php esc_html_e('Backup File Creation'); ?></h3>
            <p class="hasdests"></p>
            <table class="form-table hasdests">
                <tr class="nosync">
                    <th scope="row"><label for="archivename"><?php esc_html_e('Archive name'); ?></label></th>
                    <td>
                        <input name="archivename" type="text" id="archivename" placeholder="my-backup"
                               value="<?php echo esc_attr(S3Testing_Option::get($jobid, 'archivename')); ?>"
                               class="regular-text code"/>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Archive Format'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Archive Format'); ?></span></legend>
                            <?php
                            if (class_exists(\ZipArchive::class)) {
                                echo '<p><label for="idarchiveformat-zip">
                                       <input class="radio" type="radio"' . checked('.zip', $archive_format_option, false) . ' name="archiveformat" id="archiveformat-zip" value=".zip"/> ' . esc_html__('Zip') . '
                                        </label></p>';
                            } else {
                                echo '<p><label for="idarchiveformat-zip"><input class="radio" type="radio"' . checked('.zip', $archive_format_option, false) . ' name="archiveformat" id="idarchiveformat-zip" value=".zip" disabled="disabled" /> ' . esc_html__('Zip') . '</label>';
                                echo '<br /><span class="description">' . esc_html(__('ZipArchive PHP class is missing, so s3testing will use PclZip instead.')) . '</span></p>';
                            }
                            echo '<p><label for="idarchiveformat-tar"><input class="radio" type="radio"' . checked('.tar', $archive_format_option, false) . ' name="archiveformat" id="idarchiveformat-tar" value=".tar" /> ' . esc_html__('Tar') . '</label></p>';

                            if (function_exists('gzopen')) {
                                echo '<p><label for="idarchiveformat-targz"><input class="radio" type="radio"' . checked('.tar.gz', $archive_format_option, false) . ' name="archiveformat" id="idarchiveformat-targz" value=".tar.gz" /> ' . esc_html__('Tar GZip') . '</label></p>';
                            } else {
                                echo '<p><label for="idarchiveformat-targz"><input class="radio" type="radio"' . checked('.tar.gz', $archive_format_option, false) . ' name="archiveformat" id="idarchiveformat-targz" value=".tar.gz" disabled="disabled" /> ' . esc_html__('Tar GZip') . '</label>';
                                echo '<br /><span class="description">' . esc_html(sprintf(__('Disabled due to missing %s PHP function.'), 'gzopen()')) . '</span></p>';
                            }
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <h3 class="title hasdests"><?php esc_html_e('Job Destination'); ?></h3>
            <p class="hasdests"></p>
            <table class="form-table hasdests">
                <tr>
                    <th scope="row"><?php esc_html_e('Where should your backup file be stored?'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php esc_html_e('Where should your backup file be stored?'); ?></span>
                            </legend><?php
                            foreach ($destinations as $id => $dest) {
                                $syncclass = '';
                                if (!$dest['can_sync']) {
                                    $syncclass = 'nosync';
                                }
                                echo '<p class="' . esc_attr($syncclass) . '"><label for="dest-select-' . strtolower($id) . '"><input class="checkbox" id="dest-select-' . strtolower(esc_attr($id)) . '" type="checkbox" ' . checked(true, in_array($id, S3Testing_Option::get($jobid, 'destinations'), true), false) . ' name="destinations[]" value="' . esc_attr($id) . '" ' . disabled(!empty($dest['error']), true, false) . ' /> ' . esc_attr($dest['info']['description']);
                                if (!empty($dest['error'])) {
                                    echo '<br><span class="description">' . esc_attr($dest['error']) . '</span>';
                                }
                                echo '</label></p>';
                            }
                            ?></fieldset>
                    </td>
                </tr>
            </table>
            <?php
            break;
        case 'cron':
            ?>
                <div class="table" id="info-tab-cron">
                    <h3 class="title"><?php esc_html_e('Job Schedule'); ?></h3>
                    <p></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Start job'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span><?php esc_html_e('Start job'); ?></span></legend>
                                    <label for="idactivetype">
                                        <input class="radio"
                                               type="radio" <?php checked('', S3Testing_Option::get($jobid, 'activetype'), true) ?>
                                               name="activetype"
                                                id="activetype"
                                                value=""/>
                                        <?php esc_html_e('manually only'); ?>
                                    </label><br/>
                                    <label for="idactivetype-wpcron">
                                        <input class="radio"
                                                type="radio"
                                                name="activetype"
                                                id="activetype-wpcron"
                                                value="wpcron"/>
                                        <?php esc_html_e('with WordPress cron'); ?>
                                    </label><br/>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <h3 class="title wpcron"><?php esc_html_e('Schedule execution time'); ?></h3>
                    <table class="form-table wpcron">
                        <tr>
                            <th scope="row"><?php _e('Scheduler'); ?></th>
                            <td>
                                <table>
                                    <tr>
                                        <th>
                                            <?php _e('Type'); ?>
                                        </th>
                                        <th>
                                            <?php _e('Minute'); ?>
                                        </th>
                                    </tr>
                                    <tr>

                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php
            break;
        default:
            echo '<div class="table" id="info-tab-' . $_GET['tab'] . '">';
            if (strstr((string)$_GET['tab'], 'dest-')) {
                $dest_object = S3Testing::get_destination(str_replace('dest-', '', (string)$_GET['tab']));
                $dest_object->edit_tab($jobid);
            }
            if (strstr((string)$_GET['tab'], 'jobtype-')) {
                $id = strtoupper(str_replace('jobtype-', '', (string)$_GET['tab']));
                $job_types[$id]->edit_tab($jobid);
            }

            echo '</div>';
    }
        echo '<p class="submit">';
        submit_button('Save changes', 'primary', 'save', false, ['tabindex' => '2', 'accesskey' => 'p']);
        echo '</p></form>';
        ?>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                //auto post if things change
                var changed = false;
                $('#editjob').change(function() {
                    changed = true;
                });
                $('.nav-tab').click(function() {
                    if(changed) {
                        $( 'input[name="nexttab"]' ).val( $(this).data( "nexttab" ) );
                        $( '#editjob' ).submit();
                        return false;
                    }
                });
            });
        </script>
        <?php
        //add inline js
        if (strstr((string)$_GET['tab'], 'dest-')) {
            $dest_object = S3Testing::get_destination(str_replace('dest-', '', sanitize_text_field($_GET['tab'])));
            $dest_object->edit_inline_js();
        }
    }

}
