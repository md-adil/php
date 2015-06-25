<?php
class DB {
	public static $instance;
	public static $connected;
	private $table;
	private $select;
	private $queryLog;
	private $lastId;
	private $condition;
	private $joins;
	private $jtable;
	private $sort;
	private $group;
	private $limit;
	private $bindings = [];

	private function __construct() {}

	public static function create($table = null) {
		if(!self::$instance) {
			self::$instance = new self();
		}
		if(!$table) {
			return self::$instance;
		}
		return self::$instance->table($table);
	}

	public function connect($config) {
		if(DB::$connected) {
			throw new Exception("Already Connected", 501);
		}
		try {
			$dsn = $config['driver'] . ':host=' . $config['host'] . ';dbname=' . $config['database'];
			$this->conn = new PDO($dsn, $config['user'], $config['password']);
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			DB::$connected = true;
			return $this;
			
		} catch (Exception $e) {
			exit(json_encode([
				'Message' => $e->getMessage(),
				'Code' => $e->getCode(),
				'Connection' => $config
			]));
		}
	}

	public function __toString() {
		return json_encode($this->get());
	}

	public function __get($name) {
		if($name == 'conn') {
			throw new Exception("Connect to database first using db()->connect(array configs)", 1);
		}
	}

	public function queryLog() {
		return json_encode($this->queryLog);
	}

	/**
	 * Private Helper functions
	 */

	/**
	 * Executing query to files
	 */
	private function execute() {
		$props = ['conn', 'queryLog', 'instance', 'connected'];
		$query = $this->prepare();
		$bindings = $this->bindings;
		$this->queryLog[] = [
			'Query' => $query,
			'Bindings' => $bindings
		];
		foreach ($this as $prop => $value) {
			if(in_array($prop, $props)) continue;
			$this->$prop = null;
		}
		$this->bindings = [];
		try {
			$stmt = $this->conn->prepare($query);
			$stmt->execute($bindings);
			return $stmt;
		} catch (Exception $e) {
			$errors = [
				'Message' => $e->getMessage(),
				'Code' => $e->getCode(),
				'Line' => $e->getLine(),
				'Query' => $query,
				'Bindings' => $bindings
			];
			exit(json_encode($errors));
		}
	}

	/**
	 * Preparing and building query
	 */
	private function prepare() {
		if($this->query) {
			return $this->query;
		}
		$query = '';
		if(!$this->table) {
			return "SHOW TABLES";
		}
		if(!$this->select) {
			$this->select = "*";
		}
		$query .= "SELECT {$this->select} FROM {$this->table}";
		if($this->joins) {
			$query .= "{$this->joins}";
		}
		if($this->condition) {
			$query .= " WHERE {$this->condition}";
		}
		if($this->group) {
			$query .= " GROUP BY {$this->group}";
		}
		if($this->sort) {
			$query .= " ORDER BY {$this->sort}";
		}
		if($this->limit) {
			$query .= " LIMIT {$this->limit}";
		}
		return $query;
	}

	private function struct($name) {
		if(strpos($name, "`") !== false) {
			return $name;
		}
		$name = str_replace('.', '`.`', $name);
		return '`' . implode("` `", preg_split("/\s+/", $name, 2)) . '`';
	}

	private function condClauser($fn, $c) {
		if(!$this->condition || preg_match("/\($/", $this->condition)) {
			$this->condition .= "(";
		} else {
			$this->condition .= " {$c} (";
		}
		$fn($this);
		$this->condition .= ")";
		return $this;
	}

	private function condition($args, $c = 'AND') {
		$condition = '';
		switch(count($args)) {
			case 1:
				$arg = $args[0];
				if(gettype($arg) == 'object') {
					return $this->condClauser($arg, $c);
				} elseif(gettype($arg) == 'array') {
					foreach ($arg as $column => $value) {
						$condition .= " {$c} {$column} = ?";
						$this->bindings[] = $value;
					}
				} else {
					throw new Exception("INVALID Argument passed", 1);
				}
				break;
			case 2:
				$condition .= " {$c} {$args[0]} = ?";
				$this->bindings[] = $args[1];
				break;

			case 3:
				$condition .= " {$c} {$args[0]} {$args[1]} ?";
				$this->bindings[] = $args[2];
				break;
		}
		if(!$this->condition || preg_match("/\($/", $this->condition)) {
			$this->condition .= trim($condition, " {$c} ");
		} else {
			$this->condition .= $condition;
		}
		return $this;
	}

