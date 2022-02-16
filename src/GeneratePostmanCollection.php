<?php

namespace Alrifay\ApidocToPostman;

use Illuminate\Support\Collection;
use League\HTMLToMarkdown\HtmlConverter;

class GeneratePostmanCollection
{
    private array $apiData = [];
    private array $apiProject = [];
    private array $postmanData = [];

    public function __construct($project, $data)
    {
        $this->apiData = $data;
        $this->apiProject = $project;
        $this->postmanData['$schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
    }

    public function generate(): GeneratePostmanCollection
    {
        $this->setInfo();
        $this->setItems();
        $this->setEvent();
        $this->setVariables();
        return $this;
    }

    public function save($path)
    {
        return \File::put($path, json_encode($this->postmanData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function setVariables()
    {
        $this->postmanData['variable'] = collect($this->apiProject['postman']['permissions'] ?? [])
            ->map(fn($variable, $permission) => [
                'key'   => $variable,
                'value' => '',
            ])->values();
        $this->postmanData['variable'][] = [
            'key'   => 'url',
            'value' => $this->apiProject['url'],
        ];
    }

    private function setEvent()
    {
        $acceptJsonEvent = [
            'listen' => 'prerequest',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    "pm.request.headers.add({key: 'Accept', value: 'application/json' });",
                ],
            ],
        ];
        $this->postmanData['event'] = [$acceptJsonEvent];
    }

    private function setInfo()
    {
        $this->postmanData['info'] = [
            'name'    => $this->apiProject['name'] ?? config('app.name'),
            'schema'  => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            'version' => (string)now()->timestamp,
        ];
    }

    private function setItems()
    {
        $this->postmanData['item'] = collect($this->apiData)->mapToGroups(function (array $api) {
            $name = str_replace('-', '.', str_replace('_', ' ', $api['group']));
            return [$name => $api];
        })
            ->undot()
            ->map(function ($group, $groupName) {
                return [
                    'name' => trim($groupName),
                    'item' => $this->formatGroups($group),
                ];
            })->values();
    }

    public function formatGroups($groups)
    {
        if ($groups instanceof Collection) {
            return $this->formatItems($groups);
        }
        if (array_is_list($groups)) {
            return $this->formatItems($groups);
        }
        $items = [];
        foreach ($groups as $groupName => $group) {
            $items[] = [
                'name' => trim($groupName),
                'item' => $this->formatGroups($group),
            ];
        }
        return $items;
    }

    private function formatItems(Collection $group): array
    {
        return $group->sortBy('title')->map(function (array $api) {
            $item = [
                'name'    => $api['title'],
                'request' => [
                    'method' => \Str::upper($api['type']),
                    'url'    => '{{url}}' . $api['url'],
                    'header' => $this->formatHeaders($headers = $api['header']['fields']['Header'] ?? []),
                ],
            ];
            $isGetRequest = \Str::upper($api['type']) == 'GET';
            $parameters = $api['parameter']['fields']['Parameter'] ?? [];
            $permission = \Str::before($api['permission'][0]['name'] ?? '', ' ');
            preg_match_all('/:(?<params>\w+)/m', $api['url'], $pathParameters);
            $pathParameters = $pathParameters['params'];
            $parameters = array_filter($parameters, fn($parameter) => !in_array($parameter['field'], $pathParameters));
            if ($isGetRequest) {
                $item['request']['url'] .= $this->formatQueryString($parameters);
            } else {
                $item['request']['body'] = [
                    'mode'     => 'formdata',
                    'formdata' => $this->formatFormdataParameters($parameters),
                ];
            }

            $authHeader = collect($api['header']['examples'] ?? [])
                ->first(function (array $headerExample) {
                    return \Str::startsWith($headerExample['content'], 'Authorization');
                });
            if ($authHeader) {
                $default = \Str::afterLast($authHeader['content'], ' ');
                $tokenName = $permission ? "{$permission}_token" : $default;
                $item['request']['auth'] = [
                    'type'   => 'bearer',
                    'bearer' => [
                        [
                            'key'   => 'token',
                            'value' => '{{' . $tokenName . '}}',
                            'type'  => 'string',
                        ],
                    ],
                ];
            }

            return $item;
        })->values()->toArray();
    }

    private function formatFormdataParameters(array $params): array
    {
        return collect($params)->mapToGroups(function (array $parameter, $index) {
            $parent = \Str::before($parameter['field'], '.');
            return [$parent => $parameter];
        })->flatMap(function (Collection $parameter) {
            if ($parameter->count() == 1) {
                return $this->formatFormdataParameter($parameter->first());
            } else {//nested
                $parameter->shift();
                $children = $parameter->flatMap(fn($x) => $this->formatFormdataParameter($x));
                $parameters = [];

                for ($i = 0; $i < 2; $i++) {
                    $parameters = array_merge($parameters, $children->map(function (array $child) use ($i) {
                        $keyParts = explode('.', $child['key']);
                        $newKey = array_shift($keyParts) . "[$i]" . collect($keyParts)
                                ->map(fn($str) => "[$str]")
                                ->join('');
                        $child['key'] = $newKey;
                        return $child;
                    })->toArray());
                }
                return $parameters;
            }
        })->toArray();

        return collect($params)
            ->flatMap(function (array $parameter) {
                return $this->formatFormdataParameter($parameter);
            })->toArray();
    }

    private function formatFormdataParameter(array $parameter): array
    {
        $optional = $parameter['optional'] ?? false;
        $type = $this->getType($parameter['type']);
        $parsedParameter = [
            'key'         => $parameter['field'],
            'disabled'    => $optional,
            'description' => ($optional ? '(Optional) ' : '') . $this->toMarkdown($parameter['description'] ?? ''),
            'type'        => $type,
        ];
        if ($type == 'file') {
            $parsedParameter['src'] = null;
        } else {
            $parsedParameter['value'] = $parameter['defaultValue'] ?? '';
        }
        $parameters = [$parsedParameter];
        if (\Str::endsWith($parameter['type'], '[]')) {
            $secondParameter = $parsedParameter;
            $parsedParameter['key'] .= '[0]';
            $secondParameter['key'] .= '[1]';
            $secondParameter['disabled'] = true;
            $parameters = [$parsedParameter, $secondParameter];
        }
        return $parameters;
    }

    private function getType(string $type): string
    {
        $type = strtolower($type);
        $types = [
            'string'  => 'text',
            'number'  => 'text',
            'date'    => 'text',
            'boolean' => 'text',
            'image'   => 'file',
            'file'    => 'file',
        ];
        return $types[$type] ?? $types[$type . '[]'] ?? $types['string'];
    }

    private function formatQueryString($parameters): string
    {
        $query = http_build_query(collect($parameters)->map(function (array $parameter) {
            $parameter['defaultValue'] ??= '';
            if (strtolower($parameter['type'] ?? '') == 'boolean') {
                $parameter['defaultValue'] = ($parameter['defaultValue'] ?? null) == 'false' ? 0 : 1;
            }
            return $parameter;
        })->pluck('defaultValue', 'field')->toArray());
        return $query ? "?$query" : '';
    }

    private function toMarkdown(string $html): string
    {
        $converter = new HtmlConverter(['strip_tags' => true]);
        return $converter->convert($html);
    }

    private function formatHeaders(array $headers)
    {
        return collect($headers)->filter(function (array $header) {
            return 'Authorization' != ($header['field'] ?? '');
        })->map(function (array $header) {
            return [
                'key'         => $header['field'] ?? '',
                'value'       => $header['defaultValue'] ?? '',
                'description' => $this->toMarkdown($header['description'] ?? ''),
            ];
        })->toArray();
    }
}
