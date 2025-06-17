#!/bin/bash

# 生成SSH密钥（如果不存在）
if [ ! -f /root/.ssh/id_rsa ]; then
    echo "生成SSH密钥..."
    ssh-keygen -t rsa -f /root/.ssh/id_rsa -N ""
    echo "SSH密钥生成完成"
fi

# 配置Ansible
echo "配置Ansible..."
chmod 600 /root/.ssh/id_rsa
sed -i 's/#host_key_checking = False/host_key_checking = False/' /etc/ansible/ansible.cfg

# 启动服务
echo "启动Apache和SSH服务..."
service ssh start
service apache2 start
service cron start

# 保持容器运行
tail -f /dev/null
