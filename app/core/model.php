<?php

class Model
{
  function isSqlError($queryResult)
  {
    if (isset($queryResult['error'])) {
      return true;
    } else {
      return false;
    }
  }

  function getAccountsByHash($hash, $getFullInfo = false)
  {
    $accounts = Conn::queryData("
      SELECT a.id, a.name, a.login, a.rights, a.reg_date, a.balance
      " . ($getFullInfo ? ', a.exchange_rate_markup, p.token' : '') . "
      FROM accounts a LEFT JOIN platforms p ON a.platform_id = p.id
      WHERE a.hash = :hash
    ", array('hash' => $hash));
    return $accounts;
  }

  function changeAccountBalance($id, $changeValue) {
    return Conn::query("
      UPDATE accounts
      SET balance = balance + :change_value
      WHERE id = :id
    ", array('id' => $id, 'change_value' => $changeValue));
  }

  function getAccountsById($id)
  {
    $accounts = Conn::queryData("
      SELECT a.id, a.name, a.login, a.rights, a.reg_date, a.balance, a.exchange_rate_markup, p.token
      FROM accounts a LEFT JOIN platforms p ON a.platform_id = p.id
      WHERE a.id = :id
    ", array('id' => $id));
    return $accounts;
  }

  function encryptPassword($password) {
    return md5($password . 'd&9fHggO');
  }
}
