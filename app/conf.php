<?php

$config = parse_ini_file('config.ini');

define('MODEL_PATH', $config['MODEL_PATH']);
define('CTRL_PATH', $config['CTRL_PATH']);
define('CORE_PATH', $config['CORE_PATH']);
define('MODULES_PATH', $config['MODULES_PATH']);
define('API_PATH', $config['API_PATH']);
define('API_VERSION', $config['API_VERSION']);
define('REACT_FILE', $config['REACT_FILE']);
#set TRUE on release!
define('RELEASE', $config['RELEASE']);

// DataBase Section
define('DB_HOST', $config['DB_HOST']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
define('DB_NAME', $config['DB_NAME']);

// EXCHANGE PANEL
define('EXCHANGE_PANEL_HOST', $config['EXCHANGE_PANEL_HOST']);
define('EXCHANGE_PANEL_API_PATH', $config['EXCHANGE_PANEL_API_PATH']);
define('EXCHANGE_PANEL_API_HEADER', $config['EXCHANGE_PANEL_API_HEADER']);
define('EXCHANGE_PANEL_API_TOR_PROXY', $config['EXCHANGE_PANEL_API_TOR_PROXY']);

// BLOCKCHAIN
define('BLOCKCHAIN_API_HOST', $config['BLOCKCHAIN_API_HOST']);
define('BLOCKCHAIN_API_KEY', $config['BLOCKCHAIN_API_KEY']);
define('BLOCKCHAIN_API_XPUB', $config['BLOCKCHAIN_API_XPUB']);
