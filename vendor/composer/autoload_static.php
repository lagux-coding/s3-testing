<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitcd624fb20f889334c9790014fe69762d
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
        'b067bc7112e384b61c701452d53a14a8' => __DIR__ . '/..' . '/mtdowling/jmespath.php/src/JmesPath.php',
        '8a9dc1de0ca7e01f3e08231539562f61' => __DIR__ . '/..' . '/aws/aws-sdk-php/src/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Component\\OptionsResolver\\' => 34,
        ),
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Http\\Client\\' => 16,
        ),
        'J' => 
        array (
            'JmesPath\\' => 9,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
        ),
        'B' => 
        array (
            'Base32\\' => 7,
        ),
        'A' => 
        array (
            'Aws\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Component\\OptionsResolver\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/options-resolver',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-factory/src',
            1 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-client/src',
        ),
        'JmesPath\\' => 
        array (
            0 => __DIR__ . '/..' . '/mtdowling/jmespath.php/src',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'Base32\\' => 
        array (
            0 => __DIR__ . '/..' . '/christian-riesen/base32/src',
        ),
        'Aws\\' => 
        array (
            0 => __DIR__ . '/..' . '/aws/aws-sdk-php/src',
        ),
    );

    public static $classMap = array (
        'AWS\\CRT\\Auth\\AwsCredentials' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/AwsCredentials.php',
        'AWS\\CRT\\Auth\\CredentialsProvider' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/CredentialsProvider.php',
        'AWS\\CRT\\Auth\\Signable' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/Signable.php',
        'AWS\\CRT\\Auth\\SignatureType' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/SignatureType.php',
        'AWS\\CRT\\Auth\\SignedBodyHeaderType' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/SignedBodyHeaderType.php',
        'AWS\\CRT\\Auth\\Signing' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/Signing.php',
        'AWS\\CRT\\Auth\\SigningAlgorithm' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/SigningAlgorithm.php',
        'AWS\\CRT\\Auth\\SigningConfigAWS' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/SigningConfigAWS.php',
        'AWS\\CRT\\Auth\\SigningResult' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/SigningResult.php',
        'AWS\\CRT\\Auth\\StaticCredentialsProvider' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Auth/StaticCredentialsProvider.php',
        'AWS\\CRT\\CRT' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/CRT.php',
        'AWS\\CRT\\HTTP\\Headers' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/HTTP/Headers.php',
        'AWS\\CRT\\HTTP\\Message' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/HTTP/Message.php',
        'AWS\\CRT\\HTTP\\Request' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/HTTP/Request.php',
        'AWS\\CRT\\HTTP\\Response' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/HTTP/Response.php',
        'AWS\\CRT\\IO\\EventLoopGroup' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/IO/EventLoopGroup.php',
        'AWS\\CRT\\IO\\InputStream' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/IO/InputStream.php',
        'AWS\\CRT\\Internal\\Encoding' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Internal/Encoding.php',
        'AWS\\CRT\\Internal\\Extension' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Internal/Extension.php',
        'AWS\\CRT\\Log' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Log.php',
        'AWS\\CRT\\NativeResource' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/NativeResource.php',
        'AWS\\CRT\\OptionValue' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Options.php',
        'AWS\\CRT\\Options' => __DIR__ . '/..' . '/aws/aws-crt-php/src/AWS/CRT/Options.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'S3Testing_Admin' => __DIR__ . '/../..' . '/inc/class-admin.php',
        'S3Testing_Adminbar' => __DIR__ . '/../..' . '/inc/class-adminbar.php',
        'S3Testing_Create_Archive' => __DIR__ . '/../..' . '/inc/class-create-archive.php',
        'S3Testing_Create_Archive_Exception' => __DIR__ . '/../..' . '/inc/class-create-archive-exception.php',
        'S3Testing_Cron' => __DIR__ . '/../..' . '/inc/class-cron.php',
        'S3Testing_Destination_Downloader' => __DIR__ . '/../..' . '/inc/class-destination-downloader.php',
        'S3Testing_Destination_S3' => __DIR__ . '/../..' . '/inc/class-destination-s3.php',
        'S3Testing_Directory' => __DIR__ . '/../..' . '/inc/class-directory.php',
        'S3Testing_File' => __DIR__ . '/../..' . '/inc/class-file.php',
        'S3Testing_Install' => __DIR__ . '/../..' . '/inc/class-install.php',
        'S3Testing_Job' => __DIR__ . '/../..' . '/inc/class-job.php',
        'S3Testing_JobType_DBDump' => __DIR__ . '/../..' . '/inc/class-jobtype-dbdump.php',
        'S3Testing_JobType_File' => __DIR__ . '/../..' . '/inc/class-jobtype-file.php',
        'S3Testing_JobTypes' => __DIR__ . '/../..' . '/inc/class-jobtypes.php',
        'S3Testing_MySQLDump' => __DIR__ . '/../..' . '/inc/class-mysqldump.php',
        'S3Testing_MySQLDump_Exception' => __DIR__ . '/../..' . '/inc/class-mysqldump.php',
        'S3Testing_Option' => __DIR__ . '/../..' . '/inc/class-option.php',
        'S3Testing_Page_Backups' => __DIR__ . '/../..' . '/inc/class-page-backups.php',
        'S3Testing_Page_EditJob' => __DIR__ . '/../..' . '/inc/class-page-editjob.php',
        'S3Testing_Page_Jobs' => __DIR__ . '/../..' . '/inc/class-page-jobs.php',
        'S3Testing_Page_S3Testing' => __DIR__ . '/../..' . '/inc/class-page-s3testing.php',
        'S3Testing_Path_Fixer' => __DIR__ . '/../..' . '/inc/class-path-fixer.php',
        'S3Testing_S3_Destination' => __DIR__ . '/../..' . '/inc/class-s3-destination.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitcd624fb20f889334c9790014fe69762d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitcd624fb20f889334c9790014fe69762d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitcd624fb20f889334c9790014fe69762d::$classMap;

        }, null, ClassLoader::class);
    }
}
