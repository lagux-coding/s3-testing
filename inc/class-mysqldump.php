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

    public function dump_head($wp_info = false)
    {
// get sql timezone
        $res = $this->mysqli->query('SELECT @@time_zone');
        ++$GLOBALS[\wpdb::class]->num_queries;
        $mysqltimezone = $res->fetch_row();
        $mysqltimezone = $mysqltimezone[0];
        $res->close();

        //For SQL always use \n as MySQL wants this on all platforms.
        $dbdumpheader = "-- ---------------------------------------------------------\n";
        $dbdumpheader .= '-- Backup with S3Testing ver.: ' . S3Testing::get_plugin_data('Version') . "\n";
        $dbdumpheader .= "-- https://github.com/lagux-coding\n";
        if ($wp_info) {
            $dbdumpheader .= '-- Blog Name: ' . get_bloginfo('name') . "\n";
            $dbdumpheader .= '-- Blog URL: ' . trailingslashit(get_bloginfo('url')) . "\n";
            $dbdumpheader .= '-- Blog ABSPATH: ' . trailingslashit(str_replace('\\', '/', (string) ABSPATH)) . "\n";
            $dbdumpheader .= '-- Blog Charset: ' . get_bloginfo('charset') . "\n";
            $dbdumpheader .= '-- Table Prefix: ' . $GLOBALS[\wpdb::class]->prefix . "\n";
        }
        $dbdumpheader .= '-- Database Name: ' . $this->dbname . "\n";
        $dbdumpheader .= '-- Backup on: ' . date('Y-m-d H:i.s', current_time('timestamp')) . "\n";
        $dbdumpheader .= "-- ---------------------------------------------------------\n\n";
        //for better import with mysql client
        $dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
        $dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
        $dbdumpheader .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
        $dbdumpheader .= '/*!40101 SET NAMES ' . $this->mysqli->character_set_name() . " */;\n";
        $dbdumpheader .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n";
        $dbdumpheader .= "/*!40103 SET TIME_ZONE='" . $mysqltimezone . "' */;\n";
        $dbdumpheader .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
        $dbdumpheader .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
        $dbdumpheader .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
        $dbdumpheader .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";
        $this->write($dbdumpheader);
    }

    public function dump_table_head($table)
    {
        //dump View
        if ($this->table_types[$table] === 'VIEW') {
            //Dump the view table structure
            $fields = [];
            $res = $this->mysqli->query('SELECT * FROM `' . $table . '` LIMIT 1');
            ++$GLOBALS[\wpdb::class]->num_queries;
            if ($this->mysqli->error) {
//                trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), $this->mysqli->error, 'SELECT * FROM `' . $table . '` LIMIT 1'), E_USER_WARNING);
            } else {
                $fields = $res->fetch_fields();
                $res->close();
            }
            if ($res) {
                $tablecreate = "\n--\n-- Temporary table structure for view `" . $table . "`\n--\n\n";
                $tablecreate .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
                $tablecreate .= '/*!50001 DROP VIEW IF EXISTS `' . $table . "`*/;\n";
                $tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
                $tablecreate .= "/*!40101 SET character_set_client = '" . $this->mysqli->character_set_name() . "' */;\n";
                $tablecreate .= 'CREATE TABLE `' . $table . "` (\n";

                foreach ($fields as $field) {
                    $tablecreate .= '  `' . $field->orgname . "` tinyint NOT NULL,\n";
                }
                $tablecreate = substr($tablecreate, 0, -2) . "\n";
                $tablecreate .= ");\n";
                $tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;\n";
                $this->write($tablecreate);
            }

            return 0;
        }

        //dump normal Table
        $tablecreate = "\n--\n-- Table structure for `" . $table . "`\n--\n\n";
        $tablecreate .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
        $tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
        $tablecreate .= "/*!40101 SET character_set_client = '" . $this->mysqli->character_set_name() . "' */;\n";
        //Dump the table structure
        $res = $this->mysqli->query('SHOW CREATE TABLE `' . $table . '`');
        ++$GLOBALS[\wpdb::class]->num_queries;
        if ($this->mysqli->error) {
            trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), $this->mysqli->error, 'SHOW CREATE TABLE `' . $table . '`'), E_USER_WARNING);
        } else {
            $createtable = str_replace( '"', '`', $res->fetch_assoc() );
            $res->close();
            $tablecreate .= $createtable['Create Table'] . ";\n";
            $tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;\n";
            $this->write($tablecreate);

            if ($this->table_status[$table]['Engine'] !== 'MyISAM') {
                $this->table_status[$table]['Rows'] = '~' . $this->table_status[$table]['Rows'];
            }

            if ($this->table_status[$table]['Rows'] !== 0) {
                //Dump Table data
                $this->write("\n--\n-- Backup data for table `" . $table . "`\n--\n\nLOCK TABLES `" . $table . "` WRITE;\n/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;\n");
            }

            return $this->table_status[$table]['Rows'];
        }

        return 0;
    }
}

class S3Testing_MySQLDump_Exception extends Exception
{

}