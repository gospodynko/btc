<?php

class controller_Accounts extends Controller
{

  function __construct()
  {
    $this->model = new model_Accounts();
  }

  function action_get()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $accounts = $this->model->get();
    if (parent::isSqlError($accounts)) {
      return;
    }

    parent::echoRequest($accounts);
  }

  function action_add()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $available_fields = ['login', 'name', 'password', 'rights', 'exchange_rate_markup', 'balance', 'platform_id'];
    $data = $this->check_updating_fields($available_fields, true);
    if (empty($data)) {
      return;
    }

    $id = $this->model->add($data);
    if (parent::isSqlError($id)) {
      return;
    }

    $accounts = parent::getAccountsById($id);
    if (empty($accounts)) {
      return;
    }

    parent::echoRequest($accounts[0]);
  }

  function action_delete()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $data = parent::parsePostData();
    if (empty($data)) {
      return null;
    }

    if (!isset($data['id'])) {
      parent::echoRequest('Не указан id для удаления', 405);
      return;
    }

    $id = $data['id'];
    $query = $this->model->delete($id);
    if (parent::isSqlError($query)) {
      return;
    }

    parent::echoRequest('Success');
  }

  function action_update()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $available_fields = ['id', 'login', 'name', 'password', 'rights', 'exchange_rate_markup', 'balance', 'platform_id'];
    $data = $this->check_updating_fields($available_fields);
    if (empty($data)) {
      return;
    }

    if (!isset($data['id'])) {
      parent::echoRequest('Не передан id аккаунта для обновления', 405);
      return;
    }

    $id = $data['id'];
    $update_query = $this->model->update($id, $data);
    if (parent::isSqlError($update_query)) {
      return;
    }

    $accounts = parent::getAccountsById($id);
    if (empty($accounts)) {
      return;
    }

    parent::echoRequest($accounts[0]);
  }

  function action_get_platforms()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $platforms = $this->model->get_platforms();
    if (parent::isSqlError($platforms)) {
      return;
    }

    parent::echoRequest($platforms);
  }

  function action_get_platform_exchange_rate()
  {
    $account = parent::getAccountWithAdminRights();
    if (empty($account)) {
      return;
    }

    if (!isset($_GET['platform_id'])) {
      parent::echoRequest('Не указан id платформы для получения обменного курса', 405);
      return;
    }

    $platformId = $_GET['platform_id'];
    $platformsList = $this->model->getPlatformById($platformId);
    if (parent::isSqlError($platformsList)) {
      return;
    }

    if (empty($platformsList)) {
      parent::echoRequest('Отсутствует платформа с указанным id', 405);
      return;
    }

    $platform = $platformsList[0];
    if (empty($platform['token'])) {
      parent::echoRequest('Отсутствует токен для указанной платформы', 500);
      return;
    }

    $platformToken = $platform['token'];
    $exchangeRate = parent::getPlatformExchangeRate($platformToken);
    if (is_null($exchangeRate)) {
      return;
    }

    parent::echoRequest($exchangeRate);
  }

  function action_get_source_exchange_rate()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $exchangeRate = parent::getBtcToRurExchangeRate();
    if (isset($exchangeRate['error'])) {
      return;
    }

    parent::echoRequest($exchangeRate);
  }

  private function check_updating_fields($available_fields, $is_all_fields_required = false)
  {
    $data = parent::parsePostData();
    if (empty($data)) {
      return null;
    }

    $not_match_fields = [];
    foreach ($data as $key => $field):
      if (!in_array($key, $available_fields)) {
        $not_match_fields[] = $key;
      }
    endforeach;

    if (!empty($not_match_fields)) {
      $error_fields = '';
      for ($i = 0; $i < count($not_match_fields); $i++):
        $error_fields .= ($i === 0 ? '' : ', ') . $not_match_fields[$i];
      endfor;
      parent::echoRequest('Переданы неизвестные поля: ' . $error_fields, 405);
      return null;
    }

    if ($is_all_fields_required && count($available_fields) !== count($data)) {
      parent::echoRequest('Недостаточно данных', 405);
      return null;
    }

    if (isset($data['password']) && iconv_strlen($data['password']) < 6) {
      parent::echoRequest('Длина пароля должна быть не менее 6 символов', 405);
      return null;
    }

    return $data;
  }
}
