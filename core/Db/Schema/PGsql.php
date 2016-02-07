<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Db\Schema;

use Exception;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db\SchemaInterface;
use Piwik\Db;
use Piwik\DbHelper;

/**
 * PGsql schema
 */
class PGsql implements SchemaInterface
{
    private $tablesInstalled = null;

    /**
     * Get the SQL to create Piwik tables
     *
     * @return array  array of strings containing SQL
     */
    public function getTablesCreateSql()
    {
        $prefixTables = $this->getTablePrefix();

        $tables = array(
            'user'    => 'CREATE TABLE {$prefixTables}user (
                          login VARCHAR(100) NOT NULL,
                          password CHAR(32) NOT NULL,
                          alias VARCHAR(45) NOT NULL,
                          email VARCHAR(100) NOT NULL,
                          token_auth CHAR(32) NOT NULL,
                          superuser_access INT NOT NULL DEFAULT \'0\',
                          date_registered TIMESTAMP NULL,
                            PRIMARY KEY(login),
                            UNIQUE (token_auth)
                          ) 
            ',

            'access'  => 'CREATE TABLE {$prefixTables}access (
                          login VARCHAR(100) NOT NULL,
                          idsite INTEGER NOT NULL,
                          access VARCHAR(10) NULL,
                            PRIMARY KEY(login, idsite)
                          ) 
            ',

