<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2009 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>, qaohao<qaohao@gmail.com>
// +----------------------------------------------------------------------
// | Modify by qaohao at 2016.11.9 16:47. This is china advertise standard.
// +----------------------------------------------------------------------

/**
 * 中广协IP 地理位置查询类
 * 参考Thinkphp IpLocation类
 *
 * @category   ORG
 * @package  ORG
 * @subpackage  Net
 * @author    liu21st <liu21st@gmail.com>, qaohao@gmail.com
 */
class IpLocation
{
    /**
     * QQWry.Dat文件指针
     *
     * @var resource
     */
    private $fp;

    /**
     * 第一条IP记录的偏移地址
     *
     * @var int
     */
    private $firstip;

    /**
     * 最后一条IP记录的偏移地址
     *
     * @var int
     */
    private $lastip;

    /**
     * IP记录的总条数（不包含版本信息记录）
     *
     * @var int
     */
    private $totalip;

    /**
     * 构造函数，打开 QQWry.Dat 文件并初始化类中的信息
     *
     * @param string $filename
     * @return IpLocation
     */
    public function __construct($filename = "ip.dat")
    {
        $this->fp = 0;
        if (($this->fp = fopen(dirname(__FILE__) . '/' . $filename, 'rb')) !== false) {
            $this->firstip = $this->getlong();
            $this->lastip = $this->getlong();
            $this->totalip = ($this->lastip - $this->firstip) / 12;
        }
    }

    /**
     * 根据所给 IP 地址或域名返回所在地区代码
     *
     * @param string $ip
     * @return Location code
     */
    public function getlocation($ip = '')
    {
        // 如果数据文件没有被正确打开，则直接返回空
        if (!$this->fp) {
            return null;
        }

        $ip = empty($ip) ? get_client_ip() : $ip;
        $ip = gethostbyname($ip);
        // 将输入的IP地址转化为可比较的IP地址
        // 不合法的IP地址会被转化为255.255.255.255
        $ip = IpLocation::packip($ip);

        // 对分搜索
        $l = 0;                         // 搜索的下边界
        $u = $this->totalip;            // 搜索的上边界
        $findip = $this->lastip;        // 如果没有找到就返回最后一条IP记录（ip.dat的版本信息）
        while ($l <= $u) {              // 当上边界小于下边界时，查找失败
            $i = floor(($l + $u) / 2);  // 计算近似中间记录
            fseek($this->fp, $this->firstip + $i * 12);
            $beginip = fread($this->fp, 4);     // 获取中间记录的开始IP地址

            if ($ip < $beginip) {       // 用户的IP小于中间记录的开始IP地址时
                $u = $i - 1;            // 将搜索的上边界修改为中间记录减一
            } else {
                $endip = fread($this->fp, 4);   // 获取中间记录的结束IP地址
                if ($ip > $endip) {     // 用户的IP大于中间记录的结束IP地址时
                    $l = $i + 1;        // 将搜索的下边界修改为中间记录加一
                } else {                  // 用户的IP在中间记录的IP范围内时
                    $findip = $this->firstip + $i * 12;
                    break;              // 则表示找到结果，退出循环
                }
            }
        }

        //获取查找到的IP地理位置信息
        fseek($this->fp, $findip + 8);
        return $this->getlong();
    }

    /**
     * 将IP标准csv文件转换成data格式文件。
     * <p/>
     * csv文件格式：
     * 起始ip大端，结束ip大端，位置代码
     * ...
     *
     * @param $csv csv文件
     * @param $dat dat格式文件
     * @return bool
     */
    public static function file_csv2dat($csv, $dat)
    {
        if (!file_exists($csv)) {
            return false;
        }

        $fp = fopen($csv, 'r');
        $fpw = fopen($dat, 'w');

        $first = 8;
        $end = $first;
        fwrite($fpw, pack('N', $first), 4);
        fseek($fpw, 4, SEEK_CUR);
        while ($data = fgetcsv($fp)) {
            fwrite($fpw, IpLocation::packip($data[0]), 4);
            fwrite($fpw, IpLocation::packip($data[1]), 4);
            fwrite($fpw, pack('N', intval($data[2])), 4);
            $end += 12;
        }
        fseek($fpw, 4, SEEK_SET);
        fwrite($fpw, pack('N', $end), 4);

        fflush($fpw);
        fclose($fpw);
        fclose($fp);

        return true;
    }

    /**
     * 析构函数，用于在页面执行结束后自动关闭打开的文件。
     */
    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
        $this->fp = 0;
    }

    /**
     * 返回读取的长整型数
     *
     * @access private
     * @return int
     */
    private function getlong()
    {
        //将读取的little-endian编码的4个字节转化为长整型数
        $result = unpack('N', fread($this->fp, 4));
        return $result['1'];
    }

    /**
     * 返回压缩后可进行比较的IP地址
     *
     * @access private
     * @param string $ip
     * @return string
     */
    private static function packip($ip)
    {
        // 将IP地址转化为长整型数，如果在PHP5中，IP地址错误，则返回False，
        // 这时intval将Flase转化为整数-1，之后压缩成big-endian编码的字符串
        return pack('N', intval(ip2long($ip)));
    }

}