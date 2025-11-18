# BloomBoard — PHP single-file demo

Este repositorio contiene una versión *single-file* en PHP del demo **BloomBoard** (interfaz y lógica en el cliente).
Está pensada para desplegarse fácilmente en **Azure App Services** (o en cualquier servidor que ejecute PHP 8.0+).

## Contenido
- `bloomboard.php` — Archivo único que sirve la página completa (HTML + CSS + JS).

## Instrucciones rápidas (Azure App Service)
1. Crea un App Service para PHP (por ejemplo PHP 8.2) en el portal de Azure.
2. Configura el *document root* apuntando al lugar donde pongas `bloomboard.php` (por lo general `site/wwwroot`).
3. Sube `bloomboard.php` al directorio raíz de la aplicación (por FTP, Git o ZIP deploy).
4. Asegúrate de que el App Service use PHP (no Node) y que tu archivo sea accesible como `https://<tu-app>.azurewebsites.net/bloomboard.php` o `https://<tu-app>.azurewebsites.net/` si renombras `index.php`.

## Desarrollo local
Puedes probar localmente con PHP integrado:

```bash
php -S 0.0.0.0:8000 bloomboard.php
# luego abrir http://localhost:8000/bloomboard.php
```

## Licencia
MIT — si quieres que incluya otra licencia, dímelo y la cambio.

## CI/CD (Azure DevOps)

A template `azure-pipelines.yml` is included. Configure pipeline variables `azureSubscription` and `azureWebAppName` and run the pipeline to deploy the app.
