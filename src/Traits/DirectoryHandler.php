<?php

namespace DddLaravel\Traits;

trait DirectoryHandler {

	/**
     * Find route index in tree of routes, ignoring if string to compare is colored.
     *
     * @var string $find
     * @var array $route_tree
     * @return int
     */
    private function findSelected(string $find, array $route_tree)
    {  
    	$divide = explode('@', $find);
    	$find = (count($divide)  > 0 ) ? $divide[1] : $find ;
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
    private function getOnlyName(string $route, $without_extension = false)
    {
        $array = explode('/', $route);
        return ($without_extension)? str_replace('.php', '', $array[count($array)-1]) : $array[count($array)-1];
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    private function transFormPathToNameSapace(string $path)
    {
        return ucfirst(str_replace(['/', '.php'], ['\\', ''], $path));
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    private function transFormPathToParameterName(string $path)
    {
        return lcfirst(str_replace(['Interface'], '', $this->getOnlyName($path, true)));
    }

}