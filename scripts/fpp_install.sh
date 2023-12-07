#!/bin/bash

# fpp-PictureFrame install script

BASEDIR=$(dirname $0)
cd $BASEDIR
cd ..

apt-get update
apt-get -y install php-imap

cp scripts/CheckForNewPictureFrameImages.sh /home/fpp/media/scripts/
chown fpp:fpp /home/fpp/media/scripts/CheckForNewPictureFrameImages.sh

cp scripts/pf-monitor*.sh /home/fpp/media/scripts/
chown fpp.fpp /home/fpp/media/scripts/pf-monitor*sh

systemctl --now enable smbd
systemctl --now enable nmbd

sed -i '/^Service_smbd_nmbd/d' /home/fpp/media/settings
echo 'Service_smbd_nmbd = "1"' >> /home/fpp/media/settings

