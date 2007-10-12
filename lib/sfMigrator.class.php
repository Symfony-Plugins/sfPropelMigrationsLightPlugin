<?php
/*
 * This file is part of the sfPropelMigrationsLightPlugin package.
 * (c) 2006-2007 Martin Kreidenweis <sf@kreidenweis.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * manages all calls to the sfMigration class instances 
 * 
 * @package    symfony
 * @subpackage plugin
 * @author     Martin Kreidenweis <sf@kreidenweis.com>
 * @version    SVN: $Id$
 */
class sfMigrator
{
  /**
   * string array of migration files' names
   *
   * @var array $migrations
   */
  private $migrations = array();
  
  public function __construct()
  {
    $this->loadMigrations();
  }

  /**
   * does all the migrating
   *
   * @param int $destVersion version number to migrate to, default: migrates to max existing
   * @return int number of executed migrations
   */
  public function migrate($destVersion = null)
  {
    $maxVersion = $this->getMaxVersion();
    if ($destVersion === null) 
    {
      $destVersion = $maxVersion;
    }
    else
    {
      $destVersion = (int)$destVersion;
      if (($destVersion > $maxVersion) || ($destVersion < 0))
      {
        throw new sfException("Migration $destVersion does not exist.");
      }
    }
    
    $sourceVersion = $this->getCurrentVersion();
    
    // do appropriate stuff according to migration direction
    if ($destVersion == $sourceVersion)
    {
      return 0;
    }
    else if ($destVersion < $sourceVersion)
    {
      $res = $this->migrateDown($sourceVersion, $destVersion);
    }
    else 
    { 
      $res = $this->migrateUp($sourceVersion, $destVersion); 
    }
        
    return $res;
  }
  
  /**
   * Generates a new, emtpy migration stub
   * 
   * @param string $name  name of the new migration
   * @return string       filename of the new migration file
   */
  public function generateMigration($name) 
  {
    $template = "<?php

class %MigrationClassName% extends sfMigration {
  public function up() 
  {
    
  }

  public function down() 
  {
    
  }
}
";
    
    // calculate version number for new migration
    $maxVersion = (int)$this->getMaxVersion();
    $newVersion = sprintf("%03d", $maxVersion + 1);
    
    // generate new migration class stub
    $newClass = str_replace('%MigrationClassName%', 'Migration'.$newVersion, $template);
    
    // sanitize name
    $name = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    
    // write new migration stub
    $newFileName = $this->getMigrationsDir().DIRECTORY_SEPARATOR.$newVersion.'_'.$name.'.php';
    file_put_contents($newFileName, $newClass);
    
    return $newFileName;
  }
  
  /**
   * Writes the given version as current version to the database
   *
   * @param int $version  new current version
   */
  protected function setCurrentVersion($version)
  {
    $conn = Propel::getConnection();

    $version = (int)$version;

    $conn->executeUpdate("UPDATE schema_info SET version = $version");
  }

  /**
   * migrates down from version $from to version $to
   *
   * @param int $from
   * @param int $to
   * @return int number of executed migrations
   */
  protected function migrateDown($from, $to) 
  {
    $con = Propel::getConnection();
    $counter = 0;
    
    // iterate over all needed migrations
    for ($i = $from; $i > $to; $i--)
    {
      $con->begin();
      try
      {
        $migration = $this->getMigrationObject($i);
        $migration->down();
        
        $this->setCurrentVersion($i-1);
        
        $con->commit();
      }
      catch (Exception $e)
      {
        $con->rollback();
        throw $e;
      }
      
      $counter++;
    }
    
    return $counter;
  }
  
  /**
   * migrates up from version $from to version $to
   * 
   * @param int $from
   * @param int $to
   * @return int number of executed migrations
   */
  protected function migrateUp($from, $to) 
  {
    $con = Propel::getConnection();
    $counter = 0;
    
    // iterate over all needed migrations
    for ($i = $from + 1; $i <= $to; $i++)
    {
      $con->begin();
      try
      {
        $migration = $this->getMigrationObject($i);
        $migration->up();
        
        $this->setCurrentVersion($i);
        
        $con->commit();
      }
      catch (Exception $e)
      {
        $con->rollback();
        throw $e;
      }
        
      $counter++;
    }

    return $counter;
  }
  
