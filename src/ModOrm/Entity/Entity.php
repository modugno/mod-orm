<?php
namespace ModOrm\Entity;

use PDO;
use ModOrm\Database\Database;
use ModOrm\Entity\SqlFactory;

class Entity extends Database {

	public static $instance;
	public static $sql = "SELECT %s FROM %s";
	public static $select;
	public static $where;
	public static $whereValue = [];
	public static $whereFlg = false;
	public static $order;
	public static $limit;
	public static $join;
	public static $leftjoin;
	public static $table;

	public function __construct()
	{
		parent::init();
	}

	// magic methods
	public static function __callStatic($method, $args) {
		
		if (substr($method, 0, 5) == "getBy") {

			$field = strtolower(substr($method, 5));

			array_unshift($args, $field);
			$class = get_called_class();
			return call_user_func_array([$class, 'get'], $args);

		}

		throw new \Exception(sprintf("The static method %s not found.", $method));
	}

	public static function getInstance() {
		
		return self::$instance == null ? new static : self::$instance;
	}

	public function defaultStatement() {
		self::$where      = "";
		self::$whereFlg   = false;
		self::$whereValue = [];
	}

	public static function hydrateArgs($args) {
		$array    = [];
		$array[0] = $args[0];
		$array[1] = "=";
		$array[2] = $args[1];	

		if(count($args) > 2) {
			$array[0] = $args[0];
			$array[1] = $args[1];
			$array[2] = $args[2];
		}
		return $array;
	}

	public static function setTableName($name) {
 		static::$table = $name;
 	}
 	
 	public function getTableName() {
 		return static::$table;
 	}

 	public function table($name)
	{
		self::setTableName($name);
		return self::getInstance();
	}

	// retorna todos
	public static function all($fields = null, $alias = null) {
		$alias  = ($alias != null)  ? $alias  : "";
		$fields = ($fields != null) ? $fields : "*";
		$select = "{$fields} {$alias}"; 

		$statement = (new static)->getConnection()->prepare(sprintf(self::$sql, $select, (new static)->getTableName()));
		$statement->execute();
		return $statement->fetchAll(PDO::FETCH_OBJ);
	}

	public static function select($fields, $alias = null) {
		$alias = ($alias != null) ? $alias : "";
		self::$select = "{$fields} {$alias}";
		return self::getInstance();
	}

 	// retorna um
	public static function get() {
		if (func_num_args() == 1 || func_num_args() > 2) {
			throw new \Exception("Invalid Argument");
		}

		if (func_num_args() == 2) {
			$args = func_get_args();
			self::where($args[0], $args[1]);
		}

		$sql = SqlFactory::buildStatement((new static)->getTableName());

		$statement = (new static)->getConnection()->prepare($sql);
		$statement->execute(self::$whereValue);

		self::defaultStatement();
		$resultSet = $statement->fetchAll(PDO::FETCH_CLASS, get_called_class()); 
		return $resultSet;
	}

	public function getBy($field, $value) {
		return self::get($field, $value);
	}	

	public function find($value) {
		$pk = (new static)->getPkTable();
		$resultSet = self::get($pk, $value);
		return array_shift($resultSet);
	}

	public static function where() {
		$args = func_get_args();
		SqlFactory::buildWhere($args);
		return self::getInstance();
	}

	public static function orWhere() {
		$args  = func_get_args();
		SqlFactory::buildWhere($args, true); // true define that object is OR
		return self::getInstance();
	}

	public static function whereIn($field, Array $args) {
		SqlFactory::buildWhereInNot($field, $args);
		return self::getInstance();
	}

	public static function whereNotIn($field, Array $args) {
		SqlFactory::buildWhereInNot($field, $args, true);
		return self::getInstance();	
	}

	public static function doWhere() {
		if(func_num_args() === 0) {
			throw new \Exception("Você precisa passar um parametro");
		}

		$args = func_get_args();
		
		if(!is_callable($args[0])) {
			throw new \Exception("O argumento tem que ser uma função");
		}

		$args[0](self::getInstance());

		return self::getInstance();
	}

	public static function whereBet($column, Array $args, $operator = "AND") {
		SqlFactory::buildBetween($column, $args, $operator);
		return self::getInstance();
	}

	public static function whereNotBet($column, Array $args, $operator = "AND") {
		SqlFactory::buildBetween($column, $args, $operator, true);
		return self::getInstance();
	}

	public static function whereNull($field) {
		$attr = (!self::$whereFlg) ? " WHERE " : " AND ";
		self::$where .= " {$attr} {$field} IS NULL ";
		return self::getInstance();
	}

