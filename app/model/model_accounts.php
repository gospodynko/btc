<?php

class model_Accounts extends Model
{
  public function get()
  {
    return Conn::queryData("
      SELECT id, name, login, rights, reg_date, balance, exchange_rate_markup, platform_id
      FROM accounts
    ");
  }

  public function add($data)
  {
    $fields = '';
    $values = '';
    $i = 0;
    foreach ($data as $key => $value) {
      $fields .= ($i === 0 ? "" : ",") . "`" . $key . "`";
      $values .= ($i === 0 ? "" : ",") . "'" . ($key === 'password' ? parent::encryptPassword($value) : $value) . "'";
      $i++;
    }
    $query = Conn::query("
      INSERT INTO accounts
      (" . $fields . ")
      VALUES (" . $values . ")
    ");
    if (parent::isSqlError($query)) {
      return $query;
    }
    return Conn::lastId();
  }

  public function delete($id)
  {
    return Conn::query("
      DELETE FROM accounts WHERE id = :id
    ", array('id' => $id));
  }

  public function update($userId, $update_fields)
  {
    $query_fields = '';
    $i = 0;
    foreach ($update_fields as $key => $value) {
      $query_fields .= ($i === 0 ? "" : ",") . "`" . $key . "`='" . ($key === 'password' ? parent::encryptPassword($value) : $value) . "'";
      $i++;
    }
    $query = Conn::query("
      UPDATE `accounts` SET " . $query_fields . " WHERE id = :id 
    ", array('id' => $userId));
    return $query;
  }

  public function getAccountsByIdAndPassword($userId, $password)
  {
    return Conn::queryData("
      SELECT id
      FROM accounts
      WHERE id = :id AND password = :password
    ", array('id' => $userId, 'password' => parent::encryptPassword($password)));
  }
}
