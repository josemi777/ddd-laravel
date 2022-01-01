<?php

namespace DddLaravel\Commands;

use Illuminate\Console\Command;
use DddLaravel\Traits\DirectoryHandler;

class InjectionsMakeCommand extends Command
{
    use DirectoryHandler;

    protected $elements_types_dirs = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:injection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make all injections you need in your DDD elements';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->elements_types_dirs = [
                    'Use Case' => 'app/src/Application/UseCase',
                    'Service' => 'app/src/Infrastructure/Service',
                    'Repository' => 'app/src/Infrastructure/Repository'
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int or message
     */
    public function handle()
    {
        system('clear');
        $element            = $this->choice('¿Sobre qué tipo de elemento vas a realizar la inyección de dependencias?', array_keys($this->elements_types_dirs), 'Use Case');
        $options            = $this->getDirContentsSimple($this->elements_types_dirs[$element]);
        system('clear');

        do {
                $select_to_inject   = $this->choice('Elemento sobre el que crear la inyección', array_reverse(array_column($options,'name')));
                $selected_index     = $this->findSelected($select_to_inject, $options);
                $selected           = $options[$selected_index];

                if ( is_dir($selected['dir']) ) {

                        system('clear');
                        $this->error('El elemento seleccionado no puede ser un directorio');
                }

        } while (is_dir($selected['dir']));
        
        do {
                system('clear');
                $posibles_to_inject     = $this->getDirContentsSimple('app/src/Domain');
                $posibles_to_inject   []= ['name' => 'Cancelar'];
                $to_inject_selected     = $this->choice('Selecciones los elementos a añadir separados por comas (Los directorios serán excluidos)', array_reverse(array_column($posibles_to_inject,'name')), null, null, true);
                $injects_selected       = [];

                foreach ($to_inject_selected as $inject_selected) {
                        if ($inject_selected == 'Cancelar') {
                            exit;
                        }
                        $index          = $this->findSelected($inject_selected, $posibles_to_inject);
                        $element_index  = $posibles_to_inject[$index];
                        if (!is_dir($element_index['dir'])) {
                            $injects_selected [] = $element_index;
                        }
                }

                if ( count($injects_selected) > 0 ) {

                    $this->info('Los siguientes elementos serán inyectados en <comment>"'.$selected['normal_name'].'"</comment>:');
                    foreach ($injects_selected as $showInject) {
                        $this->line('- <comment>'.$showInject['normal_name'].'</comment>');
                    }
                    $continue = $this->confirm('¿Es correcto?', true);

                } else { $continue = false; }

        } while (!$continue);

        if ( $this->insertInjectionsInFile($injects_selected, $selected['dir']) ) {

            $this->info('¡Inserción realizada con éxito en el fichero <comment>"'.$selected['normal_name'].'"</comment>!:');
            foreach ($injects_selected as $showInject) {
                $this->line('- <comment>'.$showInject['normal_name'].'</comment>');
            }
            $this->newLine();

        } else {

            $this->error(' Ocurrió un problema al realizar las inserciones en <comment>"'.$selected['dir'].'"</comment> , revisa el fichero.');
        }

        $this->call('make-ddd:update-injections');
        $this->call('make-ddd:dump-dependencies');

        return 0;
    }


    /**
     * Insert the selected and formatted injections in the indicated file.
     *
     * @var array $injections
     * @var string $file_path
     * 
     * @return int 0 or 1
     */
    private function insertInjectionsInFile(array $injections, string $file_path)
    {
        $file_content   = file_get_contents($file_path);
        $to_add         = $this->findNotAddeds($injections, $file_content);

        preg_match('/__construct.*\(.*((\n.*){0,})\)(.*\n?){/', $file_content, $matches['constructor']);

        if (!$matches['constructor']) {
            preg_match('/class.*\n?{(\n*\t*.*private.*)*/', $file_content, $add_constructor);
            $add_constructor = $add_constructor[0];
            $file_content   = str_replace(
                                $add_constructor,
                                $add_constructor.PHP_EOL."\tpublic function __construct(){\n\t}",
                                $file_content
                            );
            preg_match('/__construct.*\(.*((\n.*){0,})\)(.*\n?){/', $file_content, $matches['constructor']);
        }

        preg_match_all('/\nuse .*;/', $file_content, $matches['headers']);
        preg_match_all('/private \$.*;/', $file_content, $matches['variables']);

        $this->addHeadersAndVariables($matches, $to_add, $file_content);
        $this->addConstructorInjections($matches, $to_add, $file_content);

        return file_put_contents($file_path, $file_content);
    }