	public static function whereNotNull($field) {
		$attr = (!self::$whereFlg) ? " WHERE " : " AND ";
		self::$where .= " {$attr} {$field} IS NOT NULL ";
		return self::getInstance();
	}

	public static function orderBy($field, $order) {
		self::$order = " ORDER BY {$field} {$order} ";
		return self::getInstance();
	}

	public static function limit($limit, $offset = null) {
		if(func_num_args() === 0) {
			throw new \Exception("Faltou argumentos");
		}

		$args = func_get_args();

		self::$limit = (count($args) == 2) ? " LIMIT {$args[0]},{$args[1]}" : " LIMIT {$args[0]} ";
		return self::getInstance();
	}

	public static function like($field, $value) {
		SqlFactory::buildLike($field, $value);
		return self::getInstance();
	}

	public static function orLike($field, $value) {
		SqlFactory::buildLike($field, $value, true);
		return self::getInstance();
	}

	public static function join($table, $tableField, $operator, $field) {
		self::$join = " INNER JOIN {$table} ON {$tableField} {$operator} {$field}";
		return self::getInstance();
	}

	public static function leftJoin($table, $tableField, $operator, $field) {
		self::$leftjoin = " LEFT JOIN {$table} ON {$tableField} {$operator} {$field}";
		return self::getInstance();
	}

	// query manual
	public static function query($query) {
		$statement = (new static)->getConnection()->prepare($query);
		$statement->execute();
		return $statement;
	}

	// stored procedure
	public static function execProc($procedure, $parameters = []) {

		$parameters = ($parameters != null) ? $parameters : [];
		
		// verificar forma de arrumar os parametros
		$placeholder = (count($parameters) == 1) ? "?" : "";

		if(count($parameters) > 1) {
			$placeholder = str_repeat("?,", count($parameters));
			$placeholder = substr($placeholder, 0, -1);
		}

		$sql = "CALL {$procedure}($placeholder)";

		$statement = (new static)->getConnection()->prepare($sql);
		$statement->execute($parameters);

		return $statement->fetchAll(PDO::FETCH_OBJ);
	}

 	// insere
	public static function insert($obj) {
 		// verifica se é um objeto que esta sendo passado
		if(!is_object($obj)) 
			throw new \Exception(sprintf("'%s' não é um objecto válido.", $obj));

 		$attributes  = array_keys((new static)->hydrateFields($obj)); // pega os atributos da classe
 		$fields      = implode(', ', $attributes); // pega os campos que serão inseridos

 		// pega o placeholder do objeto
 		$placeholder = str_repeat("?,", count($attributes));
 		$placeholder = substr($placeholder, 0, -1);

 		// cria o array de objetos com os valores do objeto
 		$arrValues = [];
 		foreach($attributes as $attr) {
 			array_push($arrValues, $obj->$attr);
 		}
 		$sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", (new static)->getTableName(), $fields, $placeholder);
 		$statement = (new static)->getConnection()->prepare($sql);
 		$statement->execute($arrValues);
 		return (new static)->getConnection()->lastInsertId();
 	}

 	// atualiza
 	public static function update($obj, $where = null) {
 		// verifica se é um objeto que esta sendo passado
 		if(!is_object($obj)) 
 			throw new Exception("'$obj' não é um objeto válido.");
 		
 		$attributes  = array_keys(get_object_vars($obj)); // pega os atributos da classe
 		$fields = null;
 		for($i = 0; $i < count($attributes); $i++) {
 			$fields .= "{$attributes[$i]} = ?,";
 		}
 		$fields = substr($fields, 0, -1); // remove a ultima virgula
 		
 		// cria o array de objetos com os valores do objeto
 		$arrValues = [];
 		foreach($attributes as $attr) {
 			array_push($arrValues, $obj->$attr);
 		}

 		// pega o filtro do where 
 		if($where != null) {
 			$filter = key($where);
 			$statement = (new static)->getConnection()->prepare("UPDATE " . static::$table . " SET $fields WHERE {$filter} = ?");
 			array_push($arrValues, $where[$filter]);
 		} else {
 			$statement = (new static)->getConnection()->prepare("UPDATE " . static::$table . " SET $fields WHERE id = ?");
 			array_push($arrValues, $obj->id);
 		}
 		$statement->execute($arrValues);
 	}

 	public function delete($where, $value) {
 		$statement = (new static)->getConnection()->prepare("DELETE FROM " . static::$table . " WHERE {$where} = ?");
 		$statement->execute([$value]);
 	}

 	// salva
 	public function save() {
 		if(!isset($this->id)) {
 			self::insert($this);
 			return;
 		} 
 		self::update($this);
 	}
 }