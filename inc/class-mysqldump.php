<?php
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
class S3Testing_MySQLDump
{
    public $tables_to_dump = [];
    private $mysqli;
    private $connected = false;
    private $handle;
    private $table_types = [];
    private $table_status = [];
    private $dbname = '';
    public $views_to_dump = [];
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

    public function dump_table($table, $start = 0, $length = 0)
    {
        if (!is_numeric($start) || $start < 0) {
            throw new S3Testing_MySQLDump_Exception(sprintf(__('Start for table backup is not correctly set: %1$s'), $start));
        }

        if (!is_numeric($length) || $length < 0) {
            throw new S3Testing_MySQLDump_Exception(sprintf(__('Length for table backup is not correctly set: %1$s'), $length));
        }

        $done_records = 0;

        if ($this->get_table_type_for($table) === 'VIEW') {
            return $done_records;
        }

        //get data from table
        try {
            $res = $this->do_table_query($table, $start, $length);
        } catch (S3Testing_MySQLDump_Exception $e) {

            return 0;
        }

        $fieldsarray = [];
        $fieldinfo = [];
        $fields = $res->fetch_fields();
        $i = 0;

        foreach ($fields as $field) {
            $fieldsarray[$i] = $field->orgname;
            $fieldinfo[$fieldsarray[$i]] = $field;
            ++$i;
        }
        $dump = '';
        while ($data = $res->fetch_assoc()) {
            $values = [];

            foreach ($data as $key => $value) {
                if ($value === null) { // Make Value NULL to string NULL
                    $value = 'NULL';
                } elseif (in_array((int) $fieldinfo[$key]->type, [MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL, MYSQLI_TYPE_BIT, MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_YEAR], true)) {//is value numeric no esc
                    $value = empty($value) ? 0 : $value;
                } elseif (in_array((int) $fieldinfo[$key]->type, [MYSQLI_TYPE_TIMESTAMP, MYSQLI_TYPE_DATE, MYSQLI_TYPE_TIME, MYSQLI_TYPE_DATETIME, MYSQLI_TYPE_NEWDATE], true)) {//date/time types
                    $value = "'{$value}'";
                } elseif ($fieldinfo[$key]->flags & MYSQLI_BINARY_FLAG) {//is value binary
                    $hex = unpack('H*', $value);
                    $value = empty($value) ? "''" : "0x{$hex[1]}";
                } else {
                    $value = "'" . $this->escapeString($value) . "'";
                }
                $values[] = $value;
            }
            //new query in dump on more than 50000 chars.
            if (empty($dump)) {
                $dump = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $fieldsarray) . "`) VALUES \n";
            }
            if (strlen($dump) <= 50000) {
                $dump .= '(' . implode(', ', $values) . "),\n";
            } else {
                $dump .= '(' . implode(', ', $values) . ");\n";
                $this->write($dump);
                $dump = '';
            }
            ++$done_records;
        }
        if (!empty($dump)) {
            // Remove trailing , and newline.
            $dump = substr($dump, 0, -2) . ";\n";
            $this->write($dump);
        }
        $res->close();

