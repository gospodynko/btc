<?php

class Conn
{
  public static $conn = false;

  function connect()
  {
    if (self::$conn === false) {
      $host = DB_HOST;
      $db = DB_NAME;
      $charset = 'utf8';
      $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
      $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

      ];
      self::$conn = new PDO($dsn, DB_USER, DB_PASS, $opt);
    }
  }

  public function queryData($query, $vars = array(), $like_vars = FALSE)
  {
    self::connect();
    try {
      $stmt = self::$conn->prepare($query);
      $stmt->execute($vars);
      try {
        if ($like_vars === true) return $stmt->fetchAll(PDO::FETCH_UNIQUE); else return $stmt->fetchAll();
      } catch (PDOException $e) {
        return array('error' => $e->getMessage());
      }
    } catch (PDOException $e) {
      return array('error' => $e->getMessage());
    }
  }

  public function query($query, $vars = array())
  {
    self::connect();
    try {
      $stmt = self::$conn->prepare($query);
      $result = $stmt->execute($vars);
    } catch (PDOException $e) {
      return array('error' => $e->getMessage());
    }
    return $result;
  }

  public function lastID()
  {
    self::connect();
    return self::$conn->lastInsertId();
  }
}