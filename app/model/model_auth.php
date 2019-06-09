<?php

class model_Auth extends Model
{
  function getAccounts($login, $pass)
  {
    $accounts = Conn::queryData("
      SELECT `id`, `rights` FROM accounts
      WHERE login = :login AND password = :pass
    ", array('login' => $login, 'pass' => parent::encryptPassword($pass)));
    return $accounts;
  }

  function setHash($userId, $hash)
  {
    $query = Conn::query("
      UPDATE accounts SET `hash` = :hash WHERE `id` = :id
    ", array('id' => $userId, 'hash' => $hash));
    return $query;
  }
}