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
crontab -l > /tmp/$SYSUSER
cat >> /tmp/$SYSUSER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
42 2 * * *	/usr/bin/php /opt/rpidns/www/db/clean_db.php
42 3 * * *	/usr/bin/sqlite3 /opt/rpidns/www/db/rpidns.sqlite 'VACUUM;'
EOF
cat /tmp/$SYSUSER | crontab -u $SYSUSER -
rm -rf /tmp/$SYSUSER

chmod 664 /opt/rpidns/www/rpisettings.php
chown pi:www-data /opt/rpidns/www/rpisettings.php

curl https://gitlab.com/wireshark/wireshark/raw/master/manuf -o /opt/rpidns/scripts/mac.db

#/etc/php/7.3/fpm/php.ini
#disable_functions
#service php7.3-fpm restart

#pi@pi-dev:/opt/rpidns/www $ cat /etc/php/7.3/fpm/pool.d/www.conf |grep limit_extension
#;security.limit_extensions = .php .php3 .php4 .php5 .php7
#pi@pi-dev:/opt/rpidns/www $ 
