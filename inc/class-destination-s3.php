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
            's3region' => 'us-east-1',
            's3ssencrypt' => '',
            's3storageclass' => '',
            's3dir' => trailingslashit(sanitize_file_name(get_bloginfo('name'))),
            's3maxbackups' => 15,
            's3syncnodelete' => true,
        ];
    }

    public function edit_tab($jobid)
    {
        ?>
        <h3 class="title">
            <?php esc_html_e('S3 Service', 'backwpup'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3region">
                        <?php esc_html_e('Select a S3 service', 'backwpup'); ?>
                    </label>
                </th>
                <td>

                    <select name="s3region"
                            id="s3region"
                            title="<?php esc_attr_e('S3 Region', 'backwpup'); ?>">
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
        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Access Keys', 'backwpup'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3accesskey">
                        <?php esc_html_e('Access Key', 'backwpup'); ?>
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
                <th scope="row"><label for="s3secretkey"><?php esc_html_e('Secret Key', 'backwpup'); ?></label></th>
                <td>
                    <input id="s3secretkey" name="s3secretkey" type="password"
                           value="" class="regular-text" autocomplete="off"/>
                </td>
            </tr>
        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Bucket', 'backwpup'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="s3bucketselected">
                        <?php esc_html_e('Bucket selection', 'backwpup'); ?>
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
                                's3accesskey' => S3Testing_Option::get($jobid, 's3accesskey'),
                                's3secretkey' => S3Testing_Option::get($jobid, 's3secretkey'),
                                's3region' => S3Testing_Option::get($jobid, 's3region'),
                            ]
                        );
                    } ?>
                 </td>
            </tr>

        </table>

        <h3 class="title">
            <?php esc_html_e('S3 Backup settings', 'backwpup'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ids3dir">
                        <?php esc_html_e('Folder in bucket', 'backwpup'); ?>
                    </label>
                </th>
                <td>
                    <input id="ids3dir"
                           name="s3dir"
                           type="text"
                           value="<?php echo esc_attr(S3Testing_Option::get($jobid, 's3dir')); ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('File deletion', 'backwpup'); ?></th>
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
                            &nbsp;<?php esc_html_e('Number of files to keep in folder.', 'backwpup'); ?>
                        </label>
                        <p>
                            <?php _e(
                                '<strong>Warning</strong>: Files belonging to this job are now tracked. Old backup archives which are untracked will not be automatically deleted.',
                                'backwpup'
                            ); ?>
                        </p>
                        <?php
                    } else { ?>
                        <label for="ids3syncnodelete">
                            <input class="checkbox" value="1"
                                   type="checkbox"
                                <?php checked(S3Testing_Option::get($jobid, 's3syncnodelete'), true); ?>
                                   name="s3syncnodelete"
                                   id="ids3syncnodelete"
                            />
                            <?php esc_html_e('Do not delete files while syncing to destination!', 'backwpup'); ?>
                        </label>
                    <?php } ?>
                </td>
            </tr>
        </table>

        <h3 class="title"><?php esc_html_e('Amazon specific settings', 'backwpup'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ids3storageclass">
                        <?php esc_html_e('Amazon: Storage Class', 'backwpup'); ?>
                    </label>
                </th>
                <td>
                    <?php $storageClass = S3Testing_Option::get($jobid, 's3storageclass'); ?>
                    <select name="s3storageclass"
                            id="ids3storageclass"
                            title="<?php esc_html_e('Amazon: Storage Class', 'backwpup'); ?>">
                        <option value=""
                            <?php selected('', $storageClass, true); ?>>
                            <?php esc_html_e('Standard', 'backwpup'); ?>
                        </option>
                        <option value="STANDARD_IA"
                            <?php selected('STANDARD_IA', $storageClass, true); ?>>
                            <?php esc_html_e('Standard-Infrequent Access', 'backwpup'); ?>
                        </option>
                        <option value="ONEZONE_IA"
                            <?php selected('ONEZONE_IA', $storageClass, true); ?>>
                            <?php esc_html_e('One Zone-Infrequent Access', 'backwpup'); ?>
                        </option>
                        <option value="REDUCED_REDUNDANCY"
                            <?php selected('REDUCED_REDUNDANCY', $storageClass, true); ?>>
                            <?php esc_html_e('Reduced Redundancy', 'backwpup'); ?>
                        </option>
                        <option value="INTELLIGENT_TIERING"
                            <?php selected('INTELLIGENT_TIERING', $storageClass, true); ?>>
                            <?php esc_html_e('Intelligent-Tiering', 'backwpup'); ?>
                        </option>
                        <option value="GLACIER_IR"
                            <?php selected('GLACIER_IR', $storageClass, true); ?>>
                            <?php esc_html_e('Glacier Instant Retrieval', 'backwpup'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ids3ssencrypt">
                        <?php esc_html_e('Server side encryption', 'backwpup'); ?>
                    </label>
                </th>
                <td>
                    <input class="checkbox"
                           value="AES256"
                           type="checkbox"
                        <?php checked(S3Testing_Option::get($jobid, 's3ssencrypt'), 'AES256'); ?>
                           name="s3ssencrypt"
                           id="ids3ssencrypt"
                    />
                    <?php esc_html_e('Save files encrypted (AES256) on server.', 'backwpup'); ?>
                </td>
            </tr>
        </table>

        <?php
    }

    public function edit_ajax($args = [])
    {
        $buckets = [];
        $buckets_list = [];
        $error = '';
        $ajax = false;

        if (!$args) {
            $args = [];
            check_ajax_referer('s3testing_ajax_nonce');
            $args['s3accesskey'] = sanitize_text_field($_POST['s3accesskey']);
            $args['s3secretkey'] = sanitize_text_field($_POST['s3secretkey']);
            $args['s3region'] = sanitize_text_field($_POST['s3region']);

            $ajax = true;
        }

        echo '<span id="s3bucketerror" class="s3testing-message-error">';

        if (!empty($args['s3accesskey']) && !empty($args['s3secretkey'])) {
            $options = [
                    'label' => __('Custom S3 destination', 'backwpup'),
                'endpoint' => $args['s3base_url'],
                'region' => $args['s3base_region'],
            ];
                $aws = S3Testing_S3_Destination::formOptionArray($options);


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
            esc_html_e('Missing access key!', 'backwpup');
        } elseif (empty($args['s3secretkey'])) {
            esc_html_e('Missing secret access key!', 'backwpup');
        } elseif (!empty($error) && $error === 'Access Denied') {
            echo '<input type="text" name="s3bucket" id="s3bucket" value="' . esc_attr($args['s3bucketselected']) . '" >';
        } elseif (!empty($error)) {
            echo esc_html($error);
        } elseif (empty($buckets) || count($buckets['Buckets']) < 1) {
            esc_html_e('No bucket found!', 'backwpup');
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
        }

        if ($ajax) {
            exit();
        }
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
                        s3region: $('#s3region').val(),
                        _ajax_nonce: $('#s3testingajaxnonce').val()
                    };
                    console.log("Sending AJAX request with data:", data);
                    $.post(ajaxurl, data, function (response) {
                        console.log("Response from server:", response);
                        $('#s3bucketerror').remove();
                        $('#s3bucket').remove();
                        $('#s3bucketselected').after(response);
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        console.log("AJAX request failed:", textStatus, errorThrown);
                    });
                }

                // Trigger bucket list update when region or keys change
                $('select[name="s3region"]').change(function () {
                    awsgetbucket();
                });
                $('input[name="s3accesskey"], input[name="s3secretkey"]').on('keyup', function () {
                    awsgetbucket();
                });
            });
        </script>
        <?php
    }
}