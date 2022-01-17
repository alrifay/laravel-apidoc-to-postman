<?php /** @noinspection PhpMissingFieldTypeInspection */

namespace Alrifay\ApidocToPostman;

use Illuminate\Console\Command;

class GeneratePostmanCollectionCommand extends Command
{
    protected $signature = 'postman:collection ' .
    '{--project=public/doc/api_project.json : Generated api_project.json file from apidoc} ' .
    '{--data=public/doc/api_data.json : Generated api_data.json file from apidoc} ' .
    '{--out=public/doc/postman.json : Generated file path}';

    protected $description = 'Convert apidoc generated file to postman collection';

    public function handle(): int
    {
        $projectFilePath = $this->option('project');
        $dataFilePath = $this->option('data');
        $outputFilePath = base_path($this->option('out'));

        if (!\File::exists($projectFilePath)) {
            $this->error("$projectFilePath not found.");
            return static::INVALID;
        }

        if (!\File::exists($dataFilePath)) {
            $this->error("$dataFilePath not found.");
            return static::INVALID;
        }
        $generator = new GeneratePostmanCollection($projectFilePath, $dataFilePath);
        $generator->generate()->save($outputFilePath);
        $this->info('The command was successful!');
        return static::SUCCESS;
    }
}
