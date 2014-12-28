<?php
error_reporting(E_ALL);
class Database extends PDO {
	public $stmt;
	public function __construct() {
		$host = "mysql:host=localhost;dbname=adil";
		$user = "root";
		$pwd = "";
		try {
			parent::__construct($host, $user, $pwd);
			$this->setAttribute(parent::ATTR_ERRMODE, parent::ERRMODE_EXCEPTION);
		}catch (PDOException $e) {
			echo $e->getMessage();
		}
	}

	private function myQuery($query, $bind = []) {
		$this->stmt = $this->prepare($query);
		$this->stmt->execute($bind);
		return $this;
	}

	private function get($fetch = 'both') {
		$f = "parent::FETCH_" . strtoupper($fetch);
		return $this->stmt->fetchAll(constant($f));
	}
	public function getUsersname() {
		return $this->myQuery("SELECT username from user")->get('assoc');
	}

	public function getName() {
		return $this->myQuery("SELECT name from user")->get();
	}

	public function insertUser($name, $username, $password) {
		$this->myQuery("INSERT INTO user(name, username, password)
		 values(:name, :username, :password)", [
		 	'name' => $name,
		 	'username' => $username,
		 	'password' => $password
		 ]);
		return $this;
	}

	public function getUserById($id) {
		$res = $this->myQuery("SELECT username FROM user WHERE id=:id", ["id" => $id])->get();
		if($res) return $res[0]['username'];
		return false;
	}
}

return new Database();
