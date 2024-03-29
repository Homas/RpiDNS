#!/bin/sh
SYSUSER=`who am i | awk '{print $1}'`
#TODO
#check $SYSUSER vs $SUDO_USER
apt-get -q -y install php-fpm sqlite3 php-sqlite3 unzip
#init DB
mkdir -p /opt/rpidns/www/db
chown $SUDO_USER:www-data /opt/rpidns/www/db
chmod 775 /opt/rpidns/www/db

touch /opt/rpidns/www/db/rpidns.sqlite
chown $SUDO_USER:www-data /opt/rpidns/www/db/rpidns.sqlite
chmod 660 /opt/rpidns/www/db/rpidns.sqlite
/usr/bin/php /opt/rpidns/scripts/init_db.php

#install crontabs
crontab -l > /tmp/$SUDO_USER
cat >> /tmp/$SUDO_USER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
42 2 * * *	sleep 25;/usr/bin/php /opt/rpidns/scripts/clean_db.php
42 3 * * *	sleep 25;/usr/bin/sqlite3 /opt/rpidns/www/db/rpidns.sqlite 'VACUUM;'
EOF
cat /tmp/$SUDO_USER | crontab -u $SUDO_USER -
rm -rf /tmp/$SUDO_USER

chmod 664 /opt/rpidns/www/rpisettings.php
chown $SUDO_USER:www-data /opt/rpidns/www/rpisettings.php

#Install MAC DB
curl https://gitlab.com/wireshark/wireshark/raw/master/manuf -o /opt/rpidns/scripts/mac.db

#Download libs
curl -L https://unpkg.com/bootstrap@4.5.3/dist/css/bootstrap.min.css -o /opt/rpidns/www/rpi_admin/css/bootstrap.min.css
curl -L https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css -o /opt/rpidns/www/rpi_admin/css/bootstrap-vue.min.css
curl -L https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.min.js -o /opt/rpidns/www/rpi_admin/js/vue.min.js
curl -L https://unpkg.com/babel-polyfill@latest/dist/polyfill.min.js -o /opt/rpidns/www/rpi_admin/js/polyfill.min.js
curl -L https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.js -o /opt/rpidns/www/rpi_admin/js/bootstrap-vue.min.js
curl -L https://unpkg.com/axios/dist/axios.min.js -o /opt/rpidns/www/rpi_admin/js/axios.min.js
curl -L https://cdn.jsdelivr.net/npm/apexcharts -o /opt/rpidns/www/rpi_admin/js/apexcharts
curl -L https://cdn.jsdelivr.net/npm/vue-apexcharts -o /opt/rpidns/www/rpi_admin/js/vue-apexcharts

curl -L https://use.fontawesome.com/releases/v5.12.1/fontawesome-free-5.12.1-web.zip -o /tmp/fontawesome-free-5.12.1-web.zip
unzip /tmp/fontawesome-free-5.12.1-web.zip -d /tmp
cp /tmp/fontawesome-free-5.12.1-web/css/all.css /opt/rpidns/www/rpi_admin/css
cp -r /tmp/fontawesome-free-5.12.1-web/webfonts /opt/rpidns/www/rpi_admin/webfonts
rm -R /tmp/fontawesome-free-5.12.1-web /tmp/fontawesome-free-5.12.1-web.zip

#/etc/php/7.3/fpm/php.ini
#disable_functions
#service php7.3-fpm restart

#pi@pi-dev:/opt/rpidns/www $ cat /etc/php/7.3/fpm/pool.d/www.conf |grep limit_extension
#;security.limit_extensions = .php .php3 .php4 .php5 .php7
#pi@pi-dev:/opt/rpidns/www $

### 2020-07-07 Manage bind from apache
adduser www-data bind
adduser $SUDO_USER bind
adduser $SUDO_USER www-data
