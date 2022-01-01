<?php

namespace DddLaravel\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use DddLaravel\Traits\DirectoryHandler;

class InjectionsUpdateCommand extends Command
{
    use DirectoryHandler;

    const YAML_FILE = 'App/src/config/injection.yml';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:update-injections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update config/injection.yml file with all dependencies in the system';


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
        system('clear');
        if (!file_exists(self::YAML_FILE)) {
            $this->error(' El archivo de inyecciones no existe. ');
        }

        $files_usecase  = $this->getDirContentsSimple('app/src/Application/UseCase');
        $files_rest     = $this->getDirContentsSimple('app/src/Infrastructure');
        
        $rest_result    = $this->separeToAdds($files_rest);
        $usecase_result = $this->separeToAdds($files_usecase, true);
        
        $result_merged = [
                        'to_add_first' => array_merge($rest_result['to_add_first'], $usecase_result['to_add_first']),
                        'to_add_second' => array_merge($rest_result['to_add_second'], $usecase_result['to_add_second']),
                        'not_to_add' => array_merge($rest_result['not_to_add'], $usecase_result['not_to_add'])
                        ];

        if ($this->insertInYaml($result_merged)) {
            $this->info('<comment>' . self::YAML_FILE . '</comment> Actualizado con éxito.');
        } else {
            $this->error(' Error al actualizar <comment>' . self::YAML_FILE . '</comment> ');
        }
        $this->newLine();
    }


    /**
     * Insert the given injection array into the given configuration yaml file.
     *
     * @var array $injections
     * 
     * @return int 0 or 1
     */
    private function insertInYaml(array $injections)
    {
        $yaml_file = Yaml::parse(file_get_contents(self::YAML_FILE));
        $yaml_file = ($yaml_file)? : [];
        foreach ($injections['to_add_first'] as $injection) {
            $yaml_file = array_merge($injection, $yaml_file);
        }
        foreach ($injections['to_add_second'] as $injection) {
            $yaml_file = array_merge(   $injection, $yaml_file);
        }

        return file_put_contents(self::YAML_FILE, Yaml::dump($yaml_file,3));
    }


    /**
     * Separates, and formats, a given array of injections to insert.
     * The separation consists of Classes that do not have dependencies,
     * Injections that depend on other classes and injections that already
     * exist in the configuration yaml file.
     *
     * @var array $files
     * @var $is_use_cases
     * 
     * @return array
     */
    private function separeToAdds(array $files, $is_use_cases = false)
    {
        $to_add_first   = [];
        $to_add_second  = [];
        $not_to_add     = [];

        foreach ($files as $file) {
            if (!is_dir($file['dir'])) {
                
                $have_injections = $this->getInjectionsFromFile($file['dir']);
                $yaml_injection  = $this->generateYmlFormat($file['dir']);
                $exist           = $this->allreadyExistInjection($yaml_injection, self::YAML_FILE);

                if (!$have_injections && !$exist && !$is_use_cases) {

                    $to_add_first []= $yaml_injection;

                } else if ($have_injections && !$exist) {

                    $to_add_second []= $yaml_injection;

                } else if (!$is_use_cases){
                    $not_to_add []= $yaml_injection;
                }
            }
        }

        return ['to_add_first' => $to_add_first, 
                'to_add_second' => $to_add_second,
                'not_to_add' => $not_to_add
                ];
    }


    /**
     * Read the constructor of a given class and extract from it the dependencies it needs.
     *
     * @var string $file_path
     * 
     * @return string or false
     */
    private function getInjectionsFromFile(string $file_path)
    {
        $file_content   = file_get_contents($file_path);
        preg_match('/__construct.*\(.*((\n.*){0,})\)(.*\n?){/', $file_content, $injections);
        return (isset($injections[1]))? $injections[1] : false;
    }


    /**
     * Given the content of a file, it extracts from it the
     * dependencies that the class needs and generates an array
     * of well-formatted injections for the Yaml configuration file.
     *
     * @var string $file_path
     * 
     * @return array $formated
     */
    private function generateYmlFormat(string $file_path)
    {
        $class_name                     = $this->getOnlyName($file_path, true);
        $formated[$class_name]['class'] = $this->transFormPathToNameSapace($file_path);
        $injections = $this->getInjectionsFromFile($file_path);

        if ( $injections ) {
            $injections = explode(',', $injections);
            foreach ($injections as $injection) {
                $cleaned_injection = explode('$', str_replace(['Interface', ' ', "\n", "\t"], '', $injection));
                $formated[$class_name]['neededClass'][]= "@".$cleaned_injection[0];
            }
        }
        return $formated;
    }


    /**
     * Check if an already formatted injection exists in the given file or not.
     *
     * @var array &$new_injection
     * @var string $yaml_file
     * 
     * @return int 0 or 1
     */
    private function allreadyExistInjection(array &$new_injection, string $yaml_file)
    {
        $yaml_contents  = Yaml::parseFile($yaml_file);
        $index          = array_keys($new_injection);
        $index          = $index[0];
        
        if (
            isset($yaml_contents[$index])
            && ($yaml_contents[$index] == $new_injection[$index])
        ){
            return true;

        } else if (
            isset($yaml_contents[$index])
            && ($yaml_contents[$index] != $new_injection[$index])
        ){
            return true;

        } else {

            return false;
        }
    }

}