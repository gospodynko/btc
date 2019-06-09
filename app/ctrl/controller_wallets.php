<?php

class controller_Wallets extends Controller {

  public $model;

  function __construct()
  {
    $this->model = new model_Wallets();
  }

  function action_add ()
  {
    $postData = parent::parsePostData();
    if (is_null($postData)) {
      return;
    }

    $adminRights = false;
    $account = null;
    if (isset($postData['account_id'])) {
      if (empty(parent::getAccountWithAdminRights())) {
        return;
      }
      $accountsList = parent::getAccountsById($postData['account_id']);
      if (parent::isSqlError($accountsList)) {
        return;
      }
      $account = $accountsList[0];
      $adminRights = true;
    } else {
      $account = parent::getAccountWithTraderRights(true);
      if (empty($account)) {
        return;
      }
    }

    if (!isset($postData['data'])) {
      parent::echoRequest('Не указан ни один кошелек', 405);
      return;
    }
    $wallets = $postData['data'];
    foreach ($wallets as $wallet) {
      if (!$wallet['wallet']) {
        parent::echoRequest('Не указан номер кошелька', 405);
        return;
      }
      if (!$wallet['limit']) {
        parent::echoRequest('Не указан лимит кошелька', 405);
        return;
      }
      $save = $this->model->saveWallets($wallet['wallet'], $wallet['limit'], $postData['account_id']);
        if (parent::isSqlError($save)) {
          parent::echoRequest('Ошибка работы БД', 405);
          return;
        }
        return parent::echoRequest('Данные сохранены успешно!', 200);
    }
  }
  public function action_get ()
  {
    $account = null;
    $adminRights = false;
    if (isset($_GET['account_id'])) {
      if (empty(parent::getAccountWithAdminRights())) {
        return;
      }
      $accountsList = parent::getAccountsById($_GET['account_id']);
      if (parent::isSqlError($accountsList)) {
        return;
      }
      $account = $accountsList[0];
      $adminRights = true;
    } else {
      $account = parent::getAccountWithTraderRights(true);
      if (empty($account)) {
        return;
      }
    }
    $account_id = $account['id'];
    $wallets = $this->model->getWallets($account_id);
    if (parent::isSqlError($accountsList)) {
      return;
    }
    parent::echoRequest($wallets);
  }
  public function action_delete ()
  {

  }
}
