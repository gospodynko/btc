<?php

class model_transactions extends Model
{
  public function create_transaction($accountId, $secretCode)
  {
    return Conn::query("
      INSERT INTO transactions (account_id, secret)
      VALUES (:account_id, :secret)
    ", array('account_id' => $accountId, 'secret' => $secretCode));
  }

  public function add_address_to_transaction($id, $address, $status)
  {
    return Conn::query("
      UPDATE transactions SET address = :address, status = :status
      WHERE id = :id
    ", array('id' => $id, 'address' => $address, 'status' => $status, ));
  }

  public function update_balance($secretCode, $address, $value, $status)
  {
    return Conn::query("
      UPDATE transactions SET value = :value, status = :status
      WHERE secret = :secret AND address = :address
    ", array('secret' => $secretCode, 'address' => $address, 'value' => $value, 'status' => $status));
  }

  public function add_error_to_transactions($id, $error)
  {
    return Conn::query("
      UPDATE transactions SET status = :error
      WHERE id = :id
    ", array('id' => $id, 'error' => $error));
  }

  public function get_account_id_by_transaction($secretCode, $address)
  {
    return Conn::queryData("
      SELECT account_id FROM transactions
      WHERE secret = :secret AND address = :address
    ", array('secret' => $secretCode, 'address' => $address));
  }
}