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
     * @return int or message
     */
    public function handle()
    {
        if (!file_exists('App/src/config/injection.yml')) {
            //$this->makeDirectory('/APP/src/config');
            system('mkdir app/src/config');
            $this->files->put('APP/src/config/injection.yml','## injection of dependencies');
        }
        $yamlContents = Yaml::parseFile('App/src/config/injection.yml');

        if ($yamlContents) {

            $yamlContents = $this->resolveDependencies($yamlContents, $yamlContents);
            $first = true;
            foreach($yamlContents as $injections) {
                $class      = $injections['class'];
                $add        = ($first)? '' : "\t\t" ;
                $first      = false;
                $init_level = "\t\t\t\t\t\t";
                if (isset($injections['neededClass'])) {
                    $this->content .= $add.'$this->app->bind(\\'.$class.'::class, function ($app) {'."
            return new \\".$class."(".$init_level.$this->exploreNeededClasses($injections['neededClass'], $yamlContents, $init_level).');
        });'.PHP_EOL;
                }
            }           
        }
        $path = $this->getPath(self::path.'AppServiceProvider');
        $this->makeDirectory($path);
        $this->files->put($path, $this->buildClass('AppServiceProvider'));
        $this->newLine();
        $this->line("<info>Injections updated succesfully</info>");
        $this->newLine();
    }


    /**
     * Replace all declarations shortened with @ by the necessary constructor.
     *
     * @param array $yaml
     * @param array $whole_yaml
     * 
     * @return array $yaml
     */
    private function resolveDependencies(array $yaml, array $whole_yaml)
    {
        foreach($yaml as $key => $injections) {
            if (isset($injections['neededClass'])) {
                foreach ($injections['neededClass'] as $key2 => $dependency) {
                    //$dependency = (!is_array($dependency))?: $dependency['class'] ;
                    if (strpos($dependency, '@') !== false) {
                        $wholeDependency = str_replace('@', '', $dependency);
                        $yaml[$key]['neededClass'][$key2] = $whole_yaml[$wholeDependency];
                    }
                }
            }
        }
        return $yaml;
    }


    /**
     * Traverses the entire dependency tree given by the array in the injects.yml
     * file and generates a string to mount the necessary bind constructor for Laravel.
     *
     * @param array $injections
     * @param array $whole_yaml
     * @param string $level
     * 
     * @return string $dependencies
     */
    private function exploreNeededClasses(array $injections, array $whole_yaml, string $level = "")
    {
        $dependencies = '';
        foreach($injections as $injection) {

            if (isset($injection['class'])) {
                $class = $injection['class'];
                if (strpos($class, '@') !== false) {
                    $wholeDependency = str_replace('@', '', $class);
                    $injection = $whole_yaml[$wholeDependency];
                }
                if (isset($injection['neededClass'])) {

                    $dependencies .= "\n".$level."new \\".$class."(".$this->exploreNeededClasses($injection['neededClass'], $whole_yaml, $level."\t\t")."),";
                }
                else {
                    $dependencies .= "\n".$level."new \\".$class.'(),';
                }
            } else {
                $injection_name = $injection;
                if (strpos($injection, '@') !== false) {
                    $wholeDependency    = str_replace('@', '', $injection);
                    $injection          = $whole_yaml[$wholeDependency];
                    $injection_name     = $whole_yaml[$wholeDependency]['class'];
                }
                if (isset($injection['neededClass'])) {
                    $dependencies .= "\n".$level."new \\".$injection_name."(".$this->exploreNeededClasses($injection['neededClass'], $whole_yaml, $level."\t\t")."),";
                } else {
                    $dependencies .= "\n".$level."new \\".$injection_name.'(),';
                }
            }
        }
        $dependencies = substr($dependencies, 0, -1);
        return $dependencies;
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
     * 
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);
        $rpc = str_replace('{inyections}', $this->content, $stub);
        
        return $rpc;
    }
}
