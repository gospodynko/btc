<?php

class controller_Transactions extends Controller
{
  function __construct()
  {
    $this->model = new model_Transactions();
  }

  public function action_generate_receive_address()
  {
    $account = parent::getAccountWithTraderRights(true);
    if (empty($account)) {
      return;
    }

    $secretCode = parent::generateCode();

    $createTransaction = $this->model->create_transaction($account['id'], $secretCode);
    if (parent::isSqlError($createTransaction)) {
      return;
    }
    $transactionId = Conn::lastId();

    $callbackUrl = $_SERVER['SERVER_NAME'] . '/' . API_PATH . '/' . API_VERSION . '/transactions/update_balance/?secret=' . $secretCode;
    $receiveAddressQuery = $this->make_blockchain_api_request('/v2/receive?xpub=' . BLOCKCHAIN_API_XPUB . '&callback=' . $callbackUrl . '&key=' . BLOCKCHAIN_API_KEY);

    if (isset($receiveAddressQuery['error'])) {
      parent::echoRequest($receiveAddressQuery['error'], 500);
      return;
    }

    if (!isset($receiveAddressQuery->address)) {
      parent::echoRequest('Ошибка при получении адреса кошелька', 500);
      $this->model->add_error_to_transactions($transactionId, 'Ошибка получения адреса кошелька');
      return;
    }

    $address = $receiveAddressQuery->address;
    $addAddressToTransaction = $this->model->add_address_to_transaction($transactionId, $address, 'Сгенерирован адрес');
    if (parent::isSqlError($addAddressToTransaction)) {
      return;
    }

    parent::echoRequest($address);
  }

  public function action_update_balance()
  {
    $data = parent::parsePostData();
    if (is_null($data)) {
      return;
    }

    if (!isset($data['secret']) || !isset($data['value']) || !isset($data['address'])) {
      parent::echoRequest('Недостаточно данных для обновления баланса', 405);
      return;
    }

    $secretCode = $data['secret'];
    $address = $data['address'];
    $value = $data['value'];

    $accountIdsList = $this->model->get_account_id_by_transaction($secretCode, $address);
    if (parent::isSqlError($accountIdsList)) {
      return;
    }
    if (empty($accountIdsList)) {
      parent::echoRequest('Не найдена транзакция для обновления', 405);
      return;
    }

    $accountId = $accountIdsList[0]['account_id'];

    $changeBalance = parent::changeAccountBalance($accountId, $value);
    if (is_null($changeBalance)) {
      return;
    }

    $updateBalance = $this->model->update_balance($secretCode, $address, $value, 'Получено подтверждение платежа');
    if (parent::isSqlError($updateBalance)) {
      return;
    }

    parent::echoRequest('Транзакция успешно подтверждена');
  }

  private function make_blockchain_api_request($url, $postQuery = null)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BLOCKCHAIN_API_HOST . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if (!is_null($postQuery)) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postQuery);
    }
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error)) {
      return array('error' => $curl_error);
    }

    if (empty($result)) {
      return array('error' => 'Запрос вернул пустое значение');
    }

    return $result;
  }
}
