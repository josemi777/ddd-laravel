<?php

namespace DddLaravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ThingMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:thing {type} {name} {--entity} {--migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new "thing" of DDD elements (usecase, repository, service, interface, entity)';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = '';

    protected $ownPath = '';

    protected const path = '';

    /**
     * The name of class being generated.
     *
     * @var string
     */
    private $thingClass;

    /**
     * The name of class being generated.
     *
     * @var string
     */
    private $model;


    /**
     * The name of entity being generated.
     *
     * @var string
     */
    private $entity;


    /**
     * Execute the console command.
     *
     * @return int or message
     */
    public function handle()
    {
        $this->init();

        if ($this->type == 'Service' || $this->type == 'Repository') {
            $this->createInterface();
        }

        $this->setThingClass();

        if ($this->option('entity')) {
            $this->entity = 'use App\src\Domain\Entity\\';
            $this->createModel();
        } else {
            $this->entity = '';
        }

        $path = $this->getPath($this->ownPath.$this->thingClass);

        if ($this->alreadyExists($path)) {
            $this->error($this->type.' already exists!');

            return false;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($this->thingClass));

        //$this->info($this->type.' created successfully.');

        $this->line("<info>Created $this->type :</info> $this->thingClass");
    }



    /**
     * Set Thing class name
     *
     * @return  void
     */
    private function setThingClass()
    {
        $name = ucwords($this->argument('name'));
        $split = explode('/', $this->argument('name'));
        $dir = ( count($split) > 1) ? implode("\\", array_slice($split, 0, -1)).'\\' : '';
        $name = $dir.ucfirst(end($split));
        //$modelClass = $this->parseName($name);

        $this->thingClass = $name . $this->type;

        return $this;
    }


    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);
        $split = explode('/', $this->argument('name'));

        $extra = ( count($split) > 1) ? '\\'.implode("\\", array_slice($split, 0, -1)) : '';

        $split = end($split);
        $rpc = str_replace(['{TYPEClass}', '{OWNClass}', '{EXTRAnamespace}', '{model}'],
            [   $this->type,
                ucwords($split).$this->type,
                $extra, $this->entity
            ],
                $stub);

        return $rpc;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        switch ($this->type) {
            case 'Usecase':
                return  base_path('vendor/jmsr/ddd-laravel/src/stubs/usecase.stub');
                break;
            
            default:
                return  base_path('vendor/jmsr/ddd-laravel/src/stubs/thingClass.stub');
                break;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['type', InputArgument::REQUIRED, 'The type of the class.'],
            ['name', InputArgument::REQUIRED, 'The name of the class.']
        ];
    }


    /**
     * Create a migration file for the model.
     *
     * @return void
     */
    protected function createModel()
    {
        $entity = Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));
        $this->entity = $this->entity.$entity.';';

        $this->call('make:model', [
            'name' => "src/Domain/Entity/{$entity}",
            '--migration' => $this->option('migration'),
        ]);
    }


    /**
     * Create a interface file for the model.
     *
     * @return void
     */
    protected function createInterface()
    {
        $interface = Str::studly($this->argument('name'));

        $this->call('make:interface', [
            'name' => "{$interface}",
            '--fromRepo' => $this->type,
        ]);
    }

    protected function init()
    {
        $this->type = ucfirst($this->argument('type'));
        $paths = [
            'repository' => 'src/Infrastructure/Repository/',
            'service' => 'src/Infrastructure/Service/',
            'interface' => 'src/Domain/Repository/',
            'usecase' => 'src/Application/UseCase/'
        ];
        $this->ownPath = $paths[$this->argument('type')];
    }
}
