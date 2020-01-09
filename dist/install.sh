#!/bin/bash
#
# vaccinator installation script
#
if [ $UID -ne 0 ]; then
  echo "Please run the installer as root."
  exit 1
fi

NAME=vaccin
DEST=/opt/${NAME}
CONF=init.php
LIB=lib

echo Installing updates...
set -e
#set -x
NEW=1
if [ -d ${DEST} ]; then
  NEW=0
  d=`date +%Y%m%d-%H%M%S`
  mv ${DEST} ${DEST}-$d
  if [ -f ${DEST}-$d/${LIB}/${CONF} ]; then
    mkdir -p ${DEST}/${LIB}
    cp ${DEST}-$d/${LIB}/${CONF} ${DEST}/${LIB}/${CONF}
  fi  
fi

mkdir -p ${DEST}/
cp -fr lib www ${DEST}/
if [ ! -f ${DEST}/${LIB}/${CONF} ]; then
  cp ${CONF} ${DEST}/${LIB}/${CONF}
fi

# insure read access
chmod go+r -R ${DEST}
chmod go+x `find ${DEST} -type d`


echo
if [ ${NEW} -eq 1 ]; then
  echo "Successfully installed the system"
  echo
  echo "Please setup apache to point the DocumentRoot to ${DEST}/www/"
  echo "Then visit the web page for the remaining configuration."
else
  echo "Successfully upgraded the system"
fi
echo
exit 0