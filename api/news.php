<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/DateHelper.php';
require_once __DIR__ . '/../app/helpers/TextHelper.php';
require_once __DIR__ . '/../app/helpers/ResponseHelper.php';
require_once __DIR__ . '/../app/services/StorageService.php';
require_once __DIR__ . '/../app/controllers/NewsController.php';

$storageService = new StorageService();
$controller = new NewsController($storageService);

$controller->index();