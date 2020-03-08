#!/bin/sh
SYSUSER=`who am i | awk '{print $1}'`
apt-get -q -y install php-fpm sqlite php-sqlite3 
#init DB
touch /opt/rpidns/www/rpidns.sqlite
chown $SYSUSER:www-data /opt/rpidns/www/rpidns.sqlite
chmod 660 /opt/rpidns/www/rpidns.sqlite
/usr/bin/php /opt/rpidns/scripts/init_db.php
#install crontabs
cat >> /tmp/$SYSUSER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
EOF
cat /tmp/$SYSUSER | crontab -u $SYSUSER -
rm -rf /tmp/$SYSUSER
