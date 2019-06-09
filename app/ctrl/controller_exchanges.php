<?php

class controller_Exchanges extends Controller
{
  const STATUS_CLOSE = 'close';
  const STATUS_SEND_REQUISITES = 'send_requisites';
  const STATUS_ANNUL = 'annul';
  const STATUS_CLOSE_ANNULLED = 'close_annulled';

  const TYPE_CURRENT = 'current';
  const TYPE_AUTOCANCELED = 'autocanceled';
  const TYPE_ANNULLED = 'annulled';
  const TYPE_CLOSED = 'closed';

  function __construct()
  {
    $this->model = new model_Exchanges();
  }

  function action_get()
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

    if (!isset($_GET['type'])) {
      parent::echoRequest('Не указан тип заявок для загрузки', 405);
      return;
    }
    $type = $_GET['type'];
    $dates_filter = null;
    if ($type === self::TYPE_ANNULLED || $type === self::TYPE_CLOSED) {
      if (!empty($_GET['datefrom'])) {
        $dates_filter['datefrom'] = $_GET['datefrom'];
      }
      if (!empty($_GET['dateto'])) {
        $dates_filter['dateto'] = $_GET['dateto'];
      }
      if (empty($dates_filter)) {
        parent::echoRequest('Недостаточно параметров');
        return;
      }
    }
    $exchanges = $this->getExchanges($account, $type, $adminRights, $dates_filter);
    if (is_null($exchanges)) {
      return;
    }
    parent::echoRequest($exchanges);
  }

  function action_update_status()
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

    if (!isset($account['token']) || empty($account['token'])) {
      parent::echoRequest('Для аккаунта не установлен токен доступа. Обратитесь к администратору');
      return;
    }
    $account_id = $account['id'];
    $account_balance = $account['balance'];
    $account_token = $account['token'];

    if (!isset($postData['status'])) {
      parent::echoRequest('Не передан статус обновления', 405);
      return;
    }

    if (!isset($postData['exchange_id'])) {
      parent::echoRequest('Не передан id заявки', 405);
      return;
    }

    if (!isset($postData['type'])) {
      parent::echoRequest('Не указан тип заявки для обновления', 405);
      return;
    }

    $type = $postData['type'];
    $status = $postData['status'];
    $updating_exchange_id = $postData['exchange_id'];

    switch ($status) {
      case self::STATUS_CLOSE:
      case self::STATUS_SEND_REQUISITES:
      case self::STATUS_CLOSE_ANNULLED:
        break;
      default:
        parent::echoRequest('Передан неизвестный статус', 405);
        return;
    }

    $postQuery = null;
    $requisites = null;

    if ($status === self::STATUS_SEND_REQUISITES) {
      if (!isset($postData['requisites'])) {
        parent::echoRequest('Не переданы реквизиты', 405);
        return;
      }
      $requisites = $postData['requisites'];
      $postQuery = array('number' => $requisites);
      if (isset($postData['comment'])) {
        $postQuery['comment'] = $postData['comment'];
      }
    } else if ($status === self::STATUS_CLOSE) {
      $postQuery = array('action' => 'close');
    }

    $exchanges = $this->getExchanges($account, $type, true);
    if (is_null($exchanges)) {
      return;
    }

    $updating_exchange_index = array_search($updating_exchange_id, array_column($exchanges, 'id'));
    if ($updating_exchange_index === false) {
      parent::echoRequest('Не найдена заявка для обновления', 500);
      return;
    }
    $updating_exchange = $exchanges[$updating_exchange_index];
    if (!empty($updating_exchange['error'])) {
      parent::echoRequest($updating_exchange['error']);
      return;
    }
    if ($status == self::STATUS_SEND_REQUISITES) {
      $checkUpdateData = $this->model->get_saved(array('number' => $requisites, 'rur' => $updating_exchange['rur'], 'account_id' => $account_id));
      if (parent::isSqlError($checkUpdateData)) {
        return;
      }
      if ($checkUpdateData) {
        parent::echoRequest('Данные реквизиты с указанной суммой уже были использованы ранее. Используйте другие реквизиты', 405);
        return;
      }
    }

    $trader_btc_value = $updating_exchange['btc'];
    $isNewExchange = $updating_exchange['is_new'];
    $exchange_status = $updating_exchange['status'];

    $incorrectStatus = false;
    switch ($exchange_status) {
      case 'new':
        if ($status !== self::STATUS_SEND_REQUISITES) {
          $incorrectStatus = true;
        }
        break;
      case 'payment_wait':
      case 'payed':
      case self::STATUS_SEND_REQUISITES:
        if ($status !== self::STATUS_CLOSE) {
          $incorrectStatus = true;
        }
        break;
      case 'autocanceled':
      case 'canceled':
      case self::STATUS_ANNUL:
        if (!$adminRights || $status !== self::STATUS_CLOSE_ANNULLED) {
          $incorrectStatus = true;
        }
        break;
      default:
        $incorrectStatus = true;
        break;
    }

    if ($incorrectStatus) {
      parent::echoRequest('Для заявки с данным статусом невозможно применить запрашиваемые изменения', 405);
      return;
    }

    if ($isNewExchange && $account_balance < $trader_btc_value) {
      parent::echoRequest('Недостаточно средств для обработки заявки, Пожалуйста, пополните баланс', 405);
      return;
    }

    if ($exchange_status === self::STATUS_ANNUL) {
      $changeBalanceQuery = parent::changeAccountBalance($account_id, -$trader_btc_value);
      if (is_null($changeBalanceQuery)) {
        return;
      }
    }

    if ($isNewExchange) {
      $changeBalanceQuery = parent::changeAccountBalance($account_id, -$trader_btc_value);
      if (is_null($changeBalanceQuery)) {
        return;
      }
    }

    $changeStatusQuery = parent::exchangePanelApiRequest($account_token, '/' . $updating_exchange_id . '?format=json', $postQuery);
    if (isset($changeStatusQuery['error'])) {
      if ($isNewExchange) {
        $changeBalanceQuery = parent::changeAccountBalance($account_id, $trader_btc_value, false);
        if (parent::isSqlError($changeBalanceQuery, false)) {
          $addingErrorToHistoryQuery = $this->model->addToExchangesHistory($updating_exchange_id, 'error', $account_id, 'Невозможность возврата средств в результате ошибки при завершении заявки. Сумма: ' . $trader_btc_value);
          $addingErrorToHistoryQueryError = '';
          if (parent::isSqlError($addingErrorToHistoryQuery, false)) {
            $addingErrorToHistoryQueryError = $addingErrorToHistoryQuery['error'];
          }
          parent::echoRequest('Не удалось вернуть средства на баланс аккаунта, хотя заявка не была завершена. Обратитесь к администратору и назовите номер заявки. ' . $addingErrorToHistoryQueryError, 500);
          return;
        }
      }
      parent::echoRequest($changeStatusQuery['error'], 500);
      return;
    }
    $addingToHistoryQuery = $this->model->addToExchangesHistory($updating_exchange_id, $status, $account_id, $requisites);
    $addingToHistoryQueryError = '';
    if (parent::isSqlError($addingToHistoryQuery, false)) {
      $addingToHistoryQueryError = $addingToHistoryQuery['error'];
    }

    $updating_exchange['status'] = $status;
    $updating_exchange['account_id'] = $account_id;
    if ($status === self::STATUS_SEND_REQUISITES) {
      $updating_exchange['number'] = $requisites;
    }
    $saveExchangeQueryError = '';
    $saveExchangeQuery = $this->model->saveExchange($updating_exchange, $isNewExchange);
    if (parent::isSqlError($saveExchangeQuery, false)) {
      $saveExchangeQueryError = $saveExchangeQuery['error'];
    }

    $statusResultText = '';
    switch ($status) {
      case self::STATUS_CLOSE:
      case self::STATUS_CLOSE_ANNULLED:
        $statusResultText = 'Заяка завершена';
        break;
      case self::STATUS_SEND_REQUISITES:
        $statusResultText = 'Реквизиты добавлены';
        break;
      default:
        $statusResultText = 'Заявка обновлена';
    }
    $errorsResultText = ($addingToHistoryQueryError !== '' || $saveExchangeQueryError !== '') ? (' c ошибкой при сохранении данных в историю транзакций: ' . $addingToHistoryQueryError . $saveExchangeQueryError) : '';
    parent::echoRequest($statusResultText . $errorsResultText);
  }

  function action_get_saved()
  {
    $account = parent::getAccountWithAdminRights();
    if (empty($account)) {
      return;
    }

    if (!isset($_GET['account_id'])) {
      parent::echoRequest('Не указан id аккаунта', 405);
      return;
    }

    $account_id = $_GET['account_id'];
    $exchanges = $this->model->get_saved(array('account_id' => $account_id));
    if (parent::isSqlError($exchanges)) {
      return;
    }

    parent::echoRequest($exchanges);
  }

  function action_history()
  {
    $account = parent::getAccountWithAdminRights();
    if (empty($account)) {
      return;
    }

    if (!isset($_GET['account_id'])) {
      parent::echoRequest('Не указан id аккаунта', 405);
      return;
    }

    $account_id = $_GET['account_id'];
    $history = $this->model->get_history($account_id);
    if (parent::isSqlError($history)) {
      return;
    }

    parent::echoRequest($history);
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

  function action_get_exchange_info()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    if (empty($_GET['id'])) {
      parent::echoRequest('Не указан id заявки', 405);
      return;
    }

    if (empty($_GET['account_id'])) {
      parent::echoRequest('Не указан id аккаунта', 405);
      return;
    }

    $accountsList = parent::getAccountsById($_GET['account_id']);
    if (parent::isSqlError($accountsList)) {
      return;
    }

    $exchange_id = $_GET['id'];
    $account = $accountsList[0];
    $exchange_info = parent::exchangePanelApiRequest($account['token'], '/' . $exchange_id . '?format=json');
    if (is_null($exchange_info)) {
      return;
    }

    parent::echoRequest($exchange_info);
  }

  private function getExchanges($account, $type, $withSourceFields = false, $dates_filter = null)
  {
    if (!isset($account['exchange_rate_markup']) || empty($account['exchange_rate_markup'])) {
      parent::echoRequest('Для аккаунта не установлен курс обмена. Обратитетесь к администратору', 403);
      return null;
    }
    if (!isset($account['token']) || empty($account['token'])) {
      parent::echoRequest('Для аккаунта не установлен токен доступа. Обратитетесь к администратору', 403);
      return null;
    }
    $account_id = $account['id'];
    $account_rate_markup = $account['exchange_rate_markup'];
    $account_token = $account['token'];

    $savedActiveExchanges = $this->model->get_saved(array('status' => self::STATUS_SEND_REQUISITES, 'account_id' => $account_id));
    if (parent::isSqlError($savedActiveExchanges)) {
      return null;
    }

    $is_with_pagination = false;
    $queryUrl = '';

    switch ($type) {
      case self::TYPE_AUTOCANCELED:
        $queryUrl = '/orders/autocanceled?page=1&per_page=150&format=json';
        $is_with_pagination = true;
        break;
      default:
        $queryUrl = '/orders?format=json';
    }

    $currentExchanges = parent::exchangePanelApiRequest($account_token, $queryUrl);
    if (isset($currentExchanges['error'])) {
      parent::echoRequest($currentExchanges['error'], 500);
      return null;
    }

    if ($is_with_pagination && isset($currentExchanges['data'])) {
      $currentExchanges = $currentExchanges['data'];
    }

    if (!is_array($currentExchanges)) {
      parent::echoRequest('Ошибка в структуре данных по заявкам', 500);
      return null;
    }

    $sourceExchangeRate = parent::getBtcToRurExchangeRate();
    if (isset($sourceExchangeRate['error'])) {
      return null;
    }

    $filteredCurrentExchanges = array_filter($currentExchanges, function ($exchange) {
      if (isset($exchange['payment_method_type'])) {
        return $exchange['payment_method_type'] === 'qiwi';
      }
      return false;
    });

    $formattedCurrentExchanges = [];

    foreach ($filteredCurrentExchanges as &$exchange) {
      $exchangeError = null;

      $exchange['client_number'] = !empty($exchange['payment_method_data']) && !empty($exchange['payment_method_data']['payfrom'])
        ? $exchange['payment_method_data']['payfrom']
        : null;

      $exchange['stats'] = !empty($exchange['stats']) && !empty($exchange['stats']['exchanges'])
        ? $exchange['stats']['exchanges']
        : null;


      if (isset($exchange['rur']) && !empty($exchange['rur']) && isset($exchange['btc']) && !empty($exchange['btc'])) {
        $savedExchangesList = $this->model->get_saved(array('platform_exchange_id' => $exchange['id']));
        if (parent::isSqlError($savedExchangesList, false)) {
          $exchangeError = $savedExchangesList['error'];
        }
        $platformBtcValue = $exchange['btc'];

        if (!isset($savedExchangesList['error']) && count($savedExchangesList) === 1) {
          $savedExchange = $savedExchangesList[0];

          // Если заявка ранее была сохранена под другим аккаунтом, то не выводим ее
          if ($savedExchange['account_id'] !== $account_id) {
            continue;
          }
          $exchange['btc'] = $savedExchange['btc'];
          $exchange['exchange_rate'] = $savedExchange['exchange_rate'];
          $exchange['is_new'] = false;

          if ($withSourceFields) {
            $exchange['btc_platform'] = $savedExchange['btc_platform'];
            $exchange['source_exchange_rate'] = $savedExchange['source_exchange_rate'];
          }

          if ($savedExchange['rur'] != $exchange['rur'] || !empty($platformBtcValue) && $savedExchange['btc_platform'] != $platformBtcValue) {
            $exchangeError = 'Сумма в заявке не совпадает с сохраненными данными, проверьте данные заявки';
          }
        } else {
          if ($withSourceFields) {
            $exchange['btc_platform'] = $platformBtcValue;
            $exchange['source_exchange_rate'] = $sourceExchangeRate;
          }
          $exchangeRate = round($sourceExchangeRate * (1 + $account_rate_markup), 2);
          $exchange['btc'] = round($exchange['rur'] / $exchangeRate, 10);
          $exchange['exchange_rate'] = $exchangeRate;
          $exchange['is_new'] = true;
        }
      } else {
        $exchangeError = 'Отстутствует значение суммы по заявке';
      }

      $exchange['error'] = $exchangeError;

      $fieldsToDelete = ['payment_method_type', 'payment_method_data', 'user_id', 'user_name', 'user_orders', 'user_register_date'];
      foreach ($fieldsToDelete as $value) {
        if (isset($exchange[$value])) {
          unset($exchange[$value]);
        }
      }
      $formattedCurrentExchanges[] = $exchange;
    }

    $balanceValueToIncrease = 0;
    $exchangesIdsToAnnul = [];

    foreach ($savedActiveExchanges as $savedExchange) {
      $currentExchangeIndex = array_search($savedExchange['id'], array_column($formattedCurrentExchanges, 'id'));
      if ($currentExchangeIndex === false) {
        $balanceValueToIncrease += $savedExchange['btc'];
        $exchangesIdsToAnnul[] = $savedExchange['id'];
      }
    }

    if (count($exchangesIdsToAnnul) > 0) {
      $changeBalanceQuery = parent::changeAccountBalance($account_id, $balanceValueToIncrease);
      if (is_null($changeBalanceQuery)) {
        return null;
      }

      $annulExchangesQuery = $this->model->annul_exchanges($exchangesIdsToAnnul, $account_id, self::STATUS_ANNUL);
      if (parent::isSqlError($annulExchangesQuery, false)) {
        $revertBalanceQuery = parent::changeAccountBalance($account_id, -$balanceValueToIncrease, false);
        if (parent::isSqlError($revertBalanceQuery, false)) {
          $addingErrorToHistoryQueryError = '';
          $exchangesIdsToAnnulString = join(', ', $exchangesIdsToAnnul);
          foreach ($exchangesIdsToAnnul as $id) {
            $addingErrorToHistoryQuery = $this->model->addToExchangesHistory(
              $id,
              'error',
              $account_id,
              "Невозможность возврата средств в результате ошибки при аннулировании заявок. Общая сумма (по заявкам "
              . $exchangesIdsToAnnulString . "): " . $balanceValueToIncrease
            );
            if (parent::isSqlError($addingErrorToHistoryQuery, false)) {
              $addingErrorToHistoryQueryError = $addingErrorToHistoryQuery['error'];
            }
          }
          parent::echoRequest(
            "Не удалось вернуть средства на баланс аккаунта, хотя заявки не были аннулированы. Обратитесь к администратору и назовите номера заявок ("
            . $exchangesIdsToAnnulString . "). " . $addingErrorToHistoryQueryError,
            500
          );
          return null;
        }
        parent::echoRequest($annulExchangesQuery['error'], 500);
        return null;
      }

      foreach ($exchangesIdsToAnnul as $id) {
        $this->model->addToExchangesHistory($id, self::STATUS_ANNUL, $account_id);
      }
    }

    switch ($type) {
      case self::TYPE_CURRENT:
      case self::TYPE_AUTOCANCELED:
        return $formattedCurrentExchanges;
      case self::TYPE_ANNULLED:
        $annulledExchanges = $this->model->get_saved(array('account_id' => $account_id, 'status' => [self::STATUS_ANNUL]), $dates_filter);
        if (parent::isSqlError($annulledExchanges)) {
          return null;
        }
        return $annulledExchanges;
      case self::TYPE_CLOSED:
        $closedExchanges = $this->model->get_saved(array('account_id' => $account_id, 'status' => [self::STATUS_CLOSE, self::STATUS_CLOSE_ANNULLED]), $dates_filter);
        if (parent::isSqlError($closedExchanges)) {
          return null;
        }
        return $closedExchanges;
      default:
        parent::echoRequest('Передан неизвестный тип заявок', 405);
        return null;
    }
  }
}
