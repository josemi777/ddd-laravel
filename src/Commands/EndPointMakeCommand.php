<?php

namespace DddLaravel\Commands;

use Illuminate\Console\Command;
use Storage;

class EndPointMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-ddd:end-point {--usecase=} {--url=} {--name=} {--rtype=}';

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
        if ($usecase = $this->option('usecase')) {
            echo 'Usecase: '.$usecase;

        } else {

            system('clear');

            $list = $this->listPossibleUseCases();
            unset($list['dirs'][0]);
            unset($list['dirs'][1]);

            if ($list['selected'] == 'TODOS') {

                if ($this->confirm("<error> ¿Seguro que quieres crear todos los enpoints? </error>", true)) {

                    system('clear');
                    $bar = $this->output->createProgressBar(count($list['dirs']));
                    $bar->start();

                    $results = [];

                    foreach ($list['dirs'] as $dir) {

                        $dire = explode('@', $dir['name']);
                        $dire = $dire[1];
                        $bar->advance(); echo ' -> '.$dire;

                        $route_info = $this->generateEndpointInfoFromString($dire);
                        $route = $this->createEndPointText($route_info);

                        $results[] = ['status' => $this->addToRoutes($route), 'name' => $this->getOnlyName($dire)];
                        echo ("\r");
                    }
                    $bar->finish();
                    system('clear');
                    foreach ($results as $result) {
                        $this->messageEndpointCreator($result['status'], $result['name']);
                    }
                    $this->newLine();

                }

            } else if ($list['selected'] != 'Cancelar') {

                $dire_to_search = explode('@', $list['selected']);
                $dire_to_search = $dire_to_search[1];

                $key = $this->findSelected($dire_to_search, $list['dirs']);
                $this->line("Seleccionado: ".$list['dirs'][$key]['normal_name']);
                $this->newLine();

                system('clear');
                $route_info = $this->generateEndpointInfoFromString($list['dirs'][$key]['dir']);
                $route = $this->createEndPointText($route_info);
                $this->messageEndpointCreator($this->addToRoutes($route), $this->getOnlyName($list['dirs'][$key]['dir']));
                $this->newLine();
            }
        }

        return 1;
    }

    private function messageEndpointCreator($status, $name)
    {
        if ($status) {
            $this->info('- End-Point creado para: '.$name);
        } else {
            $this->line('<error>- NO se ha podido crear end-point para: '.$name.'</error>');
        }
    }

    private function addToRoutes($route, $file = 'web')
    {
        return file_put_contents('routes/'.$file.'.php', PHP_EOL.$route, FILE_APPEND | LOCK_EX);
    }

    private function generateEndpointInfoFromString($url, $name=null, $method='post', $controllerDir=null, $function='execute')
    {
        $url_string = trim(strtolower(implode('_', preg_split('/\B(?=[A-Z])/s',str_replace(['app/', 'src/', 'Application/', 'UseCase/', '.php', "\e[90m", "\e[0m"], '', $url)))));
        $url_array = explode('/', $url);
        $class_name = $this->getOnlyName($url);
        return [
            'url' => '/'.$url_string,
            'name' => ($name)?: str_replace('/', '.', $url_string),
            'method' => $method,
            'controllerDir' => trim(ucfirst(str_replace(['.php', '/', "\e[90m", "\e[0m"], ['', '\\','',''], ($controllerDir)?: $url))),
            'function' => $function
        ];
    }

    private function createEndPointText($data)
    {
        $route_text = 'Route::';
        $route_text .= (is_array($data['method']))? 'match('.json_encode($data['method']).',' : $data['method'].'(' ;
        $route_text .= "'".$data['url']."', ";
        $route_text .= "'".$data['controllerDir']."@".$data['function']."'";
        $route_text .= ')';
        $route_text .= ($data['name'])? "->name('".$data['name']."')" : '';
        $route_text .= ';';

        return $route_text;
    }

    private function findSelected($find, $array)
    {
        
        foreach ($array as $key => $element) {

            $to_find = trim(str_replace(['/','.', ' ', "\e[90m", "\e[0m"], '' , $find));
            $to_compare = trim(str_replace(['/','.', ' ', "\e[90m", "\e[0m"], '' , $element['dir']));
            if ((string)$to_find == (string)$to_compare) {
                return $key;
            }
        }
        return false;
    }

    private function listPossibleUseCases()
    {
        $dirs = $this->getDirContentsSimple('app/src/Application/UseCase');

        if (count($dirs) > 0) {
            $dirs[] = ['name' => 'TODOS'];
            $dirs[] = ['name' => 'Cancelar'];
            $dirs = array_reverse($dirs);
            $selection = $this->choice('Selecciona el/los UseCase que quieras pointear', array_column($dirs, 'name'), 0);
            return [
                    'dirs' => $dirs,
                    'selected' => $selection
                ];
        }

        $this->newLine();
        $this->error(PHP_EOL.' No se ha creado ningún UseCase aún '.PHP_EOL);
        $this->newLine();
    }

    private function getDirContentsMulti($dir) {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = [
                                'name' => $this->getOnlyName($path),
                                'dir' => $path
                            ];
            } else if ($value != "." && $value != "..") {
                
                $folder_name = $this->getOnlyName($path);
                $results[] = [
                                            'name' => $folder_name,
                                            'dir' => $path,
                                            'subs' => $this->getDirContentsMulti($path)
                                        ];
            }
        }
        return $results;
    }

    private function getDirContentsSimple($dir, &$results = array(), $level = '') {
        $files = scandir($dir,1);
        foreach ($files as $key => $value) {

            $path = $dir . DIRECTORY_SEPARATOR . $value;

            if (!is_dir($path)) {

                $results[] = [
                            'name' => $level.$this->getOnlyName($path)." \e[90m @".$path." \e[0m",
                            'normal_name' => $this->getOnlyName($path),
                            'dir' => $path
                            ];

            } else if ($value != "." && $value != "..") {

                $next_level = $level.'....';
                $this->getDirContentsSimple($path, $results, $next_level);
                $results[] = [
                            'name' => $level.'<comment>'.$this->getOnlyName($path).'</comment>'." \e[90m @".$path." \e[0m",
                            'normal_name' => $this->getOnlyName($path),
                            'dir' => $path
                            ];
            }
        }

        return $results;
    }

    private function getOnlyName($string)
    {
        $array = explode('/', $string);
        return $array[count($array)-1];
    }

}