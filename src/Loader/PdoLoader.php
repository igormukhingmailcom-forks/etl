<?php

namespace BiSight\Etl\Loader;

use BiSight\Etl\RowInterface;
use LinkORB\Component\DatabaseManager\DatabaseManager;
use RuntimeException;

class PdoLoader implements LoaderInterface
{
    private $pdo;
    private $tablename;
    private $indexes;
    private $columns = array();
    
    public function __construct($dbname, $tablename, $indexes = null)
    {
        $dbm = new DatabaseManager();
        $pdo = $dbm->getPdo($dbname);

        $this->pdo = $pdo;
        $this->tablename = $tablename;
        $this->indexes = $indexes;
    }
    
    public function getTablename()
    {
        return $this->tablename;
    }
    
    public function load(RowInterface $row)
    {
        $sql = "INSERT INTO " . $this->tablename;
        $sql .= " (";
        foreach ($this->columns as $column) {
            $sql .= $column->getName() . ", ";
        }
        $sql = rtrim($sql, ", ");
        $sql .= ") VALUES (";
        $values = array();
        
        foreach ($this->columns as $column) {
            $values[] = $row->get($column->getName());
            $sql .= "?, ";
        }
        $sql = rtrim($sql, ", ");
        $sql .= ");";
        
        //echo $sql;
        
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute($values);
    }
    
    public function init($columns)
    {
        $this->columns = $columns;
        
        $sql = "DROP TABLE " . $this->tablename;
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute();

        
        $sql = "CREATE TABLE " . $this->tablename;
        $sql .= "(";
        foreach ($columns as $column) {
            
            switch ($column->getType()) {
                case "LONG":
                    $type = "int(" . $column->getLength() . ")";
                    break;
                case "DOUBLE":
                    $type = "double";
                    break;
                case "VAR_STRING":
                    $type = "varchar(" . $column->getLength() . ")";
                    break;
                default:
                    throw new RuntimeException("Unsupported type: " . $column->getType());
            }
            $sql .= $column->getName() . " " . $type . ", ";
        }
        $sql = rtrim($sql, ", ");
        $sql .= ");";
        //echo $sql;
        
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute();

    }
    
    public function cleanup()
    {
        $indexes = $this->indexes;
        $indexes = str_replace("\n", ";", $indexes);
        $lines = explode(';', $indexes);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line) {
                $part = explode(':', $line);
                $indexname = $part[0];
                if (count($part)!=2) {
                    throw new RuntimeException("Failed parsing indexline: " . $line);
                }
                $columnnames = explode(',', $part[1]);
                
                $sql = "CREATE INDEX " . $indexname;
                $sql .= " ON " . $this->tablename;
                $sql .= "(";
                
                foreach ($columnnames as $columnname) {
                    $sql .= $columnname . ', ';
                }
                
                $sql = rtrim($sql, ' ,');
                $sql .= ");";
                //echo "\n" .$sql . "\n";
                
                $this->stmt = $this->pdo->prepare($sql);
                $res = $this->stmt->execute();
            }
        }
    }
}
