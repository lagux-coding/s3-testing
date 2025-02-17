<?php
use Base32\Base32;

final class S3Testing_Option
{
    public static function default_site_options()
    {
        add_site_option('s3testing_version', '1.0.0');
        add_site_option( 's3testing_jobs', [] );
    }

    public static function get($jobid, $option, $default = null, $use_cache = true)
    {
        $jobid = (int) $jobid;
        $option = sanitize_key(trim($option));

        if (empty($jobid) || empty($option)) {
            return false;
        }

        $jobs_options = self::jobs_options($use_cache);

        if (!isset($jobs_options[$jobid][$option]) && $default !== null) {
            return $default;
        }

        if (!isset($jobs_options[$jobid][$option])) {
            return self::defaults_job($option);
        }

        $option_value = $jobs_options[$jobid][$option];

        return $option_value;
    }

    public static function next_job_id()
    {
        $ids = self::get_job_ids();
        sort($ids);

        return end($ids) + 1;
    }

    public static function get_job_ids($key = null, $value = false)
    {
        $key = sanitize_key(trim((string) $key));
        $jobs_options = self::jobs_options(false);

        if (empty($jobs_options)) {
            return [];
        }

        //get option job ids
        if (empty($key)) {
            return array_keys($jobs_options);
        }

        //get option ids for option with the defined value
        $new_option_job_ids = [];

        foreach ($jobs_options as $id => $option) {
            if (isset($option[$key]) && $value == $option[$key]) {
                $new_option_job_ids[] = (int) $id;
            }
        }
        sort($new_option_job_ids);

        return $new_option_job_ids;
    }

    private static function jobs_options($use_cache = true)
    {
//        global $current_site;

//        //remove from cache
//        if (!$use_cache) {
//            if (is_multisite()) {
//                $network_id = $current_site->id;
//                $cache_key = "{$network_id}:s3testing_jobs";
//                wp_cache_delete($cache_key, 'site-options');
//            } else {
//                wp_cache_delete('s3testing_jobs', 'options');
//                $alloptions = wp_cache_get('alloptions', 'options');
//                if (isset($alloptions['s3testing_jobs'])) {
//                    unset($alloptions['s3testing_jobs']);
//                    wp_cache_set('alloptions', $alloptions, 'options');
//                }
//            }
//        }

        return get_site_option('s3testing_jobs', []);
    }

    public static function defaults_job($key = '')
    {
        $key = sanitize_key(trim($key));

        //set defaults
        $default = [];
        $default['type'] = ['FILE', 'DBDUMP'];
        $default['destinations'] = ['S3'];
        $default['name'] = 'New Job';
        $default['activetype'] = '';
        $default['logfile'] = '';
        $default['lastbackupdownloadurl'] = '';
        $default['cronselect']            = 'basic';
        $default['cron']                  = '0 3 * * *';
        $default['mailaddresslog']        = sanitize_email( get_bloginfo( 'admin_email' ) );
        $default['mailaddresssenderlog']  = 's3testing ' . get_bloginfo( 'name' ) . ' <' . sanitize_email( get_bloginfo( 'admin_email' ) ) . '>';
        $default['mailerroronly']         = true;
        $default['backuptype']            = 'archive';
        $default['archiveformat']         = '.tar';
        $default['archivename']           = 'my-backup';
        $default['archivenamenohash']     = '%Y-%m-%d_%H-%i-%s_%hash%';
        // defaults vor destinations.
        foreach ( S3Testing::get_registered_destinations() as $dest_key => $dest ) {
            if ( ! empty( $dest['class'] ) ) {
                $dest_object = S3Testing::get_destination( $dest_key );
                $default     = array_merge( $default, $dest_object->option_defaults() );
            }
        }
        //defaults vor job types
        foreach (S3Testing::get_job_types() as $job_type) {
            $default = array_merge($default, $job_type->option_defaults());
        }

        //return all
        if (empty($key)) {
            return $default;
        }
        //return one default setting
        if (isset($default[$key])) {
            return $default[$key];
        }

        return false;
    }

    public static function update_jobs_options($options)
    {
        return update_site_option('s3testing_jobs', $options);
    }

    public static function update($jobid, $option, $value)
    {
        $jobid = (int) $jobid;
        $option = sanitize_key(trim($option));

        if(empty($jobid) || empty($option)) {
            return false;
        }

        $job_options = self::jobs_options(false);
        $job_options[$jobid][$option] = $value;

        return self::update_jobs_options($job_options);
    }

    public static function delete_job($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $id = intval($id);
        $jobs_options = self::jobs_options(false);
        unset($jobs_options[$id]);

        return self::update_jobs_options($jobs_options);
    }

    public static function get_job($id, $use_cache = true)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $id = intval($id);
        $jobs_options = self::jobs_options($use_cache);
        if (isset($jobs_options[$id]['archivename'])) {
            $jobs_options[$id]['archivename'] = self::normalize_archive_name(
                $jobs_options[$id]['archivename'],
                $id,
                true
            );
        }

        $options = wp_parse_args($jobs_options[$id], self::defaults_job());

        return $options;
    }

    public static function normalize_archive_name($archive_name, $jobid, $substitute_hash = true)
    {
        $hash = S3Testing::get_plugin_data('hash');
        $generated_hash = self::get_generated_hash($jobid);

        // Does the string contain %hash%?
        if (strpos($archive_name, '%hash%') !== false) {
            if ($substitute_hash) {
                return str_replace('%hash%', $generated_hash, $archive_name);
            }
            // Nothing needs to be done since we don't have to substitute it.
            return $archive_name;
        }

        if ($substitute_hash) {
            return $archive_name . '_' . $generated_hash;
        }

        return $archive_name . '_%hash%';
    }

    public static function get_generated_hash($jobid)
    {
        return Base32::encode(pack(
                'H*',
                sprintf(
                    '%02x%06s%02x',
                    random_int(0, 255),
                    S3Testing::get_plugin_data('hash'),
                    random_int(0, 255)
                )
            )) .
            sprintf('%02d', $jobid);
    }
}