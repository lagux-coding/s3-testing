<?php
use Aws\S3\S3Client;
class S3Testing_S3_Destination
{

    private $options = [];
    public function __construct($options)
    {
        $default = [
            'label' => __('Custom S3 destination'),
            'endpoint' => '',
            'region' => 'us-west-1',
            'multipart' => true,
            'only_path_style_bucket' => true,
            'version' => 'latest',
            'signature' => 'v4',
        ];

        $this->options = array_merge($default, $options);
    }

    public static function options()
    {
        return apply_filters(
            's3testing_s3_destination',
            [
                'us-east-2' => [
                    'label' => __('Amazon S3: US East (Ohio)'),
                    'region' => 'us-east-2',
                    'multipart' => true,
                ],
                'us-east-1' => [
                    'label' => __('Amazon S3: US East (N. Virginia)'),
                    'region' => 'us-east-1',
                    'multipart' => true,
                ],
                'us-west-1' => [
                    'label' => __('Amazon S3: US West (N. California)'),
                    'region' => 'us-west-1',
                    'multipart' => true,
                ],
                'us-west-2' => [
                    'label' => __('Amazon S3: US West (Oregon)'),
                    'region' => 'us-west-2',
                    'multipart' => true,
                ],
                'af-south-1' => [
                    'label' => __('Amazon S3: Africa (Cape Town)'),
                    'region' => 'af-south-1',
                    'multipart' => true,
                ],
                'ap-east-1' => [
                    'label' => __('Amazon S3: Asia Pacific (Hong Kong)'),
                    'region' => 'ap-east-1',
                    'multipart' => true,
                ],
                'ap-southeast-3' => [
                    'label' => __('Amazon S3: Asia Pacific (Jakarta)'),
                    'region' => 'ap-southeast-3',
                    'multipart' => true,
                ],
                'ap-south-1' => [
                    'label' => __('Amazon S3: Asia Pacific (Mumbai)'),
                    'region' => 'ap-south-1',
                    'multipart' => true,
                ],
                'ap-northeast-3' => [
                    'label' => __('Amazon S3: Asia Pacific (Osaka)'),
                    'region' => 'ap-northeast-3',
                    'multipart' => true,
                ],
                'ap-northeast-2' => [
                    'label' => __('Amazon S3: Asia Pacific (Seoul)'),
                    'region' => 'ap-northeast-2',
                    'multipart' => true,
                ],
                'ap-southeast-1' => [
                    'label' => __('Amazon S3: Asia Pacific (Singapore)'),
                    'region' => 'ap-southeast-1',
                    'multipart' => true,
                ],
                'ap-southeast-2' => [
                    'label' => __('Amazon S3: Asia Pacific (Sydney)'),
                    'region' => 'ap-southeast-2',
                    'multipart' => true,
                ],
                'ap-northeast-1' => [
                    'label' => __('Amazon S3: Asia Pacific (Tokyo)'),
                    'region' => 'ap-northeast-1',
                    'multipart' => true,
                ],
                'ca-central-1' => [
                    'label' => __('Amazon S3: Canada (Central)'),
                    'region' => 'ca-central-1',
                    'multipart' => true,
                ],
                'eu-central-1' => [
                    'label' => __('Amazon S3: Europe (Frankfurt)'),
                    'region' => 'eu-central-1',
                    'multipart' => true,
                ],
                'eu-west-1' => [
                    'label' => __('Amazon S3: Europe (Ireland)'),
                    'region' => 'eu-west-1',
                    'multipart' => true,
                ],
                'eu-west-2' => [
                    'label' => __('Amazon S3: Europe (London)'),
                    'region' => 'eu-west-2',
                    'multipart' => true,
                ],
                'eu-south-1' => [
                    'label' => __('Amazon S3: Europe (Milan)'),
                    'region' => 'eu-south-1',
                    'multipart' => true,
                ],
                'eu-west-3' => [
                    'label' => __('Amazon S3: Europe (Paris)'),
                    'region' => 'eu-west-3',
                    'multipart' => true,
                ],
                'eu-north-1' => [
                    'label' => __('Amazon S3: Europe (Stockholm)'),
                    'region' => 'eu-north-1',
                    'multipart' => true,
                ],
                'me-south-1' => [
                    'label' => __('Amazon S3: Middle East (Bahrain)'),
                    'region' => 'me-south-1',
                    'multipart' => true,
                ],
                'sa-east-1' => [
                    'label' => __('Amazon S3: South America (São Paulo)'),
                    'region' => 'sa-east-1',
                    'multipart' => true,
                ],
                'us-gov-east-1' => [
                    'label' => __('Amazon S3: AWS GovCloud (US-East)'),
                    'region' => 'us-gov-east-1',
                    'multipart' => true,
                ],
                'us-gov-west-1' => [
                    'label' => __('Amazon S3: AWS GovCloud (US-West)'),
                    'region' => 'us-gov-west-1',
                    'multipart' => true,
                ],
                'google-storage' => [
                    'label' => __('Google Storage: EU (Multi-Regional)'),
                    'region' => 'EU',
                    'endpoint' => 'https://storage.googleapis.com',
                ],
                'google-storage-us' => [
                    'label' => __('Google Storage: USA (Multi-Regional)'),
                    'region' => 'US',
                    'endpoint' => 'https://storage.googleapis.com',
                ],
                'google-storage-asia' => [
                    'label' => __('Google Storage: Asia (Multi-Regional)'),
                    'region' => 'ASIA',
                    'endpoint' => 'https://storage.googleapis.com',
                ],
                'dreamhost' => [
                    'label' => __('Dream Host Cloud Storage'),
                    'endpoint' => 'https://objects-us-west-1.dream.io',
                ],
                'digital-ocean-sfo2' => [
                    'label' => __('DigitalOcean: SFO2'),
                    'endpoint' => 'https://sfo2.digitaloceanspaces.com',
                ],
                'digital-ocean-nyc3' => [
                    'label' => __('DigitalOcean: NYC3'),
                    'endpoint' => 'https://nyc3.digitaloceanspaces.com',
                ],
                'digital-ocean-ams3' => [
                    'label' => __('DigitalOcean: AMS3'),
                    'endpoint' => 'https://ams3.digitaloceanspaces.com',
                ],
                'digital-ocean-sgp1' => [
                    'label' => __('DigitalOcean: SGP1'),
                    'endpoint' => 'https://sgp1.digitaloceanspaces.com',
                ],
                'digital-ocean-fra1' => [
                    'label' => __('DigitalOcean: FRA1'),
                    'endpoint' => 'https://fra1.digitaloceanspaces.com',
                ],
                'scaleway-ams' => [
                    'label' => __('Scaleway: AMS'),
                    'region' => 'nl-ams',
                    'endpoint' => 'https://s3.nl-ams.scw.cloud',
                ],
                'scaleway-par' => [
                    'label' => __('Scaleway: PAR'),
                    'region' => 'fr-par',
                    'endpoint' => 'https://s3.fr-par.scw.cloud',
                ],
            ]
        );
    }

