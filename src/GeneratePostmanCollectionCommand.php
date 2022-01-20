<?php /** @noinspection PhpMissingFieldTypeInspection */

namespace Alrifay\ApidocToPostman;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GeneratePostmanCollectionCommand extends Command
{
    protected $signature = 'postman:collection ' .
    '{--out=public/doc/postman.json : Generated file path}';

    protected $description = 'Convert apidoc generated file to postman collection';

    public function handle(): int
    {
        $process = new Process(['node', __DIR__ . '/generate.js'], base_path());
        $process->setEnv([
            'NODE_PATH' => base_path('node_modules')
        ]);

        if ($process->run() != self::SUCCESS) {
            $this->error('Failed to generate api documentation');
            echo $process->getErrorOutput();
            return self::FAILURE;
        }

        $result = json_decode($process->getOutput(), associative: true);
        $outputFilePath = base_path($this->option('out'));

        $generator = new GeneratePostmanCollection($result['project'], $result['data']);
        $generator->generate()->save($outputFilePath);
        $this->info('The command was successful!');
        return static::SUCCESS;
    }
}
