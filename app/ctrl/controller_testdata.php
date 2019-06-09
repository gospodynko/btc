<?php

class controller_Testdata extends Controller {
	// получение списка заявок
	function action_orders()
	{
		$data = [
			[
	      "id" => 66,
	      "rur" => 5000,
	      "btc" => 0.01036668,
	      "number" => '',
	      "comment" => '00066',
	      "status" => 'currenttt',
	      "date" => '2018-08-10 15:52:49',
	      "wait_till" => '0000-00-00 00:00:00',
	      "payment_method_type" => 'qiwi',
	      "payment_method_data" => [
	         "payfrom" => '+79995559999'
	      ],
	      "user_id" => 11,
	      "user_name" => 'user',
	      "user_orders" => 1390,
	      "stats" => [
	         "exchanges" => [
	            "last_success_exchange" => '2018-07-02 16:39:35',
	            "last_month" => [
	               "success_count" => 0,
	               "canceled_count" => 2
	            ],
	            "total" => [
	               "success_count" => 6,
	               "canceled_count" => 18
	            ]
	         ],
	         "orders" => [
	            "last_success_order" => '2018-08-02 00:05:05',
	            "count" => '3533',
	            "price_avg" => 1256111,
	            "markets_count" => 16
	         ]
	      ]
	   ],
	   [
	       "id" => 67,
	       "rur" => 16000,
	       "btc" => 0.03879537,
	       "number" => '+71234567890',
	       "comment" => '00066',
	       "status" => 'payed',
	       "date" => '2018-08-10 15:52:49',
	       "wait_till" => '0000-00-00 00:00:00',
	       "payment_method_type" => 'qiwi',
	       "payment_method_data" => [
	          "payfrom" => '+79995559999'
	       ],
	       "user_id" => 11,
	       "user_name" => 'user',
	       "user_orders" => 1390,
	       "stats" => [
	          "exchanges" => [
	             "last_success_exchange" => '',
	             "last_month" => [
	                "success_count" => 0,
	                "canceled_count" => 0
	             ],
	             "total" => [
	                "success_count" => 0,
	                "canceled_count" => 0
	             ]
	          ],
	          "orders" => [
	             "last_success_order" => '',
	             "count" => 0,
	             "price_avg" => 0,
	             "markets_count" => 0
	          ]
	       ]
	    ]
	  ];
	  $this->echoJson($data);
	}

	// обновление статуса заявки
	function action_construct($arg)
	{
		header('Content-Type: application/json');
		$this->echoJson(['status' => 'success']);
	}

	function echoJson($data)
	{
		header('Content-Type: application/json');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
	}
}
