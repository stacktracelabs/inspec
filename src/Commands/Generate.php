<?php


namespace StackTrace\Inspec\Commands;


use Illuminate\Console\Command;

class Generate extends Command
{
    protected $signature = 'inspec:generate {output}';

    protected $description = 'Generate the OpenAPI spec.';

    public function handle(): int
    {


        return 0;
    }
}