        return $done_records;
    }

    public function dump_table_footer($table): void
    {
        if ($this->table_status[$table]['Rows'] !== 0) {
            $this->write('/*!40000 ALTER TABLE `' . $table . "` ENABLE KEYS */;\nUNLOCK TABLES;\n");
        }
    }

    public function dump_footer()
    {

        //dump Views
        foreach ($this->views_to_dump as $view) {
            $this->dump_view_table_head($view);
        }

        //dump procedures and functions
        $this->write("\n--\n-- Backup routines for database '" . $this->dbname . "'\n--\n");

        // Temporarily set mysqli to throw exceptions
        $driver = new \mysqli_driver();
        $mode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

        // Dump Functions
        $this->dump_functions();

        // Dump Procedures
        $this->dump_procedures();

        // Dump Triggers
        $this->dump_triggers();

        // Restore report mode for other methods that do not support exceptions yet
        // This should be changed ASAP to support SQL exceptions globally
        $driver->report_mode = $mode;

        //for better import with mysql client
        $dbdumpfooter = "\n/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n";
        $dbdumpfooter .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
        $dbdumpfooter .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
        $dbdumpfooter .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n";
        $dbdumpfooter .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
        $dbdumpfooter .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
        $dbdumpfooter .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
        $dbdumpfooter .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";
        $dbdumpfooter .= "\n-- Backup completed on " . date('Y-m-d H:i:s', current_time('timestamp')) . "\n";
        $this->write($dbdumpfooter);
    }

    public function dump_view_table_head($view): void
    {
        //Dump the view structure
        $res = $this->mysqli->query('SHOW CREATE VIEW `' . $view . '`');
        ++$GLOBALS[\wpdb::class]->num_queries;
        if ($this->mysqli->error) {

        } else {
            $createview = $res->fetch_assoc();
            $res->close();
            $tablecreate = "\n--\n-- View structure for `" . $view . "`\n--\n\n";
            $tablecreate .= 'DROP TABLE IF EXISTS `' . $view . "`;\n";
            $tablecreate .= 'DROP VIEW IF EXISTS `' . $view . "`;\n";
            $tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
            $tablecreate .= "/*!40101 SET character_set_client = '" . $this->mysqli->character_set_name() . "' */;\n";
            $tablecreate .= $createview['Create View'] . ";\n";
            $tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;\n";
            $this->write($tablecreate);
        }
    }

    protected function dump_functions()
    {
        try {
            $statusResult = $this->query('SHOW FUNCTION STATUS');
        } catch (\mysqli_sql_exception $e) {
            $this->logQueryError($e, 'SHOW FUNCTION STATUS');

            return;
        }

        while ($function = $statusResult->fetch_assoc()) {
            if ($this->getDbName() !== $function['Db']) {
                continue;
            }

            $query = sprintf(
                'SHOW CREATE FUNCTION `%1$s`.`%2$s`',
                $function['Db'],
                $function['Name']
            );

            try {
                $createResult = $this->query($query);
                $createFunction = $createResult->fetch_assoc();
                $createResult->close();

                if ($createFunction === null) {
                    continue;
                }

                $sql = sprintf(
                    "\n--\n" .
                    "-- Function structure for %1\$s\n" .
                    "--\n\n" .
                    "/*!50003 DROP FUNCTION IF EXISTS `%1\$s` */;\n" .
                    "/*!50003 SET @saved_cs_client      = @@character_set_client */ ;\n" .
                    "/*!50003 SET @saved_cs_results     = @@character_set_results */ ;\n" .
                    "/*!50003 SET @saved_col_connection = @@collation_connection */ ;\n" .
                    "/*!50003 SET character_set_client  = %2\$s */ ;\n" .
                    "/*!50003 SET character_set_results = %2\$s */ ;\n" .
                    "/*!50003 SET collation_connection  = %3\$s */ ;\n" .
                    "/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;\n" .
                    "/*!50003 SET sql_mode              = '%4\$s' */ ;\n" .
                    "DELIMITER ;;\n" .
                    "%5\$s ;;\n" .
                    "DELIMITER ;\n" .
                    "/*!50003 SET sql_mode              = @saved_sql_mode */ ;\n" .
                    "/*!50003 SET character_set_client  = @saved_cs_client */ ;\n" .
                    "/*!50003 SET character_set_results = @saved_cs_results */ ;\n" .
                    "/*!50003 SET collation_connection  = @saved_col_connection */ ;\n",
                    $createFunction['Function'],
                    $createFunction['character_set_client'],
                    $createFunction['collation_connection'],
                    $createFunction['sql_mode'],
                    $createFunction['Create Function']
                );
                $this->write($sql);
            } catch (\mysqli_sql_exception $e) {
                $this->logQueryError($e, $query);
            }
        }

        $statusResult->close();
    }

    protected function dump_procedures()
    {
        try {
            $statusResult = $this->query('SHOW PROCEDURE STATUS');
        } catch (mysqli_sql_exception $e) {
            $this->logQueryError($e, 'SHOW PROCEDURE STATUS');

            return;
        }

        while ($procedure = $statusResult->fetch_assoc()) {
            if ($this->getDbName() !== $procedure['Db']) {
                continue;
            }

            $query = sprintf(
                'SHOW CREATE PROCEDURE `%1$s`.`%2$s`',
                $procedure['Db'],
                $procedure['Name']
            );

            try {
                $createResult = $this->query($query);
                $createProcedure = $createResult->fetch_assoc();
                $createResult->close();

                if ($createProcedure === null) {
                    continue;
                }

                $sql = sprintf(
                    "\n--\n" .
                    "-- Procedure structure for %1\$s\n" .
                    "--\n\n" .
                    "/*!50003 DROP PROCEDURE IF EXISTS `%1\$s` */;\n" .
                    "/*!50003 SET @saved_cs_client      = @@character_set_client */ ;\n" .
                    "/*!50003 SET @saved_cs_results     = @@character_set_results */ ;\n" .
                    "/*!50003 SET @saved_col_connection = @@collation_connection */ ;\n" .
                    "/*!50003 SET character_set_client  = %2\$s */ ;\n" .
                    "/*!50003 SET character_set_results = %2\$s */ ;\n" .
                    "/*!50003 SET collation_connection  = %3\$s */ ;\n" .
                    "/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;\n" .
                    "/*!50003 SET sql_mode              = '%4\$s' */ ;\n" .
                    "DELIMITER ;;\n" .
                    "%5\$s ;;\n" .
                    "DELIMITER ;\n" .
                    "/*!50003 SET sql_mode              = @saved_sql_mode */ ;\n" .
                    "/*!50003 SET character_set_client  = @saved_cs_client */ ;\n" .
                    "/*!50003 SET character_set_results = @saved_cs_results */ ;\n" .
                    "/*!50003 SET collation_connection  = @saved_col_connection */ ;\n",
                    $createProcedure['Procedure'],
                    $createProcedure['character_set_client'],
                    $createProcedure['collation_connection'],
                    $createProcedure['sql_mode'],
                    $createProcedure['Create Procedure']
                );
                $this->write($sql);
            } catch (mysqli_sql_exception $e) {
                $this->logQueryError($e, $query);
            }
        }

        $statusResult->close();
    }

    protected function dump_triggers()
    {
        $query = sprintf('SHOW TRIGGERS FROM `%1$s`', $this->getDbName());

        try {
            $statusResult = $this->query($query);
        } catch (\mysqli_sql_exception $e) {
            $this->logQueryError($e, $query);

            return;
        }

        while ($trigger = $statusResult->fetch_assoc()) {
            $query = sprintf(
                'SHOW CREATE TRIGGER `%1$s`.`%2$s`',
                $this->getDbName(),
                $trigger['Trigger']
            );

            try {
                $createResult = $this->query($query);
                $createTrigger = $createResult->fetch_assoc();
                $createResult->close();

                if ($createTrigger === null) {
                    continue;
                }

                $sql = sprintf(
                    "\n--\n" .
                    "-- Trigger structure for %1\$s\n" .
                    "--\n\n" .
                    "/*!50032 DROP TRIGGER IF EXISTS `%1\$s` */;\n" .
                    "/*!50003 SET @saved_cs_client      = @@character_set_client */ ;\n" .
                    "/*!50003 SET @saved_cs_results     = @@character_set_results */ ;\n" .
                    "/*!50003 SET @saved_col_connection = @@collation_connection */ ;\n" .
                    "/*!50003 SET character_set_client  = %2\$s */ ;\n" .
                    "/*!50003 SET character_set_results = %2\$s */ ;\n" .
                    "/*!50003 SET collation_connection  = %3\$s */ ;\n" .
                    "/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;\n" .
                    "/*!50003 SET sql_mode              = '%4\$s' */ ;\n" .
                    "DELIMITER ;;\n" .
                    "/*!50003 %5\$s */;;\n" .
                    "DELIMITER ;\n" .
                    "/*!50003 SET sql_mode              = @saved_sql_mode */ ;\n" .
                    "/*!50003 SET character_set_client  = @saved_cs_client */ ;\n" .
                    "/*!50003 SET character_set_results = @saved_cs_results */ ;\n" .
                    "/*!50003 SET collation_connection  = @saved_col_connection */ ;\n",
                    $createTrigger['Trigger'],
                    $createTrigger['character_set_client'],
                    $createTrigger['collation_connection'],
                    $createTrigger['sql_mode'],
                    $createTrigger['SQL Original Statement']
                );
                $this->write($sql);
            } catch (\mysqli_sql_exception $e) {
                $this->logQueryError($e, $query);
            }
        }

        $statusResult->close();
    }

    protected function getDbName()
    {
        return $this->dbname;
    }

    protected function query($sql)
    {
        $result = $this->getConnection()->query($sql);
        s3testing_wpdb()->num_queries++;

        return $result;
    }

    protected function write($data)
    {
        $written = fwrite($this->handle, (string) $data);

        if (!$written) {
            throw new S3Testing_MySQLDump_Exception(__('Error while writing file!'));
        }
    }

    protected function get_table_type_for($table)
    {
        if (isset($this->table_types[$table])) {
            return $this->table_types[$table];
        }

        return null;
    }

    protected function do_table_query($table, $start, $length)
    {
        if ($length == 0 && $start == 0) {
            $res = $this->mysqli->query('SELECT * FROM `' . $table . '` ', MYSQLI_USE_RESULT);
        } else {
            $res = $this->mysqli->query('SELECT * FROM `' . $table . '` LIMIT ' . $start . ', ' . $length, MYSQLI_USE_RESULT);
        }
        ++$GLOBALS[\wpdb::class]->num_queries;
        if ($this->mysqli->error) {
            throw new BackWPup_MySQLDump_Exception($this->mysqli->error);
        }

        return $res;
    }

    protected function escapeString($value)
    {
        return $this->mysqli->real_escape_string($value);
    }
}

class S3Testing_MySQLDump_Exception extends Exception
{

}