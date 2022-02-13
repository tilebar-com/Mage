<?php
namespace Mage\Mage\Profiler;

trait Profile
{

    public static $profiler = [];

    public static $file = '\var\log\profiler.txt';

    public static function start($name, $output = false)
    {
        $start = microtime(TRUE);
        static::$profiler[$name]['name'] = $name;
        static::$profiler[$name]['start'] = $start;
        if ($output)
            echo "\n Start Profiler $name : $start \n";
    }

    public static function end($name, $output = false)
    {
        $end = microtime(TRUE);
        static::$profiler[$name]['end'] = $end;
        $result = $end - static::$profiler[$name]['start'];
        static::$profiler[$name]['total'] = $result;
        if ($output)
            echo "\nProfiler $name took: $result seconds\n";
    }

    public static function profilerResult($output = 'table')
    {
        $out = '';
        if ($output == 'table') {
            $out = '<table>';
            foreach (static::$profiler as $row) {
                $out .= '<tr>';
                foreach ($row as $item) {
                    $out .= "<td>{$item}</td>";
                }
                $out .= '</tr>';
            }
            $out .= '</table>';
            return $out;
        } else if ($output == 'asci') {
            include_once(__DIR__ . '../tools/asciTable.php');
            $ascii_table = new ascii_table();
            $out = $ascii_table->make_table(static::$profiler, 'Profiler Data');
            return $out;
        } else if ($output == 'js-comment') {
            include_once(__DIR__ . '../asciTable.php');
            $out = '/*';
            $ascii_table = new ascii_table();
            $out .= $ascii_table->make_table(static::$profiler, 'Profiler Data');
            $out .= '*/';
            return $out;
        } else if ($output == 'html-comment') {
            include_once(__DIR__ . '../tools/asciTable.php');
            $ascii_table = new ascii_table();
            $out = "<!--- \n" . $ascii_table->make_table(static::$profiler, 'Profiler Data') . "\n --->";
            return $out;
        }
        return  self::$profiler;
    }

    public static function profilerWrite($file = false, $out = 'asci'){
        if($file === false){
            $file = static::$file;
        }
        file_put_contents($file, static::profilerResult($out));
    }
}
