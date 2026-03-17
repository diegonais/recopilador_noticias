<?php

class NewsController
{
    private $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index()
    {
        $news = $this->storageService->readNews();

        ResponseHelper::json(array(
            'success' => true,
            'count' => count($news),
            'updated_at' => $this->storageService->getLastUpdatedAt(),
            'data' => $news,
        ));
    }
}