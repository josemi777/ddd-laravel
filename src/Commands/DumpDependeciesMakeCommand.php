<?php

namespace DddLaravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Yaml\Yaml;

class DumpDependeciesMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:dump-dependencies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update system injection from config/injection.yml';

    /**
     * @var string
     */
    protected const path = 'Providers/';

    /**
     *
     * @var string
     */
    protected $content;
    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!file_exists('App/src/config/injection.yml')) {
            //$this->makeDirectory('/APP/src/config');
            system('mkdir app/src/config');
            $this->files->put('APP/src/config/injection.yml','## injection of dependencies');
        }
        $yamlContents = Yaml::parse(file_get_contents('App/src/config/injection.yml'));
        $dependencies = null;  
        
        if ($yamlContents) {
            foreach($yamlContents as $injections) {
                $class = $injections['class'];            
                
                foreach ($injections['subclass'] as $dependency) {
                    $dependencies .= PHP_EOL.'new \\'.$dependency.'() ,'; 
                }
                $dependencies = substr($dependencies, 0, -1);
                
                $this->content .= '$this->app->bind(\\'.$class.'::class, function ($app) {
                    return new \\'.$class.'('.PHP_EOL.$dependencies.');
                });'.PHP_EOL . PHP_EOL;
                
                $dependencies = null;
            }
        }
        
        $path = $this->getPath(self::path.'AppServiceProvider');
        
        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass('AppServiceProvider'));

        $this->line("<info>Injections updated succesfully</info>");

    }
    
    /**
     * 
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return  base_path('vendor/jmsr/ddd-laravel/src/stubs/appServiceProvider.stub');
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
        $rpc = str_replace('{inyections}', $this->content, $stub);
        
        return $rpc;
    }
}
