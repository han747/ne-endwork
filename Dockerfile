FROM ubuntu:20.04
ENV DEBIAN_FRONTEND=noninteractive

# 安装依赖 加个sshpass和cron
RUN apt-get update && \
    apt-get install -y \
    openssh-server \
    ansible \
    apache2 \
    sshpass \
    vim \
    libapache2-mod-php \
    cron \
    && rm -rf /var/lib/apt/lists/*

# 复制文件
COPY ansible/hosts /etc/ansible/hosts
COPY html /var/www/html/
COPY init_container.sh /init_container.sh

# 添加cron任务配置
COPY cronjob /etc/cron.d/output-php-cron
RUN chmod 0644 /etc/cron.d/output-php-cron
RUN crontab /etc/cron.d/output-php-cron

# 设置权限
RUN chmod +x /init_container.sh

# 暴露端口
EXPOSE 22 80

# 使用初始化脚本启动容器
CMD ["/init_container.sh"]