    /**
     * Add the definition of the "private" variables and the necessary "uses"
     * in the right place in the given file.
     *
     * @var array $existing
     * @var array $to_add
     * @var string &$file_content
     * 
     * @return void
     */
    private function addHeadersAndVariables(array $existing, array $to_add, string &$file_content)
    {
        $existing_headers       = $existing['headers'][0];
        $existing_variables     = $existing['variables'][0];
        $new_headers_strings    = '';
        $new_variables_strings  = '';

        foreach ($to_add['headers'] as $n_header) {
            $new_headers_strings .= 'use '.$this->transFormPathToNameSapace($n_header['dir']).';'.PHP_EOL;
        }
        foreach ($to_add['variables'] as $n_variables) {
            $new_variables_strings .= "\tprivate $".$this->transFormPathToParameterName($n_variables['dir']).";".PHP_EOL;
        }
  
        $last_header    = $this->assignByRegxOrLasItem($existing_headers, 'namespace.*\n?;', $file_content);
        $last_variables = $this->assignByRegxOrLasItem($existing_variables, 'class.*\n?{?', $file_content);

        $file_content   = str_replace(
                                    $last_header,
                                    $last_header.PHP_EOL.$new_headers_strings,
                                    $file_content
                                );
        $file_content   = str_replace(
                                    $last_variables,
                                    $last_variables.PHP_EOL.$new_variables_strings,
                                    $file_content
                                );
    }


    /**
     * Add the necessary parameters to the constructor and declare the appropriate
     * assignments, above the parameters, in the appropriate place in the given file.
     *
     * @var array $existing
     * @var array $to_add
     * @var string &$file_content
     * 
     * @return void
     */
    private function addConstructorInjections(array $existing, array $to_add, string &$file_content)
    {
        $parameters     = '';
        $assignments    = '';

        foreach ($to_add['constructor_params'] as $n_constr_params) {
            
            $parameters  .= PHP_EOL."\t\t".$this->getOnlyName($n_constr_params['dir'], true)."\t$".$this->transFormPathToParameterName($n_constr_params['dir']).",";
            $assignments .= PHP_EOL."\t\t\$this->".$this->transFormPathToParameterName($n_constr_params['dir'])."\t= $".$this->transFormPathToParameterName($n_constr_params['dir']).";";
        }
        
        $existing_assigments = $existing['constructor'][0];
        $existing_parameters = ($existing['constructor'][1])? : '__construct(';

        $file_content = str_replace(
                            $existing_assigments,
                            $existing_assigments.$assignments,
                            $file_content
                        );
        $file_content = str_replace(
                            $existing_parameters,
                            $existing_parameters.rtrim($parameters, ',').PHP_EOL."\t",
                            $file_content
                        );
    }


    /**
     * It compares all the declarations of use, declarations of variables
     * and parameters of the constructor with the same that are going to
     * be added and returns of each one those that do not yet exist in the
     * content of the given file.
     *
     * @var array $injects
     * @var string $file_content
     * 
     * @return array $result
     */
    private function findNotAddeds(array $injects, string $file_content)
    {
        $result = ['headers' => [], 'variables' => [], 'constructor_params' => []];

        foreach ($injects as $inject) {

            $use_dir        = $this->transFormPathToNameSapace($inject['dir']);
            $variable_name  = $this->transFormPathToParameterName($inject['dir']);
            $class_name     = str_replace('.php', '', $this->getOnlyName($inject['dir']));

            if ( !strpos($file_content, 'use '.$use_dir.';') )
            {
                $result['headers'] []= $inject;
            }
            if ( !strpos($file_content, 'private $'.$variable_name.';') )
            {
                $result['variables'] []= $inject;
            }
            if ( !strpos($file_content, $class_name.' $'.$variable_name) )
            {
                $result['constructor_params'] []= $inject;
            }
        }
        return $result;
    }


    /**
     * Given an array of objects, it returns the last element if the array
     * has at least one element, and if it does not evaluate the given regular
     * expression, on the given file and returns this match.
     *
     * @var array $items
     * @var string $regx
     * @var string $file_content
     * 
     * @return string
     */
    private function assignByRegxOrLasItem(array $items, string $regx, string $file_content)
    {
        if (!count($items) > 0) {
            preg_match('/'.$regx.'/', $file_content, $result); 
            return $result[0];
        } else {
            return $items[(count($items)-1)];
        }
    }
}
