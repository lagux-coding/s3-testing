<?php
class S3Testing_MySQLDump
{
    public $tables_to_dump = [];
    private $mysqli;
    private $connected = false;
    private $handle;
    private $table_types = [];
    private $table_status = [];
    private $dbname = '';
    public function __construct($args = [])
    {
        if (!class_exists(\mysqli::class)) {
            throw new S3testing_MySQLDump_Exception(__('No MySQLi extension found. Please install it.'));
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $args = $resolver->resolve($args);

        $driver = new mysqli_driver();
        $mode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

        $this->connect($args);

        $driver->report_mode = $mode;

        //set charset
        if (!empty($args['dbcharset'])) {
            $this->setCharset($args['dbcharset']);
        }

        //open file if set
        if ($args['dumpfile']) {
                $this->handle = fopen($args['dumpfile'], 'ab');
        } else {
            $this->handle = $args['dumpfilehandle'];
        }

        //check file handle
        if (!$this->handle) {
            throw new S3Testing_MySQLDump_Exception(__('Cannot open SQL backup file'));
        }

        //get table info
        $res = $this->mysqli->query('SHOW TABLE STATUS FROM `' . $this->dbname . '`');
        ++$GLOBALS[\wpdb::class]->num_queries;
        if ($this->mysqli->error) {
            throw new S3Testing_MySQLDump_Exception(sprintf(__('Database error %1$s for query %2$s'), $this->mysqli->error, 'SHOW TABLE STATUS FROM `' . $this->dbname . '`'));
        }

        while ($tablestatus = $res->fetch_assoc()) {
            $this->table_status[$tablestatus['Name']] = $tablestatus;
        }
        $res->close();

        //get table names and types from Database
        $res = $this->mysqli->query('SHOW FULL TABLES FROM `' . $this->dbname . '`');
        ++$GLOBALS[\wpdb::class]->num_queries;
        if ($this->mysqli->error) {
            throw new S3testing_MySQLDump_Exception(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), $this->mysqli->error, 'SHOW FULL TABLES FROM `' . $this->dbname . '`'));
        }

        while ($table = $res->fetch_array(MYSQLI_NUM)) {
            $this->table_types[$table[0]] = $table[1];
            $this->tables_to_dump[] = $table[0];
            if ($table[1] === 'VIEW') {
                $this->views_to_dump[] = $table[0];
                $this->table_status[$table[0]]['Rows'] = 0;
            }
        }
        $res->close();
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'dbhost' => DB_HOST,
            'dbport' => null,
            'dbsocket' => null,
            'dbname' => DB_NAME,
            'dbuser' => DB_USER,
            'dbpassword' => DB_PASSWORD,
            'dbcharset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
            'dumpfilehandle' => fopen('php://output', 'wb'),
            'dumpfile' => null,
            'dbclientflags' => defined('MYSQL_CLIENT_FLAGS') ? MYSQL_CLIENT_FLAGS : 0,
            'compression' => function (Options $options) {
                if ($options['dumpfile'] !== null
                    && substr(strtolower((string) $options['dumpfile']), -3) === '.gz') {

                }

            },
        ]);

        $port = $socket = null;

        $resolver->setNormalizer('dbhost', function (Options $options, $value) use (&$port, &$socket) {
            if (strpos($value, ':') !== false) {
                [$value, $part] = array_map('trim', explode(':', $value, 2));
                if (is_numeric($part)) {
                    $port = intval($part);
                } elseif (!empty($part)) {
                    $socket = $part;
                }
            }

            return $value ?: 'localhost';
        });

        $resolver->setDefault('dbport', function (Options $options) use (&$port) {
            return $port;
        });

        $resolver->setDefault('dbsocket', function (Options $options) use (&$socket) {
            return $socket;
        });

        $resolver->setAllowedValues('dumpfilehandle', function ($value) {
            // Ensure handle is writable
            $metadata = stream_get_meta_data($value);

            return !($metadata['mode'][0] === 'r' && strpos($metadata['mode'], '+') === false);
        });

        $resolver->setAllowedTypes('dbhost', 'string');
        $resolver->setAllowedTypes('dbport', ['null', 'int']);
        $resolver->setAllowedTypes('dbsocket', ['null', 'string']);
        $resolver->setAllowedTypes('dbname', 'string');
        $resolver->setAllowedTypes('dbuser', 'string');
        $resolver->setAllowedTypes('dbpassword', 'string');
        $resolver->setAllowedTypes('dbcharset', ['null', 'string']);
        $resolver->setAllowedTypes('dumpfilehandle', 'resource');
        $resolver->setAllowedTypes('dumpfile', ['null', 'string']);
        $resolver->setAllowedTypes('dbclientflags', 'int');
    }

    public function isConnected()
    {
        return $this->connected === true;
    }

    protected function getConnection()
    {
        if ($this->mysqli === null) {
            $this->mysqli = mysqli_init();
        }

        return $this->mysqli;
    }

    protected function connect(array $args)
    {
        if ($this->isConnected()) {
            return;
        }

        $mysqli = $this->getConnection();

        if (!$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5)) {
            trigger_error(__('Setting of MySQLi connection timeout failed'), E_USER_WARNING); // phpcs:ignore
        }

        //connect to Database
        try {
            $mysqli->real_connect(
                $args['dbhost'],
                $args['dbuser'],
                $args['dbpassword'],
                $args['dbname'],
                $args['dbport'],
                $args['dbsocket'],
                $args['dbclientflags']
            );
        } catch (\mysqli_sql_exception $e) {

        }

        //set db name
        $this->dbname = $args['dbname'];

        // We are now connected
        $this->connected = true;
    }

    public function setCharset($charset)
    {
        if ($charset === 'utf8' && $this->getConnection()->set_charset('utf8mb4') === true) {
            return 'utf8mb4';
        }
        if ($this->getConnection()->set_charset($charset) === true) {
            return $charset;
        }
        if ($charset === 'utf8mb4' && $this->getConnection()->set_charset('utf8') === true) {
            return 'utf8';
        }

        return false;
    }
}

class S3Testing_MySQLDump_Exception extends Exception
{

}