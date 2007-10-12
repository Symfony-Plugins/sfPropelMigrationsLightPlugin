<?php
/*
 * This file is part of the sfPropelMigrationsLightPlugin package.
 * (c) 2006-2007 Martin Kreidenweis <sf@kreidenweis.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base class for all Migrations
 *
 * @package    symfony
 * @subpackage plugin
 * @author     Martin Kreidenweis <sf@kreidenweis.com>
 * @version    SVN: $Id$
 */
abstract class sfMigration
{
  private $dbConnection = null;
  private $migrator = null;
  private $migrationNumber = null;

  /**
   * constructor
   *
   * @param sfMigrator $migrator   the migrator instance calling this migration
   * @param int $migrationNumber  the DB version the migration (up) will migrates to
   */
  public function __construct(sfMigrator $migrator, $migrationNumber)
  {
    $this->dbConnection = Propel::getConnection();
    $this->migrator = $migrator;
    $this->migrationNumber = $migrationNumber;
  }
  
  /**
   * returns the migrator instance that called the migration
   *
   * @return sfMigrator
   */
  public function getMigrator()
  {
    return $this->migrator;
  }
  
  /**
   * returns the Propel connection
   *
   * @return Connection
   */
  protected function getConnection()
  {
    return $this->dbConnection;
  }
  
  /**
   * returns the migration number of this migration
   *
   * @param mixed $formatted  if true the result is a zero-padded string, otherwise an int is returned
   * @return mixed
   */
  protected function getMigrationNumber($formatted = true)
  {
    if ($formatted)
    {
      return sprintf("%03d", $this->migrationNumber);
    }
    else
    {
      return (int)$this->migrationNumber;
    }
  }
  
  /**
   * Executes a SQL statement, returns the number of affected rows
   *
   * @param string $sql the SQL code to execute
   * @return number of affected rows
   * @throws SQLException
   */
  protected final function executeSQL($sql)
  {
    return $this->getConnection()->executeUpdate($sql);
  }

  /**
   * Executes the SQL query and returns the resultset.
   * 
   * @param string $sql The SQL statement.
   * @param int $fetchmode
   * @return object ResultSet
   * @throws SQLException if a database access error occurs.
   */
  protected final function executeQuery($sql, $fetchmode = null)
  {
    return $this->getConnection()->executeQuery($sql, $fetchmode);
  }

  /**
   * adds a column to an existing table [untested]
   *
   * @param string $table the table name
   * @param string $column the name of the column to add
   * @param string $type the type of the column to add
   * @param bool $notNull if the column should be NOT NULL
   * @param mixed $default default value for the column
   */
  protected function addColumn($table, $column, $type, $notNull = false, $default = null)
  {
    $sql = "ALTER TABLE $table ADD COLUMN $column $type";
    if ($notNull) { $sql += " NOT NULL"; }
    if ($default !== null) { 
      if (!is_int($default)) { $default = "'" . $default . "'"; }
      $sql += " DEFAULT $default"; 
    }

    return $this->executeSQL($sql);
  }

  /**
   * Loads the fixture files of the migration.
   * Has to be called manually.
   *
   * Be careful. Due to the nature Propel and fixture-loading works you'll probably get problems
   * when you change the definitions of affected tables in later migrations.
   *
   * @param bool $deleteOldRecords  whether the affected tables' content should be deleted prior to loading the fixtures, default: false
   */
  protected function loadFixtures($deleteOldRecords = false)
  {
    $fixturesDir = $this->getMigrator()->getMigrationsFixturesDir() . DIRECTORY_SEPARATOR . $this->getMigrationNumber();
    
    if (!is_dir($fixturesDir))
    {
      throw new sfException("no fixtures exist for migration " . $this->getMigrationNumber());
    }
    
    $data = new sfPropelData();
    $data->setDeleteCurrentData($deleteOldRecords);
    $data->loadData($fixturesDir, 'symfony');
  }

  /**
   * begins a transaction
   */
  protected function begin()
  {
    $this->getConnection()->begin();
  }

  /**
   * commits transaction
   */
  protected function commit()
  {
    $this->getConnection()->commit();
  }

  /**
   * rolls back transaction
   */
  protected function rollback()
  {
    $this->getConnection()->rollback();
  }

  /**
   * output some diagnostic or informational message
   */
  protected function diag($text)
  {
    // something a little more sophisticated might be better...
    echo $text."\n";
  }

  /**
   * bring the Database Schema up to the current Version from the previous one
   */
  abstract public function up();

  /**
   * bring the schema down to the previous version, i.e. undo the modifications made in up()
   */
  abstract public function down();
}
