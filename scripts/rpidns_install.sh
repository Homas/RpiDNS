#!/bin/sh
SYSUSER=`who am i | awk '{print $1}'`
apt-get -q -y install php-fpm sqlite php-sqlite3 
#init DB
mkdir -p /opt/rpidns/www/db
chown $SYSUSER:www-data /opt/rpidns/www/db
chmod 775 /opt/rpidns/www/db

touch /opt/rpidns/www/db/rpidns.sqlite
chown $SYSUSER:www-data /opt/rpidns/www/db/rpidns.sqlite
chmod 660 /opt/rpidns/www/db/rpidns.sqlite
/usr/bin/php /opt/rpidns/scripts/init_db.php

#install crontabs
cat >> /tmp/$SYSUSER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
EOF
cat /tmp/$SYSUSER | crontab -u $SYSUSER -
rm -rf /tmp/$SYSUSER

curl https://gitlab.com/wireshark/wireshark/raw/master/manuf -o /opt/rpidns/scripts/mac.db

#/etc/php/7.3/fpm/php.ini
#disable_functions
#service php7.3-fpm restart