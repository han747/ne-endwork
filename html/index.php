<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <title>Ansible数据大屏</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        // 等待DOM加载完成
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化主机列表事件（仅保留删除功能）
            initHostListEvents();
            // 初始化添加主机表单
            initAddFormEvent();
            // 定时刷新主机状态（每30秒）
            setInterval(refreshHostStatus, 30000); 
        });

        // 初始化主机列表操作事件（仅保留删除）
        function initHostListEvents() {
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('delete-host')) {
                    const host_ip = e.target.getAttribute('data-ip');
                    if (confirm(`确定要删除主机 ${host_ip} 吗？`)) {
                        deleteHost(host_ip);
                    }
                }
            });
        }

        // 初始化添加主机表单
        function initAddFormEvent() {
            const addForm = document.getElementById('add-host-form');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitForm(this, 'add'); 
                });
            }
        }

        // 表单提交函数（仅保留添加）
        function submitForm(formElement, action) {
            const submitBtn = formElement.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "处理中...";

            const formData = new FormData(formElement);
            formData.append('action', action);

            fetch('update_hosts.php', {
                method: 'POST',
                body: formData
            })
           .then(response => response.text())
           .then(data => {
                alert(data);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;

                if (data.includes('成功')) {
                    location.reload();
                }
            })
           .catch(error => {
                alert('操作失败: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        // 删除主机函数
        function deleteHost(host_ip) {
            fetch(`update_hosts.php?action=delete&host_ip=${host_ip}`)
           .then(response => response.text())
           .then(data => {
                alert(data);
                if (data.includes('成功')) {
                    location.reload();
                }
            })
           .catch(error => {
                alert('操作失败: ' + error.message);
            });
        }

        // 刷新主机状态
        function refreshHostStatus() {
            fetch('update_hosts.php?action=refresh_status')
           .then(response => response.text())
           .then(data => {
                location.reload();
            })
           .catch(error => {
                console.error('刷新状态失败:', error);
            });
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-6 text-center">Ansible数据大屏</h1>

        <!-- 统计卡片区域 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-gray-600 font-medium">总主机数</h3>
                <p class="text-2xl font-bold">
                    <?php 
                    $hosts_file = '/etc/ansible/hosts';
                    $hosts_content = file_exists($hosts_file) ? file($hosts_file) : [];
                    $total = 0;
                    foreach ($hosts_content as $line) {
                        if (preg_match('/ansible_ssh_user/', trim($line))) {
                            $total++;
                        }
                    }
                    echo $total; 
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-gray-600 font-medium">在线主机数</h3>
                <p class="text-2xl font-bold text-green-600">
                    <?php 
                    $online = 0;
                    $hosts = [];
                    foreach ($hosts_content as $line) {
                        if (preg_match('/^([^\s]+)\s+ansible_ssh_user=([^\s]+)/', trim($line), $m)) {
                            $hosts[] = $m[1];
                        }
                    }
                    foreach ($hosts as $ip) {
                        $status_file = __DIR__ . "/output/{$ip}.txt";
                        if (file_exists($status_file) && strpos(file_get_contents($status_file), 'Status: True')!==false) {
                            $online++;
                        }
                    }
                    echo $online; 
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-gray-600 font-medium">离线主机数</h3>
                <p class="text-2xl font-bold text-red-600">
                    <?php echo $total - $online; ?>
                </p>
            </div>
        </div>

        <!-- 主机列表区域 -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h2 class="text-xl font-bold mb-4">主机列表</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 text-left">IP地址</th>
                            <th class="py-2 px-4 text-left">用户名</th>
                            <th class="py-2 px-4 text-left">主机组</th>
                            <th class="py-2 px-4 text-left">状态</th>
                            <th class="py-2 px-4 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_group = '';

                        foreach ($hosts_content as $line) {
                            $line = trim($line);

                            if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
                                $current_group = $matches[1];
                                continue;
                            }

                            if (preg_match('/^([^\s]+)\s+ansible_ssh_user=([^\s]+)/', $line, $matches)) {
                                $host_ip = $matches[1];
                                $host_user = $matches[2];
                                system(' /var/www/html/output.sh');
                                $status_file = __DIR__ . "/output/{$host_ip}.txt";
                                $status = "未知";
                                $status_class = "text-gray-500";

                                if (file_exists($status_file)) {
                                    $status_content = file_get_contents($status_file);
                                    if (strpos($status_content, "Status: True")!== false) {
                                        $status = "在线";
                                        $status_class = "text-green-600";
                                    } else {
                                        $status = "离线";
                                        $status_class = "text-red-600";
                                    }
                                }

                                echo "<tr class=\"hover:bg-gray-50\">";
                                echo "<td class=\"py-2 px-4\">$host_ip</td>";
                                echo "<td class=\"py-2 px-4\">$host_user</td>";
                                echo "<td class=\"py-2 px-4\">$current_group</td>";
                                echo "<td class=\"py-2 px-4\"><span class=\"font-medium $status_class\">$status</span></td>";
                                echo "<td class=\"py-2 px-4\">";
                                echo "<button class=\"delete-host bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded\" data-ip=\"$host_ip\" aria-label=\"删除主机 $host_ip\">";
                                echo "<i class=\"fa fa-trash\"></i> 删除</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php include "/var/www/html/output.php"?>

        <!-- 上面这行无法使用     添加主机表单 -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">添加主机</h2>
            <form id="add-host-form" method="post" action="update_hosts.php">
                <input type="hidden" name="action" value="add">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2" for="host_ip">主机IP:</label>
                    <input 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" 
                        type="text" 
                        name="host_ip" 
                        required 
                        aria-label="主机IP地址"
                        placeholder="请输入主机IP"
                    >
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2" for="host_user">用户名:</label>
                    <input 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" 
                        type="text" 
                        name="host_user" 
                        required 
                        aria-label="主机用户名"
                        placeholder="请输入用户名"
                    >
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2" for="host_pass">密码:</label>
                    <input 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" 
                        type="password" 
                        name="host_pass" 
                        required 
                        aria-label="主机密码"
                        placeholder="请输入密码"
                    >
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2" for="host_group">主机组:</label>
                    <select 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" 
                        name="host_group" 
                        aria-label="主机组选择"
                    >
                        <option value="web_servers">Web服务器</option>
                        <option value="db_servers">数据库服务器</option>
                    </select>
                </div>
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    <i class="fa fa-plus mr-2"></i>添加主机
                </button>
            </form>
        </div>
    </div>
    
</body>

</html>