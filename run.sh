cd /opt/logger/
while true
do
    sudo php /opt/logger/application.php app:logger
    sleep 2
done
