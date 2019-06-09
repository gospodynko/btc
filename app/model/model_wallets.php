<?php


class model_Wallets extends Model {

  function saveWallets ($wallet, $limit, $account_id)
  {
    return Conn::query("
      INSERT INTO wallets
      (wallet, lim, account_id)
      VALUES (:wallet, :lim, :account_id)
    ", array(
        'wallet' => $wallet, 'lim' => $limit, 'account_id' => $account_id)
    );
  }

  function getWallets ($account_id)
  {
    return Conn::queryData("
      SELECT * 
      FROM wallets
      WHERE account_id = $account_id"
    );
  }

  function deleteWallets ($id)
  {
    return Conn::query("
      DELETE FROM wallets WHERE id = :id
    ", array('id' => $id));
  }

}