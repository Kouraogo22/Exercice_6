<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Cloner le répertoire du porjet 

- git clone https://github.com/Kouraogo22/Exercice_6.git
- cd Exercice_6

## Copier .env.example dans .env 

- cp .env.example .env

## Généré la clé d'application 

- php artisan Key Générate 

## Effectuer les migrations 

- php artisan migrate
puis
- php artisan migrate --database=mysql_second

## Créer des donnés dans la base de donnée

- php artisan db:seed --class=ClientSeeder

## Lancer le server 

- php artisan serve 

## Démarrer docker avec les commande suivantes ( il faut voir docker installer sur sa machine )

- docker-compose down
- docker volume prune
- docker-compose up -d 

## Lien pour le test des requêtes dans SoapUi 

- http://localhost:8000/soap/server

## Commande pour tester le fonction de la base de donnée maître esclave synchroniser par kafka 

-  php artisan clients:sync-etl
-  php artisan kafka:consume

