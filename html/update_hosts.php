<?php
// 更新主机管理
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? $_GET['host_ip'] ?? '';

$hosts_file = '/etc/ansible/hosts';
$hosts_content = file($hosts_file);

// 确保文件可写
if (!is_writable($hosts_file)) {
    die("错误：无法写入Ansible主机文件");
}

// 验证输入
if (empty($host_ip) && $action !== 'refresh_status') {
    die("错误：请提供主机IP");
}

function save_hosts_file($content, $file) {
    $temp_file = tempnam(dirname($file), 'ansible_');
    file_put_contents($temp_file, $content);
    rename($temp_file, $file);
    return true;
}

switch ($action) {
    case 'add':
        $host_user = $_POST['host_user'] ?? '';
        $host_pass = $_POST['host_pass'] ?? '';
        $host_group = $_POST['host_group'] ?? 'web_servers';
        
        // 检查主机是否已存在
        foreach ($hosts_content as $line) {
            if (strpos($line, $host_ip) !== false) {
                die("错误：主机 $host_ip 已存在");
            }
        }
        
        // 构建新主机行
        $new_host = "$host_ip ansible_ssh_user=$host_user ansible_ssh_pass=$host_pass\n";
        $group_found = false;
        
        // 查找或添加主机组
        for ($i = 0; $i < count($hosts_content); $i++) {
            if (trim($hosts_content[$i]) === "[$host_group]") {
                $group_found = true;
                array_splice($hosts_content, $i + 1, 0, $new_host);
                break;
            }
        }
        
        if (!$group_found) {
            // 添加新组
            $hosts_content[] = "\n[$host_group]\n";
            $hosts_content[] = $new_host;
        }
        
        if (save_hosts_file(implode('', $hosts_content), $hosts_file)) {
            // 测试连接
            exec("ansible $host_ip -m ping -o", $output, $return_code);
            
            if ($return_code === 0) {
                // 连接成功，更新状态
                exec("ansible-playbook /etc/ansible/ping.yml");
                echo "主机 $host_ip 添加成功并连接测试通过";
            } else {
                echo "主机 $host_ip 添加成功，但连接测试失败:<br>";
                echo implode("<br>", $output);
            }
        } else {
            echo "错误：无法更新主机文件";
        }
        break;
        
    case 'delete':
        $new_content = [];
        $deleted = false;
        
        foreach ($hosts_content as $line) {
            if (!str_contains($line, $host_ip)) {
                $new_content[] = $line;
            } else {
                $deleted = true;
            }
        }
        
        if ($deleted && save_hosts_file(implode('', $new_content), $hosts_file)) {
            // 删除对应的状态文件
            $status_file = "output/{$host_ip}.txt";
            if (file_exists($status_file)) {
                unlink($status_file);
            }
            
            exec("ansible-playbook /etc/ansible/ping.yml");
            echo "主机 $host_ip 删除成功";
        } else {
            echo "未找到主机 $host_ip 或删除失败";
        }
        break;
        
    case 'update':
        $host_user = $_POST['host_user'] ?? '';
        $host_pass = $_POST['host_pass'] ?? '';
        $host_group = $_POST['host_group'] ?? 'web_servers';
        
        // 先删除原有主机记录
        $new_content = [];
        $found = false;
        
        foreach ($hosts_content as $line) {
            if (!str_contains($line, $host_ip)) {
                $new_content[] = $line;
            } else {
                $found = true;
            }
        }
        
        if ($found) {
            // 构建更新后的主机行
            if (!empty($host_pass)) {
                // 如果提供了新密码
                $updated_host = "$host_ip ansible_ssh_user=$host_user ansible_ssh_pass=$host_pass\n";
            } else {
                // 否则保持原有密码（从原记录中提取）
                foreach ($hosts_content as $line) {
                    if (str_contains($line, $host_ip)) {
                        preg_match('/ansible_ssh_pass=([^\s]+)/', $line, $matches);
                        $old_pass = $matches[1] ?? '';
                        $updated_host = "$host_ip ansible_ssh_user=$host_user ansible_ssh_pass=$old_pass\n";
                        break;
                    }
                }
            }
            
            // 添加更新后的主机信息到指定组
            $group_found = false;
            
            for ($i = 0; $i < count($new_content); $i++) {
                if (trim($new_content[$i]) === "[$host_group]") {
                    $group_found = true;
                    array_splice($new_content, $i + 1, 0, $updated_host);
                    break;
                }
            }
            
            if (!$group_found) {
                $new_content[] = "\n[$host_group]\n";
                $new_content[] = $updated_host;
            }
            
            if (save_hosts_file(implode('', $new_content), $hosts_file)) {
                exec("ansible-playbook /etc/ansible/ping.yml");
                echo "主机 $host_ip 更新成功";
            } else {
                echo "错误：无法更新主机文件";
            }
        } else {
            echo "未找到主机 $host_ip";
        }
        break;
        
    case 'refresh_status':
        exec("ansible-playbook /etc/ansible/ping.yml > /dev/null 2>&1 &");
        echo "正在刷新主机状态...";
        break;
        
    default:
        echo "未知操作";
}
?>