# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2019-02-18
### Fixed
- Corrige problema que impide ejecutar el plugin en Integración, cuando está recién instalado.
- Indica a WooCommerce que el plugin es compatible con la versión 3.5.4
### Changed
- Al recibir el pago de forma exitósa, el estado de la compra pasa a "Processing" en vez de "Completed".

## [2.1.5] - 2019-01-10
### Changed
- Se elimina la condición de VCI == "TSY" || VCI == "" para evaluar la respuesta de getTransactionResult debido a que
esto podría traer problemas con transacciones usando tarjetas internacionales.

## [2.1.4] - 2018-12-27
### Added
- Agrega logs de transacciones para poder obtener los datos como token, orden de compra, etc.. necesarios para el proceso de certificación.

## [2.1.3] - 2018-12-18
### Fixed
- Corrige el sistema de configuraciones del plugin.
### Added
- Agrega funcionalidad para probar el servicio Webpay desde el panel de configuraciones del plugin.
- Mejoras en el proceso de pago y creación de la orden en estados correctos.

## [2.1.2] - 2018-12-18
### Fixed
- Se corrige un error de la sección de administración al guardar las configuraciones que provocaba error con la validación de certificados.

## [2.1.1] - 2018-12-17
### Fixed
- Se corrige un error de la sección de administración al verificar los certificados.

## [2.1.0] - 2018-12-14
### Changed
- Ahora soporta php 7.1
### Fixed
- Se mejoran el proceso de pago con Webpay.
- Se corrigen varios errores de la sección de administración del plugin.
