<?php
class S3Testing_Destination_Downloader
{
    public const STATE_DOWNLOADING = 'downloading';
    public const STATE_ERROR = 'error';
    public const STATE_DONE = 'done';
    private const OPTION_BASE_URL = 's3base_url';
    private const OPTION_BUCKET = 's3bucket';
    private const OPTION_ACCESS_KEY = 's3accesskey';
    private const OPTION_SECRET_KEY = 's3secretkey';
    public $data;
    public $destination;
    public static function download_by_ajax()
    {
        $dest = (string) filter_input(INPUT_GET, 'destination');
        if(!$dest) {
            return;
        }

        $jobid = (int) filter_input(INPUT_GET, 'jobid', FILTER_SANITIZE_NUMBER_INT);
        if(!$jobid) {
            return;
        }

        $file = (string) filter_input(INPUT_GET, 'file');
        if(!$file) {
            return;
        }

        set_time_limit(0);
        //eventsource headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');

        echo ':' . str_repeat(' ', 2048) . "\n\n";

        // Ensure we're not buffered.
        wp_ob_end_flush_all();
        flush();

        $temp_file = self::download_to_temp($jobid, $file);
    }

    public static function download_file()
    {
        $file = (string) filter_input(INPUT_GET, 'local_file');
        if (!$file) {
            wp_die('No file specified.');
        }

        check_ajax_referer('s3testing_action_nonce', 'nonce');

        $temp_file = S3Testing::get_plugin_data('temp') . '/' . basename($file);
        if (!file_exists($temp_file)) {
            wp_die('File not found.');
        }

        @set_time_limit(300);
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-tar');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($temp_file));

        ob_end_clean();
        readfile($temp_file);
        unlink($temp_file);
        exit();

    }

    private static function download_to_temp($jobid, $file)
    {
        $s3 = S3Testing_S3_Destination::fromJobId($jobid);
        $s3Client = $s3->client(
            S3Testing_Option::get($jobid, self::OPTION_ACCESS_KEY),
            S3Testing_Option::get($jobid, self::OPTION_SECRET_KEY)
        );

        $bucket = S3Testing_Option::get($jobid, self::OPTION_BUCKET);
        $key = $file;

        try {
            $temp_dir = S3Testing::get_plugin_data('temp') . '/';
            $temp_file = $temp_dir . basename($file);

            if($temp_file) {
                unlink($temp_file);
            }

            $fileSize = $s3Client->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ])['ContentLength'];

            $chunkSize = 2 * 1024 * 1024; // 2MB
            $start = 0;

            while ($start < $fileSize) {
                $end = min($start + $chunkSize - 1, $fileSize - 1);
                $result = $s3Client->getObject([
                    'Bucket' => $bucket,
                    'Key'    => $key,
                    'Range'  => "bytes=$start-$end",
                ]);

                file_put_contents($temp_file, $result['Body'], FILE_APPEND);

                self::send_message([
                    'state' => self::STATE_DOWNLOADING,
                    'download_percent' => round(($end + 1) / $fileSize * 100),
                ]);

                flush();
                if (($end + 1) >= $fileSize) {
                    break;
                }
                $start += $chunkSize;
            }

            self::send_message([
                'state' => self::STATE_DONE,
                'message' => esc_html__('Your download is being generated &hellip;'),
            ]);

        } catch (AwsException $e) {
            self::send_message([
                'state' => self::STATE_ERROR,
                'message' => $e->getMessage(),
            ], 'log');
        }
    }

    private static function send_message($data, $event = 'message')
    {
        echo "event: {$event}\n";
        echo 'data: ' . wp_json_encode($data) . "\n\n";
        flush();
    }
}