<?php

use Aws\Exception\AwsException;

class S3Testing_Destination_S3
{
    public function option_defaults()
    {
        return [
            's3base_url' => '',
            's3base_multipart' => true,
            's3base_pathstylebucket' => false,
            's3base_version' => 'latest',
            's3base_signature' => 'v4',
            's3accesskey' => '',
            's3secretkey' => '',
            's3bucket' => '',
            's3region' => 'ap-southeast-2',
//            's3ssencrypt' => '',
//            's3storageclass' => '',
            's3dir' => trailingslashit(sanitize_file_name(get_bloginfo('name'))),
//            's3maxbackups' => 15,
//            's3syncnodelete' => true,
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
                    <label for="s3region">
                        <?php esc_html_e('Select a S3 service'); ?>
                    </label>
                </th>
                <td>

                    <select name="s3region"
                            id="s3region"
                            title="<?php esc_attr_e('S3 Region'); ?>">
                        <?php foreach (S3Testing_S3_Destination::options() as $id => $option) { ?>
                            <option value="<?php echo esc_attr($id); ?>"
                                <?php selected($id, S3Testing_Option::get($jobid, 's3region')); ?>
                            >
                                <?php echo esc_html($option['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </td>
            </tr>
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
                           value="<?php echo esc_attr(S3Testing_Option::get(
                               $jobid,
                               's3secretkey'
                           )); ?>" class="regular-text" autocomplete="off"/>
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
                                's3secretkey' => S3Testing_Option::get($jobid, 's3secretkey'),
                                's3region' => S3Testing_Option::get($jobid, 's3region'),
                                's3bucketselected' => S3Testing_Option::get($jobid, 's3bucket'),
                            ], true
                        );
                    } ?>
                 </td>
            </tr>
<!--            <tr>-->
<!--                <th scope="row">-->
<!--                    <label for="s3dirselected">-->
<!--                        --><?php //esc_html_e('Folder selection'); ?>
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
                           value="<?php echo esc_attr(S3Testing_Option::get($jobid, 's3dir')); ?>"
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
                                's3secretkey' => S3Testing_Option::get($jobid, 's3secretkey'),
                                's3region' => S3Testing_Option::get($jobid, 's3region'),
                                's3bucketselected' => S3Testing_Option::get($jobid, 's3bucket'),
                            ]
                        );
                    }
                    ?>
                </td>
            </tr>
        </table>

        <h3 class="title"><?php esc_html_e('Amazon specific settings'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ids3storageclass">
                        <?php esc_html_e('Amazon: Storage Class'); ?>
                    </label>
                </th>
                <td>
                    <?php $storageClass = S3Testing_Option::get($jobid, 's3storageclass'); ?>
                    <select name="s3storageclass"
                            id="ids3storageclass"
                            title="<?php esc_html_e('Amazon: Storage Class'); ?>">
                        <option value=""
                            <?php selected('', $storageClass, true); ?>>
                            <?php esc_html_e('Standard'); ?>
                        </option>
                        <option value="STANDARD_IA"
                            <?php selected('STANDARD_IA', $storageClass, true); ?>>
                            <?php esc_html_e('Standard-Infrequent Access'); ?>
                        </option>
                        <option value="ONEZONE_IA"
                            <?php selected('ONEZONE_IA', $storageClass, true); ?>>
                            <?php esc_html_e('One Zone-Infrequent Access'); ?>
                        </option>
                        <option value="REDUCED_REDUNDANCY"
                            <?php selected('REDUCED_REDUNDANCY', $storageClass, true); ?>>
                            <?php esc_html_e('Reduced Redundancy'); ?>
                        </option>
                        <option value="INTELLIGENT_TIERING"
                            <?php selected('INTELLIGENT_TIERING', $storageClass, true); ?>>
                            <?php esc_html_e('Intelligent-Tiering'); ?>
                        </option>
                        <option value="GLACIER_IR"
                            <?php selected('GLACIER_IR', $storageClass, true); ?>>
                            <?php esc_html_e('Glacier Instant Retrieval'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <?php
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
            $args['s3region'] = sanitize_text_field($_POST['s3region']);
            $args['s3base_url'] = s3testing_esc_url_default_secure($_POST['s3base_url'], ['http', 'https']);
            $args['s3bucketselected'] = sanitize_text_field($_POST['s3bucketselected']);

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
                    'Delimiter' => '/',
                ]);

                if(!empty($folders['CommonPrefixes'])) {
                    $folders_list = $folders['CommonPrefixes'];
                }

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
        echo '<option' . selected($args['s3dirselected'], '', false) . '>/</option>'; // Option Root
        if (!empty($folders_list)) {

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
        S3Testing_Option::update($jobid, 's3secretkey', sanitize_text_field($_POST['s3secretkey']));
        S3Testing_Option::update(
            $jobid,
            's3base_url',
            isset($_POST['s3base_url'])
                ? s3testing_esc_url_default_secure($_POST['s3base_url'], ['http', 'https'])
                : ''
        );
        S3Testing_Option::update($jobid, 's3region', sanitize_text_field($_POST['s3region']));
        S3Testing_Option::update(
            $jobid,
            's3bucket',
            isset($_POST['s3bucket']) ? sanitize_text_field($_POST['s3bucket']) : ''
        );

        $_POST['s3dir'] = trailingslashit(str_replace(
            '//',
            '/',
            str_replace('\\', '/', trim(sanitize_text_field($_POST['s3dir'])))
        ));

        S3Testing_Option::update($jobid, 's3dir', $_POST['s3dir']);
    }

    public function job_run_archive(S3Testing_Job $job_object)
    {
        try {
            if (empty($job_object->job['s3base_url'])) {
                $aws_destination = S3Testing_S3_Destination::fromOption($job_object->job['s3region']);
            } else {
                $aws_destination = S3Testing_S3_Destination::fromJobId($job_object->job['jobid']);
            }

            //create s3 client
            $s3 = $aws_destination->client(
                $job_object->job['s3accesskey'],
                $job_object->job['s3secretkey']
            );

            if ($s3->doesBucketExist($job_object->job['s3bucket'])) {
                $bucketregion = $s3->getBucketLocation(['Bucket' => $job_object->job['s3bucket']]);
            } else {

                return true;
            }

//            if ($aws_destination->supportsMultipart()) {
//                $multipart_uploads = $s3->listMultipartUploads([
//                    'Bucket' => $job_object->job['s3bucket'],
//                    'Prefix' => (string) $job_object->job['s3dir'],
//                ]);
//
//                $uploads = $multipart_uploads->get('Uploads');
//
//                if (!empty($uploads)) {
//                    foreach ($uploads as $upload) {
//                        $s3->abortMultipartUpload([
//                            'Bucket' => $job_object->job['s3bucket'],
//                            'Key' => $upload['Key'],
//                            'UploadId' => $upload['UploadId'],
//                        ]);
//                    }
//                }
//            }

            if (!$up_file_handle = fopen($job_object->backup_folder . $job_object->backup_file, 'rb')) {
                return false;
            }

            $create_args = [];
            $create_args['Bucket'] = $job_object->job['s3bucket'];
            $create_args['ACL'] = 'private';

            $create_args['Metadata'] = ['BackupTime' => date('Y-m-d H:i:s', $job_object->start_time)];

            $create_args['Body'] = $up_file_handle;
            $create_args['Key'] = $job_object->job['s3dir'] . $job_object->backup_file;

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
                'Key' => $job_object->job['s3dir'] . $job_object->backup_file,
            ]);

        } catch (Exception $e) {
        }
        return true;
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
                        s3base_url      : $( 'input[name="s3base_url"]' ).val(),
                        s3region: $('#s3region').val(),
                        _ajax_nonce: $('#s3testingajaxnonce').val(),
                        isBucket: $('#isBucket').val(),
                    };
                    console.log("Sending AJAX request with data:", data);
                    $.post(ajaxurl, data, function (response) {
                        console.log("Response from server:", response);
                        $( '#s3bucketerror' ).remove();
                        $( '#s3bucket' ).remove();
                        $( '#s3bucketselected' ).after( response );
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
                        s3base_url: $('input[name="s3base_url"]').val(),
                        s3region: $('#s3region').val(),
                        _ajax_nonce: $('#s3testingajaxnonce').val(),
                    };

                    console.log("Selected bucket:", data);

                    $.post(ajaxurl, data, function (response) {
                        console.log("Response from server:", response);
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



    public function can_run(array $job_settings): bool
    {
        if (empty($job_settings['s3accesskey'])) {
            return false;
        }

        return !(empty($job_settings['s3secretkey']));
    }
}