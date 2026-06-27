#!/bin/bash

# fpp-PictureFrame install script

BASEDIR=$(dirname $0)
cd $BASEDIR
cd ..

# Image fetching is handled by fetchNewImages.py (Python stdlib: imaplib +
# email). No PHP IMAP extension or other packages are required.

cp scripts/CheckForNewPictureFrameImages.sh /home/fpp/media/scripts/
chown fpp:fpp /home/fpp/media/scripts/CheckForNewPictureFrameImages.sh

cp scripts/pf-monitor*.sh /home/fpp/media/scripts/
chown fpp:fpp /home/fpp/media/scripts/pf-monitor*sh

systemctl --now enable smbd
systemctl --now enable nmbd

sed -i '/^Service_smbd_nmbd/d' /home/fpp/media/settings
echo 'Service_smbd_nmbd = "1"' >> /home/fpp/media/settings

