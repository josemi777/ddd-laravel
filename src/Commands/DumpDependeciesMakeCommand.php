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
        $yamlContents = Yaml::parseFile('App/src/config/injection.yml');

        if ($yamlContents) {

            $yamlContents = $this->resolveDependencies($yamlContents);
            foreach($yamlContents as $injections) {
                $class = $injections['class'];
                if (isset($injections['neededClass'])) {
                    $this->content .= '$this->app->bind(\\'.$class.'::class, function ($app) {
                                        return new \\'.$class.'('.$this->exploreNeededClasses($injections['neededClass']).');
                                    });'.PHP_EOL;
                }
            }           
        }
        $path = $this->getPath(self::path.'AppServiceProvider');
        $this->makeDirectory($path);
        $this->files->put($path, $this->buildClass('AppServiceProvider'));
        $this->line("<info>Injections updated succesfully</info>");
    }

    private function resolveDependencies($yaml)
    {
        foreach($yaml as $key => $injections) {
            if (isset($injections['neededClass'])) {
                foreach ($injections['neededClass'] as $key2 => $dependency) {
                    if (strpos($dependency, '@') !== false) {
                        $wholeDependency = str_replace('@', '', $dependency);
                        $yaml[$key]['neededClass'][$key2] = $yaml[$wholeDependency];
                    }
                }
            }
        }
        return $yaml;
    }

    private function exploreNeededClasses($injections)
    {
        $dependencies = '';

        foreach($injections as $injection) {

            if (isset($injection['class'])) {
                $class = $injection['class'];

                if (isset($injection['neededClass'])) {

                    $dependencies .= 'new \\'.$class.'('.$this->exploreNeededClasses($injection['neededClass']).'),';
                }
                else {
                    $dependencies .= 'new \\'.$class.'(),';
                }
            } else {
                $dependencies .= 'new \\'.$injection.'(),';
            }
            /*$dependencies .= 'new \\'.$class.'(';

              if (isset($injection['neededClass'])) {
                    foreach ($injection['neededClass'] as $dependency) {

                    if (is_array($dependency)) {
                        $subdepends .= 'new \\'.$dependency['class'].'('.$this->exploreNeededClasses($dependency).') ,'; 
                    }
                    else {
                        $subdepends .= 'new \\'.$dependency.'(),';
                    }
                }
            }
            $subdepends = substr($dependencies, 0, -1);

            $dependencies .= $subdepends.')';*/
            
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
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);
        $rpc = str_replace('{inyections}', $this->content, $stub);
        
        return $rpc;
    }
}
