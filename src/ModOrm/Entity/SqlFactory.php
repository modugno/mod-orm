<?php

namespace ModOrm\Entity;

use ModOrm\Database\Database;

class SqlFactory {

	public static function buildWhere($args, $operator = false) {
		if(count($args) === 0) {
			throw new \Exception('Faltou argumentos na função ' . __METHOD__);
		}

		$where = Entity::hydrateArgs($args);
		Entity::$whereValue[] = $where[2];

		$attr = (!Entity::$whereFlg) ? "WHERE" : "AND";
		$attr = ($operator) ? " OR " : $attr;

		Entity::$where   .= " {$attr} {$where[0]} {$where[1]} ? ";
		Entity::$whereFlg = true;
		return;
	}

	public static function buildWhereInNot($field, Array $args, $not = false) {
		$values = implode(',', $args);
		$attr   = (!Entity::$whereFlg) ? " WHERE " : " AND ";
		$not    = ($not) ? " NOT " : "";

		Entity::$where .= " {$attr} {$field} {$not} in ($values)";
		Entity::$whereFlg = true;
		return;
	}

	public function buildBetween($column, Array $args, $operator, $not = false) {

		$operator = trim(strtoupper($operator));
		if (!in_array($operator, ['AND', 'OR'])) {
			throw new \Exception(sprintf("Operator %s invalid!", $operator));
		}

		$not = ($not) ? " NOT " : "";

		// self::$whereValue[] = $args;

		if (!Entity::$whereFlg) {
			Entity::$where = " WHERE {$column} {$not} BETWEEN {$args[0]} AND {$args[1]}";
			Entity::$whereFlg = true;
			return;
		}

		$operator = (strtoupper($operator) == "AND") ? " AND " : " OR ";
		
		Entity::$where .= " {$operator} {$column} {$not} BETWEEN {$args[0]} AND {$args[1]}";
		return;
	}

	public function buildLike($field, $value, $operator = false) {
		if(func_num_args() === 0) {
			throw new \Exception('Faltou argumentos na função ' . __METHOD__);
		}

		Entity::$whereValue[] = $value;

		$attr           = (!Entity::$whereFlg) ? "WHERE" : "AND";
		$attr           = ($operator) ? " OR " : $attr;

		Entity::$where   .= " {$attr} {$field} LIKE ? ";
		Entity::$whereFlg = true;

		return;
	}

	public function buildStatement($table) {
		$fields = (Entity::$select != "" || Entity::$select != null) ? Entity::$select : "*";
		$sql    = sprintf(Entity::$sql, $fields, $table);
		$sql   .= (Entity::$where     != "") ? Entity::$where : "";
		$sql   .= (Entity::$order     != "") ? Entity::$order : "";
		$sql   .= (Entity::$limit     != "") ? Entity::$limit : "";
		$sql   .= (Entity::$join      != "") ? Entity::$join : "";
		$sql   .= (Entity::$leftjoin  != "") ? Entity::$leftjoin : "";
		return $sql;
	}
}