  /**
   * returns the migration object for the given version
   *
   * @param int $version
   * @return sfMigration
   */
  protected function getMigrationObject($version)
  {
    $file = $this->getMigrationFileName($version);

    // load the migration class
    require_once($file);
    $migrationClass = 'Migration'.$this->getMigrationNumberFromFile($file);
    
    return new $migrationClass($this, $version);
  }
  
  /**
   * version to filename
   *
   * @param int $version
   * @return string filename
   */
  protected function getMigrationFileName($version)
  {
    return $this->migrations[$version-1];
  }
    
  /**
   * loads all migration file names
   */
  protected function loadMigrations() 
  {
    $this->migrations = sfFinder::type('file')->name('/^\d{3}.*\.php$/')->maxdepth(0)->in($this->getMigrationsDir());
    
    sort($this->migrations);

    if (count($this->migrations) > 0)
    {
      $minVersion = $this->getMinVersion();
      $maxVersion = $this->getMaxVersion();

      if ($minVersion != 1) 
      {
        throw new sfInitializationException("First migration is not migration 1. Some migration files may be missing.");
      }
      
      if (($maxVersion - $minVersion + 1) != count($this->migrations))
      {
        throw new sfInitializationException("Migration count unexpected. Migration files may be missing. Migration numbers must be unique.");
      }
    }
  }
  
  /**
   * returns the list of migration filenames
   *
   * @return array
   */
  public function getMigrations() 
  {
    return $this->migrations;
  }
  
  /**
   * @return the lowest migration that exists
   */
  public function getMinVersion() 
  {
    if (count($this->migrations) == 0)
    {
      return 0;
    }
    else
    {
      return $this->getMigrationNumberFromFile($this->migrations[0]);
    }
  }

  /**
   * @return the highest existing migration number
   */
  public function getMaxVersion()
  {
    if (count($this->migrations) == 0)
    {
      return 0;
    }
    else
    {
      return $this->getMigrationNumberFromFile($this->migrations[count($this->migrations)-1]);
    }  
  }
  
  /**
   * retrives the current schema version from the database
   * 
   * if no schema version is currently stored in the database, 
   * one is created and initialized with 0
   *
   * @return int
   */
  public function getCurrentVersion() 
  {
    $conn = Propel::getConnection();
    
    // check if schema_info table exists
    $rs = $conn->executeQuery("SHOW TABLES LIKE 'schema_info'");
    if ($rs->getRecordCount() == 1)
    {
      $rs = $conn->executeQuery("SELECT version FROM schema_info");
      
      if ($rs->next())
      {
        $currentVersion = $rs->getInt("version");
      }
      else
      {
        throw new sfDatabaseException("unable to retrieve current schema version");
      }
    }
    else
    {
      // no schema_info table exists yet
      // so we create it
      $conn->executeUpdate("CREATE TABLE schema_info (version INTEGER UNSIGNED)");
      // and insert the version record
      // if no schema_info existed before, we'll call that version 0
      $conn->executeUpdate("INSERT INTO schema_info SET version = 0");
      $currentVersion = 0;
    }
    
    return $currentVersion;
  }
  
  /**
   * returns the number encoded in the given migration file name
   * 
   * @param string $file the filename to look at
   */
  public function getMigrationNumberFromFile($file) 
  {
    $match_count = preg_match('#'.preg_quote(DIRECTORY_SEPARATOR, '#').'(\d{3}).*\.php$#', $file, $matches);
    $number = $matches[1];
    
    if ($match_count < 1) {
      throw new sfParseException("Migration filename could not be parsed.");
    }
    
    return $number;
  }
  
  public function getMigrationsDir() 
  {
    return sfConfig::get('sf_data_dir').DIRECTORY_SEPARATOR.'migrations';
  }
  
  public function getMigrationsFixturesDir()
  {
    return $this->getMigrationsDir().DIRECTORY_SEPARATOR.'fixtures';
  }
}