    public function client($accessKey, $secretKey)
    {
        $s3Options = [
            'signature' => $this->signature(),
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
            'region' => $this->region(),
            'http' => [
                'verify' => false,
            ],
            'version' => $this->version(),
            'use_path_style_endpoint' => $this->onlyPathStyleBucket(),
        ];

        if ($this->endpoint()) {
            $s3Options['endpoint'] = $this->endpoint();
            if (!$this->region()) {
                $s3Options['bucket_endpoint'] = true;
            }
        }

        $s3Options = apply_filters('s3_testing_options', $s3Options);

        return new S3Client($s3Options);
    }

    public static function fromOption($idOrUrl)
    {
        $destinations = self::options();
        return new self($destinations[$idOrUrl]);
    }

    public static function fromOptionArray($optionsArr)
    {
        return new self($optionsArr);
    }

    public static function fromJobId($jobId)
    {
        $options = [
            'label' => __('Custom S3 destination'),
            'endpoint' => S3Testing_Option::get($jobId, 's3base_url'),
            'region' => S3Testing_Option::get($jobId, 's3region'),
        ];

        return self::fromOptionArray($options);
    }

    public function label()
    {
        return $this->options['label'];
    }

    public function region()
    {
        return $this->options['region'];
    }

    public function endpoint()
    {
        return $this->options['endpoint'];
    }

    public function version()
    {
        return $this->options['version'];
    }

    public function signature()
    {
        return $this->options['signature'];
    }

    public function supportsMultipart()
    {
        return (bool) $this->options['multipart'];
    }

    public function onlyPathStyleBucket()
    {
        return (bool) $this->options['only_path_style_bucket'];
    }

}