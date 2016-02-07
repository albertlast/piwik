<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Db\Adapter;

use Exception;
use Piwik\Config;
use Piwik\Db;
use Piwik\Db\AdapterInterface;
use Piwik\Piwik;
use Zend_Config;
use Zend_Db_Adapter_Pgsql;

/**
 */
class PgsqlDirect extends Zend_Db_Adapter_Pgsql implements AdapterInterface
{
    /**
     * Constructor
     *
     * @param array|Zend_Config $config database configuration
     */
    public function __construct($config)
    {
        // Enable LOAD DATA INFILE
        $config['driver_options'][MYSQLI_OPT_LOCAL_INFILE] = true;
        parent::__construct($config);
    }

    /**
     * Reset the configuration variables in this adapter.
     */
    public function resetConfig()
    {
        $this->_config = array();
    }

    /**
     * Return default port.
     *
     * @return int
     */
    public static function getDefaultPort()
    {
        return 5432;
    }

    protected function _connect()
    {
        if ($this->_connection) {
            return;
        }

        parent::_connect();

    }

    /**
     * Check MySQL version
     *
     * @throws Exception
     */
    public function checkServerVersion()
    {
        $serverVersion   = $this->getServerVersion();
        $requiredVersion = Config::getInstance()->General['minimum_pgsql_version'];

        if (version_compare($serverVersion, $requiredVersion) === -1) {
            throw new Exception(Piwik::translate('General_ExceptionDatabaseVersion', array('PGSql', $serverVersion, $requiredVersion)));
        }
    }

    /**
     * Check client version compatibility against database server
     *
     * @throws Exception
     */
    public function checkClientVersion()
    {
        $serverVersion = $this->getServerVersion();
        $clientVersion = $this->getClientVersion();
    }

    /**
     * Returns true if this adapter's required extensions are enabled
     *
     * @return bool
     */
    public static function isEnabled()
    {
        $extensions = @get_loaded_extensions();
        return in_array('pgsql', $extensions);
    }

    /**
     * Returns true if this adapter supports blobs as fields
     *
     * @return bool
     */
    public function hasBlobDataType()
    {
        return true;
    }

    /**
     * Returns true if this adapter supports bulk loading
     *
     * @return bool
     */
    public function hasBulkLoader()
    {
        return true;
    }

    /**
     * Test error number
     *
     * @param Exception $e
     * @param string $errno
     * @return bool
     */
    public function isErrNo($e, $errno)
    {
        if (is_null($this->_connection)) {
            if (preg_match('/(?:\[|\s)([0-9]{4})(?:\]|\s)/', $e->getMessage(), $match)) {
                return $match[1] == $errno;
            }
            return pg_connect_errno() == $errno;
        }

        return pg_last_error($this->_connection) == $errno;
    }

    /**
     * Execute unprepared SQL query and throw away the result
     *
     * Workaround some SQL statements not compatible with prepare().
     * See http://framework.zend.com/issues/browse/ZF-1398
     *
     * @param string $sqlQuery
     * @return int  Number of rows affected (SELECT/INSERT/UPDATE/DELETE)
     */
    public function exec($sqlQuery)
    {
	if(strpos($sqlQuery, 'OCK TABLES')) //no (UN)LOCK
		return 1;
		
	$sqlQuery = str_replace('`', '"', $sqlQuery);
	
	if(strpos($sqlQuery, 'OAD DATA INFILE')){
	    return $this->convertMysqlBulktoPG($sqlQuery);
	}
	
        $rc = pg_query($this->_connection, $sqlQuery);
        $rowsAffected = pg_affected_rows($rc);
        if (!is_bool($rc)) {
            pg_free_result($rc);
        }
        return $rowsAffected;
    }

    /**
     * Is the connection character set equal to utf8?
     *
     * @return bool
     */
    public function isConnectionUTF8()
    {
        $charset = pg_parameter_status($this->_connection,'server_encoding');
        return $charset === 'utf8';
    }

    /**
     * Get client version
     *
     * @return string
     */
    public function getClientVersion()
    {
        $this->_connect();

        $version  = $this->_connection->server_version;
        $major    = (int)($version / 10000);
        $minor    = (int)($version % 10000 / 100);
        $revision = (int)($version % 100);

        return $major . '.' . $minor . '.' . $revision;
    }
    
    private function convertMysqlBulktoPG($sqlQuery){
	preg_match("/\"(\w*)\"/", $sqlQuery, $targetTable);
	$targetTable = $targetTable[1];
	
	pg_query($this->_connection,'CREATE TEMP TABLE '.$targetTable.'_tmp as select * from '.$targetTable.' where 1=0');
	
	preg_match("/ESCAPED BY \'(\W*)\'/", $sqlQuery, $escape);
	$escape = $escape[1];
	
	preg_match("/ENCLOSED BY\W*\'(\W*)\'/", $sqlQuery, $enclosed);
	$enclosed = $enclosed[1];
	
	preg_match("/INFILE\W*\'(.*)\'/", $sqlQuery, $path);
	$path = $path[1];
	
	preg_match("/CHARACTER SET\W*(\w*)/", $sqlQuery, $encoding);
	$encoding = $encoding[1];
	
	preg_match("/\((.*)\)/", $sqlQuery, $col);
	$col = $col[1];
	
	$rc = pg_query($this->_connection,'SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS data_type
				    FROM   pg_index i
				    JOIN   pg_attribute a ON a.attrelid = i.indrelid
							 AND a.attnum = ANY(i.indkey)
				    WHERE  i.indrelid = \''.$targetTable.'\'::regclass
				    AND    i.indisprimary');
	$result = pg_fetch_array($rc);
	if (is_array($result) && count($result) > 0) {
	    $pkey = $result['attname'];
	}  else {
	    $pkey = '';
	}
	
	$arrayCol= explode(',', $col);
	$colCount=0;
	
	for($i = 0; $i < count($arrayCol); $i++){
	    if($arrayCol[$i]== $pkey)
		continue;
	    
	    if($colCount > 0)
		$excludedCol.=',';
	    $excludedCol .= $arrayCol[$i].' = EXCLUDED.'.$arrayCol[$i];
	    $colCount++;
	}
	
	$rc = pg_query($this->_connection,'copy "'.$targetTable.'_tmp"('.$col.') from \''.$path.'\' with encoding \''.$encoding.'\'');
	
	$inserSQL = 'insert into "'.$targetTable.'"('.$col.') select '.$col.''
		. ' FROM "'.$targetTable.'_tmp" ON CONFLICT('.$pkey.') DO UPDATE SET '.$excludedCol;
	
	$rc = pg_query($this->_connection,$inserSQL);
	$test = pg_affected_rows($rc);
	
	pg_query($this->_connection,'DROP TABLE '.$targetTable.'_tmp');
	
	return pg_affected_rows($rc);
	
    }
}
