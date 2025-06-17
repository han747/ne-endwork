<?php
// output.php（仅负责生成数据，不再覆盖自身）
// 执行 Ansible ping 并记录结果
$ansibleResult = shell_exec('ansible all -m ping 2>&1'); 
file_put_contents('ansible_output.log', $ansibleResult); // 调试日志

// 解析 Ansible 输出结果，提取主机状态
$hosts = [];
$lines = explode("\n", $ansibleResult);
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // 匹配主机 IP 和状态（SUCCESS/FAILURE）
    if (preg_match('/^([\d.]+)\s+\|\s+(SUCCESS|FAILURE)/', $line, $matches)) {
        $ip = $matches[1];
        $status = ($matches[2] === 'SUCCESS') ? '在线' : '离线';
        $hosts[$ip] = $status;
    }
}

// 读取 Ansible 主机清单（/etc/ansible/hosts）
$hostsFile = '/etc/ansible/hosts';
$hostsContent = file_exists($hostsFile) ? file($hostsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// 解析主机清单，生成供前端使用的结构化数据
$hostsList = [];
$currentGroup = '';

foreach ($hostsContent as $line) {
    $line = trim($line);

    // 识别主机组（如 [web_servers]）
    if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
        $currentGroup = $matches[1];
        continue;
    }

    // 解析主机信息（匹配 ansible_ssh_user 格式）
    if (preg_match('/^([^\s]+)\s+ansible_ssh_user=([^\s]+)/', $line, $matches)) {
        $hostIp = $matches[1];
        $hostUser = $matches[2];
        $hostStatus = isset($hosts[$hostIp]) ? $hosts[$hostIp] : '未知';

        // 将主机信息添加到列表
        $hostsList[] = "$hostIp ansible_ssh_user=$hostUser ansible_status=$hostStatus";

        // 生成每个主机的状态文件（使用绝对路径）
        $outputDir = '/var/www/html/output'; // 容器内的绝对路径
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true); // 创建目录（如果不存在）
        }

        // 写入状态文件（绝对路径）
        $statusFile = "$outputDir/$hostIp.txt";
        $statusContent = "Status: " . ($hostStatus === '在线' ? 'True' : 'False') . "\n";
        file_put_contents($statusFile, $statusContent);
    }
}

// 写入供前端统计的内容（新增 hosts_data.php 文件）
$outputPath = '/var/www/html/hosts_data.php'; 
file_put_contents($outputPath, '<?php return ' . var_export($hostsList, true) . '; ?>');
?>