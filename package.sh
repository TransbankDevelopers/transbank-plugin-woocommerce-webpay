#!/bin/sh

#Script for create the plugin artifact
echo "Travis tag: $TRAVIS_TAG"

if [ "$TRAVIS_TAG" = "" ]
then
   TRAVIS_TAG='1.0.0'
fi

cd webpay
SRC_DIR="woocommerce-transbank"
#FILE1="webpay.php"
#FILE2="config.xml"
#FILE3="config_es.xml"

#cd $SRC_DIR
#composer install --no-dev
#composer update --no-dev
#cd ..

#sed -i.bkp "s/$this->version = '3.0.6'/$this->version = '${TRAVIS_TAG#"v"}'/g" "$SRC_DIR/$FILE1"
#sed -i.bkp "s/\[3.0.6\]/\[${TRAVIS_TAG#"v"}\]/g" "$SRC_DIR/$FILE2"
#sed -i.bkp "s/\[3.0.6\]/\[${TRAVIS_TAG#"v"}\]/g" "$SRC_DIR/$FILE3"


PLUGIN_FILE="plugin-woocommerce-webpay-$TRAVIS_TAG.zip"

zip -FSr $PLUGIN_FILE $SRC_DIR # -x webpay/vendor/tecnickcom/tcpdf/fonts/a*\/* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/d*\/* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/f*\/* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/a* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/ci* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/d* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/f* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/k* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/m* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/p* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/s* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/t* \
#                                    webpay/vendor/tecnickcom/tcpdf/fonts/u* \
#                                    webpay/vendor/tecnickcom/tcpdf/examples/\* \
#                                    webpay/vendor/apache/log4php/src/examples/\* \
#                                    webpay/vendor/apache/log4php/src/test/\* \
#                                    webpay/vendor/apache/log4php/src/site/\* \
#                                    "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE2.bkp" "$SRC_DIR/$FILE3.bkp"

#cp "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE1"
#cp "$SRC_DIR/$FILE2.bkp" "$SRC_DIR/$FILE2"
#cp "$SRC_DIR/$FILE3.bkp" "$SRC_DIR/$FILE3"
#rm "$SRC_DIR/$FILE1.bkp"
#rm "$SRC_DIR/$FILE2.bkp"
#rm "$SRC_DIR/$FILE3.bkp"

mv $PLUGIN_FILE ../

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"