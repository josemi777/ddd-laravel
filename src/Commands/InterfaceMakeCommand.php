<?php

namespace DddLaravel\Commands;


use Illuminate\Console\GeneratorCommand;
use Illuminate\Console\Command;

class InterfaceMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:interface {name} {--fromRepo=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Interface';


    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Interface';

    protected const path = 'src/Domain/';

    protected $fromRepo = '';

    /**
     * The name of class being generated.
     *
     * @var string
     */
    private $interfaceClass;

    /**
     * The name of class being generated.
     *
     * @var string
     */
    private $model;

    /**
     * Execute the console command.
     *
     * @return int or message
     */
    public function handle()
    {
        $this->init();

        $this->setInterfaceClass();

        $extraPath = (($this->fromRepo)?: '').'/';

        $path = $this->getPath(self::path.$extraPath.$this->interfaceClass);

        if ($this->alreadyExists($path)) {
            $this->error($this->type.' already exists!');

            return false;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($this->interfaceClass));

        //$this->info($this->type.' created successfully.');

        $this->line("<info>Created Interface :</info> $this->interfaceClass");
    }

    /**
     * Set repository class name
     *
     * @return  void
     */
    private function setInterfaceClass()
    {
        $name = ucwords($this->argument('name'));
        $split = explode('/', $this->argument('name'));
        $dir = ( count($split) > 1) ? implode("\\", array_slice($split, 0, -1)).'\\' : '';
        $name = $dir.ucfirst(end($split));

        $this->interfaceClass = $name . $this->fromRepo .'Interface';

        return $this;
    }


    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * 
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);
        $split = explode('/', $this->argument('name'));

        $extra = ( count($split) > 1) ? '\\'.implode("\\", array_slice($split, 0, -1)) : '';

        $split = end($split);

        $rpc = str_replace(['{TYPEClass}', '{OWNClass}', '{EXTRAnamespace}'], [$this->fromRepo, ucwords($split). $this->fromRepo .'Interface', $extra], $stub);

        return $rpc;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return  base_path('vendor/jmsr/ddd-laravel/src/stubs/interface.stub');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the interface class.'],
        ];
    }

    protected function init()
    {
        $this->fromRepo = ($this->option('fromRepo')) ? : '';
    }
}
