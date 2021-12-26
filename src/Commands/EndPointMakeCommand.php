<?php

namespace DddLaravel\Commands;

use Illuminate\Console\Command;
use Storage;

class EndPointMakeCommand extends Command
{ 
    protected $route_name   = null;
    protected $method       = 'post';
    protected $url          = null;
    protected $function     = 'execute';
    protected $file         = 'web';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:end-point {--usecase=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an endpoint from Use cases created';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($file = $this->choice('¿Acceso WEB o por API? [default: "'.$this->file.'"]', ['web','api'], 'web')) {
            $this->file = $file;
        }

        if ($usecase = $this->option('usecase')) {

            $route_info = $this->generateEndpointInfoFromString($usecase);
            $this->askForConfigure($route_info);
            $this->messageEndpointCreator(
                $this->addToRoutes(
                    $this->createEndPointText($route_info)
                ) , $usecase
            );
            $this->newLine();

        } else {

            system('clear');

            $list = $this->listPossibleUseCases('app/src/Application/UseCase');

            if ($list['selected'] == 'TODOS') {

                $this->createMultipleEndPoints($list['dirs']);

            } else if ($list['selected'] != 'Cancelar' && $list['selected']) {

                $dire_to_search = explode('@', $list['selected']);
                $dire_to_search = $dire_to_search[1];
                $dire_to_search_cleaned = trim(str_replace(["\e[90m", "\e[0m"], '', $dire_to_search));


                if (is_dir($dire_to_search_cleaned)) {

                    $dirs = $this->getDirContentsSimple($dire_to_search_cleaned);
                    $this->createMultipleEndPoints($dirs);
                }
                else {

                    $key            = $this->findSelected($dire_to_search, $list['dirs']);

                    $this->line("Seleccionado: ".$list['dirs'][$key]['normal_name']);
                    $this->newLine();

                    system('clear');

                    $route_info = $this->generateEndpointInfoFromString($list['dirs'][$key]['dir']);
                    $route      = $this->createEndPointText($route_info);

                    $this->messageEndpointCreator($this->addToRoutes($route), $this->getOnlyName($list['dirs'][$key]['dir']));
                    $this->newLine();
                }

            } else if ($list['selected'] == 'Cancelar') {
                system('clear');
            }
        }
        return 1;
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    private function createMultipleEndPoints($dirs)
    {
        if ($this->confirm("<error> ¿Seguro que quieres crear todos los enpoints? </error>", true)) {

            $different_configure = $this->confirm('¿Quieres configurar un ajuste diferente para cada endpoint?');

            system('clear');

            if (!$different_configure) {
                $bar = $this->output->createProgressBar(count($dirs));
                $bar->start();
            }

            $results = [];

            foreach ($dirs as $dir) {

                if (!is_dir($dir['dir'])) {

                    $dire = explode('@', $dir['name']);
                    $dire = $dire[1];

                    if (!$different_configure) { $bar->advance(); echo ' -> '.$dire; }

                    $route_info = $this->generateEndpointInfoFromString($dire);

                    if ($different_configure) { $this->askForConfigure($route_info); }

                    $route = $this->createEndPointText($route_info);

                    $results[] = [
                                'status' => $this->addToRoutes($route),
                                'name'   => $this->getOnlyName($dire)
                                ];
                    echo ("\r");
                }
            }
            if (!$different_configure) { $bar->finish(); }

            system('clear');
            foreach ($results as $result) {
                $this->messageEndpointCreator($result['status'], $result['name']);
            }
            $this->newLine();

        }
    }


    /**
     * Return src/Application source tree without existed routes in Laravel's route file selected.
     *
     * @var array &$list
     */
    private function filterRouteList(array &$list)
    {
        foreach ($list as $key => $item) {
            $route = $this->createEndPointText($this->generateEndpointInfoFromString($item['dir']));
            if ($this->allreadyExistRoute($route) !== false) {
                unset($list[$key]);
            }
        }
        $allDirs = array_column($list, 'dir');
        foreach ($list as $key => $item) {
            if (is_dir($item['dir'])) {
                $no_existe = true;
                foreach ($allDirs as $one) {
                    if (!is_dir($one)) {
                        if ((strpos($one, $item['dir']) !== false)) {
                            $no_existe = false;
                        }
                    }
                }
                if ($no_existe) { unset($list[$key]); }
            }
        }
    }


    /**
     * Asks and modify the route object (array $data) passed by the responses.
     *
     * @var array &$data
     */
    private function askForConfigure(array &$data)
    {
        system('clear');

        if ($url = $this->ask('Ruta para el endpoint [default: "'.$data['url'].'"]')){
            $data['url'] = $url;
        }

        if ($name = $this->ask('(opcional) Nombre acceso rápido para Laravel [default: "'.$data['name'].'"]')) {
            $data['name'] = $url;
        }

        if ($method = $this->ask('Tipo de llamada (puedes configurar más de uno separandolos por espacios) [default: "'.$data['method'].'"]')) {
            $data['method'] = (strpos($method, ',') !== false)? explode(',', $method) : $method;
        }

        if ($function = $this->ask('Método "run" del endpoint [default: "'.$data['function'].'"]')) {
            $data['function'] = $function;
        }
    }


    /**
     * Prints message ($name) in console acording the status ($status) passed.
     *
     * @var boolean $satus
     * @var string $name
     */
    private function messageEndpointCreator(int $status, string $name)
    {
        if ($status) {
            $this->info('- End-Point creado para: '.$name);
        } else {
            $this->line('<error>- NO se ha podido crear end-point para: '.$name.'</error>');
        }
    }


