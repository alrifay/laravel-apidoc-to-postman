<?php

namespace Alrifay\ApidocToPostman;

use Illuminate\Support\Collection;
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

    public static function getCollection($id)
    {
        return Http::withHeaders([
            'X-API-Key' => self::$key,
        ])
            ->get(self::$baseUrl . "/collections/$id")
            ->json();
    }

    public static function updateCollection($id, $data)
    {
        return Http::withHeaders([
            'X-API-Key' => self::$key,
        ])->put(self::$baseUrl . "/collections/$id", [
            'collection' => $data,
        ])->json();
    }

    public static function populateValues(array &$generatedCollection, array $postmanCollection, string $PreviousKey = '')
    {
        $PreviousKey = $PreviousKey ? "$PreviousKey." : $PreviousKey;
        foreach ($generatedCollection as $key => $value) {
            if ($key == 'formdata') {
                static::syncArray($generatedCollection[$key], \Arr::get($postmanCollection, $PreviousKey . 'formdata'));
            } elseif (is_iterable($value)) {
                self::populateValues($generatedCollection[$key], $postmanCollection, $PreviousKey . $key);
            }
        }
    }

    public static function syncArray(array &$data, array $postmanData = null)
    {
        if (!$postmanData) return;
        $postmanData = collect($postmanData);
        foreach ($data as &$param) {
            $postmanParam = $postmanData->firstWhere('key', $param['key']);
            if (!$postmanParam) {
                continue;
            }
            if ($postmanParam['value'] ?? null) {
                $param['value'] = $postmanParam['value'];
            }
            if ($postmanParam['src'] ?? null) {
                $param['src'] = $postmanParam['src'];
            }
            $param['disabled'] = $postmanParam['disabled'] ?? $param['disabled'] ?? false;
        }
    }

    public static function createOrUpdateCollection($data, $name = null)
    {
        $collection = static::collections()->firstWhere('name', $name);
        $data['info']['name'] = $name;
        $data['info']['description'] = \Str::random();
        if ($collection) {
            static::populateValues($data, static::getCollection($collection['id'])['collection']);
            return static::updateCollection($collection['id'], $data);
        }
        return static::createCollection($data);
    }

    public static function syncCollection(PostmanConfig $config, array $collection): bool
    {
        self::$key = $config->key;
        $collection = json_decode(json_encode($collection), true);
        $response = self::createOrUpdateCollection($collection, $config->collectionName);
        if ($response['error'] ?? null) {
            \Log::error($response['error']['message'] ?? '', $response);
            return false;
        }
        return true;
    }
}