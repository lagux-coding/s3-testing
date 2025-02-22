<?php
class S3Testing_Install
{
    public static function activate()
    {

        if (is_multisite()) {
            add_site_option('s3testing_jobs', []);
        } else {
            add_option('s3testing_jobs', [], null, 'no');
        }

        wp_clear_scheduled_hook('s3testing_cron');

        //make new schedule
        $activejobs = S3Testing_Option::get_job_ids( 'activetype', 'wpcron' );
        if ( ! empty( $activejobs ) ) {
            foreach ( $activejobs as $id ) {
                $cron_next = S3Testing_Cron::cron_next( S3Testing_Option::get( $id, 'cron' ) );
                wp_schedule_single_event( $cron_next, 's3testing_cron', [ 'arg' => $id ] );
            }
        }

        if (!wp_next_scheduled('backwpup_check_cleanup')) {
            wp_schedule_event(time(), 'twicedaily', 's3testing_check_cleanup');
        }

        S3Testing_Option::default_site_options();

    }

    public static function deactivate() {

        wp_clear_scheduled_hook('s3testing_cron');
        $activejobs = S3Testing_Option::get_job_ids('activetype', 'wpcron');
        if (!empty($activejobs)) {
            foreach ($activejobs as $id) {
                wp_clear_scheduled_hook('s3testing_cron', ['arg' => $id]);
            }
        }

        wp_clear_scheduled_hook('s3testing_check_cleanup');
    }
}