    /**
     * Check if plain text Laravel's route exists in selected Laravel's routes file.
     *
     * @var string $route
     * @return boolean
     */
    private function allreadyExistRoute(string $route)
    {
        return strpos(file_get_contents('routes/'.$this->file.'.php'),$route);
    }


    /**
     * Insert plain text Laravel route on the selected file of Laravel's routes (web or api).
     *
     * @var string $route
     * @return boolean
     */
    private function addToRoutes(string $route)
    {
        if($this->allreadyExistRoute($route) !== false) {
            return false;
        }
        return file_put_contents('routes/'.$this->file.'.php', PHP_EOL.$route, FILE_APPEND | LOCK_EX);
    }


    /**
     * Generates array from a route string with necessary parameters to format a Laravel's route.
     *
     * @var string $url
     * @var (optional) string $controllerDir - default "null"
     * @return array
     */
    private function generateEndpointInfoFromString(string $url, string $controllerDir=null)
    {
        $name       = $this->route_name;
        $method     = $this->method;
        $function   = $this->function;

        $url_string = trim(strtolower(implode('_', preg_split('/\B(?=[A-Z])/s',str_replace(['app/', 'src/', 'Application/', 'UseCase/', '.php', 'usecase', 'Usecase', "\e[90m", "\e[0m"], '', $url)))));
        $url_array  = explode('/', $url);
        $class_name = $this->getOnlyName($url);

        return [
            'url'           => '/'.$url_string,
            'name'          => ($name)?: str_replace('/', '.', $url_string),
            'method'        => $method,
            'controllerDir' => trim(ucfirst(str_replace(['.php', '/', "\e[90m", "\e[0m"], ['', '\\','',''], ($controllerDir)?: $url))),
            'function'      => $function
        ];
    }


    /**
     * Convert basic endpoint information to plain text formatted to Laravel route.
     *
     * @var array $data
     * @return string
     */
    private function createEndPointText(array $data)
    {
        $route_text  = 'Route::';
        $route_text .= (is_array($data['method']))? 'match('.json_encode($data['method']).',' : $data['method'].'(' ;
        $route_text .= "'".$data['url']."', ";
        $route_text .= "'".$data['controllerDir']."@".$data['function']."'";
        $route_text .= ')';
        $route_text .= ($data['name'])? "->name('".$data['name']."')" : '';
        $route_text .= ';';

        return $route_text;
    }


    /**
     * Find route index in tree of routes, ignoring if string to compare is colored.
     *
     * @var string $find
     * @var array $route_tree
     * @return int
     */
    private function findSelected(string $find, array $route_tree)
    {  
        foreach ($route_tree as $index => $element) {

            $to_find    = trim(str_replace(['/','.', ' ', "\e[90m", "\e[0m"], '' , $find));
            $to_compare = trim(str_replace(['/','.', ' ', "\e[90m", "\e[0m"], '' , $element['dir']));

            if ((string)$to_find == (string)$to_compare) {
                return $index;
            }
        }
        return false;
    }


    /**
     * List in console a tree of project Use cases to choose one.
     *
     * @var string $path
     * @return array or error message
     */
    private function listPossibleUseCases(string $path)
    {
        $dirs = $this->getDirContentsSimple($path);

        $this->filterRouteList($dirs);

        if (count($dirs) > 0) {

            $dirs[]     = ['name' => 'TODOS'];
            $dirs[]     = ['name' => 'Cancelar'];
            $dirs       = array_reverse($dirs);
            $selection  = $this->choice('Selecciona el/los UseCase que quieras pointear', array_column($dirs, 'name'), 0);
            unset($dirs[0]);
            unset($dirs[1]);
            return [
                    'dirs'      => $dirs,
                    'selected'  => $selection
                ];
        }

        $this->newLine();
        $this->error(PHP_EOL.' Ya existen todos los endpoint posibles o no se ha creado ningún UseCase aún '.PHP_EOL);
        $this->newLine();
    }


    /**
     * Method description.
     *
     * @var string $dir
     * @return array
     */
    private function getDirContentsMulti(string $dir)
    {
        $files = scandir($dir);

        foreach ($files as $key => $value) {

            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);

            if (!is_dir($path)) {

                $results[] = [
                            'name'  => $this->getOnlyName($path),
                            'dir'   => $path
                            ];
            }
            else if ($value != "." && $value != "..") {
                
                $folder_name = $this->getOnlyName($path);
                $results[]   = [
                                'name'  => $folder_name,
                                'dir'   => $path,
                                'subs'  => $this->getDirContentsMulti($path)
                                ];
            }
        }
        return $results;
    }


    /**
     * Method description.
     *
     * @var string
     * @return array
     */
    private function getDirContentsSimple($dir, &$results = array(), $level = '')
    {
        $files = scandir($dir,1);
        foreach ($files as $key => $value) {

            $path = $dir . DIRECTORY_SEPARATOR . $value;

            if (!is_dir($path)) {

                $results[] = [
                            'name'          => $level.$this->getOnlyName($path)." \e[90m @".$path." \e[0m",
                            'normal_name'   => $this->getOnlyName($path),
                            'dir'           => $path
                            ];

            } else if ($value != "." && $value != "..") {

                $next_level = $level.'....';
                $this->getDirContentsSimple($path, $results, $next_level);
                $results[] = [
                            'name'          => $level.'<comment>'.$this->getOnlyName($path).'</comment>'." \e[90m @".$path." \e[0m",
                            'normal_name'   => $this->getOnlyName($path),
                            'dir'           => $path
                            ];
            }
        }
        return $results;
    }


    /**
     * Get last element from a string divided by "/".
     *
     * @var string $route
     * @return string
     */
    private function getOnlyName(string $route)
    {
        $array = explode('/', $route);
        return $array[count($array)-1];
    }

}