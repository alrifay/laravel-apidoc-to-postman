<?php

namespace Alrifay\ApidocToPostman;

use Illuminate\Support\Facades\Http;

class Postman
{
    private static string $key = '';
    private static string $baseUrl = 'https://api.getpostman.com';

    public static function collections()
    {
        return Http::withHeaders([
            'X-API-Key' => self::$key,
        ])->get(self::$baseUrl . "/collections")->collect('collections');
    }

    public static function createCollection($data)
    {
        return Http::withHeaders([
            'X-API-Key' => self::$key,
        ])->post(self::$baseUrl . "/collections", [
            'collection' => $data,
        ])->json();
    }

    public static function updateCollection($id, $data)
    {
        return Http::withHeaders([
            'X-API-Key' => self::$key,
        ])->put(self::$baseUrl . "/collections/$id", [
            'collection' => $data,
        ])->json();
    }

    public static function createOrUpdateCollection($data, $name = null)
    {
        $collection = static::collections()->firstWhere('name', $name);
        $data['info']['name'] = $name;
        $data['info']['description'] = \Str::random();
        if ($collection) {
            return static::updateCollection($collection['id'], $data);
        }
        return static::createCollection($data);
    }

    public static function syncCollection(PostmanConfig $config, array $collection): bool
    {
        self::$key = $config->key;

        $response = self::createOrUpdateCollection($collection, $config->collectionName);
        if ($response['error'] ?? null) {
            \Log::error($response['error']['message'] ?? '', $response);
            return false;
        }
        return true;
    }
}