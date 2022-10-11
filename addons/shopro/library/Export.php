<?php

namespace addons\shopro\library;

use EasyWeChat\Factory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use addons\shopro\library\Redis;
/**
 *
 */
class Export
{


    public function __construct()
    {
    }

    public function exportExcel($expTitle, $expCellName, $expTableData, &$spreadsheet = null, &$sheet = null, $pages = [])
    {
        $page = $pages['page'] ?? 1;
        $page_size = $pages['page_size'] ?? 1000;
        $is_last_page = $pages['is_last_page'] ?? 1;
        $current_total = $pages['current_total'] ?? 0;

        if ($current_total) {
            // 每次传来的 expTableData 数据条数不等，比如订单导出
            $base_cell = $current_total - count($expTableData) + 2;
        }else {
            $base_cell = ($page - 1) * $page_size + 2;
        }

        $fileName = $expTitle;
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

        if ($page == 1) {
            // 不限时
            set_time_limit(0);
            // 根据需要调大内存限制
            ini_set('memory_limit', '512M');
            
            $cache_type = 'redis';
            // 设置缓存
            if ($cache_type == 'redis' && class_exists(\Cache\Adapter\Redis\RedisCachePool::class)) {
                // 将表格数据暂存 redis,可以降低 php 进程内存占用，需要安装扩展包  composer require cache/simple-cache-bridge cache/redis-adapter
                $options = [
                    // 'select' => 0        // 注释解开，并且换成一个空的 select 库，redis 库默认是 0-15 共 16 个库
                ];

                $redis = (new Redis($options))->getRedis();
                $pool = new \Cache\Adapter\Redis\RedisCachePool($redis);
                $simpleCache = new \Cache\Bridge\SimpleCache\SimpleCacheBridge($pool);

                \PhpOffice\PhpSpreadsheet\Settings::setCache($simpleCache);
            } else if ($cache_type == 'file' && class_exists(FilesystemCachePool::class)) {
                // 将数据暂存磁盘，可以降低内存，但是导出速度会大幅下降 需要安装扩展包  composer require cache/filesystem-adapter
                $path = ROOT_PATH . 'runtime' . DS . 'export/';
                @mkdir($path);
                $filesystemAdapter = new Local($path);
                $filesystem        = new Filesystem($filesystemAdapter);
                $pool = new FilesystemCachePool($filesystem);
    
                \PhpOffice\PhpSpreadsheet\Settings::setCache($pool);
            }

            // 实例化excel
            $spreadsheet = new Spreadsheet();
            // 初始化工作簿
            $sheet = $spreadsheet->getActiveSheet(0);
            // 给表头设置边框
            $sheet->getStyle('A1:' . $cellName[$cellNum - 1] . '1')->getFont()->setBold(true);

            // 表头
            $i = 0;
            foreach ($expCellName as $key => $cell) {
                $sheet->setCellValue($cellName[$i] . '1', $cell);
                $i++;
            }
        }

        // for ($i = 0; $i < $cellNum; $i++) {
        //     $sheet->getColumnDimension($cellName[$i])->setWidth(30);
        // }

        // 写入数据
        for ($i = 0; $i < $dataNum; $i++) {
            if ($is_last_page && $i == ($dataNum - 1)) {
                $sheet->mergeCells('A' . ($i + $base_cell) . ':' . $cellName[$cellNum - 1] . ($i + $base_cell));
                $sheet->setCellValue('A' . ($i + $base_cell), $expTableData[$i][key($expCellName)]);
            } else {
                $j = 0;
                foreach ($expCellName as $key => $cell) {
                    $sheet->setCellValue($cellName[$j] . ($i + $base_cell), $expTableData[$i][$key]);
                    $j++;
                }
            }
        }

        if ($is_last_page) {
            // ini_set('memory_limit', '256M');

            ob_end_clean();
            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $fileName . '.xlsx"');
            header("Content-Disposition:attachment;filename=$fileName.xlsx"); //attachment新窗口打印inline本窗口打印
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }
    }
}