	private function buildJoin($lhs, $con, $rhs) {
		if(is_numeric($lhs) || preg_match('/\'|\"/', $lhs)) {
			$lhs = trim($lhs, '"\'');
		} elseif(strpos($lhs, ".") === false) {
			$tmp = preg_split("/\s+/", $this->table, 2);
			$table = end($tmp);
			$lhs = "$table.`{$lhs}`";
		}
		if(is_numeric($rhs) || preg_match('/\'|"/', $rhs)) {
			$rhs = trim($rhs, '"\'');
		} elseif(strpos($rhs, ".") === false) {
			$tmp = preg_split("/\s+/", $this->jtable, 2);
			$table = end($tmp);
			$rhs = "{$table}.`{$rhs}`";
		}
		return "{$lhs} {$con} {$rhs}";
	}

	private function joinOn($args, $cond = null) {
		$jQuery = '';
		switch (count($args)) {
			case 1:
				$args[0]($this);
				return '';
				break;
			case 2:
				$jQuery .= $this->buildJoin($args[0], '=', $args[1]);
				break;
			case 3:
				$jQuery .= $this->buildJoin($args[0], $args[1], $args[2]);
				break;
			default:
				throw new Exception("INVALID ARGUMENT PASSED", 1);
				break;
		}
		if($cond && !preg_match("/ON$/", $this->joins)) {
			return " {$cond} {$jQuery}";
		}
		return " " . $jQuery;
	}

	private function joining($join, $args) {
		$table = $this->struct(array_shift($args));
		$this->jtable = $table;
		$this->joins .= " {$join} {$table} ON";
		$this->joins .= $this->joinOn($args);
	}

	/**
	 * End Private functions
	 */

	public function transaction() {
		$this->conn->beginTransaction();
	}

	public function commit() {
		$this->conn->commit();
	}

	public function query($query, $bindings = []) {
		$this->query = $query;
		$this->bindings = $bindings;
		return $this;
	}

