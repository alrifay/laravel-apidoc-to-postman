<?php

namespace Alrifay\ApidocToPostman;

use Illuminate\Console\Command;

class PostmanConfig
{
    public string $key = '';
    public string $collectionName = '';
    public bool $sync = false;

    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/postman.json');
    }

    public static function read(Command $command, string $defaultCollectionName = ''): PostmanConfig
    {
        $config = new self;
        if ($config->fileExists()) {
            $config->readFile();
            return $config;
        }
        $config->askForConfig($command, $defaultCollectionName);
        $config->saveConfig();
        return $config;
    }

    private function fileExists(): bool
    {
        return \File::exists($this->path);
    }

    private function readFile()
    {
        $config = json_decode(\File::get($this->path), false);
        $this->key = $config->key ?? $this->key;
        $this->collectionName = $config->collectionName ?? $this->collectionName;
        $this->sync = $config->sync ?? $this->sync;
    }

    public function askForConfig(Command $command, string $defaultCollectionName = '')
    {
        $this->sync = $command->confirm('Sync collection to postman?');
        if (!$this->sync) {
            return;
        }
        $this->collectionName = $command->ask('Collection name in postman', $defaultCollectionName);
        $this->key = $command->secret('Postman api token (https://web.postman.co/settings/me/api-keys)');
        //TODO: Check key
    }

    private function saveConfig(): void
    {
        \File::put($this->path, json_encode([
            'key'            => $this->key,
            'collectionName' => $this->collectionName,
            'sync'           => $this->sync,
        ], JSON_PRETTY_PRINT));
    }

}