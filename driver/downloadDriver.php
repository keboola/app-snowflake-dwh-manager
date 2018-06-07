<?php
date_default_timezone_set('Europe/Prague');
ini_set('display_errors', true);
error_reporting(E_ALL);

$basedir = dirname(__DIR__);

require_once $basedir . '/vendor/autoload.php';

$client =  new \Aws\S3\S3Client([
    'region' => 'us-east-1',
    'version' => '2006-03-01',
    'credentials' => [
        'key' => getenv('AWS_ACCESS_KEY'),
        'secret' => getenv('AWS_SECRET_KEY'),
    ],
]);

$client->getObject([
    'Bucket' => 'keboola-configs',
    'Key' => 'drivers/snowflake/snowflake-odbc-2.13.17.x86_64.deb',
    'SaveAs' => './snowflake-odbc-x86_64.deb'
]);

$client->getObject([
    'Bucket' => 'keboola-configs',
    'Key' => 'drivers/snowflake-snowsql/snowsql-1.1.50-linux_x86_64.bash',
    'SaveAs' => './snowsql-linux_x86_64.bash'
]);
