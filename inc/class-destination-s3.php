<?php

use Aws\Exception\AwsException;
use Aws\Iam\IamClient;

class S3Testing_Destination_S3
{
    private const EXTENSIONS = [
        '.tar.gz',
        '.tar',
        '.zip',
    ];

    public function option_defaults()
    {
        return [
            's3base_url' => '',
            's3base_multipart' => true,
            's3base_pathstylebucket' => false,
            's3accesskey' => '',
            's3secretkey' => '',
            's3bucket' => '',
            's3dircreate' => '',
            's3newfolder' => '',
            's3region' => 'ap-southeast-2',
            's3dir' => trailingslashit(sanitize_file_name(get_bloginfo('name'))),
            's3maxbackups' => 15,
        ];
    }

    public function edit_tab($jobid)
    {
        ?>
        <h3 class="title">
            <?php esc_html_e('S3 Service'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3base_url">
                        <?php esc_html_e('S3 Server URL'); ?>
                    </label>
                </th>
                <td>
                    <input
                            id="s3base_url"
                            name="s3base_url"
                            type="text"
                            value="<?php echo esc_attr(
                                S3Testing_Option::get($jobid, 's3base_url')
                            ); ?>"
                            class="regular-text"
                            autocomplete="off"
                    />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="s3base_region"><?php esc_html_e(
                            'Region',
                        ); ?>
                </th>
                <td>
                    <input type="text" name="s3region" value="" class="regular-text" autocomplete="off" readonly/>

                </td>
            </tr>
        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Access Keys'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3accesskey">
                        <?php esc_html_e('Access Key'); ?>
                    </label>
                </th>
                <td>
                    <input id="s3accesskey"
                           name="s3accesskey"
                           type="text"
                           value="<?php echo esc_attr(S3Testing_Option::get($jobid, 's3accesskey')); ?>"
                           class="regular-text"
                           autocomplete="off"
                    />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3secretkey"><?php esc_html_e('Secret Key'); ?></label></th>
                <td>
                    <input id="s3secretkey" name="s3secretkey" type="password"
                           value="<?php echo esc_attr(S3Testing_Encryption::decrypt(S3Testing_Option::get(
                               $jobid,
                               's3secretkey'
                           ))); ?>" class="regular-text" autocomplete="off"/>
                </td>
            </tr>
        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Bucket'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3bucketselected">
                        <?php esc_html_e('Bucket selection'); ?>
                    </label>
                </th>
                <td>
                    <input id="s3bucketselected"
                           name="s3bucketselected"
                           type="hidden"
                           value="<?php echo esc_attr(S3Testing_Option::get($jobid, 's3bucket')); ?>"
                    />
                    <?php
                    if (S3Testing_Option::get($jobid, 's3accesskey')
                        && S3Testing_Option::get($jobid, 's3secretkey')
                    ) {
                        $this->edit_ajax(
                            [
                                's3base_url' => S3Testing_Option::get($jobid, 's3base_url'),
                                's3accesskey' => S3Testing_Option::get($jobid, 's3accesskey'),
                                's3secretkey' => S3Testing_Encryption::decrypt(S3Testing_Option::get($jobid, 's3secretkey')),
                                's3bucketselected' => S3Testing_Option::get($jobid, 's3bucket'),
                            ], true
                        );
                    } ?>
                </td>
            </tr>
            <!--            <tr>-->
            <!--                <th scope="row">-->
            <!--                    <label for="s3dirselected">-->
            <!--                        --><?php //esc_html_e('Folder selection');
            ?>
            <!--                    </label>-->
            <!--                </th>-->
            <!--                <td>-->
            <!--                    <input id="s3dirselected"-->
            <!--                           name="s3dirselected"-->
            <!--                           type="hidden"-->
            <!--                           value=""-->
            <!--                    />-->
            <!--                    -->
            <!---->
            <!--                </td>-->
            <!--            </tr>-->
        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Backup settings'); ?>
        </h3>
        <table class="form-table">
        <tr>
            <th scope="row">
                <label for="s3dirselected">
                    <?php esc_html_e('Folder in bucket'); ?>
                </label>
            </th>
            <td>
                <input id="s3dirselected"
                       name="s3dirselected"
                       type="hidden"
                       value="<? echo S3Testing_Option::get($jobid, 's3dir'); ?>"
                       class="regular-text"
                />
                <?php

                if (S3Testing_Option::get($jobid, 's3accesskey')
                    && S3Testing_Option::get($jobid, 's3secretkey')
                ) {
                    $this->edit_ajax_dir(
                        [
                            's3base_url' => S3Testing_Option::get($jobid, 's3base_url'),
                            's3accesskey' => S3Testing_Option::get($jobid, 's3accesskey'),
                            's3secretkey' => S3Testing_Encryption::decrypt(S3Testing_Option::get($jobid, 's3secretkey')),
                            's3bucketselected' => S3Testing_Option::get($jobid, 's3bucket'),
                        ]
                    );
                }
                ?>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="s3dircreate">
                    <?php esc_html_e('Or create a new folder inside the selected folder'); ?>
                </label>
            </th>
            <td>
                <input id="s3dircreate"
                       name="s3dircreate"
                       type="text"
                       value="<?php echo S3Testing_Option::get($jobid, 's3dircreate') == '/' ? '' : S3Testing_Option::get($jobid, 's3dircreate'); ?>"
                       size="63"
                       class="regular-text"
                       autocomplete="off"
                />
                <p class="description"><?php esc_html_e(
                        'Leave it blank to backup to the root of the selected folder',
                    ); ?></p>
            </td>
        </tr>
        <tr>
        <th scope="row"><?php esc_html_e('File deletion'); ?></th>
        <td>
        <?php
        if (S3Testing_Option::get($jobid, 'backuptype') === 'archive') {
            ?>
            <label for="ids3maxbackups">
                <input id="ids3maxbackups"
                       name="s3maxbackups"
                       type="number"
                       min="0"
                       step="1"
                       value="<?php echo esc_attr(S3Testing_Option::get($jobid, 's3maxbackups')); ?>"
                       class="small-text"
                />
                <?php esc_html_e('Number of files to keep in folder.'); ?>
            </label>
            </td>
            </tr>
            </table>

            <?php
        }
    }

    public function edit_ajax($args = [], $bucket = true)
    {
        $error = '';
        $buckets = [];
        $buckets_list = [];
        $ajax = false;

        if (!$args) {
            $args = [];
            check_ajax_referer('s3testing_ajax_nonce');
            $args['s3accesskey'] = sanitize_text_field($_POST['s3accesskey']);
            $args['s3secretkey'] = sanitize_text_field($_POST['s3secretkey']);
            $args['s3base_url'] = s3testing_esc_url_default_secure($_POST['s3base_url'], ['http', 'https']);
            $args['s3bucketselected'] = sanitize_text_field($_POST['s3bucketselected']);
            $ajax = true;
        }

        if ($args['s3base_url']) {
            $args['s3region'] = $args['s3base_url'];
        }

        echo '<span id="s3bucketerror" class="s3testing-message-error">';
        if (!empty($args['s3accesskey']) && !empty($args['s3secretkey'])) {
            if (empty($args['s3base_url'])) {
                $aws = S3Testing_S3_Destination::fromOption($args['s3region']);
            } else {
                $options = [
                    'label' => __('Custom S3 destination'),
                    'endpoint' => $args['s3base_url'],
                ];
                $aws = S3Testing_S3_Destination::fromOptionArray($options);
            }

            try {
                $s3 = $aws->client($args['s3accesskey'], $args['s3secretkey']);

                $buckets = $s3->listBuckets();

                if (!empty($buckets['Buckets'])) {
                    $buckets_list = $buckets['Buckets'];
                }

            } catch (Exception $e) {
                $error = $e->getMessage();
                if ($e instanceof AwsException) {
                    $error = $e->getAwsErrorMessage();
                }
            }
        }

        if (empty($args['s3accesskey'])) {
            esc_html_e('Missing access key!');
        } elseif (empty($args['s3secretkey'])) {
            esc_html_e('Missing secret access key!');
        } elseif (!empty($error) && $error === 'Access Denied') {
            echo '<input type="text" name="s3bucket" id="s3bucket" value="' . esc_attr($args['s3bucketselected']) . '" >';
        } elseif (!empty($error)) {
            echo esc_html($error);
        } elseif (empty($buckets) || count($buckets['Buckets']) < 1) {
            esc_html_e('No bucket found!');
        }
        echo '</span>';

        if (!empty($buckets_list)) {
            echo '<select name="s3bucket" id="s3bucket">';

            foreach ($buckets_list as $bucket) {
                echo '<option ' . selected($args['s3bucketselected'], esc_attr($bucket['Name']), false) . '>'
                    . esc_attr($bucket['Name'])
                    . '</option>';
            }
            echo '</select>';

            $this->edit_inline_dir_js();
        }

        if ($ajax) {
            exit();
        }
    }

    public function edit_ajax_dir($args = [])
    {
        $error = '';
        $folders = [];
        $folders_list = [];
        $ajax = false;

        if (!$args) {
            $args = [];
            check_ajax_referer('s3testing_ajax_nonce');
            $args['s3accesskey'] = sanitize_text_field($_POST['s3accesskey']);
            $args['s3secretkey'] = sanitize_text_field($_POST['s3secretkey']);
            $args['s3base_url'] = s3testing_esc_url_default_secure($_POST['s3base_url'], ['http', 'https']);
            $args['s3bucketselected'] = sanitize_text_field($_POST['s3bucketselected']);
            $args['s3dirselected'] = sanitize_text_field($_POST['s3dirselected']);

            $ajax = true;
        }

        if ($args['s3base_url']) {
            $args['s3region'] = $args['s3base_url'];
        }

        echo '<span id="s3direrror" class="s3testing-message-error">';
        if (!empty($args['s3accesskey']) && !empty($args['s3secretkey'])) {
            if (empty($args['s3base_url'])) {
                $aws = S3Testing_S3_Destination::fromOption($args['s3region']);
            } else {
                $options = [
                    'label' => __('Custom S3 destination'),
                    'endpoint' => $args['s3base_url'],
                ];
                $aws = S3Testing_S3_Destination::fromOptionArray($options);
            }

            try {
                $s3 = $aws->client($args['s3accesskey'], $args['s3secretkey']);

                $folders = $s3->listObjectsV2([
                    'Bucket' => $args['s3bucketselected'],
                ]);

                $folders_list = [['Prefix' => './']];

                if (!empty($folders['Contents'])) {
                    foreach ($folders['Contents'] as $object) {
                        $key = $object['Key'];
                        if (str_ends_with($key, '/')) {
                            $folders_list[] = ['Prefix' => $key];
                        } else {
                            $folderPath = dirname($key) . '/';
                            if (!in_array(['Prefix' => $folderPath], $folders_list)) {
                                $folders_list[] = ['Prefix' => $folderPath];
                            }
                        }
                    }
                }

                usort($folders_list, fn($a, $b) => strcmp($a['Prefix'], $b['Prefix']));
            } catch (Exception $e) {
                $error = $e->getMessage();
                if ($e instanceof AwsException) {
                    $error = $e->getAwsErrorMessage();
                }
            }
        }

        if (empty($args['s3accesskey'])) {
            esc_html_e('No Bucket found');
        } elseif (empty($args['s3secretkey'])) {
            esc_html_e('No Bucket found');
        } elseif (!empty($error)) {
            echo esc_html($error);
        }
        echo '</span>';

        echo '<select name="s3dir" id="s3dir">';
        if (count($folders_list) > 0) {

            foreach ($folders_list as $folder) {
                echo '<option ' . selected($args['s3dirselected'], esc_attr($folder['Prefix']), false) . '>'
                    . esc_attr($folder['Prefix'])
                    . '</option>';
            }
            echo '</select>';
        }

        if ($ajax) {
            exit();
        }
    }

    public function edit_form_post_save($jobid)
    {
        S3Testing_Option::update($jobid, 's3accesskey', sanitize_text_field($_POST['s3accesskey']));
        S3Testing_Option::update($jobid, 's3secretkey',
            isset($_POST['s3secretkey'])
                ? S3Testing_Encryption::encrypt($_POST['s3secretkey'])
                : '');
        S3Testing_Option::update(
            $jobid,
            's3base_url',
            isset($_POST['s3base_url'])
                ? s3testing_esc_url_default_secure($_POST['s3base_url'], ['http', 'https'])
                : ''
        );
        S3Testing_Option::update($jobid, 's3base_region', sanitize_text_field($_POST['s3base_region']));
        S3Testing_Option::update(
            $jobid,
            's3bucket',
            isset($_POST['s3bucket']) ? sanitize_text_field($_POST['s3bucket']) : ''
        );

        $_POST['s3dir'] = trailingslashit(str_replace(
            '//',
            '/',
            str_replace('\\', '/', trim(sanitize_text_field($_POST['s3dir']))
            )));

        $_POST['s3dircreate'] = trailingslashit(str_replace(
            '//',
            '/',
            str_replace('\\', '/', trim(sanitize_text_field($_POST['s3dircreate'])))
        ));

        if ($_POST['s3dircreate'] != '/') {
            $_POST['s3newfolder'] = $_POST['s3dir'] . $_POST['s3dircreate'];
        }

        S3Testing_Option::update($jobid, 's3dir', $_POST['s3dir']);
        S3Testing_Option::update($jobid, 's3dircreate', $_POST['s3dircreate']);
        S3Testing_Option::update($jobid, 's3newfolder', $_POST['s3newfolder'] == null ? '' : $_POST['s3newfolder']);
        S3Testing_Option::update($jobid, 's3maxbackups', !empty($_POST['s3maxbackups']) ? absint($_POST['s3maxbackups']) : 0);
    }

    public function job_run_archive(S3Testing_Job $job_object)
    {
        $job_object->substeps_todo = 2 + $job_object->backup_filesize;

        if ($job_object->steps_data[$job_object->step_working]['SAVE_STEP_TRY'] != $job_object->steps_data[$job_object->step_working]['STEP_TRY']) {
            $job_object->log(
                sprintf(
                    __('%d. Trying to send backup file to S3 Service&#160;&hellip;'),
                    $job_object->steps_data[$job_object->step_working]['STEP_TRY']
                )
            );
        }


        try {
            if (empty($job_object->job['s3base_url'])) {
                $aws_destination = S3Testing_S3_Destination::fromOption($job_object->job['s3region']);
            } else {
                $aws_destination = S3Testing_S3_Destination::fromJobId($job_object->job['jobid']);
            }

            //create s3 client
            $s3 = $aws_destination->client(
                $job_object->job['s3accesskey'],
                S3Testing_Encryption::decrypt($job_object->job['s3secretkey'])
            );

            $job_object->log(
                sprintf(
                    __('Connected to S3 Bucket "%1$s"'),
                    $job_object->job['s3bucket']
                )
            );
            $job_object->log(
                sprintf(
                    __('Starting upload to S3 Service&#160;&hellip;')
                )
            );

            if (!$up_file_handle = fopen($job_object->backup_folder . $job_object->backup_file, 'rb')) {
                return false;
            }

            $create_args = [];
            $create_args['Bucket'] = $job_object->job['s3bucket'];
            $create_args['ACL'] = 'private';

            $create_args['Metadata'] = ['BackupTime' => date('Y-m-d H:i:s', $job_object->start_time)];

            $create_args['Body'] = $up_file_handle;
            if ($job_object->job['s3newfolder'] == '') {
                $create_args['Key'] = $job_object->job['s3dir'] . $job_object->backup_file;
            } else {
                $create_args['Key'] = $job_object->job['s3newfolder'] . $job_object->backup_file;
            }

            try {
                $s3->putObject($create_args);
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if ($e instanceof AwsException) {
                    $errorMessage = $e->getAwsErrorMessage();
                }
                return false;
            }

            $result = $s3->headObject([
                'Bucket' => $job_object->job['s3bucket'],
                'Key' => $job_object->job['s3newfolder'] . $job_object->backup_file,
            ]);

            if ($result->get('ContentLength') == filesize($job_object->backup_folder . $job_object->backup_file)) {
                $job_object->log(
                    sprintf(
                        __('Backup transferred to %s.'),
                        $s3->getObjectUrl($job_object->job['s3bucket'], $job_object->job['s3dir'] . $job_object->backup_file)
                    )
                );
            } else {
                $job_object->log(
                    sprintf(
                        __('Cannot transfer backup to S3! (%1$d) %2$s'),
                        $result->get('status'),
                        $result->get('Message')
                    ),
                    E_USER_ERROR
                );
            }

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if ($e instanceof AwsException) {
                $errorMessage = $e->getAwsErrorMessage();
            }
            $job_object->log(
                E_USER_ERROR,
                sprintf(__('S3 Service API: %s'), $errorMessage),
                $e->getFile(),
                $e->getLine()
            );

            return false;
        }

        try {
            $this->file_update_list($job_object, true);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if ($e instanceof AwsException) {
                $errorMessage = $e->getAwsErrorMessage();
            }
            $job_object->log(
                E_USER_ERROR,
                sprintf(__('S3 Service API: %s'), $errorMessage),
                $e->getFile(),
                $e->getLine()
            );

            return false;
        }

        $job_object->log(
            sprintf(
                __('Upload to S3 Service done!'),
            ),
            E_USER_NOTICE
        );

        return true;
    }

    public function file_delete($jobdest, $backupfile)
    {
        $files = get_site_transient('s3testing_' . strtolower($jobdest));

        [$jobid, $dest] = explode('_', $jobdest);

        if (S3Testing_Option::get($jobid, 's3accesskey') && S3Testing_Option::get($jobid, 's3secretkey') && S3Testing_Option::get($jobid, 's3bucket')) {
            try {
                $aws = S3Testing_S3_Destination::fromJobId($jobid);

                $s3 = $aws->client(
                    S3Testing_Option::get($jobid, 's3accesskey'),
                    S3Testing_Encryption::decrypt(S3Testing_Option::get($jobid, 's3secretkey'))
                );

                $s3->deleteObject([
                    'Bucket' => S3Testing_Option::get($jobid, 's3bucket'),
                    'Key' => $backupfile,
                ]);

                //update file list
                foreach ((array)$files as $key => $file) {
                    if (is_array($file) && $file['file'] === $backupfile) {
                        unset($files[$key]);
                    }
                }
                $files = array_values($files);
                unset($s3);
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if ($e instanceof AwsException) {
                    $errorMessage = $e->getAwsErrorMessage();
                }


                S3Testing_Admin::message(sprintf(__('S3 Service API: %s'), $errorMessage), true);
            }
        }
        set_site_transient('s3testing_' . strtolower($jobdest), $files, YEAR_IN_SECONDS);

    }

    public function file_update_list($job, bool $delete = false)
    {
        if ($job instanceof S3Testing_Job) {
            $job_object = $job;
            $jobid = $job->job['jobid'];
        } else {
            $job_object = null;
            $jobid = $job;
        }

        if (empty($job_object->job['s3base_url'])) {
            $aws_destination = S3Testing_S3_Destination::fromOption($job_object->job['s3region']);
        } else {
            $aws_destination = S3Testing_S3_Destination::fromJobId($job_object->job['jobid']);
        }

        $s3 = $aws_destination->client(
            S3Testing_Option::get($jobid, 's3accesskey'),
            S3Testing_Encryption::decrypt(S3Testing_Option::get($jobid, 's3secretkey'))
        );

        $backupfilelist = [];
        $filecounter = 0;
        $files = [];
        $args = [
            'Bucket' => S3Testing_Option::get($jobid, 's3bucket'),
        ];

        if ($job_object->job['s3newfolder']) {
            $args['Prefix'] = $job_object->job['s3dircreate'];
        } else {
            $args['Prefix'] = '';
        }

        $objects = $s3->getIterator('ListObjects', $args);
        if (is_object($objects)) {
            foreach ($objects as $object) {
                $file = basename((string)$object['Key']);
                $changetime = strtotime((string)$object['LastModified']) + (get_option('gmt_offset') * 3600);

                if ($this->is_backup_archive($file)) {
                    $backupfilelist[$changetime] = $file;
                }

                $files[$filecounter]['folder'] = $s3->getObjectUrl(S3Testing_Option::get($jobid, 's3bucket'), dirname((string)$object['Key']));
                $files[$filecounter]['file'] = $object['Key'];
                $files[$filecounter]['filename'] = basename((string)$object['Key']);

                $files[$filecounter]['downloadurl'] = network_admin_url('admin-ajax.php') . '?page=s3testingbackups&action=download_file&file=' . $object['Key'] . '&local_file=' . basename((string)$object['Key']) . '&jobid=' . $jobid;
                $files[$filecounter]['filesize'] = (int)$object['Size'];
                $files[$filecounter]['time'] = $changetime;

                ++$filecounter;
            }
        }

        //delete if > maxbackups
        if ($delete && $job_object && $job_object->job['s3maxbackups'] > 0 && is_object($s3)) {
            if (count($backupfilelist) > $job_object->job['s3maxbackups']) {
                ksort($backupfilelist);
                $numdeltefiles = 0;

                while ($file = array_shift($backupfilelist)) {
                    if (count($backupfilelist) < $job_object->job['s3maxbackups']) {
                        break;
                    }

                    //delete files on S3
                    $args = [
                        'Bucket' => $job_object->job['s3bucket'],
                    ];

                    if ($job_object->job['s3newfolder']) {
                        $args['Key'] = $job_object->job['s3dircreate'] . $file;
                    } else {
                        $args['Key'] = $file;
                    }

                    if ($s3->deleteObject($args)) {
                        foreach ($files as $key => $filedata) {
                            if ($filedata['file'] == $job_object->job['s3dircreate'] . $file) {
                                unset($files[$key]);
                            }
                        }
                        ++$numdeltefiles;
                    }
                }
            }
        }

        set_site_transient('s3testing_' . $jobid . '_s3', $files, YEAR_IN_SECONDS);
    }

    public function file_get_list(string $jobdest): array
    {
        $list = (array)get_site_transient('s3testing_' . strtolower($jobdest));
        return array_filter($list);
    }

    public function edit_inline_js()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                function awsgetbucket() {
                    var data = {
                        action: 's3testing_dest_s3',
                        s3accesskey: $('input[name="s3accesskey"]').val(),
                        s3secretkey: $('input[name="s3secretkey"]').val(),
                        s3bucketselected: $('input[name="s3bucketselected"]').val(),
                        s3base_url: $('input[name="s3base_url"]').val(),
                        _ajax_nonce: $('#s3testingajaxnonce').val(),
                        isBucket: $('#isBucket').val(),
                    };

                    $.post(ajaxurl, data, function (response) {
                        $('#s3bucketerror').remove();
                        $('#s3bucket').remove();
                        $('#s3bucketselected').after(response);
                    });
                }

                // Trigger bucket list update when region or keys change

                $('input[name="s3accesskey"], input[name="s3secretkey"], input[name="s3base_url"]').on('keyup', function () {
                    awsgetbucket();
                });
            });
        </script>
        <?php
    }

    public function edit_inline_dir_js()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                function awsgetdir() {
                    var data = {
                        action: 's3testing_dest_s3_dir',
                        s3accesskey: $('input[name="s3accesskey"]').val(),
                        s3secretkey: $('input[name="s3secretkey"]').val(),
                        s3bucketselected: $('#s3bucket').val(),
                        s3dirselected: $('input[name="s3dirselected"]').val(),
                        s3base_url: $('input[name="s3base_url"]').val(),
                        _ajax_nonce: $('#s3testingajaxnonce').val(),
                    };

                    $.post(ajaxurl, data, function (response) {
                        $('#s3direrror').remove();
                        $('#s3dir').remove();
                        $('#s3dirselected').after(response);
                    });
                }

                $('#s3bucket').change(function () {
                    awsgetdir();
                });

                awsgetdir();
            });
        </script>
        <?php
    }

    public function admin_print_scripts()
    {

    }

    public function can_run(array $job_settings): bool
    {
        if (empty($job_settings['s3accesskey'])) {
            return false;
        }

        return !(empty($job_settings['s3secretkey']));
    }

    public function is_backup_archive($file)
    {
        $file = trim(basename($file));
        $filename = '';

        foreach (self::EXTENSIONS as $extension) {
            if (substr($file, (strlen($extension) * -1)) === $extension) {
                $filename = substr($file, 0, (strlen($extension) * -1));
            }
        }
        return !(!$filename);
    }
}