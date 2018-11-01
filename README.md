ThinkPhp版本

1、将ORG文件夹拷贝到thinkphp工程中应用Lib目录下
2、按照以下方法使用：

    import('@.ORG.CNAD.IpLocation');// 导入IpLocation类
    $Ip = new IpLocation();// 实例化类 参数表示IP地址库文件
    $area = $Ip->getlocation($qip); // 获取某个IP地址所在的位置