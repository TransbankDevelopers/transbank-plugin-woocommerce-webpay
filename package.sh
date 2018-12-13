#!/bin/sh

#Script for create the plugin artifact
echo "Travis tag: $TRAVIS_TAG"

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

SRC_DIR="woocommerce-transbank"
FILE1="class-wc-transbank.php"
DOCKER_DIR="docker-php5.6"

cd $DOCKER_DIR
docker-compose run --rm -w /var/www/html/wp-content/plugins/woocommerce-transbank webserver composer install --no-dev
docker-compose run --rm -w /var/www/html/wp-content/plugins/woocommerce-transbank webserver composer update --no-dev
cd ..

sed -i.bkp "s/Version: 2.0.4/Version: ${TRAVIS_TAG#"v"}/g" "$SRC_DIR/$FILE1"

PLUGIN_FILE="plugin-woocommerce-webpay-$TRAVIS_TAG.zip"

zip -FSr $PLUGIN_FILE $SRC_DIR -x composer.json composer.lock "$FILE1.bkp"

cp "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE1"
rm "$SRC_DIR/$FILE1.bkp"

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
