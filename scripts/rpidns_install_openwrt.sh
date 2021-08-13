#!/bin/sh
SUDO_USER=$USER
RPIPATH=/opt



: '
#opkg update
#opkg install bind-server bind-client

mkdir /usr/lib/lua/luci/controller/rpidns

cat /usr/lib/lua/luci/controller/rpidns/rpidns.lua
module("luci.controller.rpidns.rpidns", package.seeall)  --notice that rpidns is the name of the file rpidns.lua
 function index()
     entry({"admin", "RpiDNS"}, firstchild(), "RpiDNS", 60).dependent=false  --this adds the top level tab and defaults to the first sub-tab (tab_from_cbi), also it is set to position 30
     entry({"admin", "RpiDNS", "Dashboard"}, template("rpidns/dashboard"), "Dashboard", 1)
     entry({"admin", "RpiDNS", "QueryLog"}, template("rpidns/qlog"), "Query Log", 2)
     entry({"admin", "RpiDNS", "RPZhits"}, template("rpidns/rpz"), "RPZ hits", 3)
     entry({"admin", "RpiDNS", "Admin"}, template("rpidns/admin"), "Admin", 4)
end

cat /usr/lib/lua/luci/view/rpidns/dashboard.htm
<%+header%>
<script type="text/javascript">
  function iframeLoaded() {
      var iFrameID = document.getElementById('rpidnsframe');
      if(iFrameID) {
            // here you can make the height, I delete it first, then I make it again
            //iFrameID.height = "400px";
            //iFrameID.height = iFrameID.contentWindow.document.body.scrollHeight + "px";
            iFrameID.height = window.innerHeight-iFrameID.offsetTop-iFrameID.offsetParent.offsetTop-10;
      }
  }
</script>

<div style="align: left; width: 100%; height: 100%;">
 <iframe id="rpidnsframe" onload="iframeLoaded()" style="position: absolute; width: 98%; border: none;" src="/rpi_admin#i2r/0/hidemenu"></iframe>
</div>
<%+footer%>

rm -rf /tmp/luci*
'

opkg update
opkg install php7 php7-cgi sqlite3-cli php7-mod-sqlite3 php7-mod-filter git git-http unzip

##in /etc/config/uhttpd uncomment (in rpi build it was done)
#list interpreter ".php=/usr/bin/php-cgi"

cd /tmp
git clone -b dev --single-branch https://github.com/Homas/RpiDNS.git

mkdir -p $RPIPATH/rpidns/www/db
chown $SUDO_USER:$SUDO_USER $RPIPATH/rpidns/www/db
chmod 775 $RPIPATH/rpidns/www/db

mkdir -p /www/db
chown $SUDO_USER:$SUDO_USER /www/db
chmod 775 /www/db

cp -R RpiDNS/www $RPIPATH/rpidns/
cp -R RpiDNS/scripts $RPIPATH/rpidns/
ln -s $RPIPATH/rpidns/www/rpi_admin /www/rpi_admin
ln -s $RPIPATH/rpidns/www/db $RPIPATH/db

ln -s $RPIPATH/rpidns/www/rpidns_vars.php /www/rpidns_vars.php
ln -s $RPIPATH/rpidns/www/rpisettings.php /www/rpisettings.php

touch $RPIPATH/rpidns/www/db/rpidns.sqlite
chown $SUDO_USER:$SUDO_USER $RPIPATH/rpidns/www/db/rpidns.sqlite
chmod 660 $RPIPATH/rpidns/www/db/rpidns.sqlite

ln -s $RPIPATH/rpidns/www/db/rpidns.sqlite /www/db/rpidns.sqlite

#sed -i "s|/opt/|$RPIPATH/|" $RPIPATH/rpidns/scripts/*.php
#sed -i "s|/opt/|$RPIPATH/|" $RPIPATH/rpidns/www/*.php
#sed -i "s|/opt/|$RPIPATH/|" $RPIPATH/rpidns/www/rpi_admin/*.php

# /bin/ip in /opt/rpidns/scripts/parse_bind_logs.php

/usr/bin/php-cli $RPIPATH/rpidns/scripts/init_db.php

#install crontabs
crontab -l > /tmp/$SUDO_USER
cat >> /tmp/$SUDO_USER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php-cli $RPIPATH/rpidns/scripts/parse_bind_logs.php
42 2 * * *	sleep 25;/usr/bin/php-cli $RPIPATH/rpidns/scripts/clean_db.php
42 3 * * *	sleep 25;/usr/bin/sqlite3 $RPIPATH/rpidns/www/db/rpidns.sqlite 'VACUUM;'
EOF
cat /tmp/$SUDO_USER | crontab -u $SUDO_USER -
rm -rf /tmp/$SUDO_USER

chmod 664 $RPIPATH/rpidns/www/rpisettings.php
chown $SUDO_USER:$SUDO_USER $RPIPATH/rpidns/www/rpisettings.php

#Install MAC DB
curl https://gitlab.com/wireshark/wireshark/raw/master/manuf -o $RPIPATH/rpidns/scripts/mac.db

#Download libs
curl -L https://unpkg.com/bootstrap@4.5.3/dist/css/bootstrap.min.css -o $RPIPATH/rpidns/www/rpi_admin/css/bootstrap.min.css
curl -L https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css -o $RPIPATH/rpidns/www/rpi_admin/css/bootstrap-vue.min.css
curl -L https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.min.js -o $RPIPATH/rpidns/www/rpi_admin/js/vue.min.js
curl -L https://unpkg.com/babel-polyfill@latest/dist/polyfill.min.js -o $RPIPATH/rpidns/www/rpi_admin/js/polyfill.min.js
curl -L https://unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.js -o $RPIPATH/rpidns/www/rpi_admin/js/bootstrap-vue.min.js
curl -L https://unpkg.com/axios/dist/axios.min.js -o $RPIPATH/rpidns/www/rpi_admin/js/axios.min.js
curl -L https://cdn.jsdelivr.net/npm/apexcharts -o $RPIPATH/rpidns/www/rpi_admin/js/apexcharts
curl -L https://cdn.jsdelivr.net/npm/vue-apexcharts -o $RPIPATH/rpidns/www/rpi_admin/js/vue-apexcharts

curl -L https://use.fontawesome.com/releases/v5.12.1/fontawesome-free-5.12.1-web.zip -o /tmp/fontawesome-free-5.12.1-web.zip
unzip /tmp/fontawesome-free-5.12.1-web.zip -d /tmp
cp /tmp/fontawesome-free-5.12.1-web/css/all.css $RPIPATH/rpidns/www/rpi_admin/css
cp -r /tmp/fontawesome-free-5.12.1-web/webfonts $RPIPATH/rpidns/www/rpi_admin/webfonts
rm -R /tmp/fontawesome-free-5.12.1-web /tmp/fontawesome-free-5.12.1-web.zip