            'site'    => 'CREATE TABLE {$prefixTables}site (
                          idsite bigserial NOT NULL,
                          name VARCHAR(90) NOT NULL,
                          main_url VARCHAR(255) NOT NULL,
                            ts_created TIMESTAMP NULL,
                            ecommerce INT DEFAULT 0,
                            sitesearch INT DEFAULT 1,
                            sitesearch_keyword_parameters TEXT NOT NULL,
                            sitesearch_category_parameters TEXT NOT NULL,
                            timezone VARCHAR( 50 ) NOT NULL,
                            currency CHAR( 3 ) NOT NULL,
                            exclude_unknown_urls INT DEFAULT 0,
                            excluded_ips TEXT NOT NULL,
                            excluded_parameters TEXT NOT NULL,
                            excluded_user_agents TEXT NOT NULL,
                            "group" VARCHAR(250) NOT NULL,
                            "type" VARCHAR(255) NOT NULL,
                            keep_url_fragment INT NOT NULL DEFAULT 0,
                              PRIMARY KEY(idsite)
                            ) 
            ',

            'site_setting'    => 'CREATE TABLE {$prefixTables}site_setting (
                          idsite bigserial NOT NULL,
                          setting_name VARCHAR(255) NOT NULL,
                          setting_value TEXT NOT NULL,
                              PRIMARY KEY(idsite, setting_name)
                            ) 
            ',

            'site_url'    => 'CREATE TABLE {$prefixTables}site_url (
                              idsite INTEGER NOT NULL,
                              url VARCHAR(255) NOT NULL,
                                PRIMARY KEY(idsite, url)
                              )
            ',

            'goal'       => 'CREATE TABLE {$prefixTables}goal (
                              idsite int NOT NULL,
                              idgoal int NOT NULL,
                              name varchar(50) NOT NULL,
                              match_attribute varchar(20) NOT NULL,
                              pattern varchar(255) NOT NULL,
                              pattern_type varchar(10) NOT NULL,
                              case_sensitive smallint NOT NULL,
                              allow_multiple smallint NOT NULL,
                              revenue float NOT NULL,
                              deleted smallint NOT NULL default 0,
                                PRIMARY KEY(idsite,idgoal)
                              )
            ',

            'logger_message'      => 'CREATE TABLE {$prefixTables}logger_message (
                                      idlogger_message bigserial NOT NULL,
                                      tag VARCHAR(50) NULL,
                                      timestamp TIMESTAMP NULL,
                                      level VARCHAR(16) NULL,
                                      message TEXT NULL,
                                        PRIMARY KEY(idlogger_message)
                                      ) 
            ',

            'log_action'          => 'CREATE TABLE {$prefixTables}log_action (
                                      idaction bigserial NOT NULL,
                                      name TEXT,
                                      hash INT NOT NULL,
                                      type INT NULL,
                                      url_prefix INT NULL,
                                        PRIMARY KEY(idaction)
                                       );
				      CREATE INDEX index_type_hash ON {$prefixTables}log_action (type, hash);
            ',

            'log_visit'   => 'CREATE TABLE {$prefixTables}log_visit (
                              idvisit INTEGER NOT NULL,
                              idsite INTEGER NOT NULL,
                              idvisitor bigint NOT NULL,
                              visit_last_action_time timestamp with time zone NOT NULL,
                              config_id bigint NOT NULL,
                              location_ip inet NOT NULL,
                                PRIMARY KEY(idvisit)
                              ) ;
			      CREATE INDEX index_idsite_config_datetime ON {$prefixTables}log_visit (idsite, config_id, visit_last_action_time);
			      CREATE INDEX index_idsite_datetime ON {$prefixTables}log_visit (idsite, visit_last_action_time);
			      CREATE INDEX index_idsite_idvisitor ON {$prefixTables}log_visit (idsite, idvisitor);
            ',

            'log_conversion_item'   => 'CREATE TABLE {$prefixTables}log_conversion_item (
                                        idsite int NOT NULL,
                                        idvisitor bigint NOT NULL,
                                        server_time timestamp with time zone NOT NULL,
                                        idvisit INTEGER NOT NULL,
                                        idorder varchar(100) NOT NULL,
                                        idaction_sku INTEGER NOT NULL,
                                        idaction_name INTEGER NOT NULL,
                                        idaction_category INTEGER  NOT NULL,
                                        idaction_category2 INTEGER NOT NULL,
                                        idaction_category3 INTEGER NOT NULL,
                                        idaction_category4 INTEGER NOT NULL,
                                        idaction_category5 INTEGER NOT NULL,
                                        price FLOAT NOT NULL,
                                        quantity INTEGER NOT NULL,
                                        deleted INT NOT NULL,
                                          PRIMARY KEY(idvisit, idorder, idaction_sku)
                                        );
					CREATE INDEX index_idsite_servertime ON {$prefixTables}log_conversion_item (idsite, server_time);
            ',

            'log_conversion'      => 'CREATE TABLE {$prefixTables}log_conversion (
                                      idvisit int NOT NULL,
                                      idsite int NOT NULL,
                                      idvisitor bigint NOT NULL,
                                      server_time timestamp with time zone NOT NULL,
                                      idaction_url int default NULL,
                                      idlink_va int default NULL,
                                      idgoal int NOT NULL,
                                      buster int NOT NULL,
                                      idorder varchar(100) default NULL,
                                      items INT DEFAULT NULL,
                                      url text NOT NULL,
                                        PRIMARY KEY (idvisit, idgoal, buster),
                                        UNIQUE(idsite, idorder)
                                      );
				      CREATE INDEX index_conversion_servertime ON {$prefixTables}log_conversion (idsite, server_time);
            ',

            'log_link_visit_action' => 'CREATE TABLE {$prefixTables}log_link_visit_action (
                                        idlink_va serial NOT NULL,
                                        idsite int NOT NULL,
                                        idvisitor bigint NOT NULL,
                                        idvisit INTEGER NOT NULL,
                                        idaction_url_ref INTEGER NULL DEFAULT 0,
                                        idaction_name_ref INTEGER NOT NULL,
                                        custom_float FLOAT NULL DEFAULT NULL,
                                          PRIMARY KEY(idlink_va)
                                        );
					CREATE INDEX index_idvisit ON {$prefixTables}log_link_visit_action (idvisit);
            ',

            'log_profiling'   => 'CREATE TABLE {$prefixTables}log_profiling (
                                  query TEXT NOT NULL,
                                  count INTEGER NULL,
                                  sum_time_ms FLOAT NULL,
                                    UNIQUE(query)
                                  )
            ',

            'option'        => 'CREATE TABLE {$prefixTables}option (
                                option_name VARCHAR( 255 ) NOT NULL,
                                option_value TEXT NOT NULL,
                                autoload INT NOT NULL DEFAULT 1,
                                  PRIMARY KEY ( option_name )
                                );
				CREATE INDEX autoload ON {$prefixTables}option (autoload);
            ',

            'session'       => 'CREATE TABLE {$prefixTables}session (
                                id VARCHAR( 255 ) NOT NULL,
                                modified INTEGER,
                                lifetime INTEGER,
                                data TEXT,
                                  PRIMARY KEY ( id )
                                )
            ',

            'archive_numeric'     => 'CREATE TABLE {$prefixTables}archive_numeric (
                                      idarchive INTEGER NOT NULL,
                                      name VARCHAR(255) NOT NULL,
                                      idsite INTEGER NULL,
                                      date1 DATE NULL,
                                      date2 DATE NULL,
                                      period INT NULL,
                                      ts_archived timestamp with time zone NULL,
                                      "value" double precision NULL,
                                        PRIMARY KEY(idarchive, name)
                                      );
				      
				      CREATE INDEX index_idsite_dates_period ON {$prefixTables}archive_numeric (idsite, date1, date2, period, ts_archived);
				      CREATE INDEX index_period_archived ON {$prefixTables}archive_numeric (period, ts_archived);
            ',

            'archive_blob'        => 'CREATE TABLE {$prefixTables}archive_blob (
                                      idarchive INTEGER NOT NULL,
                                      name VARCHAR(255) NOT NULL,
                                      idsite INTEGER NULL,
                                      date1 DATE NULL,
                                      date2 DATE NULL,
                                      period INT NULL,
                                      ts_archived timestamp with time zone NULL,
                                      value BLOB NULL,
                                        PRIMARY KEY(idarchive, name)
                                      );
				      CREATE INDEX index_period_archived ON {$prefixTables}archive_blob (period, ts_archived);
            ',

            'sequence'        => 'CREATE TABLE {$prefixTables}sequence (
                                      "name" VARCHAR(120) NOT NULL,
                                      "value" BIGINT NOT NULL ,
                                      PRIMARY KEY("name")
                                  )
            ',
        );
	$tables = str_replace('{$prefixTables}', $prefixTables, $tables);
        return $tables;
    }

    /**
     * Get the SQL to create a specific Piwik table
     *
     * @param string $tableName
     * @throws Exception
     * @return string  SQL
     */
    public function getTableCreateSql($tableName)
    {
        $tables = DbHelper::getTablesCreateSql();

        if (!isset($tables[$tableName])) {
            throw new Exception("The table '$tableName' SQL creation code couldn't be found.");
        }

        return $tables[$tableName];
    }

    /**
     * Names of all the prefixed tables in piwik
     * Doesn't use the DB
     *
     * @return array  Table names
     */
    public function getTablesNames()
    {
        $aTables      = array_keys($this->getTablesCreateSql());
        $prefixTables = $this->getTablePrefix();

        $return = array();
        foreach ($aTables as $table) {
            $return[] = $prefixTables . $table;
        }

        return $return;
    }

    /**
     * Get list of installed columns in a table
     *
     * @param  string $tableName The name of a table.
     *
     * @return array  Installed columns indexed by the column name.
     */
    public function getTableColumns($tableName)
    {
        $db = $this->getDb();

        $allColumns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name ='$tableName'");

        $fields = array();
        foreach ($allColumns as $column) {
            $fields[trim($column['Field'])] = $column;
        }

        return $fields;
    }

    /**
     * Get list of tables installed
     *
     * @param bool $forceReload Invalidate cache
     * @return array  installed Tables
     */
    public function getTablesInstalled($forceReload = true)
    {
        if (is_null($this->tablesInstalled)
            || $forceReload === true
        ) {
            $db = $this->getDb();
            $prefixTables = $this->getTablePrefixEscaped();

            $allTables = $this->getAllExistingTables($prefixTables);

            // all the tables to be installed
            $allMyTables = $this->getTablesNames();

            // we get the intersection between all the tables in the DB and the tables to be installed
            $tablesInstalled = array_intersect($allMyTables, $allTables);

            // at this point we have the static list of core tables, but let's add the monthly archive tables
            $allArchiveNumeric = $db->fetchCol("SELECT
					table_name
				    FROM
					information_schema.tables
				    WHERE
					table_type = 'BASE TABLE'
				    AND
					table_schema NOT IN ('pg_catalog', 'information_schema')
				    AND table_name like '" . $prefixTables . "archive_numeric%'");
            $allArchiveBlob    = $db->fetchCol("SELECT
					table_name
				    FROM
					information_schema.tables
				    WHERE
					table_type = 'BASE TABLE'
				    AND
					table_schema NOT IN ('pg_catalog', 'information_schema')
				    AND table_name like '" . $prefixTables . "archive_blob%'");

            $allTablesReallyInstalled = array_merge($tablesInstalled, $allArchiveNumeric, $allArchiveBlob);

            $this->tablesInstalled = $allTablesReallyInstalled;
        }

        return $this->tablesInstalled;
    }

    /**
     * Checks whether any table exists
     *
     * @return bool  True if tables exist; false otherwise
     */
    public function hasTables()
    {
        return count($this->getTablesInstalled()) != 0;
    }

    /**
     * Create database
     *
     * @param string $dbName Name of the database to create
     */
    public function createDatabase($dbName = null)
    {
        if (is_null($dbName)) {
            $dbName = $this->getDbName();
        }

        Db::exec("CREATE DATABASE IF NOT EXISTS " . $dbName . " DEFAULT CHARACTER SET utf8");
    }

    /**
     * Creates a new table in the database.
     *
     * @param string $nameWithoutPrefix The name of the table without any piwik prefix.
     * @param string $createDefinition  The table create definition, see the "MySQL CREATE TABLE" specification for
     *                                  more information.
     * @throws \Exception
     */
    public function createTable($nameWithoutPrefix, $createDefinition)
    {
	$createDefinition = preg_replace("/ INT\(\w*\)/i", ' INT', $createDefinition);
	$createDefinition = preg_replace("/ INTEGER\(\w*\)/i", ' INT', $createDefinition);
	$createDefinition = preg_replace("/TINYINT\(\w\)/i", 'SMALLINT', $createDefinition);
	$createDefinition = preg_replace("/TINYINT/i", 'SMALLINT', $createDefinition);
	$createDefinition = preg_replace("/INT\(\w*\) NOT NULL AUTO_INCREMENT/i", 'SERIAL', $createDefinition);
	$createDefinition = preg_replace("/INT NOT NULL AUTO_INCREMENT/i", 'SERIAL', $createDefinition);
	
        $statement = sprintf("CREATE TABLE %s ( %s ) ;",
                             Common::prefixTable($nameWithoutPrefix),
                             $createDefinition,
                             $this->getTableEngine());

        try {
            Db::exec($statement);
        } catch (Exception $e) {
            // mysql code error 1050:table already exists
            // see bug #153 https://github.com/piwik/piwik/issues/153
            if (!$this->getDb()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    /**
     * Drop database
     */
    public function dropDatabase($dbName = null)
    {
        $dbName = $dbName ?: $this->getDbName();
        Db::exec("DROP DATABASE IF EXISTS " . $dbName);
    }

    /**
     * Create all tables
     */
    public function createTables()
    {
        $db = $this->getDb();
        $prefixTables = $this->getTablePrefix();

        $tablesAlreadyInstalled = $this->getTablesInstalled();
        $tablesToCreate = $this->getTablesCreateSql();
        unset($tablesToCreate['archive_blob']);
        unset($tablesToCreate['archive_numeric']);

        foreach ($tablesToCreate as $tableName => $tableSql) {
            $tableName = $prefixTables . $tableName;
            if (!in_array($tableName, $tablesAlreadyInstalled)) {
                $db->query($tableSql);
            }
        }
    }

    /**
     * Creates an entry in the User table for the "anonymous" user.
     */
    public function createAnonymousUser()
    {
        // The anonymous user is the user that is assigned by default
        // note that the token_auth value is anonymous, which is assigned by default as well in the Login plugin
        $db = $this->getDb();
        $db->query("INSERT INTO " . Common::prefixTable("user") . "
                    VALUES ( 'anonymous', '', 'anonymous', 'anonymous@example.org', 'anonymous', 0, '" . Date::factory('now')->getDatetime() . "' ) ON CONFLICT DO NOTHING;");
    }

    /**
     * Truncate all tables
     */
    public function truncateAllTables()
    {
        $tables = $this->getAllExistingTables();
        foreach ($tables as $table) {
            Db::query("TRUNCATE $table");
        }
    }

    private function getTablePrefix()
    {
        return $this->getDbSettings()->getTablePrefix();
    }

    private function getTableEngine()
    {
        return $this->getDbSettings()->getEngine();
    }

    private function getDb()
    {
        return Db::get();
    }

    private function getDbSettings()
    {
        return new Db\Settings();
    }

    private function getDbName()
    {
        return $this->getDbSettings()->getDbName();
    }

    private function getAllExistingTables($prefixTables = false)
    {
        if (empty($prefixTables)) {
            $prefixTables = $this->getTablePrefixEscaped();
        }

        return Db::get()->fetchCol("SELECT
					table_name
				    FROM
					information_schema.tables
				    WHERE
					table_type = 'BASE TABLE'
				    AND
					table_schema NOT IN ('pg_catalog', 'information_schema')
				    AND table_name like '" . $prefixTables . "%'");
    }

    private function getTablePrefixEscaped()
    {
        $prefixTables = $this->getTablePrefix();
        // '_' matches any character; force it to be literal
        $prefixTables = str_replace('_', '\_', $prefixTables);
        return $prefixTables;
    }
}