	public function keyBy($key) {
		if(!$this->select) {
			$this->select = "{$key}, $this->table.*";
		}
		$this->select = "{$key}, {$this->select}";
		return $this->execute()->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN);
	}

	public function getByGroup($key) {
		if(!$this->select) {
			$this->select = "{$key}, $this->table.*";
		}
		$this->select = "{$key}, {$this->select}";
		return $this->execute()->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_OBJ);
	}

	public function lists($key, $val) {
		$this->select($key, $val);
		return $this->execute()->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	public function first($str = null) {
		$this->limit(1);
		if($str) {
			$this->select($str);
			$res = $this->execute()->fetch(PDO::FETCH_OBJ);
			if($res) {
				return $res->$str;
			}
			return '';
		}
		return $this->execute()->fetch(PDO::FETCH_OBJ);
	}

	public function firstArray() {
		$this->limit(1);
		return $this->execute()->fetch(PDO::FETCH_ASSOC);
	}

	public function getArray() {
		return $this->execute()->fetchAll(PDO::FETCH_ASSOC);
	}

	public function get() {
		return $this->execute()->fetchAll(PDO::FETCH_OBJ);
	}

	/**
	 * Get colum
	 * @param column number
	 */
	public function column($n = null) {
		return $this->execute()->fetchAll(PDO::FETCH_COLUMN, $n);

	}

	public function getByFunc($fn) {
		return $this->execute()->fetchAll(PDO::FETCH_FUNC, $fn);
	}

	public function pdo() {
		return $this->execute();
	}

	public function table($table) {
		$this->table = $this->struct($table);
		return $this;
	}

	public function select() {
		$args = func_get_args();
		$count = count($args);
		if($count == 1) {
			$select = $args[0];
		} elseif ($count > 1) {
			$select = $args;
		} else {
			throw new Exception("Invalid use of select, pass atleast on argument array or string", 1);
		}
		if(gettype($select) == 'array') {
			$selects = '';
			foreach ($select as $column) {
				$selects .= $this->struct($column) . ', ';
			}
			$select = rtrim($selects, ', ');
		}
		$this->select .= $select;
		return $this;
	}
	
	public function showTables() {
		$this->query = "SHOW TABLES";
		return $this;
	}

	public function explain() {
		$this->query = "EXPLAIN {$this->table}";
		return $this;
	}

	public function where() {
		return $this->condition(func_get_args());
	}

	public function orWhere() {
		return $this->condition(func_get_args(), 'OR');
	}

	public function whereNull($column) {
		return $this->where($column, 'IS', 'NULL');
	}

	public function whereNotNull($column) {
		return $this->where($column, 'IS', 'NOT NULL');
	}

	public function orWhereNull($column) {
		return $this->orWhere($column, 'IS', 'NULL');
	}

	public function orWhereNotNull($column) {
		return $this->orWhere($column, 'IS', 'NOT NULL');
	}

	public function whereIn($column, array $stmt, $cond = 'AND') {
		$this->bindings = array_merge($this->bindings, $stmt);
		$ins = implode(', ', array_fill(0, count($stmt), '?'));
		$condition = "`{$column}` IN ({$ins})";
		if($this->condition) {
			$this->condition .= " {$cond} {$condition}";
		} else {
			$this->condition .= "{$condition}";
		}
		return $this;
	}

	public function orWhereIn($column, array $stmt) {
		return $this->whereIn($column, $stmt, 'OR');
	}

	public function like($column, $value) {
		if(strpos($value, '%') === false) {
			$value = "%{$value}%";
		}
		return $this->where($column, "LIKE", $value);
	}

	public function orLike($column, $value) {
		if(strpos($value, '%') === false) {
			$value = "%{$value}%";
		}
		return $this->orWhere($column, "LIKE", $value);
	}

	public function limit($limit, $offset = null) {
		if(!$offset) {
			$this->limit = (int)$limit;
		} else {
			$this->limit = (int)$offset . ',' . (int)$limit;
		}
		return $this;
	}

	public function insertBatch(array $data) {
		$bindings = [];
		$columns = implode("`, `", array_keys($data[0]));
		$values = ''; $sufx = '), (';
		foreach ($data as $value) {
			$bindings = array_merge(array_values($value));
			$values .= implode(", ", array_fill(0, count(array_values($value)), '?')) . $sufx;
		}
		$this->bindings = $bindings;
		return [$columns, rtrim($values, $sufx)];
	}

	public function insert(array $data) {
		if(count($data) !== count($data, 1)) {
			list($columns, $values) = $this->insertBatch($data);
		} else {
			$val = array_values($data);
			$columns = implode("`, `", array_keys($data));
			$this->bindings = $val;
			$values = implode(", ", array_fill(0, count($val), "?"));
		}
		if(!$this->table) {
			throw new Exception("Please specify table name if db(tablename)", 1);
		}
		$this->query = "INSERT INTO {$this->table}(`{$columns}`) VALUES({$values})";
		return 	$this->execute()->rowCount();
	}

	public function update($data) {
		$vals = array_values($data);
		$set = implode('` = ?, `', array_keys($data));
		$this->query = "UPDATE {$this->table} SET `{$set}` = ?";
		if($this->condition) {
			$this->query .= " WHERE {$this->condition}";
		}
		$this->bindings = array_merge($vals, $this->bindings);
		$this->execute();
		return $this;
	}

	public function delete() {
		$this->query = "DELETE FROM {$this->table}";
		if($this->condition) {
			$this->query .= " WHERE {$this->condition}";
		}
		$this->execute();
	}

	public function on() {
		$this->joins .= $this->joinOn(func_get_args(), 'AND');
		return $this;
	}

	public function orOn() {
		$this->joins .= $this->joinOn(func_get_args(), 'OR');
		return $this;
	}

	public function join() {
		$this->joining("INNER JOIN", func_get_args());
		return $this;
	}

	public function leftJoin() {
		$this->joining("LEFT JOIN", func_get_args());
		return $this;
	}

	public function rightJoin() {
		$this->joining("RIGHT JOIN", func_get_args());
		return $this;
	}

	public function orderBy($column, $order = 'ASC') {
		if($this->sort) {
			$this->sort = ", `{$column}` {$order}";
		} else {
			$this->sort = "`{$column}` {$order}";
		}
		return $this;
	}

	public function groupBy($column) {
		if($this->group) {
			$this->group = ", `{$column}`";
		} else {
			$this->group = "`{$column}`";
		}
		return $this;
	}

	public function count($field = '*') {
		$this->select("COUNT($field) AS `count`");
		$stmt = $this->execute();
		return $stmt->fetch(PDO::FETCH_OBJ)->count;
	}
}

function db($table = null) {
	return DB::create($table);
}