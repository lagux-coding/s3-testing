<?php
final class S3Testing_Option
{
    public static function default_site_options()
    {
        add_site_option( 'backwpup_jobs', [] );
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
//                $cache_key = "{$network_id}:backwpup_jobs";
//                wp_cache_delete($cache_key, 'site-options');
//            } else {
//                wp_cache_delete('backwpup_jobs', 'options');
//                $alloptions = wp_cache_get('alloptions', 'options');
//                if (isset($alloptions['backwpup_jobs'])) {
//                    unset($alloptions['backwpup_jobs']);
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
        $default['type'] = ['FILE'];
        $default['destinations'] = ['S3'];
        $default['name'] = 'New Job';
        $default['activetype'] = '';
        $default['logfile'] = '';
        $default['lastbackupdownloadurl'] = '';
        $default['cronselect']            = 'basic';
        $default['cron']                  = '0 3 * * *';
        $default['mailaddresslog']        = sanitize_email( get_bloginfo( 'admin_email' ) );
        $default['mailaddresssenderlog']  = 'BackWPup ' . get_bloginfo( 'name' ) . ' <' . sanitize_email( get_bloginfo( 'admin_email' ) ) . '>';
        $default['mailerroronly']         = true;
        $default['backuptype']            = 'archive';
        $default['archiveformat']         = '.tar';
        $default['archivename']           = '%Y-%m-%d_%H-%i-%s_%hash%';
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
}