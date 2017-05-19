<?php

namespace MobOrm\ModOrm;

use MobOrm\ModOrm\Config;
use PDO;

class Database {

	/* db informations */
	public static $dbname;
	public static $connect;
	public static $table = null;

	public function __construct() {
		self::init();
	}

	public static function init() {
		if(!isset(self::$connect)) {
			self::$connect  = self::connect();
		}
	}
 	
 	public function connect() {
 		$sql = sprintf("mysql:host=%s;dbname=%s", Config::HOST, Config::DBNAME);
 		return new \PDO($sql, Config::USER, Config::PASS);
 	}

 	public static function getConnection() {
 		return self::$connect;
 	}

 	public static function getDatabaseName() {
 		return self::$dbname;
 	}

 	public static function setDatabaseName() {
 		self::$dbname = Config::DBNAME;
 	}

 	public static function table($name) {
 		self::$table = $name;
 	}
 	
 	public function getTableName() {
 		if (self::$table == null) {
 			return static::$table;
 		}
 		return self::$table;
 	}

 	// get all columns names
 	public static function getColumnNames() {
 		$sql  = sprintf("DESCRIBE %s.%s ;", self::getDatabaseName(), self::getTableName());
 		$smtp = self::getConnection()->prepare($sql);
 		$smtp->execute();

 		$fields = [];

 		foreach($smtp->fetchAll() as $row)
 			$fields[] = $row['Field'];

 		return $fields;
 	}

 	public static function getPkTable() {
 		$sql = sprintf("SHOW keys FROM %s WHERE Key_name = %s", static::$table, "'PRIMARY'");
 		$smtp = self::getConnection()->prepare($sql);
 		$smtp->execute();
 		$resultSet = array_shift($smtp->fetchAll());

 		return $resultSet['Column_name'];
 	}
 	public static function hydrateFields($obj) {
 		// get columns name
 		$columns = self::getColumnNames(); 

 		// get properties from object
 		$properties = get_object_vars($obj);
 		
 		$array = array_intersect_key($properties, array_flip($columns));

 		if(count($array) == 0)
 			throw new \Exception("Propriedades não correspondem com as colunas do banco de dados");

 		return $array; // return all properties they are equals to database columns
 	}

 	// executar com transação
	public static function beginTran() {
		return self::getConnection()->beginTransaction();
	}

	public static function commit() {
		return self::getConnection()->commit();
	}

	public static function rollback() {
		return self::getConnection()->rollback();
	}
}