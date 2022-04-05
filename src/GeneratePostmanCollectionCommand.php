<?php /** @noinspection PhpMissingFieldTypeInspection */

namespace Alrifay\ApidocToPostman;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GeneratePostmanCollectionCommand extends Command
{
    protected $signature = 'postman:collection ' .
    '{--out=public/doc/postman.json : Generated file path}';

    protected $description = 'Convert apidoc generated file to postman collection';
    private GeneratePostmanCollection $generator;

    public function handle(): int
    {
        $process = new Process(['node', __DIR__ . '/generate.js'], base_path());
        $process->setEnv([
            'NODE_PATH' => base_path('node_modules'),
        ]);

        if ($process->run() != self::SUCCESS) {
            $this->error('Failed to generate api documentation');
            echo $process->getErrorOutput();
            return self::FAILURE;
        }

        $result = json_decode($process->getOutput(), associative: true);
        $outputFilePath = base_path($this->option('out'));

        $this->generator = new GeneratePostmanCollection($result['project'], $result['data']);
        $this->generator->generate()->save($outputFilePath);
        $this->info('Collection generated successfully!');

        return $this->publishCollection();
        return static::SUCCESS;
    }

    public function publishCollection()
    {
        $config = PostmanConfig::read($this, $this->generator->getCollectionName());
        if (!$config->sync) {
            return self::SUCCESS;
        }
        $this->info('sync in progress...');

        if(!Postman::syncCollection($config, $this->generator->getPostmanCollection())){
            $this->error('Sync failed');
            return static::FAILURE;
        }

        $this->info('Collection synced successfully!');
        return static::SUCCESS;
    }
}
